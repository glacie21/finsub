<?php
session_start(); // Mulai session

session_unset(); // Hapus semua variable session
session_destroy(); // Hancurkan session

header("Location: login.php"); // Redirect ke login page
exit;
?>