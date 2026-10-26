<?php
include 'auth.php';
include 'config.php';

// Cancel / resume perpanjangan otomatis untuk satu langganan milik user yang sedang login.
// Cancel (auto_renew = 0): langganan tetap Active sampai next_payment_date, lalu menjadi Inactive tanpa tagihan.
// Resume (auto_renew = 1): langganan kembali diperpanjang otomatis.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$auto_renew = filter_input(INPUT_POST, 'auto_renew', FILTER_VALIDATE_INT);

if (!$id || !in_array($auto_renew, [0, 1], true)) {
    die("Permintaan tidak valid.");
}

$user_id = (int) $_SESSION['user_id'];

// Hanya langganan Active yang bisa dibatalkan / dilanjutkan; langganan Inactive diaktifkan lewat Edit
$stmt = mysqli_prepare($conn, "UPDATE subscriptions SET auto_renew = ? WHERE id = ? AND user_id = ? AND status = 'Active'");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iii", $auto_renew, $id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    error_log("Query error: " . mysqli_error($conn));
    die("Terjadi kesalahan pada sistem.");
}

header("Location: detail_subscription.php?id=" . $id);
exit;
?>
