<?php
// Ensure NO whitespace or newlines before this opening PHP tag.
// It's critical for JSON responses.

include 'config.php';
include 'auth.php';
// Make sure the session is started to get the user_id
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

// --- Handle Usage Tracking Submission (JSON Response) ---
// This block MUST be at the very top and must exit immediately after sending JSON.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['usage_hours'], $_POST['subscription_id'])) {
    // Set content type first
    header('Content-Type: application/json');

    // Check for user_id early for JSON responses
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'User not logged in.']);
        exit;
    }

    $subscription_id = (int)$_POST['subscription_id'];
    $usage_hours = (float)$_POST['usage_hours'];
    $usage_date = date('Y-m-d'); // Keep this as current date, but unique key handles monthly

    // Debug: Check if we receive the data correctly
    error_log("DEBUG: Received usage data - subscription_id: $subscription_id, user_id: $user_id, usage_hours: $usage_hours, usage_date: $usage_date");

    // Validate input
    if ($usage_hours < 0 || $usage_hours > 744) { // Max hours in a month (31 days * 24 hours)
        echo json_encode(['success' => false, 'error' => 'Hours must be between 0 and 744 (approx. max hours in a month).']);
        exit;
    }

    // Verify subscription belongs to user
    $verify_stmt = mysqli_prepare($conn, "SELECT id FROM subscriptions WHERE id = ? AND user_id = ?");
    if ($verify_stmt) {
        mysqli_stmt_bind_param($verify_stmt, "ii", $subscription_id, $user_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);

        if (mysqli_num_rows($verify_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Subscription not found or access denied']);
            mysqli_stmt_close($verify_stmt);
            exit;
        }
        mysqli_stmt_close($verify_stmt);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error preparing verification: ' . mysqli_error($conn)]);
        exit;
    }

    // Check if table exists and has correct structure
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'usage_tracking'");
    if (mysqli_num_rows($table_check) == 0) {
        // Create table if it doesn't exist
        $create_table = "
            CREATE TABLE usage_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subscription_id INT NOT NULL,
                user_id INT NOT NULL,
                usage_date DATE NOT NULL,
                hours_used DECIMAL(4,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_monthly_usage (subscription_id, user_id, YEAR(usage_date), MONTH(usage_date)),
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";

        if (mysqli_query($conn, $create_table)) {
            error_log("DEBUG: Created usage_tracking table");
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create usage_tracking table: ' . mysqli_error($conn)]);
            exit;
        }
    }

    // Try to insert or update usage data
    $stmt_usage = mysqli_prepare($conn, "
        INSERT INTO usage_tracking (subscription_id, user_id, usage_date, hours_used)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE hours_used = VALUES(hours_used), updated_at = CURRENT_TIMESTAMP
    ");

    if ($stmt_usage) {
        mysqli_stmt_bind_param($stmt_usage, "iisd", $subscription_id, $user_id, $usage_date, $usage_hours);

        if (mysqli_stmt_execute($stmt_usage)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt_usage);
            error_log("DEBUG: Usage data saved successfully. Affected rows: $affected_rows");
            echo json_encode([
                'success' => true,
                'message' => 'Usage data saved successfully',
                'affected_rows' => $affected_rows
            ]);
        } else {
            $error = mysqli_stmt_error($stmt_usage);
            error_log("DEBUG: Execute error: $error");
            echo json_encode(['success' => false, 'error' => "Execute error: $error"]);
        }
        mysqli_stmt_close($stmt_usage);
    } else {
        $error = mysqli_error($conn);
        error_log("DEBUG: Prepare error: $error");
        // KESALAHAN SINTAKS DULU ADA DI SINI. TANDA KURUNG SIKU TAMBAHAN PADA 'success']
        echo json_encode(['success' => false, 'error' => "Prepare error: $error"]);
    }
    exit; // Important: stop execution here
}

// --- End of JSON Response Handling ---

// Redirect if user not logged in (for regular page load) - MUST BE BEFORE ANY HTML OUTPUT
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Handle subscription edit - MUST BE BEFORE ANY HTML OUTPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'], $_POST['edit_billing_cycle'])) {
    $edit_id = (int)$_POST['edit_id'];
    $edit_billing_cycle = $_POST['edit_billing_cycle'];
    $edit_start_date_str = isset($_POST['edit_start_date']) ? $_POST['edit_start_date'] : date('Y-m-d'); // Default to current date if not provided
    // Get custom prices from POST data
    $user_monthly_price = isset($_POST['edit_monthly_price']) && $_POST['edit_monthly_price'] !== '' ? (float)$_POST['edit_monthly_price'] : NULL;
    $user_yearly_price = isset($_POST['edit_yearly_price']) && $_POST['edit_yearly_price'] !== '' ? (float)$_POST['edit_yearly_price'] : NULL;

    // Validate input for billing cycle
    if (!in_array($edit_billing_cycle, ['Monthly', 'Yearly'])) {
        $_SESSION['error_message'] = 'Invalid billing cycle input.';
        header("Location: detail_subscription.php?id=" . $edit_id);
        exit;
    }

    // Validate start date format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $edit_start_date_str) || !strtotime($edit_start_date_str)) {
        $_SESSION['error_message'] = 'Invalid start date format.';
        header("Location: detail_subscription.php?id=" . $edit_id);
        exit;
    }

    // Calculate the next payment date
    $next_payment_date = ($edit_billing_cycle == 'Monthly')
        ? date('Y-m-d', strtotime($edit_start_date_str . " +30 days"))
        : date('Y-m-d', strtotime($edit_start_date_str . " +1 year"));

    // Update the database
    $stmt_update = mysqli_prepare($conn, "UPDATE subscriptions SET payment_method = ?, next_payment_date = ?, user_monthly_price = ?, user_yearly_price = ? WHERE id = ? AND user_id = ?");
    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, "ssddii", $edit_billing_cycle, $next_payment_date, $user_monthly_price, $user_yearly_price, $edit_id, $user_id);
        if (!mysqli_stmt_execute($stmt_update)) {
            error_log('Subscription Update Error: ' . mysqli_stmt_error($stmt_update));
            $_SESSION['error_message'] = 'Failed to update subscription.';
        } else {
            $_SESSION['success_message'] = 'Subscription updated successfully!';
        }
        mysqli_stmt_close($stmt_update);
    } else {
        error_log('Prepare Statement Error (Update): ' . mysqli_error($conn));
        $_SESSION['error_message'] = 'Database error during update.';
    }

    // Redirect back to the same detail page to refresh data - MUST BE BEFORE ANY HTML OUTPUT
    header("Location: detail_subscription.php?id=" . $edit_id);
    exit;
}


