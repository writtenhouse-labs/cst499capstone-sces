<?php
/*
  File: Register.php
  Description: Registration page for Garden University's Student Course Enrollment System
  Author: Sarah Manago
  Date: 09-25-2026
*/
session_start();
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/services/MockStudentInformationSystem.php";

$db = new Database();
$con = $db->connect();
$studentSystem = new MockStudentInformationSystem();

$message = "";
$messageType = "";
$selectedProgram = trim($_POST["program"] ?? "");

//Set user fields from information entered on the page
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
  $studentEmail = trim($_POST["studentEmail"] ?? "");
  $password = trim($_POST["password"] ?? "");
  $firstName = trim($_POST["firstName"] ?? "");
  $lastName = trim($_POST["lastName"] ?? "");
  $programId = ctype_digit($selectedProgram) ? (int) $selectedProgram : 0;
  $phone = trim($_POST["phone"] ?? "");
  $studentId = strtoupper(trim($_POST["studentId"] ?? ""));

  if (
    $studentEmail === "" || $password === "" || $firstName === "" || $lastName === "" ||
    !$programId || $phone === "" || $studentId === ""
  ) {
    $message = "Please complete all fields.";
    $messageType = "danger";
  } elseif (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
    $message = "Please enter a valid email address.";
    $messageType = "danger";
  } else {
    try {
      //Call student information system integration function to retrieve the student from the SIS
      //This is stubbed by providing mock data mockSisStudents.php
      $sisStudent = $studentSystem->findStudent($studentId);
    } catch (RuntimeException $exception) {
      $sisStudent = null;
      $message = "The Student Information System is temporarily unavailable. Please try again.";
      $messageType = "danger";
    }

    //Check student was found and is active 
    if ($message === "" && $sisStudent === null) {
      $message = "That Student ID was not found in the Garden University Student Information System. Contact Garden University's Registration Office at (800) GAR-DENU.";
      $messageType = "danger";
    } elseif ($message === "" && empty($sisStudent["active"])) {
      $message = "The Student Information System shows that this student is not active and cannot register for classes. Contact Garden University's Registration Office at (800) GAR-DENU.";
      $messageType = "warning";
    } elseif ($message === "") {
      //Verify the student information in the SIS matches the registration information
      $identityMatches = strcasecmp($firstName, (string) $sisStudent["firstName"]) === 0
        && strcasecmp($lastName, (string) $sisStudent["lastName"]) === 0
        && strcasecmp($studentEmail, (string) $sisStudent["studentEmail"]) === 0;

      if (!$identityMatches) {
        $message = "Your name and student email must match the Student Information System record.";
        $messageType = "danger";
      } else {
        //Check to see if the student already exists in the SCES
        $safeStudentEmail = $con->real_escape_string((string) $sisStudent["studentEmail"]);
        $safeStudentId = $con->real_escape_string((string) $sisStudent["gardenStudentId"]);
        $existingStudent = $db->executeSelectQuery(
          $con,
          "SELECT studentid FROM student
           WHERE studentemail = '$safeStudentEmail' OR gardenstudentid = '$safeStudentId'
           LIMIT 1"
        );

        if ($existingStudent->num_rows > 0) {
          $message = "An SCES account already exists for this Garden University student.";
          $messageType = "info";
        } else {
          //Build the student insert statement
          $hashedPassword = $con->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
          $safeFirstName = $con->real_escape_string((string) $sisStudent["firstName"]);
          $safeLastName = $con->real_escape_string((string) $sisStudent["lastName"]);
          $safePhone = $con->real_escape_string($phone);
          $safeEnrollmentDate = $con->real_escape_string((string) $sisStudent["enrollmentDate"]);
          //Insert the new student registration record into the SCES database
          $insertSql = "INSERT INTO student
            (studentemail, password, firstname, lastname, program, phone, gardenstudentid,
             active, enrollmentdate)
            VALUES
            ('$safeStudentEmail', '$hashedPassword', '$safeFirstName', '$safeLastName',
             $programId, '$safePhone', '$safeStudentId', 1, '$safeEnrollmentDate')";

          //If successful, display confirmation message
          if ($db->executeQuery($con, $insertSql)) {
            $message = "Student verified and registration completed! Login to browse and enroll in courses.";
            $messageType = "success";
            $selectedProgram = "";
            $_POST = [];
          }
        }
      }
    }
  }
}

