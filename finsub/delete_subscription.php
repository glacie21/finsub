<?php
include 'config.php';

// Validasi parameter id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Permintaan tidak valid.");
}

$id = (int) $_GET['id'];

// Gunakan prepared statement untuk mencegah SQL Injection
$stmt = mysqli_prepare($conn, "DELETE FROM subscriptions WHERE id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    // Log error ke server
    error_log("Query preparation failed: " . mysqli_error($conn));
    die("Terjadi kesalahan pada sistem.");
}

// Redirect setelah delete
header("Location: index.php");
exit;
?>
