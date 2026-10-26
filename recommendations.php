<?php
include 'auth.php';
include 'config.php';
include 'functions.php';

$user_id = (int) $_SESSION['user_id'];

// Perpanjang otomatis langganan yang jatuh tempo agar daftar yang dianalisis up to date
renew_due_subscriptions($conn, $user_id);

// Jumlah bulan terakhir (termasuk bulan ini) yang dipakai untuk menghitung rata-rata pemakaian
$usage_window_months = 3;
$usage_window_start = date('Y-m-01', strtotime(date('Y-m-01') . ' -' . ($usage_window_months - 1) . ' months'));

// ====================== QUERY 1: Active, auto-renewing subscriptions ======================
// Langganan yang sudah di-cancel tidak dianalisis karena memang akan berakhir
$subscriptions = [];

$stmt = mysqli_prepare($conn, "
    SELECT s.id, a.name, c.name AS category_name, s.billing_cycle, s.user_monthly_price, s.user_yearly_price,
           a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price, a.available_cycles
    FROM subscriptions s
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ? AND s.status = 'Active' AND s.auto_renew = 1
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $subscriptions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    } else {
        error_log(mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== QUERY 2: Monthly usage in the window ======================
// MAX per bulan: data lama bisa punya beberapa baris dalam satu bulan, nilai terbesar = total terakhir yang diisi
$usage_by_sub = [];

$stmt = mysqli_prepare($conn, "
    SELECT subscription_id, DATE_FORMAT(usage_date, '%Y-%m') AS month, MAX(hours_used) AS hours
    FROM usage_tracking
    WHERE user_id = ? AND usage_date >= ?
    GROUP BY subscription_id, month
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $user_id, $usage_window_start);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $usage_by_sub[$row['subscription_id']][$row['month']] = (float) $row['hours'];
        }
    } else {
        error_log(mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== Analysis ======================
$recommendations = [];
$total_monthly_cost = 0;
$potential_monthly_savings = 0;

foreach ($subscriptions as $sub) {
    $price = subscription_price($sub['billing_cycle'], $sub['user_monthly_price'], $sub['user_yearly_price'], $sub['default_monthly_price'], $sub['default_yearly_price']);
    $cost_per_month = monthly_cost($sub['billing_cycle'], $price);
    $total_monthly_cost += $cost_per_month;

    $monthly_hours = $usage_by_sub[$sub['id']] ?? [];
    $months_recorded = count($monthly_hours);
    $avg_hours = $months_recorded > 0 ? array_sum($monthly_hours) / $months_recorded : null;
    $cost_per_hour = $avg_hours > 0 ? $cost_per_month / $avg_hours : null;

    if ($months_recorded == 0) {
        $verdict = 'no_data';
    } elseif ($avg_hours == 0) {
        $verdict = 'unused';
    } elseif ($cost_per_hour > COST_PER_HOUR_THRESHOLD) {
        $verdict = 'expensive';
    } else {
        $verdict = 'good';
    }

    // Saran pindah ke Yearly hanya untuk langganan yang layak dipertahankan / belum ada datanya
    $switch_savings = null;
    $cycles = array_map('trim', explode(',', $sub['available_cycles'] ?? ''));
    if ($sub['billing_cycle'] == 'Monthly' && in_array('Yearly', $cycles) && in_array($verdict, ['good', 'no_data'])) {
        $yearly_price = subscription_price('Yearly', $sub['user_monthly_price'], $sub['user_yearly_price'], $sub['default_monthly_price'], $sub['default_yearly_price']);
        $savings = yearly_savings($price, $yearly_price);
        if ($savings > 0) {
            $switch_savings = $savings;
        }
    }

    if (in_array($verdict, ['unused', 'expensive'])) {
        $potential_monthly_savings += $cost_per_month;
    } elseif ($switch_savings !== null) {
        $potential_monthly_savings += $switch_savings / 12;
    }

    $recommendations[] = [
        'id'              => $sub['id'],
        'name'            => $sub['name'],
        'category_name'   => $sub['category_name'],
        'billing_cycle'   => $sub['billing_cycle'],
        'cost_per_month'  => $cost_per_month,
        'avg_hours'       => $avg_hours,
        'months_recorded' => $months_recorded,
        'cost_per_hour'   => $cost_per_hour,
        'verdict'         => $verdict,
        'switch_savings'  => $switch_savings,
    ];
}

// Urutkan: yang paling perlu ditindaklanjuti di atas, lalu biaya terbesar dulu
$verdict_order = ['unused' => 0, 'expensive' => 1, 'good' => 2, 'no_data' => 3];
usort($recommendations, function ($a, $b) use ($verdict_order) {
    $a_rank = $a['switch_savings'] !== null ? 1.5 : $verdict_order[$a['verdict']];
    $b_rank = $b['switch_savings'] !== null ? 1.5 : $verdict_order[$b['verdict']];
    return [$a_rank, -$a['cost_per_month']] <=> [$b_rank, -$b['cost_per_month']];
});

$needs_attention = count(array_filter($recommendations, fn($r) => in_array($r['verdict'], ['unused', 'expensive']) || $r['switch_savings'] !== null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations | FinSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include 'templates/navbar.php'; ?>

    <section class="p-4 md:p-6 max-w-7xl mx-auto space-y-8">

        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Recommendations</h1>
            <p class="text-sm text-gray-500 mt-1">
                Based on your recorded usage over the last <?= $usage_window_months ?> months.
                Subscriptions costing more than $<?= number_format(COST_PER_HOUR_THRESHOLD, 2) ?> per hour of use are flagged.
            </p>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6">
            <div class="bg-white rounded-xl shadow-md p-6">
                <p class="text-sm text-gray-500">Active Subscriptions Cost</p>
                <p class="text-2xl font-bold mt-1">$<?= number_format($total_monthly_cost, 2) ?><span class="text-sm font-normal text-gray-500">/mo</span></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <p class="text-sm text-gray-500">Potential Savings</p>
                <p class="text-2xl font-bold mt-1 text-green-600">$<?= number_format($potential_monthly_savings, 2) ?><span class="text-sm font-normal text-gray-500">/mo</span></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <p class="text-sm text-gray-500">Needs Attention</p>
                <p class="text-2xl font-bold mt-1"><?= $needs_attention ?> <span class="text-sm font-normal text-gray-500">of <?= count($recommendations) ?> subscriptions</span></p>
            </div>
        </div>

        <!-- Recommendation list -->
        <?php if (empty($recommendations)): ?>
            <div class="bg-white rounded-xl shadow-md p-6 text-center text-gray-500">
                No active subscriptions to analyze. <a href="index.php" class="text-blue-600 hover:underline">Add a subscription</a>.
            </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($recommendations as $rec): ?>
            <?php
                if ($rec['verdict'] == 'unused') {
                    $badge = ['Consider cancelling', 'bg-red-100 text-red-700'];
                    $border = 'border-red-500';
                    $message = '0 hours used in the ' . $rec['months_recorded'] . ' recorded month(s). Cancelling saves $' . number_format($rec['cost_per_month'] * 12, 2) . '/year.';
                } elseif ($rec['verdict'] == 'expensive') {
                    $badge = ['Consider cancelling', 'bg-red-100 text-red-700'];
                    $border = 'border-red-500';
                    $message = 'You pay $' . number_format($rec['cost_per_hour'], 2) . ' per hour of use. Cancelling saves $' . number_format($rec['cost_per_month'] * 12, 2) . '/year.';
                } elseif ($rec['switch_savings'] !== null) {
                    $badge = ['Switch to Yearly', 'bg-amber-100 text-amber-700'];
                    $border = 'border-amber-500';
                    $message = 'Switching to Yearly saves $' . number_format($rec['switch_savings'], 2) . '/year.';
                } elseif ($rec['verdict'] == 'no_data') {
                    $badge = ['No usage data', 'bg-gray-200 text-gray-600'];
                    $border = 'border-gray-300';
                    $message = 'Record this month\'s usage on the detail page to get a recommendation.';
                } else {
                    $badge = ['Good value', 'bg-green-100 text-green-700'];
                    $border = 'border-green-500';
                    $message = 'You pay $' . number_format($rec['cost_per_hour'], 2) . ' per hour of use.';
                }
            ?>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 <?= $border ?> flex flex-col md:flex-row md:items-center gap-4">
                <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($rec['name']))) ?>.png"
                     onerror="this.src='assets/icons/default.png'"
                     alt="<?= htmlspecialchars($rec['name']) ?>"
                     class="w-10 h-10 rounded-md border border-gray-200 p-1">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($rec['name']) ?></p>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge[1] ?>"><?= $badge[0] ?></span>
                    </div>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($rec['category_name']) ?> · <?= htmlspecialchars($rec['billing_cycle']) ?></p>
                    <p class="text-sm text-gray-700 mt-2"><?= htmlspecialchars($message) ?></p>
                </div>
                <div class="grid grid-cols-3 gap-4 text-sm text-center md:w-96">
                    <div>
                        <p class="text-gray-500">Cost</p>
                        <p class="font-semibold">$<?= number_format($rec['cost_per_month'], 2) ?>/mo</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Avg. Usage</p>
                        <p class="font-semibold"><?= $rec['avg_hours'] !== null ? number_format($rec['avg_hours'], 1) . 'h/mo' : '–' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500">Per Hour</p>
                        <p class="font-semibold"><?= $rec['cost_per_hour'] !== null ? '$' . number_format($rec['cost_per_hour'], 2) : '–' ?></p>
                    </div>
                </div>
                <a href="detail_subscription.php?id=<?= $rec['id'] ?>" class="text-blue-600 hover:underline text-sm whitespace-nowrap">View detail →</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </section>

</body>
</html>
