<?php
/*
  File: MyCourses.php
  Description: Lists a student's enrolled courses by semester with a weekly schedule.
  Author: Sarah Manago
  Date: 09-29-2026
*/
session_start();

if (empty($_SESSION["loggedIn"])) {
  header("Location: Login.php");
  exit();
}

require_once "Database.php";
$db = new Database();
$con = $db->connect();

if (empty($_SESSION["enrollmentToken"])) {
  $_SESSION["enrollmentToken"] = bin2hex(random_bytes(32));
}

$message = $_SESSION["enrollmentMessage"] ?? "";
$messageType = $message !== "" ? "success" : "";
unset($_SESSION["enrollmentMessage"]);

$studentEmail = $con->real_escape_string($_SESSION["studentemail"] ?? "");
$studentResult = $db->executeSelectQuery(
  $con,
  "SELECT studentid, firstname FROM student WHERE studentemail = '$studentEmail' LIMIT 1"
);

if ($studentResult->num_rows === 0) {
  session_unset();
  header("Location: Login.php");
  exit();
}

$student = $studentResult->fetch_assoc();
$studentId = (int) $student["studentid"];

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
  $requestedOfferingId = filter_input(INPUT_POST, "offeringId", FILTER_VALIDATE_INT);
  $submittedToken = $_POST["enrollmentToken"] ?? "";
  $enrollmentAction = $_POST["enrollmentAction"] ?? "drop";

  if (!hash_equals($_SESSION["enrollmentToken"], $submittedToken)) {
    $message = "Your request expired. Please try again.";
    $messageType = "danger";
  } elseif (!$requestedOfferingId) {
    $message = "Please select a valid course offering.";
    $messageType = "danger";
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
      $_SESSION["enrollmentMessage"] = "You left the waitlist for " .
        $waitlistedCourse["coursecode"] . " " . $waitlistedCourse["coursename"] . ".";
      header("Location: MyCourses.php");
      exit();
    }
  } else {
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
      $_SESSION["enrollmentMessage"] = "Enrollment dropped for " .
        $droppedCourse["coursecode"] . " " . $droppedCourse["coursename"] . ".";
      header("Location: MyCourses.php");
      exit();
    }
  }
}

$courseResult = $db->executeSelectQuery(
  $con,
  "SELECT schedule.*
   FROM (
     SELECT o.offeringid, c.courseid, c.coursecode, c.coursename, c.coursedescription,
            s.semestername AS semester, o.courseday, o.courselength,
            o.coursetime, o.capacity, o.availableseats, s.startdate, s.enddate,
            p.programname, 'enrolled' AS enrollmentstatus, NULL AS waitlistposition
     FROM student_enrollments e
     INNER JOIN course_offerings o ON o.offeringid = e.offeringid
     INNER JOIN courses c ON c.courseid = o.courseid
     INNER JOIN semesters s ON s.semesterid = o.semesterid
     INNER JOIN program p ON p.programid = c.programid
     WHERE e.studentid = $studentId

     UNION ALL

     SELECT o.offeringid, c.courseid, c.coursecode, c.coursename, c.coursedescription,
            s.semestername AS semester, o.courseday, o.courselength,
            o.coursetime, o.capacity, o.availableseats, s.startdate, s.enddate,
            p.programname, 'waitlisted' AS enrollmentstatus,
            (SELECT COUNT(*)
             FROM course_waitlist ahead
             WHERE ahead.offeringid = w.offeringid
               AND (ahead.waitlisteddate < w.waitlisteddate
                    OR (ahead.waitlisteddate = w.waitlisteddate AND ahead.waitlistid <= w.waitlistid))) AS waitlistposition
     FROM course_waitlist w
     INNER JOIN course_offerings o ON o.offeringid = w.offeringid
     INNER JOIN courses c ON c.courseid = o.courseid
     INNER JOIN semesters s ON s.semesterid = o.semesterid
     INNER JOIN program p ON p.programid = c.programid
     WHERE w.studentid = $studentId
   ) schedule
   ORDER BY schedule.startdate, schedule.coursetime, schedule.coursecode"
);

$coursesBySemester = [];
while ($course = $courseResult->fetch_assoc()) {
  $coursesBySemester[$course["semester"]][] = $course;
}

$programGraphics = [
  "Floral Design" => "floral-design.png",
  "Herbology" => "herbology.png",
  "Ranch Dressing Chef" => "ranch-dressing-chef.png",
  "Salad Chopping" => "salad-chopping.png",
  "Soil Science" => "soil-science.png",
  "Garden Planning" => "garden-planning.png",
  "Vines and Wines Vintner" => "vines-and-wines-vintner.png"
];

