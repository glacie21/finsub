<?php
include 'config.php';
// Anda mungkin perlu menyertakan auth.php atau memastikan session_start() dan $user_id diatur di sini
// Contoh:
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null; // Dapatkan ID pengguna dari sesi

if (!$user_id) {
    // Arahkan ke halaman login jika user_id tidak ada
    header('Location: login.php');
    exit;
}


// Handle Add Subscription POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $app_id = $_POST['app_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;
    $start_date = $_POST['start_date'] ?? date('Y-m-d'); // Ambil start_date dari form

    if (!is_numeric($app_id) || !$payment_method) {
        die("Invalid input");
    }

    // Ambil available_cycles, monthly_price, dan yearly_price dari db
    // Juga ambil category_id dari tabel apps
    $stmt = mysqli_prepare($conn, "SELECT available_cycles, monthly_price, yearly_price, name, category_id FROM apps WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $app_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $app_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$app_data) {
        die("App not found.");
    }

    $price_to_use = 0;
    if ($payment_method == 'Monthly') {
        $price_to_use = $app_data['monthly_price'];
    } elseif ($payment_method == 'Yearly') {
        $price_to_use = $app_data['yearly_price'];
    }

    if ($price_to_use <= 0) {
        die("Invalid price for selected billing cycle.");
    }

    // Hitung next_payment_date berdasarkan start_date dan payment_method
    $next_payment_date = date('Y-m-d', strtotime($start_date . ($payment_method == 'Monthly' ? ' +1 month' : ' +1 year')));

    // Insert ke tabel subscriptions
    $stmt_insert = mysqli_prepare($conn, "INSERT INTO subscriptions (user_id, app_id, payment_method, price, next_payment_date, status, start_date) VALUES (?, ?, ?, ?, ?, 'Active', ?)");
    mysqli_stmt_bind_param($stmt_insert, "iisdss", $user_id, $app_id, $payment_method, $price_to_use, $next_payment_date, $start_date);

    if (mysqli_stmt_execute($stmt_insert)) {
        $subscription_id = mysqli_insert_id($conn); // Dapatkan ID langganan yang baru dibuat
        
        // --- START: Tambahkan Log Pengeluaran Pertama ---
        $description = "Initial payment for " . $app_data['name'] . " (" . $payment_method . ")";
        $stmt_log = mysqli_prepare($conn, "INSERT INTO spending_logs (user_id, subscription_id, app_id, amount, log_date, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        // Perhatikan bahwa $app_data['category_id'] tidak langsung masuk ke spending_logs, 
        // tapi kita masukkan app_id, sehingga nanti bisa diJOIN dengan apps dan categories.
        // transaction_date akan menyimpan tanggal pembayaran actual.
        mysqli_stmt_bind_param($stmt_log, "iidssis", $user_id, $subscription_id, $app_id, $price_to_use, date('Y-m-d'), $description, $start_date); 
        
        if (!mysqli_stmt_execute($stmt_log)) {
            error_log("Error inserting initial spending log: " . mysqli_error($conn));
            // Anda bisa menambahkan pesan error ke user jika perlu, atau cukup log saja
        }
        mysqli_stmt_close($stmt_log);
        // --- END: Tambahkan Log Pengeluaran Pertama ---

        header('Location: index.php');
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_insert);
}

// Fetch apps data for the form
$apps_query = "SELECT id, name, available_cycles, monthly_price, yearly_price FROM apps";
$apps_result = mysqli_query($conn, $apps_query);
$apps = mysqli_fetch_all($apps_result, MYSQLI_ASSOC);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subscription</title>
    <link href="./css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans antialiased">
  <section class="container mx-auto mt-10 p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Add New Subscription</h2>
      <form action="add_subscription.php" method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Select Application</label>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($apps as $app): ?>
              <label class="app-radio flex items-center gap-3 p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors duration-200"
                     data-available="<?= htmlspecialchars($app['available_cycles']) ?>"
                     data-monthly-price="<?= htmlspecialchars($app['monthly_price']) ?>"
                     data-yearly-price="<?= htmlspecialchars($app['yearly_price']) ?>">
                <input type="radio" name="app_id" value="<?= $app['id'] ?>" class="form-radio h-4 w-4 text-blue-600" required>
                <span class="text-gray-900 font-semibold"><?= htmlspecialchars($app['name']) ?></span>
                <div class="ml-auto text-sm text-gray-600">
                    <?php 
                        $prices = [];
                        if ($app['monthly_price']) $prices[] = '$' . number_format($app['monthly_price'], 2) . ' (M)';
                        if ($app['yearly_price']) $prices[] = '$' . number_format($app['yearly_price'], 2) . ' (Y)';
                        echo implode(' / ', $prices);
                    ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Billing Cycle</label>
          <div class="flex gap-4">
            <label class="flex-1 flex items-center gap-2 border p-3 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200">
              <input type="radio" id="cycleMonthly" name="payment_method" value="Monthly" class="form-radio h-4 w-4 text-blue-600" required>
              <span class="text-gray-900">Monthly</span>
            </label>
            <label class="flex-1 flex items-center gap-2 border p-3 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200">
              <input type="radio" id="cycleYearly" name="payment_method" value="Yearly" class="form-radio h-4 w-4 text-blue-600">
              <span class="text-gray-900">Yearly</span>
            </label>
          </div>
        </div>

        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Subscription Start Date</label>
            <input type="date" id="start_date" name="start_date" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500" value="<?= date('Y-m-d') ?>" required>
            <p class="text-xs text-gray-500 mt-1">Select the date your subscription initially started or will start.</p>
        </div>
        
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Next Payment Date Preview</label>
            <p id="next_payment_preview" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
                Automatically calculated
            </p>
            <p class="text-xs text-gray-500 mt-1">This date will be automatically calculated based on your selected start date and billing cycle.</p>
        </div>


        <div class="flex gap-4 pt-4">
          <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors duration-200 font-semibold">Add Subscription</button>
          <a href="index.php" class="flex-1 bg-gray-200 text-gray-800 text-center py-3 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-semibold">Back to List</a>
        </div>
      </form>
    </div>
  </section>

