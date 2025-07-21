<?php
include 'auth.php';
include 'config.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];

    if ($type === 'username') {
        $new_username = trim($_POST['new_username']);

        if (empty($new_username)) {
            $error = "Username cannot be empty.";
        } else {
            // Check if username already exists
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
            mysqli_stmt_bind_param($stmt_check, "si", $new_username, $user_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $error = "Username already taken.";
            } else {
                $stmt_update = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update, "si", $new_username, $user_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $_SESSION['username'] = $new_username;
                    $message = "Username updated successfully!";
                } else {
                    $error = "Error updating username: " . mysqli_stmt_error($stmt_update);
                }
                mysqli_stmt_close($stmt_update);
            }
            mysqli_stmt_close($stmt_check);
        }

    } elseif ($type === 'password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Get current password hash
        $stmt_get = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_get, "i", $user_id);
        mysqli_stmt_execute($stmt_get);
        $result = mysqli_stmt_get_result($stmt_get);
        $row = mysqli_fetch_assoc($result);
        $db_password_hash = $row['password'];
        mysqli_stmt_close($stmt_get);

        if (!password_verify($current_password, $db_password_hash)) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirm password do not match.";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $user_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $message = "Password updated successfully!";
            } else {
                $error = "Error updating password: " . mysqli_stmt_error($stmt_update);
            }
            mysqli_stmt_close($stmt_update);
        }

    } else {
        $error = "Invalid update type.";
    }
}

// Redirect back with message
if (!empty($message)) {
    $_SESSION['profile_message'] = $message;
} elseif (!empty($error)) {
    $_SESSION['profile_error'] = $error;
}

header("Location: profile.php");
exit;
?>
