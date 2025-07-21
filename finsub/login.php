<?php
session_start();
include 'config.php';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['password'])) {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Query user
  $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM users WHERE username = ?");
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $user = mysqli_fetch_assoc($result);

  if ($user && password_verify($password, $user['password'])) {
    // Login success
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    header("Location: Homepage.php"); // Redirect to dashboard
    exit;
  } else {
    $error = "Incorrect username or password.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | FinSub</title>
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
    <h1 class="text-4xl font-extrabold text-center mb-4 text-gray-900">Welcome Back</h1>
    <p class="text-center text-gray-600 mb-6">Log in to manage your subscriptions</p>

    <?php if (isset($error)) { ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5 shadow-sm" role="alert">
      <p class="text-sm"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php } ?>

    <form method="POST" class="space-y-5">
      <div>
        <label for="username" class="block mb-2 text-sm font-semibold text-gray-700">Username</label>
        <input type="text" name="username" id="username" required
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
               placeholder="Enter your username">
      </div>
      <div>
        <label for="password" class="block mb-2 text-sm font-semibold text-gray-700">Password</label>
        <input type="password" name="password" id="password" required
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
               placeholder="********">
      </div>
      <button type="submit"
              class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 transform hover:scale-105 shadow-md">
        Log In
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
      Don't have an account?
      <a href="register.php" class="text-blue-600 hover:underline font-semibold">Sign up now</a>
    </p>
  </div>

</body>
</html>
