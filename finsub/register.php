<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['email'], $_POST['password'])) {
  $username = $_POST['username'];
  $email = $_POST['email'];
  $password = $_POST['password'];

  // Server-side password length validation
  if (strlen($password) < 6) {
    $error = "Password must be at least 6 characters long.";
  } else {
    // Check if username or email already exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
      $error = "Username or email is already registered.";
    } else {
      // Hash password
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);

      // Insert new user
      $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
      mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed_password);

      if (mysqli_stmt_execute($stmt)) {
        // Redirect to login after successful registration
        header("Location: login.php");
        exit;
      } else {
        $error = "Registration failed. Please try again.";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register | FinSub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

  <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-200">
    <h1 class="text-4xl font-extrabold text-center mb-4 text-gray-900">Create Your Account</h1>
    <p class="text-center text-gray-600 mb-6">Start managing your subscriptions effortlessly</p>

    <?php if (isset($error)) { ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5 shadow-sm" role="alert">
      <p class="text-sm"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php } ?>

    <form method="POST" class="space-y-5" onsubmit="return validateRegisterForm()">
      <div>
        <label for="username" class="block mb-2 text-sm font-semibold text-gray-700">Username</label>
        <input type="text" name="username" id="username" required
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
               placeholder="Choose a unique username">
      </div>
      <div>
        <label for="email" class="block mb-2 text-sm font-semibold text-gray-700">Email</label>
        <input type="email" name="email" id="email" required
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
               placeholder="your@email.com">
      </div>
      <div>
        <label for="password" class="block mb-2 text-sm font-semibold text-gray-700">Password</label>
        <input type="password" name="password" id="password" required minlength="6"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
               placeholder="Minimum 6 characters">
        <p id="password-error" class="text-red-600 text-xs mt-1 hidden">Password must be at least 6 characters long.</p>
      </div>
      <button type="submit"
              class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 transform hover:scale-105 shadow-md">
        Register
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
      Already have an account?
      <a href="login.php" class="text-blue-600 hover:underline font-semibold">Log in here</a>
    </p>
  </div>

  <script>
    function validateRegisterForm() {
      const password = document.getElementById('password').value;
      const passwordError = document.getElementById('password-error');

      if (password.length < 6) {
        passwordError.classList.remove('hidden');
        return false;
      } else {
        passwordError.classList.add('hidden');
        return true;
      }
    }
  </script>
</body>
</html>
