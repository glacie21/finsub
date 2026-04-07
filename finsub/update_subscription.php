<?php
include 'config.php';

// Security header
header('Content-Type: application/json');
header("X-Content-Type-Options: nosniff");

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Validasi input dengan filter_input
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$next_payment_date = filter_input(INPUT_POST, 'next_payment_date', FILTER_SANITIZE_STRING);
$billing_cycle = filter_input(INPUT_POST, 'billing_cycle', FILTER_SANITIZE_STRING);

// Validasi data
if (!$id || !$next_payment_date || !$billing_cycle) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete or invalid input']);
    exit;
}

// Prepare statement
$update_stmt = mysqli_prepare($conn, "
    UPDATE subscriptions 
    SET next_payment_date = ?, payment_method = ?
    WHERE id = ?
");

// Cek prepare berhasil
if (!$update_stmt) {
    error_log("Prepare failed: " . mysqli_error($conn));
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
    exit;
}

// Bind parameter
mysqli_stmt_bind_param($update_stmt, "ssi", $next_payment_date, $billing_cycle, $id);

// Eksekusi
if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode(['status' => 'success']);
} else {
    error_log("Execute failed: " . mysqli_stmt_error($update_stmt));
    echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
}

// Tutup statement
mysqli_stmt_close($update_stmt);

// Tutup koneksi (opsional kalau tidak dipakai lagi)
mysqli_close($conn);
?>
