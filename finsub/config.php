<?php
// Gunakan environment variable (lebih aman)
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'finsub';

// Buat koneksi
$conn = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$conn) {
    // Log error ke server (bukan ke user)
    error_log("Database connection failed: " . mysqli_connect_error());

    // Tampilkan pesan umum ke user
    die("Terjadi kesalahan pada sistem. Silakan coba lagi nanti.");
}
?>
