<?php
// Helper bersama untuk logika langganan.
// Pastikan config.php sudah di-include sebelum file ini ($conn tersedia).

// Harga sesuai siklus tagihan: prioritaskan harga custom user, fallback ke harga default app
function subscription_price($billing_cycle, $user_monthly_price, $user_yearly_price, $default_monthly_price, $default_yearly_price) {
    if ($billing_cycle == 'Monthly') {
        return $user_monthly_price !== NULL ? $user_monthly_price : $default_monthly_price;
    }
    if ($billing_cycle == 'Yearly') {
        return $user_yearly_price !== NULL ? $user_yearly_price : $default_yearly_price;
    }
    return 0;
}

// Tanggal tagihan berikutnya setelah $date sesuai siklus tagihan
function next_billing_date($date, $billing_cycle) {
    return date('Y-m-d', strtotime($date . ($billing_cycle == 'Yearly' ? ' +1 year' : ' +1 month')));
}

// Catat satu transaksi ke spending_logs
function log_spending($conn, $user_id, $subscription_id, $app_id, $amount, $transaction_date, $description) {
    $stmt = mysqli_prepare($conn, "INSERT INTO spending_logs (user_id, subscription_id, app_id, amount, log_date, transaction_date, description) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
    if (!$stmt) {
        error_log("Error preparing spending log: " . mysqli_error($conn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "iiidss", $user_id, $subscription_id, $app_id, $amount, $transaction_date, $description);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log("Error inserting spending log: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $ok;
}

// Perpanjang otomatis langganan Active milik user yang sudah jatuh tempo:
// catat tagihan pada setiap tanggal jatuh tempo yang terlewati, lalu majukan next_payment_date.
function renew_due_subscriptions($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT s.id, s.app_id, s.next_payment_date, s.billing_cycle, s.user_monthly_price, s.user_yearly_price,
               a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price
        FROM subscriptions s
        JOIN apps a ON s.app_id = a.id
        WHERE s.user_id = ? AND s.status = 'Active' AND s.next_payment_date <= CURDATE()
    ");
    if (!$stmt) {
        error_log("Error preparing renewal query: " . mysqli_error($conn));
        return;
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $due_subs = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $today = date('Y-m-d');
    foreach ($due_subs as $sub) {
        $amount = subscription_price($sub['billing_cycle'], $sub['user_monthly_price'], $sub['user_yearly_price'], $sub['default_monthly_price'], $sub['default_yearly_price']);
        $due_date = $sub['next_payment_date'];

        // Batasi iterasi untuk berjaga-jaga terhadap data tanggal yang rusak
        for ($i = 0; $due_date <= $today && $i < 120; $i++) {
            if ($amount > 0) {
                log_spending($conn, $user_id, $sub['id'], $sub['app_id'], $amount, $due_date, "Automatic renewal charge (" . $sub['billing_cycle'] . ")");
            }
            $due_date = next_billing_date($due_date, $sub['billing_cycle']);
        }

        $update = mysqli_prepare($conn, "UPDATE subscriptions SET next_payment_date = ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($update, "sii", $due_date, $sub['id'], $user_id);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
    }
}
?>