$meetingDays = [
  "MWF" => ["Monday", "Wednesday", "Friday"],
  "TTh" => ["Tuesday", "Thursday"]
];
$dayColumns = ["Monday" => 2, "Tuesday" => 3, "Wednesday" => 4, "Thursday" => 5, "Friday" => 6];
$calendarStartMinutes = 8 * 60;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Courses | Student Course Enrollment System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/onion.png">
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item"><a class="nav-link nav-icon-link" href="index.php"><img src="assets/carrot.png" alt="Home" class="nav-icon"><span>Home</span></a></li>
        <li class="nav-item"><a class="nav-link nav-icon-link active" aria-current="page" href="MyCourses.php"><img src="assets/onion.png" alt="My Courses" class="nav-icon"><span>My Courses</span></a></li>
        <li class="nav-item"><a class="nav-link nav-icon-link" href="BrowseCourses.php"><img src="assets/artichoke.png" alt="Browse" class="nav-icon"><span>Browse</span></a></li>
        <li class="nav-item"><a class="nav-link nav-icon-link" href="Profile.php"><img src="assets/capsicum.png" alt="Profile" class="nav-icon"><span>Profile</span></a></li>
        <li class="nav-item"><a class="nav-link nav-icon-link" href="Logout.php"><img src="assets/corn.png" alt="Logout" class="nav-icon"><span>Logout</span></a></li>
      </ul>
      <div class="navbar-greeting">Hi, <?php echo htmlspecialchars($_SESSION["firstname"] ?? $student["firstname"] ?? "Gardener"); ?>!</div>
    </div>
  </nav>

  <main class="container py-4 py-md-5">
    <div class="d-flex align-items-center gap-3 mb-4">
      <img src="assets/onion.png" alt="" class="browse-heading-icon">
      <div>
        <h1 class="h3 mb-1">My Courses</h1>
        <p class="text-muted mb-0">Your Garden University schedule, planted neatly by semester.</p>
      </div>
    </div>

    <?php if ($message !== "") : ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (empty($coursesBySemester)) : ?>
      <section class="card shadow-sm border-0 text-center">
        <div class="card-body py-5 px-4">
          <i class="bi bi-flower1 display-4 text-success"></i>
          <h2 class="h4 mt-3">Your course garden is ready to grow</h2>
          <p class="text-muted">Browse the catalog and enroll in your first course.</p>
          <a href="BrowseCourses.php" class="btn btn-success"><i class="bi bi-search me-2"></i>Browse Courses</a>
        </div>
      </section>
    <?php else : ?>
      <?php foreach ($coursesBySemester as $semester => $semesterCourses) : ?>
        <?php
          $scheduledCount = count(array_filter(
            $semesterCourses,
            fn($course) => $course["enrollmentstatus"] === "enrolled"
          ));
          $waitlistedCount = count($semesterCourses) - $scheduledCount;
        ?>
        <section class="semester-section mb-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-flower2 text-success fs-4"></i>
            <h2 class="h4 mb-0"><?php echo htmlspecialchars($semester); ?></h2>
            <span class="badge rounded-pill text-bg-success ms-1"><?php echo $scheduledCount; ?> enrolled</span>
            <?php if ($waitlistedCount > 0) : ?>
              <span class="badge rounded-pill text-bg-warning"><?php echo $waitlistedCount; ?> waitlisted</span>
            <?php endif; ?>
          </div>

          <div class="row g-3 mb-4">
            <?php foreach ($semesterCourses as $course) : ?>
              <?php $graphic = $programGraphics[$course["programname"]] ?? ""; ?>
              <div class="col-md-6 col-xl-4">
                <article class="card enrolled-course-card h-100 shadow-sm border-0">
                  <?php if ($graphic !== "") : ?>
                    <img src="assets/programs/<?php echo htmlspecialchars($graphic); ?>" alt="" class="my-course-watermark" aria-hidden="true">
                  <?php endif; ?>
                  <div class="card-body p-3 position-relative">
                    <div class="small text-success fw-semibold mb-1"><?php echo htmlspecialchars($course["programname"]); ?></div>
                    <h3 class="h6 mb-1"><?php echo htmlspecialchars($course["coursecode"] . " · " . $course["coursename"]); ?></h3>
                    <?php if ($course["enrollmentstatus"] === "waitlisted") : ?>
                      <span class="badge text-bg-warning mb-2"><i class="bi bi-hourglass-split me-1"></i>Waitlisted</span>
                      <div class="small text-muted">
                        Position <?php echo (int) $course["waitlistposition"]; ?> ·
                        <?php echo (int) $course["availableseats"]; ?> of <?php echo (int) $course["capacity"]; ?> seats available
                      </div>
                      <form method="POST" class="mt-3"
                        onsubmit="return confirm('Leave the waitlist for <?php echo htmlspecialchars($course["coursecode"], ENT_QUOTES); ?>?');">
                        <input type="hidden" name="offeringId" value="<?php echo (int) $course["offeringid"]; ?>">
                        <input type="hidden" name="enrollmentAction" value="leave_waitlist">
                        <input type="hidden" name="enrollmentToken" value="<?php echo htmlspecialchars($_SESSION["enrollmentToken"]); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark">
                          <i class="bi bi-hourglass-bottom me-1"></i>Remove from Waitlist
                        </button>
                      </form>
                    <?php else : ?>
                      <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($course["courseday"]); ?>,
                        <?php echo date("g:i A", strtotime($course["coursetime"])); ?> · <?php echo (int) $course["courselength"]; ?> min
                      </div>
                      <div class="small text-muted mt-1">
                        <i class="bi bi-people me-1"></i><?php echo (int) $course["availableseats"]; ?> of <?php echo (int) $course["capacity"]; ?> seats available
                      </div>
                      <form method="POST" class="mt-3"
                        onsubmit="return confirm('Drop <?php echo htmlspecialchars($course["coursecode"], ENT_QUOTES); ?> from your schedule?');">
                        <input type="hidden" name="offeringId" value="<?php echo (int) $course["offeringid"]; ?>">
                        <input type="hidden" name="enrollmentAction" value="drop">
                        <input type="hidden" name="enrollmentToken" value="<?php echo htmlspecialchars($_SESSION["enrollmentToken"]); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-onion">
                          <i class="bi bi-journal-minus me-1"></i>Drop Enrollment
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($semesterCourses) > 0) : ?>
          <div class="calendar-shell card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-4 px-4">
              <h3 class="h6 mb-1"><i class="bi bi-calendar-week text-success me-2"></i>Sample Course Week</h3>
              <p class="small text-muted mb-2">Enrolled and waitlisted weekly meetings for <?php echo htmlspecialchars($semester); ?></p>
              <div class="d-flex flex-wrap gap-3 small">
                <span><span class="calendar-legend enrolled"></span>Enrolled</span>
                <span><span class="calendar-legend waitlisted"></span>Waitlisted</span>
              </div>
            </div>
            <div class="card-body p-3 p-md-4 pt-3">
              <div class="calendar-scroll">
                <div class="week-calendar">
                  <div class="calendar-corner"></div>
                  <?php foreach ($dayColumns as $day => $column) : ?>
                    <div class="calendar-day-heading" style="grid-column: <?php echo $column; ?>;"><?php echo $day; ?></div>
                  <?php endforeach; ?>
                  <?php for ($hour = 8; $hour <= 18; $hour++) : ?>
                    <div class="calendar-time" style="grid-row: <?php echo 2 + (($hour - 8) * 2); ?>;">
                      <?php echo date("g A", strtotime(sprintf("%02d:00", $hour))); ?>
                    </div>
                  <?php endfor; ?>
                  <div class="calendar-grid-lines"></div>
                  <?php foreach ($semesterCourses as $course) : ?>
                    <?php
                      [$hour, $minute] = array_map("intval", explode(":", $course["coursetime"]));
                      $startMinutes = ($hour * 60) + $minute;
                      $rowStart = 2 + (int) floor(($startMinutes - $calendarStartMinutes) / 30);
                      $rowSpan = max(1, (int) ceil(((int) $course["courselength"]) / 30));
                      $days = $meetingDays[$course["courseday"]] ?? [];
                    ?>
                    <?php foreach ($days as $day) : ?>
                      <div class="calendar-event <?php echo $course["enrollmentstatus"] === "waitlisted" ? "calendar-event-waitlisted" : ""; ?>"
                        style="grid-column: <?php echo $dayColumns[$day]; ?>; grid-row: <?php echo $rowStart; ?> / span <?php echo $rowSpan; ?>;">
                        <strong><?php echo htmlspecialchars($course["coursecode"]); ?></strong>
                        <span><?php echo date("g:i A", strtotime($course["coursetime"])); ?></span>
                        <?php if ($course["enrollmentstatus"] === "waitlisted") : ?>
                          <span class="calendar-event-status">Waitlisted</span>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
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
