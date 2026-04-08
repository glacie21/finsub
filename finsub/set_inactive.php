<?php
include 'config.php';

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Query untuk memperbarui status langganan menjadi 'Inactive'
// jika tanggal next_payment_date sudah lewat dari hari ini
$query = "UPDATE subscriptions 
          SET status = 'Inactive' 
          WHERE next_payment_date < CURDATE()";

// Eksekusi query dan cek hasilnya
if (mysqli_query($conn, $query)) {
    // Optional: bisa ditambahkan logging jika diperlukan
    // echo "Status berhasil diperbarui.";
} else {
    // Menampilkan error jika query gagal
    error_log("Query error: " . mysqli_error($conn));
}
?>
