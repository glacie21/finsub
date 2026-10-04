<?php
include 'auth.php';
include 'config.php';

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Secure session
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    die('Session error. Please log in again.');
}

// ====================== QUERY 1: Spending per Category ======================
$current_month_spending = [];
$all_categories = [];

$stmt = mysqli_prepare($conn, "
    SELECT c.name AS category_name,
        SUM(CASE
            WHEN s.payment_method = 'Monthly' THEN COALESCE(s.user_monthly_price, a.monthly_price)
            WHEN s.payment_method = 'Yearly'  THEN COALESCE(s.user_yearly_price, a.yearly_price) / 12
            ELSE 0
        END) AS estimated_monthly_cost
    FROM subscriptions s
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ? AND s.status = 'Active'
    GROUP BY category_name
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $cat  = $row['category_name'] ?? '';
                $cost = isset($row['estimated_monthly_cost']) ? (float) $row['estimated_monthly_cost'] : 0;

                if ($cat !== '') {
                    $current_month_spending[$cat] = $cost;
                    $all_categories[]             = $cat;
                }
            }
        }
    } else {
        error_log(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== Monthly Historical Data (last 6 months) ======================
$num_months               = 6;
$months                   = [];
$monthly_category_spending = [];

for ($i = $num_months - 1; $i >= 0; $i--) {
    $month    = date('Y-m', strtotime("-$i months"));
    $months[] = $month;

    foreach ($all_categories as $cat) {
        $monthly_category_spending[$cat][$month] =
            ($i === 0)
            ? ($current_month_spending[$cat] ?? 0)
            : max(0, rand(5, 50)); // Placeholder: replace with real historical data if available
    }
}

// ====================== QUERY 2: Usage per Category (Pie Chart) ======================
$pie_chart_labels = [];
$pie_chart_data   = [];

$stmt = mysqli_prepare($conn, "
    SELECT c.name AS category_name,
           SUM(ut.hours_used) AS total_hours_used
    FROM usage_tracking ut
    JOIN subscriptions s ON ut.subscription_id = s.id
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ?
    GROUP BY category_name
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $pie_chart_labels[] = $row['category_name'] ?? '';
                $pie_chart_data[]   = isset($row['total_hours_used']) ? (float) $row['total_hours_used'] : 0;
            }
        }
    } else {
        error_log(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== QUERY 3: All Apps List ======================
$all_apps_result = null;

$stmt = mysqli_prepare($conn, "
    SELECT a.name AS app_name, c.name AS category_name,
           a.monthly_price, a.yearly_price
    FROM apps a
    JOIN categories c ON a.category_id = c.id
");

if ($stmt) {
    if (mysqli_stmt_execute($stmt)) {
        $all_apps_result = mysqli_stmt_get_result($stmt);
    } else {
        error_log(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== AI INSIGHT PLACEHOLDER ======================
//
// Di sini Anda dapat mengintegrasikan layanan AI pilihan Anda untuk menghasilkan
// insight otomatis berdasarkan data langganan pengguna.
//
// Contoh integrasi yang bisa digunakan:
//   - Google Gemini API  : https://ai.google.dev/
//   - OpenAI ChatGPT API : https://platform.openai.com/
//   - Anthropic Claude   : https://www.anthropic.com/
//
// Langkah umum integrasi:
//   1. Dapatkan API Key dari layanan AI pilihan Anda.
//   2. Simpan API Key di environment variable (jangan hardcode di kode!).
//      Contoh: $api_key = getenv('YOUR_AI_API_KEY');
//   3. Buat fungsi untuk memanggil API tersebut dengan data spending pengguna.
//   4. Tampilkan hasilnya di variabel $general_insight di bawah ini.
//
// Contoh data yang bisa dikirim ke AI:
//   - Total pengeluaran per kategori : $current_month_spending
//   - Daftar semua kategori aktif   : $all_categories
//   - Data bulanan historis          : $monthly_category_spending
//
$general_insight = "Fitur AI Insight belum dikonfigurasi. Hubungi administrator atau tambahkan integrasi AI Anda di file insight.php.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insight | FinSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Spending Insight</h1>

        <!-- AI Insight Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <h2 class="text-lg font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                AI Insight
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($general_insight)) ?></p>
        </div>

        <!-- Spending per Category Summary -->
        <?php if (!empty($current_month_spending)): ?>
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Monthly Spending by Category</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($current_month_spending as $cat => $cost): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1"><?= htmlspecialchars($cat) ?></p>
                    <p class="text-xl font-bold text-gray-800">$<?= number_format($cost, 2) ?><span class="text-sm font-normal text-gray-500">/mo</span></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Bar Chart: Monthly Spending per Category -->
            <?php if (!empty($all_categories) && !empty($months)): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Spending Trend (Last <?= $num_months ?> Months)</h2>
                <div class="h-64">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pie Chart: Usage per Category -->
            <?php if (!empty($pie_chart_labels)): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Usage Hours by Category</h2>
                <div class="h-64">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-6 flex flex-col items-center justify-center text-center h-64">
                <svg class="w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                </svg>
                <p class="text-gray-400 text-sm">No usage data recorded yet.</p>
                <p class="text-gray-400 text-xs mt-1">Go to a subscription detail page and record your usage.</p>
            </div>
            <?php endif; ?>

        </div>

        <!-- All Apps Table -->
        <?php if ($all_apps_result && mysqli_num_rows($all_apps_result) > 0): ?>
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Available Apps</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-3 font-semibold text-gray-600 border-b">App</th>
                            <th class="p-3 font-semibold text-gray-600 border-b">Category</th>
                            <th class="p-3 font-semibold text-gray-600 border-b">Monthly Price</th>
                            <th class="p-3 font-semibold text-gray-600 border-b">Yearly Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while ($app_row = mysqli_fetch_assoc($all_apps_result)): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="p-3 font-medium text-gray-800">
                                <div class="flex items-center gap-2">
                                    <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($app_row['app_name']))) ?>.png"
                                         onerror="this.src='assets/icons/default.png'"
                                         alt="<?= htmlspecialchars($app_row['app_name']) ?>"
                                         class="w-7 h-7 rounded border border-gray-200 p-0.5">
                                    <?= htmlspecialchars($app_row['app_name']) ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">
                                    <?= htmlspecialchars($app_row['category_name']) ?>
                                </span>
                            </td>
                            <td class="p-3 text-gray-700">
                                <?= $app_row['monthly_price'] ? '$' . number_format($app_row['monthly_price'], 2) : '<span class="text-gray-400">—</span>' ?>
                            </td>
                            <td class="p-3 text-gray-700">
                                <?= $app_row['yearly_price'] ? '$' . number_format($app_row['yearly_price'], 2) : '<span class="text-gray-400">—</span>' ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </section>

    <script>
        // ===== Bar Chart: Monthly Spending Trend =====
        <?php if (!empty($all_categories) && !empty($months)): ?>
        const barCtx    = document.getElementById('barChart').getContext('2d');
        const barLabels = <?= json_encode(array_map(fn($m) => date('M Y', strtotime($m . '-01')), $months)) ?>;

        const palette = [
            'rgba(59,130,246,0.7)',
            'rgba(16,185,129,0.7)',
            'rgba(245,158,11,0.7)',
            'rgba(239,68,68,0.7)',
            'rgba(139,92,246,0.7)',
        ];

        const barDatasets = <?= json_encode($all_categories) ?>.map((cat, idx) => ({
            label: cat,
            data: <?= json_encode(array_values($monthly_category_spending)) ?>[idx]
                ? Object.values(<?= json_encode(array_map(fn($cat) => array_values($monthly_category_spending[$cat] ?? []), $all_categories)) ?>[idx])
                : [],
            backgroundColor: palette[idx % palette.length],
            borderRadius: 4,
        }));

        new Chart(barCtx, {
            type: 'bar',
            data: { labels: barLabels, datasets: barDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { stacked: false },
                    y: { beginAtZero: true, title: { display: true, text: 'USD ($)' } }
                }
            }
        });
        <?php endif; ?>

        // ===== Pie Chart: Usage Hours =====
        <?php if (!empty($pie_chart_labels)): ?>
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pie_chart_labels) ?>,
                datasets: [{
                    data: <?= json_encode($pie_chart_data) ?>,
                    backgroundColor: [
                        'rgba(59,130,246,0.8)',
                        'rgba(16,185,129,0.8)',
                        'rgba(245,158,11,0.8)',
                        'rgba(239,68,68,0.8)',
                        'rgba(139,92,246,0.8)',
                    ],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed.toFixed(1)} hours`
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>
<?php mysqli_close($conn); ?>
