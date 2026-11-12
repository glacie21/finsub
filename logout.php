<?php
require_once 'session.php'; // Mulai session

session_unset(); // Hapus semua variable session
session_destroy(); // Hancurkan session

header("Location: login.php"); // Redirect ke login page
exit;
?>