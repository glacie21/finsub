<?php
include 'auth.php';
include 'config.php';

// Hentikan (set Inactive) satu langganan milik user yang sedang login.
// Langganan Inactive tidak akan diperpanjang otomatis; aktifkan lagi lewat Edit.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    die("Permintaan tidak valid.");
}

$user_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "UPDATE subscriptions SET status = 'Inactive' WHERE id = ? AND user_id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    error_log("Query error: " . mysqli_error($conn));
    die("Terjadi kesalahan pada sistem.");
}

header("Location: detail_subscription.php?id=" . $id);
exit;
?>
