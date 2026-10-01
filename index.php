<?php
/*
  File: index.php
  Description: Main landing page for Garden University's Student Course Enrollment System
  Author: Sarah Manago
  Date: 09-25-2026
*/
session_start();
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Garden University</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=<?php echo filemtime(__DIR__ . "/css/styles.css"); ?>">
  <link rel="icon" type="image/png" href="assets/avocado.png">
</head>


<!-- Navigation: Provides navigation links to access other pages within the site -->
<body>
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
      <a class="navbar-brand" href="index.php">
      </a>

      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link nav-icon-link" href="index.php">
            <img src="assets/carrot.png" alt="Home" class="nav-icon">
            <span>Home</span>
          </a>
        </li>

        <?php if (empty($_SESSION["loggedIn"])): ?>
          <!-- NOTLOGGED IN -->
          <li class="nav-item">
            <a class="nav-link nav-icon-link" href="Login.php">
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

        <?php else: ?>
          <!-- LOGGED IN -->
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
  
  <!-- Main Content: Displays the main content of the page, a placeholder for future content -->
  <div class="container mt-5">
    <h1>Welcome to Garden University's Student Course Enrollment Portal</h1>
    <p>This portal helps <b>Garden University</b> students search and enroll in our delicious and nutritious garden courses.</p>

    <address>
      <strong>Sarah Manago</strong><br>
      CST499: Capstone for Computer Software Technology<br>
      Final Capstone Project<br>
      Due October 12, 2026
    </address>
  </div>

  <!-- Footer: Contains icons for subscribing, sharing, alerts, and settings -->
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
