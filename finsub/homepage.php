<?php
include 'config.php'; // Includes database connection
session_start();
// No database connection needed for a static homepage
// include 'config.php'; // Uncomment if you need DB connection for dynamic content on homepage
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FinSub - Your Subscription Manager</title>
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
<body class="bg-gray-50 text-gray-800">

  <?php include 'templates/navbar.php'; ?>

  <main class="container mx-auto px-4 py-8 md:py-16">
    <section class="text-center py-16 md:py-24 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg mb-16">
      <h1 class="text-4xl md:text-6xl font-extrabold text-gray-900 leading-tight mb-6">
        Master Your Subscriptions <br class="hidden sm:inline">with <span class="text-blue-600">FinSub</span>
      </h1>
      <p class="text-lg md:text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
        Never lose track of your recurring payments again. FinSub helps you monitor, manage, and optimize your subscriptions effortlessly.
      </p>
      <div class="flex flex-col sm:flex-row justify-center gap-4">
        <?php if (!isset($_SESSION['user_id'])): ?>
          <a href="register.php" class="bg-blue-600 text-white px-8 py-3 rounded-full text-lg font-semibold hover:bg-blue-700 transition-colors duration-300 shadow-md transform hover:scale-105">
            Get Started Free
          </a>
          <a href="login.php" class="bg-white text-blue-600 border border-blue-600 px-8 py-3 rounded-full text-lg font-semibold hover:bg-blue-50 transition-colors duration-300 shadow-md transform hover:scale-105">
            Log In
          </a>
        <?php else: ?>
          <a href="index.php" class="bg-blue-600 text-white px-8 py-3 rounded-full text-lg font-semibold hover:bg-blue-700 transition-colors duration-300 shadow-md transform hover:scale-105">
            Go to Subscription
          </a>
        <?php endif; ?>
      </div>
    </section>

    <section class="py-12 md:py-16">
      <h2 class="text-3xl md:text-4xl font-bold text-center text-gray-900 mb-12">Features Designed for You</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">
        <div class="bg-white p-6 rounded-xl shadow-md text-center transform hover:scale-105 transition-transform duration-300">
          <div class="bg-blue-100 text-blue-600 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m4 0H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2z"></path></svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">All Subscriptions in One Place</h3>
          <p class="text-gray-600">Get a clear overview of all your active and inactive subscriptions at a glance, no more surprises.</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md text-center transform hover:scale-105 transition-transform duration-300">
          <div class="bg-green-100 text-green-600 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3m0-6v6m0 0l3-3m-3 3l-3-3"></path></svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">Track Your Spending</h3>
          <p class="text-gray-600">See exactly how much you're spending monthly and yearly on your subscriptions and identify areas to save.</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md text-center transform hover:scale-105 transition-transform duration-300">
          <div class="bg-purple-100 text-purple-600 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">Upcoming Payment Reminders</h3>
          <p class="text-gray-600">Never miss a payment or renewal date with timely notifications for your peace of mind.</p>
        </div>
      </div>
    </section>

    <section class="bg-blue-600 text-white py-16 md:py-20 rounded-xl text-center shadow-lg my-16">
      <h2 class="text-3xl md:text-5xl font-bold mb-6">Ready to Take Control?</h2>
      <p class="text-lg md:text-xl mb-10 max-w-2xl mx-auto">
        Join thousands of users who are simplifying their financial lives with FinSub. It's free, fast, and secure.
      </p>
      <?php if (!isset($_SESSION['user_id'])): ?>
        <a href="register.php" class="bg-white text-blue-600 px-10 py-4 rounded-full text-xl font-bold hover:bg-gray-100 transition-colors duration-300 shadow-lg transform hover:scale-105">
          Sign Up Now
        </a>
      <?php else: ?>
        <a href="dashboard.php" class="bg-white text-blue-600 px-10 py-4 rounded-full text-xl font-bold hover:bg-gray-100 transition-colors duration-300 shadow-lg transform hover:scale-105">
          View My Dashboard
        </a>
      <?php endif; ?>
    </section>
  </main>

  <footer class="bg-gray-800 text-white py-8 text-center">
    <p>&copy; <?php echo date("Y"); ?> FinSub. All rights reserved.</p>
    <p class="text-sm mt-2">
      <a href="#" class="hover:underline mx-2">Privacy Policy</a> |
      <a href="#" class="hover:underline mx-2">Terms of Service</a>
    </p>
  </footer>

</body>
</html>