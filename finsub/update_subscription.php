<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $next_payment_date = $_POST['next_payment_date'] ?? null;
    $billing_cycle = $_POST['billing_cycle'] ?? null;

    if (!$id || !$next_payment_date || !$billing_cycle) {
        echo json_encode(['status' => 'error', 'message' => 'Incomplete input']);
        exit;
    }

    $update_stmt = mysqli_prepare($conn, "
      UPDATE subscriptions 
      SET next_payment_date = ?, payment_method = ?
      WHERE id = ?
    ");
    mysqli_stmt_bind_param($update_stmt, "ssi", $next_payment_date, $billing_cycle, $id);
    $exec = mysqli_stmt_execute($update_stmt);

    if ($exec) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
    }
}
