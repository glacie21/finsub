<?php
// Mulai session dengan cookie yang aman dan proteksi CSRF untuk semua request POST.
// Include file ini sebagai pengganti session_start() di setiap halaman.

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;

    // Tolak session id yang tidak dibuat oleh server (mencegah session fixation)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https, // Di localhost (http) cookie tetap jalan
        'httponly' => true,      // Cookie tidak bisa dibaca JavaScript
        'samesite' => 'Lax',     // Cookie tidak ikut terkirim pada POST dari situs lain
    ]);

    session_start();
}

// Token CSRF per session
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden input untuk disisipkan di setiap <form method="POST">
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Setiap POST wajib membawa token yang cocok; jika tidak, hentikan request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (!is_string($submitted_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        http_response_code(403);
        $message = 'Sesi Anda sudah kedaluwarsa atau permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';

        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
        } else {
            echo htmlspecialchars($message);
        }
        exit;
    }
}
?>
