<?php
/*
  File: Login.php
  Description: Login page for Garden University's Student Course Enrollment System
  Author: Sarah Manago
  Date: 09-25-2026
*/

//Start session and track logged-in state
session_start();

//Include database class to manage the connection
require_once "Database.php";

//Initialize the message strings
$message = "";
$messageType = "";

//Display logged out confirmation if the user selects Logout nav link
if (isset($_GET["logout"])) {
  $message = "You are now logged out. Please log in again.";
  $messageType = "success";
}

//Handle login submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = trim($_POST["studentEmail"] ?? "");
  $password = trim($_POST["password"] ?? "");

  //Validate the username and passwrod were entered
  if (empty($email) || empty($password)) {
    $message = "Please enter your email and password.";
    $messageType = "danger";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "Please enter a valid email address.";
    $messageType = "danger";
  } else {
    //Connect to the database
    $db = new Database();
    $con = $db->connect();

    //Sanitize user email
    $email = $con->real_escape_string($email);

    //Query the database with entered credentials using database class function
    $selectSql = "SELECT * FROM student 
                  WHERE studentemail = '$email'";

    $result = $db->executeSelectQuery($con, $selectSql);

    //If the user is found, set the profile fields and transition to the profile page
    if ($result && $result->num_rows > 0) {
      $user = $result->fetch_assoc();

      //Verify the hashed password
      if (password_verify($password, $user["password"])) {

        $_SESSION["loggedIn"] = true;
        $_SESSION["studentemail"] = $user["studentemail"];
        $_SESSION["firstname"] = $user["firstname"];
        $_SESSION["lastname"] = $user["lastname"];

        header("Location: Profile.php");
        exit();
      } else {
        //If the user wasn't found or the password was incorrect
        $message = "Invalid email or password. Please try again.";
        $messageType = "danger";
      }
    } else {
      //If the user wasn't found or the password was incorrect
      $message = "Invalid email or password. Please try again.";
      $messageType = "danger";
    }

    //Close database connection
    $con->close();
  }
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Employee Portal</title>
  <!-- Load the bootstrap scripts -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/corn.png">
</head>

<body>
  <!-- Navigation: Provides navigation links to access other pages within the site -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <ul class="navbar-nav mx-auto">

        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="index.php">
            <img src="assets/carrot.png" alt="Home" class="nav-icon">
            <span>Home</span>
          </a>
        </li>

        <?php if (empty($_SESSION["loggedIn"])) : ?>
          <li class="nav-item">
            <a class="nav-link nav-icon-link active" href="Login.php">
              <img src="assets/corn.png" alt="Login" class="nav-icon">
              <span>Login</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link nav-icon-link" href="Register.php">
              <img src="assets/capsicum.png" alt="Register" class="nav-icon">
              <span>Register</span>
            </a>
          </li>

        <?php else : ?>
          <li class="nav-item">
            <a class="nav-link nav-icon-link active" href="MyCourses.php">
              <img src="assets/onion.png" alt="My Courses" class="nav-icon">
              <span>My Courses</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link nav-icon-link active" href="BrowseCourses.php">
              <img src="assets/artichoke.png" alt="Browse" class="nav-icon">
              <span>Browse Courses</span>
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
        <?php endif; ?>

      </ul>
      <?php if (!empty($_SESSION["loggedIn"])) : ?>
        <div class="navbar-greeting">Hi, <?php echo htmlspecialchars($_SESSION["firstname"] ?? "Gardener"); ?>!</div>
      <?php endif; ?>
    </div>
  </nav>
  <!-- Login form: collects user credentials and submits to server for validation -->
  <main class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
              <img src="assets/corn.png" alt="Login" class="mb-3" style="width: 70px; height: 70px; object-fit: contain;">
              <h1 class="h3">Student Login</h1>
              <p class="text-muted mb-0">Log in to browse and enroll in Garden University's courses.</p>
            </div>

            <?php if (!empty($message)) : ?>
              <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <?php echo $message; ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="Login.php">
              <div class="row g-3">

                <div class="col-12">
                  <label for="studentEmail" class="form-label">Email</label>
                  <input type="email" class="form-control" id="studentEmail" name="studentEmail" required>
                </div>

                <div class="col-12">
                  <label for="passwordField" class="form-label">Password</label>
                  <div class="input-group">
                    <input type="password" id="passwordField" name="password" class="form-control" required>
                  </div>
                </div>

              </div>

              <div class="d-grid mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                  <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </button>
              </div>

              <div class="text-center mt-3">
                <span class="text-muted">No student enrollment account yet?</span>
                <a href="Register.php" class="fw-semibold text-decoration-none"> Register here</a>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </main>
  <!-- Footer: typical page feature links -->
  <div class="container mt-5 text-center">
    <hr>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
