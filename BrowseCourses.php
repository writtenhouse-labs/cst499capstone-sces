<?php
/*
  File: BrowseCourses.php
  Description: Allows logged-in students to search Garden University's course catalog.
  Author: Sarah Manago
  Date: 09-29-2026
*/
session_start();

if (empty($_SESSION["loggedIn"])) {
  header("Location: Login.php");
  exit();
}

require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/services/MockStudentInformationSystem.php";

$db = new Database();
$con = $db->connect();
$studentSystem = new MockStudentInformationSystem();

if (empty($_SESSION["enrollmentToken"])) {
  $_SESSION["enrollmentToken"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";
$studentEmail = $con->real_escape_string($_SESSION["studentemail"] ?? "");
$studentResult = $db->executeSelectQuery(
  $con,
  "SELECT studentid, gardenstudentid FROM student WHERE studentemail = '$studentEmail' LIMIT 1"
);

if ($studentResult->num_rows === 0) {
  session_unset();
  header("Location: Login.php");
  exit();
}

$student = $studentResult->fetch_assoc();
$studentId = (int) $student["studentid"];
$gardenStudentId = $student["gardenstudentid"];

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
  $requestedOfferingId = filter_input(INPUT_POST, "offeringId", FILTER_VALIDATE_INT);
  $submittedToken = $_POST["enrollmentToken"] ?? "";
  $enrollmentAction = $_POST["enrollmentAction"] ?? "enroll";
  $sisBlocked = false;

  if (
    hash_equals($_SESSION["enrollmentToken"], $submittedToken) &&
    $requestedOfferingId &&
    in_array($enrollmentAction, ["enroll", "waitlist"], true)
  ) {
    try {
      $verifiedStudent = $studentSystem->findStudent($gardenStudentId);
    } catch (Throwable $error) {
      $verifiedStudent = null;
      $sisBlocked = true;
      $message = "Student eligibility could not be verified right now. Please try again later.";
      $messageType = "danger";
    }

    if (!$sisBlocked && $verifiedStudent === null) {
      $db->executeQuery(
        $con,
        "UPDATE student
         SET active = 0
         WHERE studentid = $studentId"
      );
      $sisBlocked = true;
      $message = "Garden University could not verify your student record. Enrollment was not changed.";
      $messageType = "warning";
    } elseif (!$sisBlocked) {
      $verifiedActive = !empty($verifiedStudent["active"]) ? 1 : 0;
      $verifiedEnrollmentDate = $con->real_escape_string($verifiedStudent["enrollmentDate"]);
      $db->executeQuery(
        $con,
        "UPDATE student
         SET active = $verifiedActive,
             enrollmentdate = '$verifiedEnrollmentDate'
         WHERE studentid = $studentId"
      );

      if ($verifiedActive !== 1) {
        $sisBlocked = true;
        $message = "Your Garden University student record is not active for enrollment.";
        $messageType = "warning";
      }
    }
  }

  if (!hash_equals($_SESSION["enrollmentToken"], $submittedToken)) {
    $message = "Your enrollment request expired. Please try again.";
    $messageType = "danger";
  } elseif (!$requestedOfferingId) {
    $message = "Please select a valid course offering.";
    $messageType = "danger";
  } elseif ($sisBlocked) {
    // The verification step above already supplied a student-friendly message.
  } elseif ($enrollmentAction === "leave_waitlist") {
    $waitlistEntry = $db->executeSelectQuery(
      $con,
      "SELECT w.waitlistid, c.coursecode, c.coursename
       FROM course_waitlist w
       INNER JOIN course_offerings o ON o.offeringid = w.offeringid
       INNER JOIN courses c ON c.courseid = o.courseid
       WHERE w.studentid = $studentId
         AND w.offeringid = " . (int) $requestedOfferingId . "
       LIMIT 1"
    );

    if ($waitlistEntry->num_rows === 0) {
      $message = "You are no longer on that course waitlist.";
      $messageType = "info";
    } else {
      $waitlistedCourse = $waitlistEntry->fetch_assoc();
      $db->executeQuery(
        $con,
        "DELETE FROM course_waitlist
         WHERE studentid = $studentId
           AND offeringid = " . (int) $requestedOfferingId
      );
      $message = "You left the waitlist for " . $waitlistedCourse["coursecode"] . " " .
        $waitlistedCourse["coursename"] . ".";
      $messageType = "success";
    }
  } elseif ($enrollmentAction === "drop") {
    $con->begin_transaction();
    $enrollmentToDrop = $db->executeSelectQuery(
      $con,
      "SELECT e.enrollmentid, c.coursecode, c.coursename
       FROM student_enrollments e
       INNER JOIN course_offerings o ON o.offeringid = e.offeringid
       INNER JOIN courses c ON c.courseid = o.courseid
       WHERE e.studentid = $studentId
         AND e.offeringid = " . (int) $requestedOfferingId . "
       LIMIT 1 FOR UPDATE"
    );

    if ($enrollmentToDrop->num_rows === 0) {
      $con->rollback();
      $message = "That course is no longer on your schedule.";
      $messageType = "info";
    } else {
      $droppedCourse = $enrollmentToDrop->fetch_assoc();
      $db->executeQuery(
        $con,
        "DELETE FROM student_enrollments
         WHERE studentid = $studentId
           AND offeringid = " . (int) $requestedOfferingId
      );
      $db->executeQuery(
        $con,
        "UPDATE course_offerings
         SET availableseats = LEAST(capacity, availableseats + 1)
         WHERE offeringid = " . (int) $requestedOfferingId
      );
      $con->commit();
      $message = "Enrollment dropped for " . $droppedCourse["coursecode"] . " " .
        $droppedCourse["coursename"] . ".";
      $messageType = "success";
    }
  } else {
    $con->begin_transaction();
    $requestedCourse = $db->executeSelectQuery(
      $con,
      "SELECT o.offeringid, o.availableseats, c.coursecode, c.coursename
       FROM course_offerings o
       INNER JOIN courses c ON c.courseid = o.courseid
       WHERE o.offeringid = " . (int) $requestedOfferingId . "
         AND o.active = 1 AND c.active = 1
       LIMIT 1 FOR UPDATE"
    );

    if ($requestedCourse->num_rows === 0) {
      $con->rollback();
      $message = "That course is no longer available.";
      $messageType = "danger";
    } else {
      $courseToEnroll = $requestedCourse->fetch_assoc();
      $alreadyEnrolled = $db->executeSelectQuery(
        $con,
        "SELECT enrollmentid FROM student_enrollments
         WHERE studentid = $studentId AND offeringid = " . (int) $requestedOfferingId . " LIMIT 1"
      );
      $alreadyWaitlisted = $db->executeSelectQuery(
        $con,
        "SELECT waitlistid FROM course_waitlist
         WHERE studentid = $studentId AND offeringid = " . (int) $requestedOfferingId . " LIMIT 1"
      );

      if ($alreadyEnrolled->num_rows > 0) {
        $con->commit();
        $message = "You are already enrolled in " . $courseToEnroll["coursecode"] . ".";
        $messageType = "info";
      } elseif ($alreadyWaitlisted->num_rows > 0) {
        $con->commit();
        $message = "You are already on the waitlist for " . $courseToEnroll["coursecode"] . ".";
        $messageType = "info";
      } elseif ((int) $courseToEnroll["availableseats"] === 0) {
        $db->executeQuery(
          $con,
          "INSERT INTO course_waitlist (studentid, offeringid) VALUES ($studentId, " .
            (int) $requestedOfferingId . ")"
        );
        $con->commit();
        $message = $courseToEnroll["coursecode"] . " is full, so you were added to the waitlist.";
        $messageType = "success";
      } else {
        $conflict = $db->executeSelectQuery(
          $con,
          "SELECT c.coursecode, c.coursename
           FROM student_enrollments e
           INNER JOIN course_offerings existing ON existing.offeringid = e.offeringid
           INNER JOIN courses c ON c.courseid = existing.courseid
           INNER JOIN semesters existingsemester ON existingsemester.semesterid = existing.semesterid
           INNER JOIN course_offerings requested ON requested.offeringid = " . (int) $requestedOfferingId . "
           INNER JOIN semesters requestedsemester ON requestedsemester.semesterid = requested.semesterid
           WHERE e.studentid = $studentId
             AND existing.courseday = requested.courseday
             AND existingsemester.startdate <= requestedsemester.enddate
             AND existingsemester.enddate >= requestedsemester.startdate
             AND existing.coursetime < ADDTIME(requested.coursetime, SEC_TO_TIME(requested.courselength * 60))
             AND ADDTIME(existing.coursetime, SEC_TO_TIME(existing.courselength * 60)) > requested.coursetime
           LIMIT 1"
        );

        if ($conflict->num_rows > 0) {
          $con->rollback();
          $conflictingCourse = $conflict->fetch_assoc();
          $message = "Schedule conflict: " . $courseToEnroll["coursecode"] .
            " overlaps with " . $conflictingCourse["coursecode"] . " " .
            $conflictingCourse["coursename"] . ". Enrollment was not added.";
          $messageType = "warning";
        } else {
          $db->executeQuery(
            $con,
            "INSERT INTO student_enrollments (studentid, offeringid) VALUES ($studentId, " .
              (int) $requestedOfferingId . ")"
          );
          $db->executeQuery(
            $con,
            "UPDATE course_offerings
             SET availableseats = availableseats - 1
             WHERE offeringid = " . (int) $requestedOfferingId . "
               AND availableseats > 0"
          );
          $con->commit();
          $message = "You are enrolled in " . $courseToEnroll["coursecode"] . " " .
            $courseToEnroll["coursename"] . "!";
          $messageType = "success";
        }
      }
    }
  }
}

$selectedProgram = trim($_GET["program"] ?? "");
$selectedSemester = trim($_GET["semester"] ?? "");
$courseId = trim($_GET["courseId"] ?? "");

$programs = $db->executeSelectQuery(
  $con,
  "SELECT programid, programname FROM program WHERE active = 1 ORDER BY programname"
);

$semesters = $db->executeSelectQuery(
  $con,
  "SELECT semesterid, semestername, startdate
   FROM semesters
   WHERE active = 1
   ORDER BY startdate"
);

$defaultSemesterResult = $db->executeSelectQuery(
  $con,
  "SELECT semesterid FROM semesters WHERE semestername = 'Fall 2026' LIMIT 1"
);
$defaultSemesterId = $defaultSemesterResult->num_rows > 0
  ? (int) $defaultSemesterResult->fetch_assoc()["semesterid"]
  : 0;
$selectedSemesterId = ctype_digit($selectedSemester) ? (int) $selectedSemester : $defaultSemesterId;

$conditions = [
  "c.active = 1",
  "o.active = 1",
  "p.active = 1",
  "o.semesterid = $selectedSemesterId",
  "e.enrollmentid IS NULL",
  "w.waitlistid IS NULL"
];

if ($selectedProgram !== "" && ctype_digit($selectedProgram)) {
  $conditions[] = "p.programid = " . (int) $selectedProgram;
}

if ($courseId !== "") {
  $courseIdValue = $con->real_escape_string($courseId);
  $conditions[] = "c.coursecode LIKE '%$courseIdValue%'";
}

$courseSql = "SELECT o.offeringid, c.courseid, c.coursecode, c.coursename, c.coursedescription,
                     s.semestername AS semester, o.courseday, o.courselength,
                     o.coursetime, o.capacity, o.availableseats, s.startdate, s.enddate,
                     p.programname, e.enrollmentid, w.waitlistid
              FROM courses c
              INNER JOIN program p ON p.programid = c.programid
              INNER JOIN course_offerings o ON o.courseid = c.courseid
              INNER JOIN semesters s ON s.semesterid = o.semesterid
              LEFT JOIN student_enrollments e ON e.offeringid = o.offeringid AND e.studentid = $studentId
              LEFT JOIN course_waitlist w ON w.offeringid = o.offeringid AND w.studentid = $studentId
              WHERE " . implode(" AND ", $conditions) . "
              ORDER BY o.coursetime, c.coursecode";

$courses = $db->executeSelectQuery($con, $courseSql);
$courseCount = $courses->num_rows;

$programGraphics = [
  "Floral Design" => "floral-design.png",
  "Herbology" => "herbology.png",
  "Ranch Dressing Chef" => "ranch-dressing-chef.png",
  "Salad Chopping" => "salad-chopping.png",
  "Soil Science" => "soil-science.png",
  "Garden Planning" => "garden-planning.png",
  "Vines and Wines Vintner" => "vines-and-wines-vintner.png"
];
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Browse Courses | Student Course Enrollment System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/artichoke.png">
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="index.php">
            <img src="assets/carrot.png" alt="Home" class="nav-icon">
            <span>Home</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="MyCourses.php">
            <img src="assets/onion.png" alt="My Courses" class="nav-icon">
            <span>My Courses</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link active" aria-current="page" href="BrowseCourses.php">
            <img src="assets/artichoke.png" alt="Browse" class="nav-icon">
            <span>Browse</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="Profile.php">
            <img src="assets/capsicum.png" alt="Profile" class="nav-icon">
            <span>Profile</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="Logout.php">
            <img src="assets/corn.png" alt="Logout" class="nav-icon">
            <span>Logout</span>
          </a>
        </li>
      </ul>
      <div class="navbar-greeting">Hi, <?php echo htmlspecialchars($_SESSION["firstname"] ?? "Gardener"); ?>!</div>
    </div>
  </nav>

  <main class="container py-4 py-md-5">
    <div class="d-flex align-items-center gap-3 mb-4">
      <img src="assets/artichoke.png" alt="" class="browse-heading-icon">
      <div>
        <h1 class="h3 mb-1">Browse Courses</h1>
        <p class="text-muted mb-0">Find an upcoming Garden University course.</p>
      </div>
    </div>

    <?php if ($message !== "") : ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <section class="card shadow-sm border-0 search-panel mb-4" aria-labelledby="course-search-heading">
      <div class="card-body p-3 p-md-4">
        <h2 id="course-search-heading" class="visually-hidden">Search courses</h2>
        <form method="GET" action="BrowseCourses.php">
          <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
              <label for="program" class="form-label fw-semibold">Program</label>
              <select class="form-select" id="program" name="program">
                <option value="">All programs</option>
                <?php while ($program = $programs->fetch_assoc()) : ?>
                  <option value="<?php echo (int) $program["programid"]; ?>"
                    <?php echo $selectedProgram === (string) $program["programid"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($program["programname"]); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-sm-6 col-lg-3">
              <label for="semester" class="form-label fw-semibold">Semester</label>
              <select class="form-select" id="semester" name="semester">
                <?php while ($semester = $semesters->fetch_assoc()) : ?>
                  <option value="<?php echo (int) $semester["semesterid"]; ?>"
                    <?php echo $selectedSemesterId === (int) $semester["semesterid"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($semester["semestername"]); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-sm-6 col-lg-3">
              <label for="courseId" class="form-label fw-semibold">Course ID</label>
              <input type="text" class="form-control" id="courseId" name="courseId"
                value="<?php echo htmlspecialchars($courseId); ?>" placeholder="Example: GAR101">
            </div>

            <div class="col-sm-6 col-lg-3 d-grid">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-search me-2"></i>Search
              </button>
            </div>
          </div>
        </form>
      </div>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 mb-0">Upcoming Courses</h2>
      <span class="text-muted small"><?php echo $courseCount; ?> course<?php echo $courseCount === 1 ? "" : "s"; ?> found</span>
    </div>

    <?php if ($courseCount > 0) : ?>
      <div class="row g-4">
        <?php while ($course = $courses->fetch_assoc()) : ?>
          <?php
            $startDate = date("M j, Y", strtotime($course["startdate"]));
            $endDate = date("M j, Y", strtotime($course["enddate"]));
            $courseTime = date("g:i A", strtotime($course["coursetime"]));
            $programGraphic = $programGraphics[$course["programname"]] ?? "";
            $actionConfirmation = (int) $course["availableseats"] > 0
              ? "Enroll in " . $course["coursecode"] . " " . $course["coursename"] . " for " . $course["semester"] . "?"
              : "Join the waitlist for " . $course["coursecode"] . " " . $course["coursename"] . " for " . $course["semester"] . "?";
          ?>
          <div class="col-md-6 col-xl-4">
            <article class="card course-card h-100 shadow-sm border-0">
              <?php if ($programGraphic !== "") : ?>
                <img src="assets/programs/<?php echo htmlspecialchars($programGraphic); ?>" alt=""
                  class="course-watermark" aria-hidden="true">
              <?php endif; ?>
              <div class="card-body d-flex flex-column p-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <span class="badge text-bg-success"><?php echo htmlspecialchars($course["semester"]); ?></span>
                  <span class="badge program-badge"><?php echo htmlspecialchars($course["programname"]); ?></span>
                </div>
                <h3 class="h5 course-number"><?php echo htmlspecialchars($course["coursecode"]); ?></h3>
                <p class="course-name mb-2"><?php echo htmlspecialchars($course["coursename"]); ?></p>
                <p class="text-muted course-description" title="<?php echo htmlspecialchars($course["coursedescription"]); ?>">
                  <?php echo htmlspecialchars($course["coursedescription"]); ?>
                </p>
                <div class="course-details mt-auto pt-3 border-top">
                  <p class="mb-2">
                    <i class="bi bi-clock text-success me-2"></i>
                    <?php echo htmlspecialchars($course["courseday"]); ?> at <?php echo $courseTime; ?>
                    <span class="text-muted">(<?php echo (int) $course["courselength"]; ?> minutes)</span>
                  </p>
                  <p class="mb-0">
                    <i class="bi bi-calendar3 text-success me-2"></i>
                    <?php echo $startDate; ?> &ndash; <?php echo $endDate; ?>
                  </p>
                  <p class="mb-0 mt-2">
                    <i class="bi bi-people text-success me-2"></i>
                    <?php echo (int) $course["availableseats"]; ?> of <?php echo (int) $course["capacity"]; ?> seats available
                  </p>
                </div>
                <form method="POST" class="mt-4"
                  onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($actionConfirmation), ENT_QUOTES); ?>);">
                  <input type="hidden" name="offeringId" value="<?php echo (int) $course["offeringid"]; ?>">
                  <input type="hidden" name="enrollmentAction" value="<?php echo (int) $course["availableseats"] > 0 ? "enroll" : "waitlist"; ?>">
                  <input type="hidden" name="enrollmentToken" value="<?php echo htmlspecialchars($_SESSION["enrollmentToken"]); ?>">
                  <button type="submit" class="btn <?php echo (int) $course["availableseats"] > 0 ? "btn-success" : "btn-warning"; ?> w-100 enroll-button"
                    aria-label="<?php echo (int) $course["availableseats"] > 0 ? "Enroll in " : "Join the waitlist for "; ?><?php echo htmlspecialchars($course["coursecode"] . " " . $course["coursename"]); ?>">
                    <?php if ((int) $course["availableseats"] > 0) : ?>
                      <i class="bi bi-journal-plus me-2"></i>Enroll
                    <?php else : ?>
                      <i class="bi bi-hourglass-split me-2"></i>Join Waitlist
                    <?php endif; ?>
                  </button>
                </form>
              </div>
            </article>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else : ?>
      <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
          <i class="bi bi-search fs-1 text-success"></i>
          <h3 class="h5 mt-3">No courses matched your search</h3>
          <p class="text-muted mb-3">Try changing or clearing one of the optional filters.</p>
          <a href="BrowseCourses.php" class="btn btn-outline-success">Clear filters</a>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <footer class="container mt-4 mb-5 text-center">
    <hr>
    <div class="d-flex justify-content-center gap-4 mt-3">
      <a href="#" class="text-decoration-none icon-link"><i class="bi bi-envelope-fill fs-3"></i><span class="icon-label">Subscribe</span></a>
      <a href="#" class="text-decoration-none icon-link"><i class="bi bi-share-fill fs-3"></i><span class="icon-label">Share</span></a>
      <a href="#" class="text-decoration-none icon-link"><i class="bi bi-bell-fill fs-3"></i><span class="icon-label">Alerts</span></a>
      <a href="#" class="text-decoration-none icon-link"><i class="bi bi-gear-fill fs-3"></i><span class="icon-label">Settings</span></a>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $con->close(); ?>
