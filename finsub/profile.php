<?php
include 'auth.php'; // Assuming auth.php handles session start and basic auth
include 'config.php';
include 'templates/navbar.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user data
$stmt_user = mysqli_prepare($conn, "SELECT username, email FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_result = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header("Location: logout.php");
    exit;
}

// Check session messages from update_profile.php
if (isset($_SESSION['profile_message'])) {
    $message = $_SESSION['profile_message'];
    unset($_SESSION['profile_message']);
}
if (isset($_SESSION['profile_error'])) {
    $error = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile Settings | FinSub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function openModal(id) {
      document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
      document.getElementById(id).classList.add('hidden');
    }

    function validatePasswordChange() {
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const passwordMatchError = document.getElementById('password-match-error');
      const passwordLengthError = document.getElementById('password-length-error');

      let isValid = true;

      // Reset errors
      passwordMatchError.classList.add('hidden');
      passwordLengthError.classList.add('hidden');

      if (newPassword.length < 6) {
        passwordLengthError.classList.remove('hidden');
        isValid = false;
      }

      if (newPassword !== confirmPassword) {
        passwordMatchError.classList.remove('hidden');
        isValid = false;
      }

      return isValid;
    }
  </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<section class="p-6 md:p-10 max-w-xl mx-auto">
  <h1 class="text-3xl font-bold text-gray-900 mb-8">Profile Settings</h1>

  <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4 shadow-sm" role="alert">
      <p class="text-sm"><?= htmlspecialchars($message) ?></p>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4 shadow-sm" role="alert">
      <p class="text-sm"><?= htmlspecialchars($error) ?></p>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="divide-y">
      <div class="p-6 flex justify-between items-center">
        <div>
          <p class="text-sm text-gray-500">Username</p>
          <p class="text-lg font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
        </div>
        <button onclick="openModal('modalUsername')" class="text-blue-600 hover:underline font-semibold px-4 py-2 rounded-md border border-blue-600 hover:bg-blue-50 transition duration-200">Edit</button>
      </div>
      <div class="p-6 flex justify-between items-center">
        <div>
          <p class="text-sm text-gray-500">Password</p>
          <p class="text-lg font-medium text-gray-800">••••••••</p>
        </div>
        <button onclick="openModal('modalPassword')" class="text-blue-600 hover:underline font-semibold px-4 py-2 rounded-md border border-blue-600 hover:bg-blue-50 transition duration-200">Change</button>
      </div>
      <div class="p-6">
        <p class="text-sm text-gray-500">Email</p>
        <p class="text-lg font-medium text-gray-800"><?= htmlspecialchars($user['email']) ?></p>
      </div>
    </div>
  </div>
</section>

<div id="modalUsername" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-xl w-full max-w-md p-6 space-y-5 shadow-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Change Username</h2>
    <form method="POST" action="update_profile.php" class="space-y-4">
      <input type="hidden" name="type" value="username">
      <div>
        <label for="new_username" class="block text-sm font-medium text-gray-700 mb-1">New Username</label>
        <input type="text" name="new_username" id="new_username" class="mt-1 w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" required>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeModal('modalUsername')" class="px-5 py-2.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100 transition duration-200">Cancel</button>
        <button type="submit" class="bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div id="modalPassword" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-xl w-full max-w-md p-6 space-y-5 shadow-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Change Password</h2>
    <form method="POST" action="update_profile.php" class="space-y-4" onsubmit="return validatePasswordChange()">
      <input type="hidden" name="type" value="password">
      <div>
        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
        <input type="password" name="current_password" id="current_password" class="mt-1 w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" required>
      </div>
      <div>
        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
        <input type="password" name="new_password" id="new_password" minlength="6" class="mt-1 w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" required>
        <p id="password-length-error" class="text-red-600 text-xs mt-1 hidden">Password must be at least 6 characters long.</p>
      </div>
      <div>
        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="mt-1 w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200" required>
        <p id="password-match-error" class="text-red-600 text-xs mt-1 hidden">Confirm password does not match.</p>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeModal('modalPassword')" class="px-5 py-2.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100 transition duration-200">Cancel</button>
        <button type="submit" class="bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">Save Changes</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>