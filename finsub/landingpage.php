<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FinSub - Simplify Your Subscriptions</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

  <!-- Navbar -->
  <nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
      <a href="landing.php" class="text-2xl font-bold text-blue-600">FinSub</a>
      <div>
        <a href="login.php" class="bg-blue-600 text-white px-5 py-2 rounded-full font-semibold hover:bg-blue-700 transition">Log In</a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="bg-gradient-to-br from-blue-50 to-blue-100 py-20">
    <div class="max-w-3xl mx-auto px-4 text-center">
      <h1 class="text-5xl font-extrabold text-gray-900 mb-4">Simplify Your Subscriptions with <span class="text-blue-600">FinSub</span></h1>
      <p class="text-lg text-gray-700 mb-8">Track, manage, and optimize all your subscriptions in one place. Stay organized and save more effortlessly.</p>
      <a href="login.php" class="bg-blue-600 text-white px-10 py-4 rounded-full text-lg font-semibold hover:bg-blue-700 transition transform hover:scale-105 shadow-md">Get Started</a>
    </div>
  </section>

  <!-- Features Section -->
  <section class="py-20">
    <div class="max-w-5xl mx-auto px-4">
      <h2 class="text-3xl font-bold text-center mb-12 text-gray-900">Why Choose FinSub?</h2>
      <div class="grid gap-8 md:grid-cols-3">

        <div class="bg-white p-6 rounded-2xl shadow hover:shadow-md transition text-center">
          <div class="bg-blue-100 text-blue-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <!-- icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M21 12c0 4.418-3.582 8-8 8H7a4 4 0 01-4-4V7a4 4 0 014-4h6c4.418 0 8 3.582 8 8z" />
            </svg>
          </div>
          <h3 class="text-xl font-semibold mb-2">Centralized Dashboard</h3>
          <p class="text-gray-600">View all your subscriptions in one organized place.</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow hover:shadow-md transition text-center">
          <div class="bg-green-100 text-green-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <!-- icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-xl font-semibold mb-2">Easy to Use</h3>
          <p class="text-gray-600">Intuitive design that makes managing subscriptions effortless.</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow hover:shadow-md transition text-center">
          <div class="bg-purple-100 text-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <!-- icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.104 0-2 .896-2 2 0 .341.086.66.236.936L4 20h16l-6.236-9.064c.15-.276.236-.595.236-.936 0-1.104-.896-2-2-2z" />
            </svg>
          </div>
          <h3 class="text-xl font-semibold mb-2">Secure & Private</h3>
          <p class="text-gray-600">Your subscription data is stored safely with full privacy protection.</p>
        </div>

      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-white py-6 border-t mt-12">
    <div class="max-w-7xl mx-auto px-4 text-center text-gray-500 text-sm">
      &copy; <?= date('Y') ?> FinSub. All rights reserved.
    </div>
  </footer>

</body>
</html>
