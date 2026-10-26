<?php
session_start();
include 'config.php';

// Security header
header('Content-Type: application/json');
header("X-Content-Type-Options: nosniff");

// Wajib login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Validasi input
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$next_payment_date = $_POST['next_payment_date'] ?? '';
$billing_cycle = $_POST['billing_cycle'] ?? '';

// Validasi data
if (!$id
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_payment_date) || !strtotime($next_payment_date)
    || !in_array($billing_cycle, ['Monthly', 'Yearly'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete or invalid input']);
    exit;
}

// Prepare statement: hanya langganan milik user yang sedang login
$update_stmt = mysqli_prepare($conn, "
    UPDATE subscriptions
    SET next_payment_date = ?, billing_cycle = ?
    WHERE id = ? AND user_id = ?
");

// Cek prepare berhasil
if (!$update_stmt) {
    error_log("Prepare failed: " . mysqli_error($conn));
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
    exit;
}

// Bind parameter
mysqli_stmt_bind_param($update_stmt, "ssii", $next_payment_date, $billing_cycle, $id, $user_id);

// Eksekusi
if (!mysqli_stmt_execute($update_stmt)) {
    error_log("Execute failed: " . mysqli_stmt_error($update_stmt));
    echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
} elseif (mysqli_stmt_affected_rows($update_stmt) === 0) {
    // Tidak ada baris berubah: id tidak ditemukan / bukan milik user, atau data sama persis
    echo json_encode(['status' => 'error', 'message' => 'Subscription not found or unchanged']);
} else {
    echo json_encode(['status' => 'success']);
}

// Tutup statement
mysqli_stmt_close($update_stmt);

// Tutup koneksi (opsional kalau tidak dipakai lagi)
mysqli_close($conn);
?>