// --- Fetch Subscription Data to Display ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    // Redirect to a safe page or show a user-friendly error - MUST BE BEFORE ANY HTML OUTPUT
    header('Location: dashboard.php'); // Or some error page
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT s.*, a.name AS app_name, a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price, a.available_cycles, c.name AS category_name, a.category_id
    FROM subscriptions s
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.id = ? AND s.user_id = ?
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        if (!$data) {
            // Subscription not found or user doesn't have permission - MUST BE BEFORE ANY HTML OUTPUT
            header('Location: dashboard.php'); // Redirect to dashboard
            exit;
        }
    } else {
        error_log('Execute Statement Error (Subscription Detail): ' . mysqli_stmt_error($stmt));
        header('Location: dashboard.php'); // Redirect on severe error - MUST BE BEFORE ANY HTML OUTPUT
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('Prepare Statement Error (Subscription Detail): ' . mysqli_error($conn));
    header('Location: dashboard.php'); // Redirect on severe error - MUST BE BEFORE ANY HTML OUTPUT
    exit;
}

// ************* MODIFIKASI DIMULAI DI SINI (Revisi untuk Line Chart dan Dummy Data) *************

// Fetch usage data for the current month
$current_year = date('Y');
$current_month = date('m');

$this_month_usage_hours = 0;
$stmt_current_month = mysqli_prepare($conn, "
    SELECT hours_used
    FROM usage_tracking
    WHERE subscription_id = ? AND user_id = ? AND YEAR(usage_date) = ? AND MONTH(usage_date) = ?
");
if ($stmt_current_month) {
    mysqli_stmt_bind_param($stmt_current_month, "iiii", $id, $user_id, $current_year, $current_month);
    mysqli_stmt_execute($stmt_current_month);
    $result_current_month = mysqli_stmt_get_result($stmt_current_month);
    if ($row = mysqli_fetch_assoc($result_current_month)) {
        $this_month_usage_hours = $row['hours_used'];
    }
    mysqli_stmt_close($stmt_current_month);
}

// Fetch usage data for the previous month (for comparison)
$previous_month_year = date('Y', strtotime('-1 month'));
$previous_month = date('m', strtotime('-1 month'));

$previous_month_usage_hours = 0;
$has_previous_month_real_data = false;
$stmt_previous_month = mysqli_prepare($conn, "
    SELECT hours_used
    FROM usage_tracking
    WHERE subscription_id = ? AND user_id = ? AND YEAR(usage_date) = ? AND MONTH(usage_date) = ?
");
if ($stmt_previous_month) {
    mysqli_stmt_bind_param($stmt_previous_month, "iiii", $id, $user_id, $previous_month_year, $previous_month);
    mysqli_stmt_execute($stmt_previous_month);
    $result_previous_month = mysqli_stmt_get_result($stmt_previous_month);
    if ($row = mysqli_fetch_assoc($result_previous_month)) {
        $previous_month_usage_hours = $row['hours_used'];
        $has_previous_month_real_data = true;
    } else {
        // DUMMY DATA for previous month if no real data is found
        $previous_month_usage_hours = round(rand(10, 60) + (rand(0, 9) / 10), 1);
    }
    mysqli_stmt_close($stmt_previous_month);
} else {
    $previous_month_usage_hours = round(rand(10, 60) + (rand(0, 9) / 10), 1);
}


// --- Prepare historical data for the Line Chart (e.g., last 6 months) ---
$num_months = 6; // How many months back to show
$chart_labels = [];
$chart_data = [];
$monthly_usage_raw = []; // To store actual data from DB for easier processing

// Fetch all relevant monthly usage data for the last N months
$stmt_historical_usage = mysqli_prepare($conn, "
    SELECT YEAR(usage_date) AS year, MONTH(usage_date) AS month, hours_used
    FROM usage_tracking
    WHERE subscription_id = ? AND user_id = ?
    AND usage_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
    ORDER BY year ASC, month ASC
");

if ($stmt_historical_usage) {
    mysqli_stmt_bind_param($stmt_historical_usage, "iii", $id, $user_id, $num_months);
    mysqli_stmt_execute($stmt_historical_usage);
    $result_historical = mysqli_stmt_get_result($stmt_historical_usage);
    while ($row = mysqli_fetch_assoc($result_historical)) {
        $monthly_usage_raw[$row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT)] = (float)$row['hours_used'];
    }
    mysqli_stmt_close($stmt_historical_usage);
}

// Populate chart data, filling in zeros for missing months and adding specific dummy data
for ($i = $num_months - 1; $i >= 0; $i--) {
    $date_obj = strtotime("-$i month");
    $month_label = date('M Y', $date_obj);
    $month_key = date('Y-m', $date_obj);

    $chart_labels[] = $month_label;

    // --- Tambahkan logika dummy data di sini ---
    // Contoh: Jika bulan adalah April 2025, set 24 jam.
    if ($month_key === '2025-04') { // Sesuaikan tahun jika perlu
        $chart_data[] = 24.0; // Dummy data: 24 hours for April 2025
    } elseif (isset($monthly_usage_raw[$month_key])) {
        $chart_data[] = $monthly_usage_raw[$month_key];
    } else {
        // Jika tidak ada data di DB dan bukan bulan dummy yang spesifik, gunakan dummy acak
        $chart_data[] = round(rand(10, 60) + (rand(0, 9) / 10), 1);
    }
    // --- Akhir logika dummy data ---
}


// ************* MODIFIKASI BERAKHIR DI SINI *************


// Determine the correct price based on the payment method
// PRIORITIZE: Use user-defined price if available, otherwise fallback to app's default price
$current_price = 0;
if ($data['payment_method'] == 'Monthly') {
    $current_price = ($data['user_monthly_price'] !== NULL) ? $data['user_monthly_price'] : $data['default_monthly_price'];
} elseif ($data['payment_method'] == 'Yearly') {
    $current_price = ($data['user_yearly_price'] !== NULL) ? $data['user_yearly_price'] : $data['default_yearly_price'];
}

// Fetch other apps in the same category (excluding the current one)
$similar_apps_query = mysqli_prepare($conn, "
    SELECT id, name, monthly_price, yearly_price, available_cycles
    FROM apps
    WHERE category_id = ? AND id != ?
    LIMIT 3
");

$similar_apps = [];
if ($similar_apps_query) {
    mysqli_stmt_bind_param($similar_apps_query, "ii", $data['category_id'], $data['app_id']);
    if (mysqli_stmt_execute($similar_apps_query)) {
        $similar_apps_result = mysqli_stmt_get_result($similar_apps_query);
        while ($row = mysqli_fetch_assoc($similar_apps_result)) {
            $similar_apps[] = $row;
        }
    } else {
        error_log('Execute Statement Error (Similar Apps): ' . mysqli_stmt_error($similar_apps_query));
    }
    mysqli_stmt_close($similar_apps_query);
} else {
    error_log('Prepare Statement Error (Similar Apps): ' . mysqli_error($conn));
}

// Calculate usage comparison text
$usage_comparison_text = '';
$usage_comparison_class = '';

// Check if current month has any recorded usage
$has_this_month_usage = ($this_month_usage_hours > 0);

if ($has_this_month_usage) {
    if ($this_month_usage_hours > $previous_month_usage_hours) {
        $diff = $this_month_usage_hours - $previous_month_usage_hours;
        $usage_comparison_text = "(+" . number_format($diff, 1) . " hours vs. last month)";
        $usage_comparison_class = 'text-green-600';
    } elseif ($this_month_usage_hours < $previous_month_usage_hours) {
        $diff = $previous_month_usage_hours - $this_month_usage_hours;
        $usage_comparison_text = "(-" . number_format($diff, 1) . " hours vs. last month)";
        $usage_comparison_class = 'text-red-600';
    } else {
        $usage_comparison_text = "(Same as last month)";
        $usage_comparison_class = 'text-gray-500';
    }
} else {
    $usage_comparison_text = "(No usage recorded for this month yet)";
    $usage_comparison_class = 'text-gray-500';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Subscription Details - <?= htmlspecialchars($data['app_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
    <?php include 'templates/navbar.php'; // Moved this inclusion here ?>

    <section class="p-4 sm:p-6 max-w-6xl mx-auto">
        <h2 class="text-2xl font-semibold mb-6">Subscription Details</h2>

        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                <div class="col-span-2 flex items-center gap-4 mb-4">
                    <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($data['app_name']))) ?>.png"
                         onerror="this.src='assets/icons/default.png'"
                         alt="<?= htmlspecialchars($data['app_name']) ?>"
                         class="w-12 h-12 rounded-lg border border-gray-200 p-1">
                    <div>
                        <p class="text-gray-500">Application Name</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($data['app_name']) ?></p>
                    </div>
                </div>

                <div>
                    <p class="text-gray-500">Category</p>
                    <p class="font-medium"><?= htmlspecialchars($data['category_name']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Billing Cycle</p>
                    <p class="font-medium" id="currentBillingCycle"><?= htmlspecialchars($data['payment_method']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Next Payment Date</p>
                    <p class="font-medium" id="currentNextPaymentDate"><?= date('F j, Y', strtotime($data['next_payment_date'])) ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Status</p>
                    <p class="font-medium text-<?= $data['status'] == 'Active' ? 'green' : 'red' ?>-600"><?= htmlspecialchars($data['status']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Price</p>
                    <p class="font-bold text-lg">$<?= number_format($current_price, 2) ?></p>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mt-6 border-t pt-4">
                <button onclick="openEditModal(
                    '<?= $data['id'] ?>',
                    '<?= htmlspecialchars($data['app_name']) ?>',
                    '<?= htmlspecialchars($data['payment_method']) ?>',
                    '<?= htmlspecialchars($data['next_payment_date']) ?>',
                    '<?= htmlspecialchars($data['user_monthly_price'] ?? '') ?>',
                    '<?= htmlspecialchars($data['user_yearly_price'] ?? '') ?>'
                    )"
                        class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors duration-200 shadow-md">
                    Edit Subscription
                </button>
                <a href="delete_subscription.php?id=<?= $data['id'] ?>" onclick="return confirm('Are you sure you want to delete this subscription?')" class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors duration-200 shadow-md">
                    Delete Subscription
                </a>
                <?php if (!$this_month_usage_hours): // Check if current month has any recorded usage ?>
                <button onclick="openUsageModal()"
                        class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 text-sm font-medium transition-colors duration-200 shadow-md">
                    Record This Month's Usage
                </button>
                <?php else: ?>
                <button onclick="openUsageModal()"
                        class="bg-orange-600 text-white px-5 py-2 rounded-lg hover:bg-orange-700 text-sm font-medium transition-colors duration-200 shadow-md">
                    Update This Month's Usage (<?= number_format($this_month_usage_hours, 1) ?>h)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-xl font-semibold mb-4">Usage Statistics (Last <?= $num_months ?> Months)</h3>
            <div class="h-64 mb-4">
                <canvas id="usageChart"></canvas>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Current Month (<?= date('M Y') ?>)</p>
                    <p class="font-bold text-lg"><?= number_format($this_month_usage_hours, 1) ?>h</p>
                    <p class="text-xs <?= $usage_comparison_class ?>"><?= $usage_comparison_text ?></p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Previous Month (<?= date('M Y', strtotime('-1 month')) ?>)</p>
                    <p class="font-bold text-lg"><?= number_format($previous_month_usage_hours, 1) ?>h</p>
                    <?php if (!$has_previous_month_real_data): ?>
                        <p class="text-xs text-gray-500">(Dummy Data)</p>
                    <?php endif; ?>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Est. Cost per Hour (This Month)</p>
                    <p class="font-bold text-lg">$<?= $this_month_usage_hours > 0 ? number_format($current_price / $this_month_usage_hours, 2) : '0.00' ?></p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Est. Cost per Hour (Last Month)</p>
                    <p class="font-bold text-lg">$<?= $previous_month_usage_hours > 0 ? number_format($current_price / $previous_month_usage_hours, 2) : '0.00' ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-xl font-semibold mb-4">Similar Apps in "<?= htmlspecialchars($data['category_name']) ?>"</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                  if (!empty($similar_apps)) {
                    foreach ($similar_apps as $s_app) {
                      // Determine the price for similar apps based on the *current* subscription's payment method
                      $s_price = ($data['payment_method'] == 'Monthly') ? $s_app['monthly_price'] : $s_app['yearly_price'];
                      $diff = $s_price - $current_price;
                      $diffPercent = ($current_price != 0) ? ($diff / $current_price) * 100 : 0;
                ?>
                    <div class="border border-gray-200 rounded-lg p-4 flex flex-col items-start">
                        <div class="flex items-center gap-3 mb-3">
                            <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($s_app['name']))) ?>.png"
                                 onerror="this.src='assets/icons/default.png'"
                                 alt="<?= htmlspecialchars($s_app['name']) ?>"
                                 class="w-10 h-10 rounded-md border border-gray-200 p-1">
                            <div>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($s_app['name']) ?></p>
                                <p class="text-sm text-gray-500">Price: $<?= number_format($s_price, 2) ?>/<?= htmlspecialchars($data['payment_method']) ?></p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600">
                            <?php if ($diff > 0) { ?>
                                <?= number_format($diffPercent,1) ?>% more expensive than <?= htmlspecialchars($data['app_name']) ?>
                            <?php } elseif ($diff < 0) { ?>
                                <?= number_format(abs($diffPercent),1) ?>% cheaper than <?= htmlspecialchars($data['app_name']) ?>
                            <?php } else { ?>
                                Same price as <?= htmlspecialchars($data['app_name']) ?>
                            <?php } ?>
                        </p>
                    </div>
                <?php
                    }
                  } else {
                ?>
                    <p class="col-span-3 text-gray-500 text-center py-4">No similar app recommendations available in this category.</p>
                <?php
                  }
                ?>
            </div>
        </div>
    </section>

    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96 max-w-md mx-4">
            <h3 class="text-xl font-bold mb-5 text-gray-800">Edit Subscription</h3>

            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="edit_id">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Application Name</label>
                    <input type="text" id="edit_app_name" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Billing Cycle</label>
                    <div class="space-y-3">
                        <label class="flex items-center p-2 rounded-md hover:bg-gray-100">
                            <input type="radio" id="edit_monthly" name="edit_billing_cycle" value="Monthly" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updateNextPaymentPreview()">
                            <span class="text-sm text-gray-800">Monthly</span>
                        </label>
                        <label class="flex items-center p-2 rounded-md hover:bg-gray-100">
                            <input type="radio" id="edit_yearly" name="edit_billing_cycle" value="Yearly" class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updateNextPaymentPreview()">
                            <span class="text-sm text-gray-800">Yearly</span>
                        </label>
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
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium shadow-md">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="usageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96 max-w-md mx-4">
            <h3 class="text-xl font-bold mb-5 text-gray-800">Record Usage for This Month</h3> <form id="usageForm">
                <input type="hidden" id="usage_subscription_id" name="subscription_id" value="<?= $data['id'] ?>">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Application</label>
                    <input type="text" value="<?= htmlspecialchars($data['app_name']) ?>" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">For Month</label> <input type="text" value="<?= date('F Y') ?>" class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none" readonly>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Total hours used this month?</label> <input type="number" id="usage_hours" name="usage_hours" step="0.1" min="0" max="744" value="<?= $this_month_usage_hours > 0 ? number_format($this_month_usage_hours, 1) : '' ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., 2.5" required> <p class="text-xs text-gray-500 mt-1">You can enter decimal values (e.g., 1.5 for 1 hour 30 minutes)</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeUsageModal()" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-medium shadow-md">
                        <?= $this_month_usage_hours > 0 ? 'Update Usage' : 'Save Usage' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="messageContainer" class="fixed top-4 right-4 z-50 hidden">
        <div id="messageBox" class="px-6 py-4 rounded-lg shadow-lg text-white font-medium">
            <span id="messageText"></span>
        </div>
    </div>

    <script>
        // Initialize chart if usage data exists
        const ctx = document.getElementById('usageChart').getContext('2d');
        const chartLabels = <?= json_encode($chart_labels) ?>;
        const chartData = <?= json_encode($chart_data) ?>;

        new Chart(ctx, {
            type: 'line', // Kembali ke line chart
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Hours Used',
                    data: chartData,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3 // Smoothness of the line
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: 'Month' }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hours' }
                    }
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' hours';
                            }
                        }
                    }
                }
            }
        });

        // Modal functions
        function openEditModal(id, appName, currentBillingCycle, currentNextPaymentDate, userMonthlyPrice, userYearlyPrice) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_app_name').value = appName;
            document.getElementById('edit_monthly').checked = (currentBillingCycle === 'Monthly');
            document.getElementById('edit_yearly').checked = (currentBillingCycle === 'Yearly');

            // Set custom price inputs
            document.getElementById('edit_monthly_price').value = userMonthlyPrice;
            document.getElementById('edit_yearly_price').value = userYearlyPrice;

            // Set start date input to today's date for new edits
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('edit_start_date').value = today;

            // Perbarui pratinjau tanggal pembayaran berikutnya saat modal dibuka
            updateNextPaymentPreview();

            document.getElementById('editModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function openUsageModal() {
            document.getElementById('usageModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            document.getElementById('usage_hours').focus();
        }

        function closeUsageModal() {
            document.getElementById('usageModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Fungsi untuk menghitung dan menampilkan tanggal pembayaran berikutnya di preview
        function updateNextPaymentPreview() {
            const startDateInput = document.getElementById('edit_start_date');
            const monthlyRadio = document.getElementById('edit_monthly');
            const yearlyRadio = document.getElementById('edit_yearly');
            const nextPaymentPreview = document.getElementById('edit_next_payment_preview');

            const startDate = startDateInput.value;
            if (!startDate) {
                nextPaymentPreview.textContent = 'Select a start date';
                return;
            }

            let calculatedDate = new Date(startDate);
            if (monthlyRadio.checked) {
                calculatedDate.setDate(calculatedDate.getDate() + 30); // Add 30 days
            } else if (yearlyRadio.checked) {
                calculatedDate.setFullYear(calculatedDate.getFullYear() + 1); // Add 1 year
            } else {
                nextPaymentPreview.textContent = 'Select a billing cycle';
                return;
            }

            // Format tanggal untuk ditampilkan (contoh: "July 13, 2026")
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            nextPaymentPreview.textContent = calculatedDate.toLocaleDateString('en-US', options);
        }

        // Tambahkan event listener untuk memanggil updateNextPaymentPreview saat tanggal atau siklus pembayaran berubah
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('edit_start_date');
            const monthlyRadio = document.getElementById('edit_monthly');
            const yearlyRadio = document.getElementById('edit_yearly');

            // Pastikan elemen ada sebelum menambahkan event listener
            if (startDateInput) {
                startDateInput.addEventListener('change', updateNextPaymentPreview);
            }
            if (monthlyRadio) {
                monthlyRadio.addEventListener('change', updateNextPaymentPreview);
            }
            if (yearlyRadio) {
                yearlyRadio.addEventListener('change', updateNextPaymentPreview);
            }
        });

        // Message functions
        function showMessage(message, type = 'success') {
            const messageContainer = document.getElementById('messageContainer');
            const messageBox = document.getElementById('messageBox');
            const messageText = document.getElementById('messageText');

            messageText.textContent = message;
            messageBox.className = `px-6 py-4 rounded-lg shadow-lg text-white font-medium ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;

            messageContainer.classList.remove('hidden');

            setTimeout(() => {
                messageContainer.classList.add('hidden');
            }, 4000);
        }

        // Handle usage form submission
        document.getElementById('usageForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;

            // Disable button and show loading
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            const formData = new FormData(this);

            // Debug: Log what we're sending
            console.log('Sending data:', {
                subscription_id: formData.get('subscription_id'),
                usage_hours: formData.get('usage_hours')
            });

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // Log the full response body if it's not JSON, for debugging
                    return response.text().then(text => {
                        console.error('Non-JSON response received:', text);
                        throw new Error('Server did not return JSON. Please check server logs for errors. Content-Type: ' + contentType);
                    });
                }

                return response.json(); // Now safely parse as JSON
            })
            .then(data => {
                console.log('Parsed data:', data);

                submitButton.disabled = false;
                submitButton.textContent = originalText;

                if (data.success) {
                    closeUsageModal();
                    showMessage('Usage data saved successfully!', 'success');
                    // Reload page after showing message to update displayed usage stats
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Error: ' + (data.error || 'Unknown error occurred'), 'error');
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                showMessage('Error: ' + error.message, 'error');
            });
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === document.getElementById('editModal')) {
                closeEditModal();
            }
            if (e.target === document.getElementById('usageModal')) {
                closeUsageModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeUsageModal();
            }
        });
    </script>
</body>
</html>