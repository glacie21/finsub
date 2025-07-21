<?php
// set_reminder.php

// Pastikan sesi dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Arahkan ke halaman login jika belum login
    exit();
}

// Sertakan file koneksi database Anda
require_once 'db_connection.php'; // Sesuaikan dengan path file koneksi database Anda

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- Logika untuk menyimpan pengaturan pengingat ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reminder_settings'])) {
    foreach ($_POST['subscription_id'] as $sub_id) {
        $reminder_enabled = isset($_POST['reminder_enabled'][$sub_id]) ? 1 : 0;
        $reminder_days_before = isset($_POST['reminder_days_before'][$sub_id]) ? intval($_POST['reminder_days_before'][$sub_id]) : 0;

        try {
            // Update atau insert pengaturan pengingat ke database
            // Asumsi ada tabel 'subscription_reminders' atau kolom di tabel 'subscriptions'
            // Contoh ini mengasumsikan kolom 'reminder_enabled' dan 'reminder_days_before' di tabel 'subscriptions'

            $stmt = $pdo->prepare("UPDATE subscriptions SET reminder_enabled = ?, reminder_days_before = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$reminder_enabled, $reminder_days_before, $sub_id, $user_id]);

            $message = "Pengaturan pengingat berhasil disimpan!";

        } catch (PDOException $e) {
            $error = "Gagal menyimpan pengaturan pengingat: " . $e->getMessage();
            break; // Hentikan loop jika ada error
        }
    }
}

// --- Ambil data langganan pengguna dari database ---
$subscriptions = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, next_due_date, reminder_enabled, reminder_days_before FROM subscriptions WHERE user_id = ? ORDER BY next_due_date ASC");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Gagal memuat langganan: " . $e->getMessage();
}

// Sertakan navbar
include_once 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Reminder - FinSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <main class="flex-grow container mx-auto px-4 py-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Pengaturan Pengingat Langganan</h2>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($subscriptions)): ?>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-gray-600">Anda belum memiliki langganan yang terdaftar. Tambahkan langganan terlebih dahulu untuk mengatur pengingat.</p>
                <a href="index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">Tambahkan Langganan</a>
            </div>
        <?php else: ?>
            <form action="set_reminder.php" method="POST" class="bg-white p-6 rounded-lg shadow-md">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Langganan
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jatuh Tempo Berikutnya
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktifkan Pengingat
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kirim Pengingat (Hari Sebelum)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($sub['next_due_date']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <input type="hidden" name="subscription_id[]" value="<?= $sub['id'] ?>">
                                        <input type="checkbox" name="reminder_enabled[<?= $sub['id'] ?>]" value="1"
                                            <?= $sub['reminder_enabled'] ? 'checked' : '' ?>
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <select name="reminder_days_before[<?= $sub['id'] ?>]"
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <?php foreach ([0, 1, 3, 5, 7, 14, 30] as $days): ?>
                                                <option value="<?= $days ?>" <?= ($sub['reminder_days_before'] == $days) ? 'selected' : '' ?>>
                                                    <?php
                                                    if ($days == 0) {
                                                        echo "Pada Hari H";
                                                    } else {
                                                        echo $days . " Hari Sebelum";
                                                    }
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 text-right">
                    <button type="submit" name="submit_reminder_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </main>

</body>
</html>