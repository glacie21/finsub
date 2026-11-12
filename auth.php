<?php
require_once 'session.php';

if (!isset($_SESSION['user_id'])) {
  // Jika belum login, redirect ke login page
  header("Location: login.php");
  exit;
}
?>