<script>
  const appRadios = document.querySelectorAll('.app-radio input[type="radio"]');
  const monthlyRadio = document.getElementById('cycleMonthly');
  const yearlyRadio = document.getElementById('cycleYearly');
  const startDateInput = document.getElementById('start_date');
  const nextPaymentPreview = document.getElementById('next_payment_preview');

  function updatePaymentOptions() {
    const selectedApp = Array.from(appRadios).find(radio => radio.checked);
    
    // Disable and uncheck both by default
    monthlyRadio.disabled = true;
    monthlyRadio.checked = false;
    yearlyRadio.disabled = true;
    yearlyRadio.checked = false;

    if (!selectedApp) {
      updateNextPaymentDatePreview(); // Clear preview if no app selected
      return;
    }

    const availableCycles = selectedApp.closest('.app-radio').dataset.available.split(',').map(s => s.trim());

    if (availableCycles.includes('Monthly')) {
      monthlyRadio.disabled = false;
      // If no cycle is currently checked, or if monthly was previously checked and is now enabled
      if (!yearlyRadio.checked || monthlyRadio.checked) {
        monthlyRadio.checked = true;
      }
    } else {
        // If monthly is not available, ensure it's not checked
        monthlyRadio.checked = false;
    }

    if (availableCycles.includes('Yearly')) {
      yearlyRadio.disabled = false;
      // If no cycle is currently checked, or if yearly was previously checked and is now enabled
      if (!monthlyRadio.checked || yearlyRadio.checked) {
        yearlyRadio.checked = true;
      }
    } else {
        // If yearly is not available, ensure it's not checked
        yearlyRadio.checked = false;
    }

    // If, after all checks, nothing is checked (e.g., app has no available cycles, though unlikely)
    // or if the previously selected cycle is now disabled, default to the first available one.
    if (!monthlyRadio.checked && !yearlyRadio.checked) {
        if (availableCycles.includes('Monthly')) {
            monthlyRadio.checked = true;
        } else if (availableCycles.includes('Yearly')) {
            yearlyRadio.checked = true;
        }
    }

    updateNextPaymentDatePreview();
  }

  function updateNextPaymentDatePreview() {
      const selectedApp = Array.from(appRadios).find(radio => radio.checked);
      const startDate = startDateInput.value;
      const selectedCycle = document.querySelector('input[name="payment_method"]:checked')?.value;

      if (!startDate || !selectedApp || !selectedCycle) {
          nextPaymentPreview.textContent = 'Select start date, app, and billing cycle to preview.';
          return;
      }

      const date = new Date(startDate);
      if (selectedCycle === 'Monthly') {
          date.setMonth(date.getMonth() + 1);
      } else if (selectedCycle === 'Yearly') {
          date.setFullYear(date.getFullYear() + 1);
      }

      // Format date to YYYY-MM-DD
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      nextPaymentPreview.textContent = `${year}-${month}-${day}`;
  }


  // Event Listeners
  appRadios.forEach(radio => radio.addEventListener('change', updatePaymentOptions));
  monthlyRadio.addEventListener('change', updateNextPaymentDatePreview);
  yearlyRadio.addEventListener('change', updateNextPaymentDatePreview);
  startDateInput.addEventListener('change', updateNextPaymentDatePreview);

  // Initial call to set correct state on page load
  updatePaymentOptions(); // This will also call updateNextPaymentDatePreview
</script>

</body>
</html>