<?php
include 'auth.php';
include 'config.php';

// Hanya izinkan POST agar penghapusan tidak bisa dipicu lewat link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Validasi parameter id
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    die("Permintaan tidak valid.");
}

$user_id = (int) $_SESSION['user_id'];

// Hapus hanya langganan milik user yang sedang login
$stmt = mysqli_prepare($conn, "DELETE FROM subscriptions WHERE id = ? AND user_id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
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
