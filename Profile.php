<?php
/*
  File: Profile.php
  Description: Displays Profile informaiton after user logs in
  Author: Sarah Manago
  Date: 09-25-2026
*/
session_start();

//Redirect if not logged in
if (empty($_SESSION["loggedIn"])) {
  header("Location: Login.php");
  exit();
}

//Connect to the database
require_once "Database.php";
$db = new Database();
$con = $db->connect();

//Get the session email
$studentEmail = $_SESSION["studentemail"];
$studentEmail = $con->real_escape_string($studentEmail);

//Build the sql query and execute it using Database class
$selectSql = "SELECT s.studentid, s.gardenstudentid, s.firstname, s.lastname,
                     s.studentemail, s.password, s.phone, p.programname,
                     s.active, s.enrollmentdate
              FROM student s
              INNER JOIN program p ON p.programid = s.program
              WHERE s.studentemail = '$studentEmail'";

$result = $db->executeSelectQuery($con, $selectSql);

//Validate a user was found
if ($result && $result->num_rows > 0) {
  $user = $result->fetch_assoc();

  //load the user vars
  $id = $user["studentid"];
  $studentId = $user["gardenstudentid"];
  $firstName = $user["firstname"];
  $lastName = $user["lastname"];
  $studentEmail = $user["studentemail"];
  $password = $user["password"];
  $program = $user["programname"];
  $phone = $user["phone"];
  $active = $user["active"];
  $enrollmentStatus = ((int) $active === 1)? "Green Thumbs Up": "Call Registration Office";
  $enrollmentDate = $user["enrollmentdate"];
}
//Clean up
$con->close();
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile | Garden University's Student Course Enrollment Portal</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/capsicum.png">
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <ul class="navbar-nav mx-auto">

        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="index.php">
            <img src="assets/carrot.png" class="nav-icon"><span>Home</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nav-icon-link active" href="MyCourses.php">
            <img src="assets/onion.png" alt="My Courses" class="nav-icon">
            <span>My Courses</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nav-icon-link active" href="BrowseCourses.php">
            <img src="assets/artichoke.png" alt="Browse" class="nav-icon">
            <span>Browse</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nav-icon-link active" href="Profile.php">
            <img src="assets/capsicum.png" class="nav-icon"><span>Profile</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="Logout.php">
            <img src="assets/corn.png" class="nav-icon"><span>Logout</span>
          </a>
        </li>

      </ul>
      <div class="navbar-greeting">Hi, <?php echo htmlspecialchars($_SESSION["firstname"] ?? $firstName ?? "Gardener"); ?>!</div>
    </div>
  </nav>

  <!-- Profile Card -->
  <main class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">

        <div class="card shadow-sm border-0">
          <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
              <img src="assets/capsicum.png" alt="Profile" class="mb-3" style="width:70px;">
              <h1 class="h3">My Profile</h1>
              <p class="text-muted">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</p>
            </div>

            <!-- Profile Info -->
            <div class="row g-3">

              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($firstName); ?>" disabled>
              </div>

              <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($lastName); ?>" disabled>
              </div>

              <div class="col-12">
                <label class="form-label">Email</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($studentEmail); ?>" disabled>
              </div>

              <div class="col-12">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" value="********" disabled>
              </div>

              <div class="col-6">
                <label class="form-label">Program</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($program); ?>" disabled>
              </div>

              <div class="col-6">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" disabled>
              </div>

              <div class="col-6">
                <label class="form-label">Enrollment Date</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($enrollmentDate); ?>" disabled>
              </div>

              <div class="col-6">
                <label class="form-label">Enrollment Status</label>
                <input
                  type="text"
                  class="form-control"
                  value="<?php echo htmlspecialchars($enrollmentStatus); ?>"
                  disabled
                >
              </div>

              <div class="col-6">
                <label class="form-label">Student Id</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($studentId); ?>" disabled>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <div class="container mt-5 text-center">
    <hr>
    <div class="d-flex justify-content-center gap-4 mt-3">
      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-envelope-fill fs-3"></i><span>Subscribe</span>
      </a>
      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-share-fill fs-3"></i><span>Share</span>
      </a>
      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-bell-fill fs-3"></i><span>Alerts</span>
      </a>
      <a href="#" class="text-decoration-none icon-link">
        <i class="bi bi-gear-fill fs-3"></i><span>Settings</span>
      </a>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