$programs = $db->executeSelectQuery(
  $con,
  "SELECT programid, programname FROM program WHERE active = 1 ORDER BY programname"
);
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register | Employee Portal</title>
  <!-- import bootstrap styles and add the pepper icon -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/capsicum.png">
</head>

<body>
  <!-- Navigation bar -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <a class="navbar-brand" href="index.php"></a>
      <!-- Navigation links -->
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="index.php">
            <img src="assets/carrot.png" alt="Home" class="nav-icon">
            <span>Home</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="Login.php">
            <img src="assets/corn.png" alt="Login" class="nav-icon">
            <span>Login</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-icon-link active" href="Register.php">
            <img src="assets/capsicum.png" alt="Register" class="nav-icon">
            <span>Register</span>
          </a>
        </li>
      </ul>
      <?php if (!empty($_SESSION["loggedIn"])) : ?>
        <div class="navbar-greeting">Hi, <?php echo htmlspecialchars($_SESSION["firstname"] ?? "Gardener"); ?>!</div>
      <?php endif; ?>
    </div>
  </nav>
  <!-- Draw the registration form card -->
  <main class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <img src="assets/capsicum.png" alt="Register" class="mb-3" style="width: 70px; height: 70px; object-fit: contain;">
              <h1 class="h3">Enroll in courses at Garden University</h1>
              <p class="text-muted mb-0">Create your user account to browse and enroll in our tossed salad of course options.
            </div>

            <?php if (!empty($message)) : ?>
              <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
              </div>
            <?php endif; ?>

            <!-- Add registration form fields -->
            <form method="POST" action="Register.php">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="firstName" class="form-label">First Name</label>
                  <input type="text" class="form-control" id="firstName" name="firstName"
                    value="<?php echo htmlspecialchars($_POST["firstName"] ?? ""); ?>" required>
                </div>

                <div class="col-md-6">
                  <label for="lastName" class="form-label">Last Name</label>
                  <input type="text" class="form-control" id="lastName" name="lastName"
                    value="<?php echo htmlspecialchars($_POST["lastName"] ?? ""); ?>" required>
                </div>

                <div class="col-md-6">
                  <label for="studentEmail" class="form-label"> Student Email</label>
                  <input type="email" class="form-control" id="studentEmail" name="studentEmail"
                    value="<?php echo htmlspecialchars($_POST["studentEmail"] ?? ""); ?>" required>
                </div>

                <div class="col-md-6">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="col-12">
                  <label for="program" class="form-label">Program of Study</label>
                  <select class="form-select" id="program" name="program" required>
                    <option value="" <?php echo $selectedProgram === "" ? "selected" : ""; ?> disabled>Select a program</option>
                    <?php while ($program = $programs->fetch_assoc()) : ?>
                      <option value="<?php echo (int) $program["programid"]; ?>"
                        <?php echo $selectedProgram === (string) $program["programid"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($program["programname"]); ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label for="phone" class="form-label">Phone</label>
                  <input type="text" class="form-control" id="phone" name="phone" placeholder="555-555-5555"
                    value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>" required>
                </div>

                <div class="col-md-4">
                  <label for="studentId" class="form-label">Student ID</label>
                  <input type="text" class="form-control" id="studentId" name="studentId" placeholder="GU12345"
                    value="<?php echo htmlspecialchars($_POST["studentId"] ?? ""); ?>" required>
                </div>

                <div class="d-grid mt-4">
                  <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-person-plus-fill me-2"></i>Create Account
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="container mt-5 text-center">
    <hr>
    <!-- Footer bar -->
    <div class="d-flex justify-content-center gap-4 mt-3">
      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-envelope-fill fs-3"></i>
        <span class="icon-label">Subscribe</span>
      </a>

      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-share-fill fs-3"></i>
        <span class="icon-label">Share</span>
      </a>

      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-bell-fill fs-3"></i>
        <span class="icon-label">Alerts</span>
      </a>

      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-gear-fill fs-3"></i>
        <span class="icon-label">Settings</span>
      </a>
    </div>
  </div>

  <!-- run the bootstrap script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $con->close(); ?>
