<?php
include 'auth.php';
include 'config.php';

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// Amankan session
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    die('Terjadi kesalahan. Silakan login ulang.');
}

$message = '';
$error = '';

// Validasi method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';

    if ($type === 'username') {

        $new_username = trim($_POST['new_username'] ?? '');

        if ($new_username === '') {
            $error = "Username cannot be empty.";
        } elseif (strlen($new_username) > 50) {
            $error = "Username too long.";
        } else {

            // Cek username
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
            if (!$stmt_check) {
                error_log(mysqli_error($conn));
                $error = "Terjadi kesalahan pada sistem.";
            } else {

                mysqli_stmt_bind_param($stmt_check, "si", $new_username, $user_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $error = "Username already taken.";
                } else {

                    $stmt_update = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE id = ?");
                    if (!$stmt_update) {
                        error_log(mysqli_error($conn));
                        $error = "Terjadi kesalahan pada sistem.";
                    } else {

                        mysqli_stmt_bind_param($stmt_update, "si", $new_username, $user_id);

                        if (mysqli_stmt_execute($stmt_update)) {
                            $_SESSION['username'] = $new_username;
                            $message = "Username updated successfully!";
                        } else {
                            error_log(mysqli_stmt_error($stmt_update));
                            $error = "Gagal memperbarui username.";
                        }

                        mysqli_stmt_close($stmt_update);
                    }
                }

                mysqli_stmt_close($stmt_check);
            }

        }

    } elseif ($type === 'password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Ambil password hash
        $stmt_get = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        if (!$stmt_get) {
            error_log(mysqli_error($conn));
            $error = "Terjadi kesalahan pada sistem.";
        } else {

            mysqli_stmt_bind_param($stmt_get, "i", $user_id);
            mysqli_stmt_execute($stmt_get);
            $result = mysqli_stmt_get_result($stmt_get);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_get);

            if (!$row) {
                $error = "User tidak ditemukan.";
            } elseif (!password_verify($current_password, $row['password'])) {
                $error = "Current password is incorrect.";
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Password confirmation does not match.";
            } else {

                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt_update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                if (!$stmt_update) {
                    error_log(mysqli_error($conn));
                    $error = "Terjadi kesalahan pada sistem.";
                } else {

                    mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $user_id);

                    if (mysqli_stmt_execute($stmt_update)) {
                        $message = "Password updated successfully!";
                    } else {
                        error_log(mysqli_stmt_error($stmt_update));
                        $error = "Gagal memperbarui password.";
                    }

                    mysqli_stmt_close($stmt_update);
                }
            }
        }

    } else {
        $error = "Invalid update type.";
    }
}

// Simpan ke session
if (!empty($message)) {
    $_SESSION['profile_message'] = $message;
} elseif (!empty($error)) {
    $_SESSION['profile_error'] = $error;
}

// Redirect
header("Location: profile.php");
exit;
?>
