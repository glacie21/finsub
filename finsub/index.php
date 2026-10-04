<?php
include 'auth.php'; // Includes session_start() and authentication check
include 'config.php'; // Includes database connection

// --- START: Logika Otomatis untuk Mengatur Langganan Inactive dan Mencatat Pengeluaran ---
// Cek dan catat pengeluaran untuk langganan yang akan berakhir atau sudah berakhir
$current_date = date('Y-m-d');
// MODIFIED: Select user-defined prices from subscriptions table
$stmt_check_due = mysqli_prepare($conn, "SELECT s.id, s.app_id, s.next_payment_date, s.payment_method, s.user_id, s.user_monthly_price, s.user_yearly_price, a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price FROM subscriptions s JOIN apps a ON s.app_id = a.id WHERE s.next_payment_date <= ? AND s.status = 'Active'");
if ($stmt_check_due) {
    mysqli_stmt_bind_param($stmt_check_due, "s", $current_date);
    mysqli_stmt_execute($stmt_check_due);
    $result_due_subs = mysqli_stmt_get_result($stmt_check_due);

    while ($due_sub = mysqli_fetch_assoc($result_due_subs)) {
        $amount = 0;
        // PRIORITIZE: Use user-defined price if available, otherwise fallback to app's default price
        if ($due_sub['payment_method'] == 'Monthly') {
            $amount = ($due_sub['user_monthly_price'] !== NULL) ? $due_sub['user_monthly_price'] : $due_sub['default_monthly_price'];
        } elseif ($due_sub['payment_method'] == 'Yearly') {
            $amount = ($due_sub['user_yearly_price'] !== NULL) ? $due_sub['user_yearly_price'] : $due_sub['default_yearly_price'];
        }

        if ($amount > 0) {
            // Catat pengeluaran ke spending_logs
            $log_stmt = mysqli_prepare($conn, "INSERT INTO spending_logs (user_id, subscription_id, app_id, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            $description = "Automatic charge for " . htmlspecialchars($due_sub['payment_method']) . " subscription.";
            mysqli_stmt_bind_param($log_stmt, "iiisss", $due_sub['user_id'], $due_sub['id'], $due_sub['app_id'], $amount, $due_sub['next_payment_date'], $description);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }

        // Perbarui status langganan menjadi 'Inactive'
        $update_status_stmt = mysqli_prepare($conn, "UPDATE subscriptions SET status = 'Inactive' WHERE id = ?");
        mysqli_stmt_bind_param($update_status_stmt, "i", $due_sub['id']);
        mysqli_stmt_execute($update_status_stmt);
        mysqli_stmt_close($update_status_stmt);
    }
    mysqli_stmt_close($stmt_check_due);
} else {
    error_log("Error in auto-inactivate prepare statement: " . mysqli_error($conn));
}
// --- END: Logika Otomatis untuk Mengatur Langganan Inactive dan Mencatat Pengeluaran ---

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';

// Get user ID from session. This variable will be used for all user-specific queries.
$user_id = $_SESSION['user_id'];

// --- Subscription update process (Edit) ---
// This block handles the submission of the subscription edit form.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'], $_POST['edit_billing_cycle'])) {
    $id = $_POST['edit_id'];
    $billing_cycle = $_POST['edit_billing_cycle'];
    $start_date_str = isset($_POST['edit_start_date']) ? $_POST['edit_start_date'] : date('Y-m-d'); // Default to current date if not provided
    // NEW: Get custom prices from POST data
    $user_monthly_price = isset($_POST['edit_monthly_price']) && $_POST['edit_monthly_price'] !== '' ? (float)$_POST['edit_monthly_price'] : NULL;
    $user_yearly_price = isset($_POST['edit_yearly_price']) && $_POST['edit_yearly_price'] !== '' ? (float)$_POST['edit_yearly_price'] : NULL;

    // Validate input for billing cycle
    if (!in_array($billing_cycle, ['Monthly', 'Yearly'])) {
        die('Invalid input for edit');
    }

    // Validate start date format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str) || !strtotime($start_date_str)) {
        die('Invalid start date format');
    }

    // Ambil data langganan yang ada untuk mendapatkan app_id dan payment_method sebelumnya
    $current_sub_stmt = mysqli_prepare($conn, "SELECT app_id, payment_method FROM subscriptions WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($current_sub_stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($current_sub_stmt);
    $current_sub_result = mysqli_stmt_get_result($current_sub_stmt);
    $current_sub_data = mysqli_fetch_assoc($current_sub_result);
    mysqli_stmt_close($current_sub_stmt);

    if (!$current_sub_data) {
        die('Subscription not found or not authorized to edit.');
    }
    $app_id = $current_sub_data['app_id']; // Dapatkan app_id dari langganan yang diedit

    // Calculate the next payment date based on the selected billing cycle and start date
    $next_payment_date = ($billing_cycle == 'Monthly')
        ? date('Y-m-d', strtotime($start_date_str . " +30 days"))
        : date('Y-m-d', strtotime($start_date_str . " +1 year"));

    // Prepare and execute the UPDATE statement for the subscription.
    // MODIFIED: Include user_monthly_price and user_yearly_price in the update
    $stmt = mysqli_prepare($conn, "UPDATE subscriptions SET payment_method = ?, next_payment_date = ?, status = 'Active', user_monthly_price = ?, user_yearly_price = ? WHERE id = ? AND user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssddii", $billing_cycle, $next_payment_date, $user_monthly_price, $user_yearly_price, $id, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            die('Subscription Update Error: ' . mysqli_stmt_error($stmt));
        }

        // Ambil harga yang sesuai setelah update (prioritaskan user-defined price)
        $app_price_query = mysqli_prepare($conn, "SELECT monthly_price, yearly_price FROM apps WHERE id = ?");
        mysqli_stmt_bind_param($app_price_query, "i", $app_id);
        mysqli_stmt_execute($app_price_query);
        $app_price_result = mysqli_stmt_get_result($app_price_query);
        $app_prices = mysqli_fetch_assoc($app_price_result);
        mysqli_stmt_close($app_price_query);

        $amount_to_log = 0;
        // PRIORITIZE: Use user-defined price if available, otherwise fallback to app's default price
        if ($billing_cycle == 'Monthly') {
            $amount_to_log = ($user_monthly_price !== NULL) ? $user_monthly_price : $app_prices['monthly_price'];
        } elseif ($billing_cycle == 'Yearly') {
            $amount_to_log = ($user_yearly_price !== NULL) ? $user_yearly_price : $app_prices['yearly_price'];
        }

        if ($amount_to_log > 0) {
            // Catat pengeluaran ke spending_logs saat langganan diedit
            $log_description = "Subscription updated and charged for " . htmlspecialchars($billing_cycle);
            $log_stmt = mysqli_prepare($conn, "INSERT INTO spending_logs (user_id, subscription_id, app_id, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($log_stmt, "iiisss", $user_id, $id, $app_id, $amount_to_log, date('Y-m-d'), $log_description);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }

    } else {
        die('Prepare Statement Error (Update): ' . mysqli_error($conn));
    }

    header("Location: index.php?filter=$filter");
    exit;
}

// --- Fetch subscriptions based on filter and user ID ---
$result = false; // Initialize $result to false
// MODIFIED: Select user_monthly_price and user_yearly_price from subscriptions table
$base_query = "SELECT s.*, a.name, c.name AS category_name, a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price, a.available_cycles
    FROM subscriptions s
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ?";

if ($filter == 'Active' || $filter == 'Inactive') {
    // If filter is 'Active' or 'Inactive', prepare a statement to fetch filtered subscriptions.
    $stmt = mysqli_prepare($conn, $base_query . " AND s.status = ?"); // Filter by status and user_id
    if ($stmt) {
        // Bind parameters: 'si' -> string, integer
        mysqli_stmt_bind_param($stmt, "is", $user_id, $filter); // Order changed to match query
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt); // Get the result set
            if (!$result) {
                die('Query Error (Get Result): ' . mysqli_error($conn)); // Handle errors getting result
            }
            // Fetch all results to store in a variable for later use in JS
            $subscriptions_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_data_seek($result, 0); // Reset pointer for displaying table
        } else {
            die('Execute Statement Error (Filtered Query): ' . mysqli_stmt_error($stmt)); // Handle execution errors
        }
    } else {
        die('Prepare Statement Error (Filtered Query): ' . mysqli_error($conn)); // Handle prepare errors
    }
} else {
    // If filter is 'All' or anything else, fetch all subscriptions for the user.
    $stmt = mysqli_prepare($conn, $base_query); // Filter only by user_id
    if ($stmt) {
        // Bind parameters: 'i' -> integer
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt); // Get the result set
            if (!$result) {
                die('Query Error (Get All Results): ' . mysqli_error($conn)); // Handle errors getting result
            }
            // Fetch all results to store in a variable for later use in JS
            $subscriptions_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_data_seek($result, 0); // Reset pointer for displaying table
        } else {
            die('Execute Statement Error (All Query): ' . mysqli_stmt_error($stmt)); // Handle execution errors
        }
    } else {
        die('Prepare Statement Error (All Query): ' . mysqli_error($conn)); // Handle prepare errors
    }
}

