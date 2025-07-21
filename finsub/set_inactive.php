<?php
include 'config.php';

// Memperbarui status langganan menjadi 'Inactive'
// di mana next_payment_date telah lewat dari tanggal saat ini.
mysqli_query($conn, "UPDATE subscriptions SET status = 'Inactive' WHERE next_payment_date < CURDATE()");
?>