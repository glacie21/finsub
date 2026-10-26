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

// Batas biaya per jam pemakaian; di atas ini langganan dianggap "boros" di recommendations.php
const COST_PER_HOUR_THRESHOLD = 1.00;

// Biaya rata-rata per bulan (harga tahunan dibagi 12)
function monthly_cost($billing_cycle, $price) {
    return $billing_cycle == 'Yearly' ? $price / 12 : $price;
}

// Selisih biaya setahun antara bayar bulanan (12x) dan bayar tahunan.
// Positif = Yearly lebih hemat. null jika salah satu harga tidak tersedia.
function yearly_savings($monthly_price, $yearly_price) {
    if (!($monthly_price > 0) || !($yearly_price > 0)) {
        return null;
    }
    return $monthly_price * 12 - $yearly_price;
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

// Proses langganan Active yang sudah jatuh tempo:
// - auto_renew = 1: catat tagihan pada setiap tanggal jatuh tempo yang terlewati, lalu majukan next_payment_date
// - auto_renew = 0 (dibatalkan user): jadikan Inactive tanpa tagihan baru
// $user_id = null memproses semua user (dipakai cron/renew_subscriptions.php).
// Mengembalikan jumlah langganan yang diperpanjang dan yang berakhir.
function renew_due_subscriptions($conn, $user_id = null) {
    $summary = ['renewed' => 0, 'expired' => 0];

    $sql = "
        SELECT s.id, s.user_id, s.app_id, s.next_payment_date, s.billing_cycle, s.auto_renew,
               s.user_monthly_price, s.user_yearly_price,
               a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price
        FROM subscriptions s
        JOIN apps a ON s.app_id = a.id
        WHERE s.status = 'Active' AND s.next_payment_date <= CURDATE()
    ";
    $stmt = mysqli_prepare($conn, $user_id === null ? $sql : $sql . " AND s.user_id = ?");
    if (!$stmt) {
        error_log("Error preparing renewal query: " . mysqli_error($conn));
        return $summary;
    }
    if ($user_id !== null) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }
    mysqli_stmt_execute($stmt);
    $due_subs = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $today = date('Y-m-d');
    foreach ($due_subs as $sub) {
        if (!$sub['auto_renew']) {
            // Periode yang sudah dibayar telah habis dan user sudah membatalkan: berhenti tanpa tagihan
            $expire = mysqli_prepare($conn, "UPDATE subscriptions SET status = 'Inactive' WHERE id = ?");
            mysqli_stmt_bind_param($expire, "i", $sub['id']);
            mysqli_stmt_execute($expire);
            mysqli_stmt_close($expire);
            $summary['expired']++;
            continue;
        }

        $amount = subscription_price($sub['billing_cycle'], $sub['user_monthly_price'], $sub['user_yearly_price'], $sub['default_monthly_price'], $sub['default_yearly_price']);
        $due_date = $sub['next_payment_date'];

        // Batasi iterasi untuk berjaga-jaga terhadap data tanggal yang rusak
        for ($i = 0; $due_date <= $today && $i < 120; $i++) {
            if ($amount > 0) {
                log_spending($conn, $sub['user_id'], $sub['id'], $sub['app_id'], $amount, $due_date, "Automatic renewal charge (" . $sub['billing_cycle'] . ")");
            }
            $due_date = next_billing_date($due_date, $sub['billing_cycle']);
        }

        $update = mysqli_prepare($conn, "UPDATE subscriptions SET next_payment_date = ? WHERE id = ?");
        mysqli_stmt_bind_param($update, "si", $due_date, $sub['id']);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
        $summary['renewed']++;
    }

    return $summary;
}
?>