// --- Fetch all available apps for the "Add Subscription" modal ---
// This query is for populating the dropdown/radio buttons in the add form.
$apps_query = mysqli_query($conn, "SELECT a.id, a.name, c.name AS category_name, a.monthly_price, a.yearly_price, a.available_cycles
    FROM apps a
    JOIN categories c ON a.category_id = c.id");
if (!$apps_query) {
    die('Apps Query Error: ' . mysqli_error($conn)); // Handle query errors
}

// --- Subscription insertion process (Add) ---
// This block handles the submission of the add subscription form.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['app_id'], $_POST['payment_method']) && !isset($_POST['edit_id'])) {
    $app_id = $_POST['app_id'];
    $payment_method = $_POST['payment_method'];
    // NEW: Get custom prices from POST data for new subscription
    $user_monthly_price = isset($_POST['add_monthly_price']) && $_POST['add_monthly_price'] !== '' ? (float)$_POST['add_monthly_price'] : NULL;
    $user_yearly_price = isset($_POST['add_yearly_price']) && $_POST['add_yearly_price'] !== '' ? (float)$_POST['add_yearly_price'] : NULL;

    // Validate input for app_id and payment_method
    if (!is_numeric($app_id) || !in_array($payment_method, ['Monthly', 'Yearly'])) {
        die("Invalid input");
    }

    // Determine next payment date based on selected payment method
    $date = ($payment_method == 'Monthly') ? date('Y-m-d', strtotime("+30 days")) : date('Y-m-d', strtotime("+1 year"));

    // Prepare and execute the INSERT statement for a new subscription.
    // MODIFIED: Include user_monthly_price and user_yearly_price in the insert
    $stmt = mysqli_prepare($conn, "INSERT INTO subscriptions (app_id, next_payment_date, status, payment_method, user_id, user_monthly_price, user_yearly_price) VALUES (?, ?, 'Active', ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "issidd", $app_id, $date, $payment_method, $user_id, $user_monthly_price, $user_yearly_price);
        if (!mysqli_stmt_execute($stmt)) {
            die('Subscription Insertion Error: ' . mysqli_stmt_error($stmt)); // Handle execution errors
        }

        // Get the last inserted subscription ID
        $new_subscription_id = mysqli_insert_id($conn);

        // Ambil harga dari tabel apps untuk logging (prioritaskan user-defined price)
        $app_price_query = mysqli_prepare($conn, "SELECT monthly_price, yearly_price FROM apps WHERE id = ?");
        mysqli_stmt_bind_param($app_price_query, "i", $app_id);
        mysqli_stmt_execute($app_price_query);
        $app_price_result = mysqli_stmt_get_result($app_price_query);
        $app_prices = mysqli_fetch_assoc($app_price_result);
        mysqli_stmt_close($app_price_query);

        $amount_to_log = 0;
        // PRIORITIZE: Use user-defined price if available, otherwise fallback to app's default price
        if ($payment_method == 'Monthly') {
            $amount_to_log = ($user_monthly_price !== NULL) ? $user_monthly_price : $app_prices['monthly_price'];
        } elseif ($payment_method == 'Yearly') {
            $amount_to_log = ($user_yearly_price !== NULL) ? $user_yearly_price : $app_prices['yearly_price'];
        }

        if ($amount_to_log > 0) {
            // Catat pengeluaran awal ke spending_logs
            $log_description = "Initial charge for " . htmlspecialchars($payment_method) . " subscription.";
            $log_stmt = mysqli_prepare($conn, "INSERT INTO spending_logs (user_id, subscription_id, app_id, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($log_stmt, "iiisss", $user_id, $new_subscription_id, $app_id, $amount_to_log, date('Y-m-d'), $log_description);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }

    } else {
        die('Prepare Statement Error (Insertion): ' . mysqli_error($conn));
    }

    header("Location: index.php?filter=$filter");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FinSub - Subscriptions</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .toggle-button-group {
        display: flex;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #d1d5db; /* Add border to the group */
    }

    .toggle-button-group input[type="radio"] {
      display: none;
    }

    .toggle-button-group label {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1rem;
      cursor: pointer;
      font-weight: 500;
      color: #374151;
      transition: all 0.2s ease-in-out;
      flex: 1;
      background-color: white; /* Default background for labels */
    }

    .toggle-button-group label:first-of-type {
      border-right: 1px solid #d1d5db; /* Separator for buttons */
    }

    /* Style for disabled labels */
    .toggle-button-group input[type="radio"]:disabled + label {
        opacity: 0.6;
        cursor: not-allowed;
        background-color: #f3f4f6;
    }

    .toggle-button-group input[type="radio"]:checked + label {
      background-color: #2563eb;
      color: white;
      border-color: #2563eb;
      z-index: 1;
    }

    .toggle-button-group input[type="radio"]:checked + label span {
        color: white;
    }

    .toggle-button-group label:hover:not(input[type="radio"]:checked + label):not(input[type="radio"]:disabled + label) {
      background-color: #eff6ff;
      color: #1d4ed8;
    }

    .app-radio-label input[type="radio"]:checked + div {
      border-color: #2563eb;
      background-color: #eff6ff;
    }

    table thead th {
      background-color: #f3f4f6 !important;
      border-bottom: 1px solid #e5e7eb !important;
    }

    table tbody tr {
      background-color: white !important;
    }

    table tbody td {
      border-bottom: 1px solid #e5e7eb !important;
    }

    .table-container {
      min-height: 200px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans overflow-hidden">
  <?php include 'templates/navbar.php'; ?>

<section class="p-4 sm:p-6 max-w-6xl mx-auto">
  <h2 class="text-2xl font-semibold mb-6">Subscriptions</h2>

  <div class="flex gap-2 text-sm mb-4">
    <a href="?filter=Active" class="px-3 py-1 rounded-full <?= $filter == 'Active' ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">Active</a>
    <a href="?filter=All" class="px-3 py-1 rounded-full <?= $filter == 'All' ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">All</a>
    <a href="?filter=Inactive" class="px-3 py-1 rounded-full <?= $filter == 'Inactive' ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">Inactive</a>
  </div>

  <div class="flex justify-end mb-4">
    <button onclick="openAdd()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors duration-200 ease-in-out shadow-md">Add Subscription</button>
  </div>

  <div class="bg-white rounded-xl shadow-md overflow-x-auto mb-8">
  <table class="min-w-full text-sm md:text-base border-collapse">
    <thead class="bg-gray-100 text-left">
      <tr>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700">Name</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700">Category</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700">Billing Cycle</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700">Next Payment</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700 whitespace-nowrap">Amount</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700 whitespace-nowrap">Status</th>
        <th class="p-4 border-b border-gray-200 font-semibold text-gray-700 whitespace-nowrap">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
      <?php
      if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)) { ?>
        <tr data-id="<?= $row['id'] ?>" class="hover:bg-gray-50 transition-colors duration-150 ease-in-out cursor-pointer" onclick="window.location.href='detail_subscription.php?id=<?= $row['id'] ?>'">
          <td class="p-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
              <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($row['name']))) ?>.png"
                   onerror="this.src='assets/icons/default.png'"
                   alt="<?= htmlspecialchars($row['name']) ?>"
                   class="w-9 h-9 rounded-md border border-gray-200 p-1">
              <div>
                <p class="font-semibold text-gray-900 app-name"><?= htmlspecialchars($row['name']) ?></p>
              </div>
            </div>
          </td>

          <td class="p-4 border-b border-gray-200">
            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
              <?= htmlspecialchars($row['category_name']) ?>
            </span>
          </td>

          <td class="p-4 border-b border-gray-200">
            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium billing-cycle
              <?= $row['payment_method'] == 'Monthly' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' ?>">
              <?= htmlspecialchars($row['payment_method']) ?>
            </span>
          </td>

          <td class="p-4 border-b border-gray-200 next-payment" data-date="<?= $row['next_payment_date'] ?>">
            <?= date('F j, Y', strtotime($row['next_payment_date'])) ?>
          </td>

          <td class="p-4 border-b border-gray-200 font-semibold text-gray-900">
            <?php
              $amount = null;
              // PRIORITIZE: Display user-defined price if available, otherwise fallback to app's default price
              if ($row['payment_method'] == 'Monthly') {
                  $amount = ($row['user_monthly_price'] !== NULL) ? $row['user_monthly_price'] : $row['default_monthly_price'];
              } elseif ($row['payment_method'] == 'Yearly') {
                  $amount = ($row['user_yearly_price'] !== NULL) ? $row['user_yearly_price'] : $row['default_yearly_price'];
              }

              if ($amount !== null && $amount !== '') {
                  echo '$' . number_format($amount, 2);
              } else {
                  echo 'N/A';
              }
            ?>
          </td>

          <td class="p-4 border-b border-gray-200">
            <span class="flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium
              <?= $row['status'] == 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 <?= $row['status'] == 'Active' ? 'text-green-600' : 'text-gray-500' ?>" fill="currentColor" viewBox="0 0 16 16">
                <?= $row['status'] == 'Active'
                  ? '<path d="M16 2L6 12l-4-4 1.5-1.5L6 9l8.5-8.5L16 2z"/>'
                  : '<circle cx="8" cy="8" r="3"/>'; ?>
              </svg>
              <?= htmlspecialchars($row['status']) ?>
            </span>
          </td>

          <td class="p-4 border-b border-gray-200 flex gap-2 flex-wrap whitespace-nowrap">
            <button onclick="event.stopPropagation(); openEdit('<?= $row['id'] ?>')"
                     class="flex items-center gap-1 bg-yellow-500 text-white px-3 py-1 rounded text-xs hover:bg-yellow-600 transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                <path d="M17.414 2.586a2 2 0 010 2.828L8.828 14H6v-2.828l8.586-8.586a2 2 0 012.828 0z"/>
              </svg>
              Edit
            </button>
            <a href="delete_subscription.php?id=<?= $row['id'] ?>"
               onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this subscription?')"
               class="flex items-center gap-1 bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M6 4a2 2 0 012-2h4a2 2 0 012 2h4v2H2V4h4zm3 4v8h2V8H9zm4 0v8h2V8h-2z" clip-rule="evenodd"/>
              </svg>
              Delete
            </a>
          </td>
        </tr>
        <?php } ?>
      <?php else: ?>
        <tr class="bg-white">
          <td colspan="7" class="p-4 text-center border-b border-gray-200">
            <div class="flex flex-col items-center justify-center py-10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 0 012 2v6m4 0H5" />
              </svg>
              <p class="text-sm text-gray-500">No subscriptions found.</p>
            </div>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

  <script>
    const appsData = <?= json_encode(mysqli_fetch_all($apps_query, MYSQLI_ASSOC)) ?>;
    const subscriptionsData = <?= json_encode($subscriptions_data ?? []) ?>; // Pass subscription data for edit modal

    function openAdd() {
      document.getElementById('addModal').classList.remove('hidden');
      document.body.classList.add('overflow-hidden'); // Add to prevent scroll

      // Reset radio payment methods and their labels
      const monthlyToggle = document.getElementById('monthlyToggle');
      const yearlyToggle = document.getElementById('yearlyToggle');
      const monthlyToggleLabel = document.getElementById('monthlyToggleLabel');
      const yearlyToggleLabel = document.getElementById('yearlyToggleLabel');

      monthlyToggle.checked = false;
      monthlyToggle.disabled = true;
      monthlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
      document.getElementById('monthlyPriceDisplay').textContent = '';

      yearlyToggle.checked = false;
      yearlyToggle.disabled = true;
      yearlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
      document.getElementById('yearlyPriceDisplay').textContent = '';

      // Clear custom price inputs for add modal
      document.getElementById('add_monthly_price').value = '';
      document.getElementById('add_yearly_price').value = '';

      // Reset app radio button selections and their visual states
      document.querySelectorAll('input[name="app_id"]').forEach(r => {
        r.checked = false;
        r.closest('label').querySelector('div').classList.remove('bg-blue-50', 'border-blue-600');
      });
    }

    function closeAdd() {
      document.getElementById('addModal').classList.add('hidden');
      document.body.classList.remove('overflow-hidden'); // Remove to allow scroll
    }

    function updatePaymentOption(appId) {
      const selectedApp = appsData.find(app => app.id == appId);
      const monthlyToggle = document.getElementById('monthlyToggle');
      const yearlyToggle = document.getElementById('yearlyToggle');
      const monthlyPriceDisplay = document.getElementById('monthlyPriceDisplay');
      const yearlyPriceDisplay = document.getElementById('yearlyPriceDisplay');
      const monthlyToggleLabel = document.getElementById('monthlyToggleLabel');
      const yearlyToggleLabel = document.getElementById('yearlyToggleLabel');
      // Get custom price inputs for add modal
      const addMonthlyPriceInput = document.getElementById('add_monthly_price');
      const addYearlyPriceInput = document.getElementById('add_yearly_price');

      // Reset states first
      monthlyToggle.disabled = true;
      monthlyPriceDisplay.textContent = '';
      yearlyToggle.disabled = true;
      yearlyPriceDisplay.textContent = '';
      monthlyToggle.checked = false;
      yearlyToggle.checked = false;
      
      // Clear custom price inputs when app selection changes
      addMonthlyPriceInput.value = '';
      addYearlyPriceInput.value = '';

      // Clear existing visual selections
      monthlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
      yearlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');

      if (!selectedApp) return;

      const availableCycles = selectedApp.available_cycles ? selectedApp.available_cycles.split(',').map(c => c.trim()) : [];

      let defaultCheckedSet = false;

      if (availableCycles.includes('Monthly') && selectedApp.monthly_price !== null && selectedApp.monthly_price !== '') {
        monthlyToggle.disabled = false;
        monthlyPriceDisplay.textContent = `${parseFloat(selectedApp.monthly_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})})`; // Show default price
        if (!defaultCheckedSet) {
            monthlyToggle.checked = true;
            defaultCheckedSet = true;
        }
      }

      if (availableCycles.includes('Yearly') && selectedApp.yearly_price !== null && selectedApp.yearly_price !== '') {
        yearlyToggle.disabled = false;
        yearlyPriceDisplay.textContent = `${parseFloat(selectedApp.yearly_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})})`; // Show default price
        if (!defaultCheckedSet) {
            yearlyToggle.checked = true;
            defaultCheckedSet = true;
        }
      }
      
      // Apply visual update based on initial check after enabling/disabling
      if (monthlyToggle.checked) {
          handlePaymentToggle('monthlyToggle');
      } else if (yearlyToggle.checked) {
          handlePaymentToggle('yearlyToggle');
      } else {
        // If no default was set, explicitly clear visual state
        handlePaymentToggle('none');
      }
    }

    function handlePaymentToggle(selectedId) {
        const monthlyToggle = document.getElementById('monthlyToggle');
        const yearlyToggle = document.getElementById('yearlyToggle');
        const monthlyToggleLabel = document.getElementById('monthlyToggleLabel');
        const yearlyToggleLabel = document.getElementById('yearlyToggleLabel');

        // Ensure only the selected one is checked (and not disabled)
        if (selectedId === 'monthlyToggle' && !monthlyToggle.disabled) {
            monthlyToggle.checked = true;
            yearlyToggle.checked = false;
        } else if (selectedId === 'yearlyToggle' && !yearlyToggle.disabled) {
            yearlyToggle.checked = true;
            monthlyToggle.checked = false;
        } else if (selectedId === 'none') { // Used for resetting visual state
            monthlyToggle.checked = false;
            yearlyToggle.checked = false;
        }

        // Apply/remove Tailwind classes based on checked state
        // Only apply if not disabled
        if (monthlyToggle.checked && !monthlyToggle.disabled) {
            monthlyToggleLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
        } else {
            monthlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
        }

        if (yearlyToggle.checked && !yearlyToggle.disabled) {
            yearlyToggleLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
        } else {
            yearlyToggleLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
        }
    }

    // New function to update the next payment preview
    function updateNextPaymentPreview() {
        const startDateInput = document.getElementById('edit_start_date');
        const billingCycleMonthly = document.getElementById('edit_monthly').checked;
        const billingCycleYearly = document.getElementById('edit_yearly').checked;
        const nextPaymentDisplay = document.getElementById('edit_next_payment_preview');

        let startDate = startDateInput.value;
        if (!startDate) {
            nextPaymentDisplay.textContent = 'Please select a start date.';
            return;
        }

        const date = new Date(startDate);
        if (isNaN(date.getTime())) {
            nextPaymentDisplay.textContent = 'Invalid start date.';
            return;
        }

        let nextPaymentDate;
        if (billingCycleMonthly) {
            date.setDate(date.getDate() + 30);
        } else if (billingCycleYearly) {
            date.setFullYear(date.getFullYear() + 1);
        } else {
            nextPaymentDisplay.textContent = 'Please select a billing cycle.';
            return;
        }

        nextPaymentDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        nextPaymentDisplay.textContent = nextPaymentDate;
    }

    function openEdit(id) {
      const subscription = subscriptionsData.find(sub => sub.id == id);
      if (!subscription) {
          alert('Subscription data not found in loaded data.');
          return;
      }
      
      const selectedApp = appsData.find(app => app.id == subscription.app_id);
      if (!selectedApp) {
          alert('Application data not found for this subscription.');
          return;
      }

      document.getElementById('edit_id').value = id;
      document.getElementById('edit_app_name').value = selectedApp.name; // Use selectedApp.name

      // NEW: Set custom prices if they exist, otherwise leave blank to use default
      document.getElementById('edit_monthly_price').value = subscription.user_monthly_price || '';
      document.getElementById('edit_yearly_price').value = subscription.user_yearly_price || '';

      const editMonthlyRadio = document.getElementById('edit_monthly');
      const editYearlyRadio = document.getElementById('edit_yearly');
      const editMonthlyLabel = document.getElementById('editMonthlyLabel'); // Get the labels for visual updates
      const editYearlyLabel = document.getElementById('editYearlyLabel');


      // Reset disabled state and checked state first
      editMonthlyRadio.disabled = true;
      editYearlyRadio.disabled = true;
      editMonthlyRadio.checked = false;
      editYearlyRadio.checked = false;
      // Clear visual styles for labels
      editMonthlyLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
      editYearlyLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');


      const availableCycles = selectedApp.available_cycles ? selectedApp.available_cycles.split(',').map(c => c.trim()) : [];
      const currentPaymentMethod = subscription.payment_method;

      // Enable Monthly if available and has price
      if (availableCycles.includes('Monthly') && selectedApp.monthly_price !== null && selectedApp.monthly_price !== '') {
          editMonthlyRadio.disabled = false;
      }

      // Enable Yearly if available and has price
      if (availableCycles.includes('Yearly') && selectedApp.yearly_price !== null && selectedApp.yearly_price !== '') {
          editYearlyRadio.disabled = false;
      }

      // Set the checked state based on the current subscription's payment method,
      // but only if that option is enabled.
      if (currentPaymentMethod === 'Monthly' && !editMonthlyRadio.disabled) {
          editMonthlyRadio.checked = true;
      } else if (currentPaymentMethod === 'Yearly' && !editYearlyRadio.disabled) {
          editYearlyRadio.checked = true;
      } else if (!editMonthlyRadio.disabled) {
          // If the current payment method is not available/enabled, and Monthly is available, select Monthly by default
          editMonthlyRadio.checked = true;
      } else if (!editYearlyRadio.disabled) {
          // If Monthly is not available/enabled, and Yearly is available, select Yearly by default
          editYearlyRadio.checked = true;
      }

      // Apply visual styles based on checked state after enabling/disabling
      if (editMonthlyRadio.checked && !editMonthlyRadio.disabled) {
          editMonthlyLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
      }
      if (editYearlyRadio.checked && !editYearlyRadio.disabled) {
          editYearlyLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
      }
      
      // Set default start date to today's date for new edits
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('edit_start_date').value = today;

      // Update preview initially
      updateNextPaymentPreview();

      document.getElementById('editModal').classList.remove('hidden');
      document.body.classList.add('overflow-hidden'); // Add to prevent scroll
    }

    function closeEdit() {
      document.getElementById('editModal').classList.add('hidden');
      document.body.classList.remove('overflow-hidden'); // Remove to allow scroll
    }

    window.addEventListener('click', function(e){
      if(e.target == document.getElementById('addModal')){
        closeAdd();
      }
      if(e.target == document.getElementById('editModal')){
        closeEdit();
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[name="app_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.app-radio-label').forEach(label => {
                    // Remove classes from the div directly inside the label
                    label.querySelector('div').classList.remove('bg-blue-50', 'border-blue-600');
                });
                if (this.checked) {
                    // Add classes to the div directly inside the label of the checked radio
                    this.closest('label').querySelector('div').classList.add('bg-blue-50', 'border-blue-600');
                }
            });
        });

        // Event listeners for edit modal to update preview
        document.getElementById('edit_monthly').addEventListener('change', updateNextPaymentPreview);
        document.getElementById('edit_yearly').addEventListener('change', updateNextPaymentPreview);
        document.getElementById('edit_start_date').addEventListener('change', updateNextPaymentPreview);

        // Event listeners for edit modal to handle toggle button visual state
        document.getElementById('edit_monthly').addEventListener('change', function() {
            const editMonthlyLabel = document.getElementById('editMonthlyLabel');
            const editYearlyLabel = document.getElementById('editYearlyLabel');
            if (this.checked) {
                editMonthlyLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
                editYearlyLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            }
        });
        document.getElementById('edit_yearly').addEventListener('change', function() {
            const editMonthlyLabel = document.getElementById('editMonthlyLabel');
            const editYearlyLabel = document.getElementById('editYearlyLabel');
            if (this.checked) {
                editYearlyLabel.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
                editMonthlyLabel.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            }
        });
    });
  </script>

  <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg w-96 max-w-md mx-4 max-h-[80vh] overflow-y-auto">
      <h3 class="text-xl font-bold mb-5 text-gray-800">Add Subscription</h3>
      
      <form method="POST" action="">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Select Application</label>
          <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
            <?php 
            mysqli_data_seek($apps_query, 0); // Reset pointer for second loop
            while($app = mysqli_fetch_assoc($apps_query)) { ?>
            <label class="flex items-center p-2 rounded-md cursor-pointer hover:bg-blue-50 transition-colors duration-150 ease-in-out app-radio-label">
              <input type="radio" name="app_id" value="<?= $app['id'] ?>" 
                     onchange="updatePaymentOption(this.value)" 
                     class="mr-3 text-blue-600 focus:ring-blue-500">
              <div class="flex items-center gap-3 flex-1 border border-transparent rounded-md p-1">
                <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($app['name']))) ?>.png"
                     onerror="this.src='assets/icons/default.png'"
                     alt="<?= htmlspecialchars($app['name']) ?>"
                     class="w-8 h-8 rounded-full border border-gray-200">
                <div>
                  <p class="font-medium text-gray-800"><?= htmlspecialchars($app['name']) ?></p>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($app['category_name']) ?></p>
                </div>
              </div>
            </label>
            <?php } ?>
          </div>
        </div>

        <div class="mb-5">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
          <div class="toggle-button-group">
            <input type="radio" id="monthlyToggle" name="payment_method" value="Monthly" onchange="handlePaymentToggle('monthlyToggle')" disabled>
            <label for="monthlyToggle" id="monthlyToggleLabel">
              Monthly <span id="monthlyPriceDisplay" class="text-gray-500 font-normal ml-1"></span>
            </label>

            <input type="radio" id="yearlyToggle" name="payment_method" value="Yearly" onchange="handlePaymentToggle('yearlyToggle')" disabled>
            <label for="yearlyToggle" id="yearlyToggleLabel">
              Yearly <span id="yearlyPriceDisplay" class="text-gray-500 font-normal ml-1"></span>
            </label>
          </div>
        </div>

        <div class="mb-4">
            <label for="add_monthly_price" class="block text-sm font-semibold text-gray-700 mb-2">Custom Monthly Price</label>
            <input type="number" step="0.01" id="add_monthly_price" name="add_monthly_price" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">Leave blank to use the default app price.</p>
        </div>

        <div class="mb-5">
            <label for="add_yearly_price" class="block text-sm font-semibold text-gray-700 mb-2">Custom Yearly Price</label>
            <input type="number" step="0.01" id="add_yearly_price" name="add_yearly_price" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">Leave blank to use the default app price.</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" onclick="closeAdd()" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
            Cancel
          </button>
          <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium shadow-md">
            Add
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg w-96 max-w-md mx-4">
      <h3 class="text-xl font-bold mb-5 text-gray-800">Edit Subscription</h3>
      
      <form method="POST" action="">
        <input type="hidden" id="edit_id" name="edit_id">
        
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Application Name</label>
          <input type="text" id="edit_app_name" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed" readonly>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Billing Cycle</label>
          <div class="toggle-button-group">
            <input type="radio" id="edit_monthly" name="edit_billing_cycle" value="Monthly" onchange="updateNextPaymentPreview()">
            <label for="edit_monthly" id="editMonthlyLabel">Monthly</label>
            <input type="radio" id="edit_yearly" name="edit_billing_cycle" value="Yearly" onchange="updateNextPaymentPreview()">
            <label for="edit_yearly" id="editYearlyLabel">Yearly</label>
          </div>
        </div>

        <div class="mb-4">
            <label for="edit_monthly_price" class="block text-sm font-semibold text-gray-700 mb-2">Custom Monthly Price</label>
            <input type="number" step="0.01" id="edit_monthly_price" name="edit_monthly_price" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">Leave blank to use the default app price.</p>
        </div>

        <div class="mb-4">
            <label for="edit_yearly_price" class="block text-sm font-semibold text-gray-700 mb-2">Custom Yearly Price</label>
            <input type="number" step="0.01" id="edit_yearly_price" name="edit_yearly_price" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">Leave blank to use the default app price.</p>
        </div>

        <div class="mb-4">
            <label for="edit_start_date" class="block text-sm font-semibold text-gray-700 mb-2">Subscription Start Date</label>
            <input type="date" id="edit_start_date" name="edit_start_date" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">Select the date your subscription initially started or will start.</p>
        </div>

        <div class="mb-5">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Next Payment Date Preview</label>
          <p id="edit_next_payment_preview" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
            Automatically calculated
          </p>
          <p class="text-xs text-gray-500 mt-1">This date will be automatically calculated based on your selected start date and billing cycle.</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" onclick="closeEdit()" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
            Cancel
          </button>
          <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium shadow-md">
            Update
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>