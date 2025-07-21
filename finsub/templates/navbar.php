<?php
// Pastikan session sudah dimulai di halaman yang memanggil navbar ini.
// Contoh: dashboard.php, index.php, insight.php, recommendations.php
// Jika tidak, Anda mungkin perlu menambahkan session_start() di awal file-file tersebut.
// session_start(); // Jangan uncomment ini jika sudah ada di file induk!

// Pastikan config.php sudah di-include di file induk untuk koneksi database ($conn)
// Contoh: include 'config.php';

// Ambil user ID dari session
// Pastikan auth.php atau mekanisme login Anda sudah mengisi $_SESSION['user_id']
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest'; // Asumsi username disimpan di session

$upcoming_subscriptions = [];
$notification_count = 0;

if ($user_id && isset($conn)) { // Hanya jalankan query jika user_id dan koneksi DB tersedia
    // Query untuk mendapatkan langganan aktif yang next_payment_date-nya dalam 30 hari ke depan
    $stmt_upcoming_subs = mysqli_prepare($conn, "
        SELECT s.id, a.name AS app_name, s.next_payment_date, s.payment_method
        FROM subscriptions s
        JOIN apps a ON s.app_id = a.id
        WHERE s.user_id = ?
        AND s.status = 'Active'
        AND s.next_payment_date <= CURDATE() + INTERVAL 30 DAY
        AND s.next_payment_date >= CURDATE()
        ORDER BY s.next_payment_date ASC
        LIMIT 5
    ");

    if ($stmt_upcoming_subs) {
        mysqli_stmt_bind_param($stmt_upcoming_subs, "i", $user_id);
        mysqli_stmt_execute($stmt_upcoming_subs);
        $result_upcoming_subs = mysqli_stmt_get_result($stmt_upcoming_subs);

        while ($row = mysqli_fetch_assoc($result_upcoming_subs)) {
            $upcoming_subscriptions[] = $row;
            $notification_count++;
        }
        mysqli_stmt_close($stmt_upcoming_subs);
    } else {
        // Handle error, in a real application you would log this
        error_log('Error preparing upcoming subscriptions query: ' . mysqli_error($conn));
    }
}
?>

<nav class="bg-white shadow-md px-6 py-4 flex justify-between items-center">
  <div class="flex items-center gap-2">
    <h1 class="text-xl font-bold text-blue-600">FinSub</h1>
  </div>

  <ul class="hidden md:flex gap-8 text-sm font-medium text-gray-700 mx-auto">
    <li><a href="homepage.php" class="hover:text-blue-600 transition">Homepage</a></li>
    <li><a href="dashboard.php" class="hover:text-blue-600 transition">Dashboard</a></li>
    <li><a href="index.php" class="hover:text-blue-600 transition">Subscriptions</a></li>
    <li><a href="insight.php" class="hover:text-blue-600 transition">Insight</a></li>
  </ul>

  <div class="hidden md:flex items-center space-x-4 relative">
    <!-- Notification Bell -->
    <div class="relative">
        <button id="notificationBell" class="relative p-2 text-gray-700 hover:text-blue-600 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <?php if ($notification_count > 0): ?>
                <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full transform translate-x-1/2 -translate-y-1/2">
                    <?= $notification_count ?>
                </span>
            <?php endif; ?>
        </button>

        <div id="notificationDropdown" class="absolute right-0 mt-2 w-72 bg-white rounded-md shadow-lg py-1 z-20 hidden">
            <div class="block px-4 py-2 text-xs text-gray-400 border-b border-gray-100">Upcoming Payments (Next 30 Days)</div>
            <?php if (!empty($upcoming_subscriptions)): ?>
                <?php foreach ($upcoming_subscriptions as $sub): ?>
                    <a href="detail_subscription.php?id=<?= $sub['id'] ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <div class="ml-1">
                            <span class="font-semibold"><?= htmlspecialchars($sub['app_name']) ?></span>
                            <p class="text-xs text-gray-500">
                                Due: <?= date('M j, Y', strtotime($sub['next_payment_date'])) ?> (<?= htmlspecialchars($sub['payment_method']) ?>)
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="block px-4 py-3 text-sm text-gray-700">No upcoming payments.</div>
            <?php endif; ?>
            <a href="index.php?filter=Active" class="block text-center px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 border-t border-gray-200">View All Active Subscriptions</a>
        </div>
    </div>

    <!-- Profile Menu -->
    <div class="relative">
        <button id="profileMenuButton" class="flex items-center space-x-2 text-gray-700 hover:text-blue-600 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <?= htmlspecialchars($username) ?>
        </button>
        <ul id="profileDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-10">
            <li><a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a></li>
            <li><a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a></li>
        </ul>
    </div>
  </div>

  <div class="md:hidden">
    <button id="menuButton" class="text-gray-700 focus:outline-none">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
      </svg>
    </button>
  </div>
</nav>

<div id="mobileMenu" class="md:hidden hidden bg-white shadow-lg absolute inset-x-0 top-0 pt-16 z-10 animate-slide-down">
  <ul class="flex flex-col gap-4 p-4 text-gray-700 font-medium">
    <li><a href="homepage.php" class="block py-2 hover:bg-gray-100 rounded transition">Homepage</a></li>
    <li><a href="dashboard.php" class="block py-2 hover:bg-gray-100 rounded transition">Dashboard</a></li>
    <li><a href="index.php" class="block py-2 hover:bg-gray-100 rounded transition">Subscriptions</a></li>
    <li><a href="insight.php" class="block py-2 hover:bg-gray-100 rounded transition">Insight</a></li>
    <li><a href="recommendations.php" class="block py-2 hover:bg-gray-100 rounded transition">Recommendations</a></li>
    <li class="border-t border-gray-200 pt-4 mt-4"></li>
    <li><a href="profile.php" class="block py-2 hover:bg-gray-100 rounded transition">Profile</a></li>
    <li><a href="logout.php" class="block py-2 text-red-600 hover:bg-red-50 rounded transition">Logout</a></li>
  </ul>
</div>

<script>
  const menuButton = document.getElementById('menuButton');
  const mobileMenu = document.getElementById('mobileMenu');
  const profileMenuButton = document.getElementById('profileMenuButton');
  const profileDropdown = document.getElementById('profileDropdown');

  // NEW: Notification elements
  const notificationBell = document.getElementById('notificationBell');
  const notificationDropdown = document.getElementById('notificationDropdown');


  // Toggle mobile menu visibility
  menuButton.addEventListener('click', () => {
    mobileMenu.classList.toggle('hidden');
    mobileMenu.classList.toggle('animate-slide-down');
    profileDropdown.classList.add('hidden'); // Close desktop profile dropdown if mobile menu opened
    notificationDropdown.classList.add('hidden'); // NEW: Close notification dropdown if mobile menu opened
  });

  // Toggle profile dropdown for desktop
  profileMenuButton.addEventListener('click', (event) => {
    profileDropdown.classList.toggle('hidden');
    notificationDropdown.classList.add('hidden'); // NEW: Close notification dropdown when profile dropdown opened
    event.stopPropagation(); // Prevent document click from closing immediately
  });

  // Toggle notification dropdown
  notificationBell.addEventListener('click', (event) => {
    notificationDropdown.classList.toggle('hidden');
    profileDropdown.classList.add('hidden'); // Close profile dropdown when notification dropdown opened
    event.stopPropagation(); // Prevent document click from closing immediately
  });

  // Close all dropdowns when clicking outside
  window.addEventListener('click', (event) => {
    if (!profileMenuButton.contains(event.target) && !profileDropdown.contains(event.target)) {
      profileDropdown.classList.add('hidden');
    }
    // NEW: Close notification dropdown when clicking outside
    if (!notificationBell.contains(event.target) && !notificationDropdown.contains(event.target)) {
      notificationDropdown.classList.add('hidden');
    }
  });
</script>

<style>
  /* Slide down animation for mobile menu */
  @keyframes slideDown {
    0% { opacity: 0; transform: translateY(-10px); }
    100% { opacity: 1; transform: translateY(0); }
  }
  .animate-slide-down {
    animation: slideDown 0.3s ease-out forwards;
  }
</style>