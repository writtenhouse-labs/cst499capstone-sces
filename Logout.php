<?php
/*
  File: Logout.php
  Description: Logs user out of Garden University's Student Course Enrollment System and ends their session
  Author: Sarah Manago
  Date: 09-25-2026
*/
session_start();

$_SESSION = [];

session_unset();
session_destroy();

setcookie(session_name(), "", time() - 3600, "/");

header("Location: Login.php?logout=1");
exit();
