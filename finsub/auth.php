<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  // Jika belum login, redirect ke login page
  header("Location: login.php");
  exit;
}
?>
