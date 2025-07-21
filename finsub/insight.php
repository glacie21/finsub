<?php
include 'auth.php'; // Pastikan pengguna sudah login
include 'config.php'; // Pastikan koneksi database sudah dibuat

// Ambil ID pengguna dari session
$user_id = $_SESSION['user_id'] ?? null; // Tambahkan null coalescing operator untuk keamanan

// Pastikan user_id tersedia
if (!$user_id) {
    // Anda bisa mengarahkan pengguna ke halaman login atau menampilkan pesan error
    // Contoh: header('Location: login.php'); exit();
    die('User ID not found. Please log in.');
}

// --- Data untuk Spending per Category Chart (Line Chart - Estimated Monthly Spending with Dummy Historical Data) ---

// 1. Ambil data perkiraan pengeluaran bulanan untuk bulan saat ini dari langganan aktif
$current_month_spending_query = "
    SELECT
        c.name AS category_name,
        SUM(CASE
            WHEN s.payment_method = 'Monthly' THEN a.monthly_price
            WHEN s.payment_method = 'Yearly' THEN a.yearly_price / 12
            ELSE 0
        END) AS estimated_monthly_cost
    FROM
        subscriptions s
    JOIN
        apps a ON s.app_id = a.id
    JOIN
        categories c ON a.category_id = c.id
    WHERE
        s.user_id = ? AND s.status = 'Active'
    GROUP BY
        category_name;
";

$stmt = mysqli_prepare($conn, $current_month_spending_query);
if ($stmt === false) {
    die('mysqli_prepare failed for current_month_spending_query: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$current_month_result = mysqli_stmt_get_result($stmt);

$current_month_spending = [];
$all_categories = [];
while ($row = mysqli_fetch_assoc($current_month_result)) {
    $current_month_spending[$row['category_name']] = (float) $row['estimated_monthly_cost'];
    if (!in_array($row['category_name'], $all_categories)) {
        $all_categories[] = $row['category_name'];
    }
}
mysqli_stmt_close($stmt);

// 2. Generate data dummy historis untuk beberapa bulan terakhir + bulan saat ini
$num_months = 6; // Menampilkan data untuk 6 bulan terakhir
$months = [];
$monthly_category_spending = []; // Menyimpan pengeluaran per kategori per bulan
$dummy_base_values = [ // Nilai dasar untuk generasi data dummy per kategori
    'Entertainment' => 30.00,
    'Productivity' => 15.00,
    'Health & Fitness' => 20.00,
    'Education' => 10.00,
    'Utilities' => 5.00,
    'Finance' => 8.00,
    'Gaming' => 12.00,
    'News' => 5.00,
    'Lifestyle' => 7.00,
    'Travel' => 15.00
];

// Tambahkan kategori yang ada ke all_categories jika belum ada dari current_month_spending
foreach ($dummy_base_values as $cat => $val) {
    if (!in_array($cat, $all_categories)) {
        $all_categories[] = $cat;
    }
}
sort($all_categories); // Pastikan urutan kategori konsisten

for ($i = $num_months - 1; $i >= 0; $i--) {
    $month_ts = strtotime("-$i months");
    $month_label = date('Y-m', $month_ts);
    $months[] = $month_label;

    foreach ($all_categories as $category) {
        // Untuk bulan saat ini, gunakan data aktual yang dihitung
        if ($i == 0) { // Bulan saat ini
            $monthly_category_spending[$category][$month_label] = $current_month_spending[$category] ?? 0;
        } else {
            // Untuk bulan-bulan sebelumnya, hasilkan data dummy berdasarkan nilai dasar dan sedikit keacakan
            $base_value = $dummy_base_values[$category] ?? 10.00; // Nilai default jika kategori tidak ada di base
            $dummy_value = $base_value + mt_rand(-500, 500) / 100.0; // +/- 5 unit
            $monthly_category_spending[$category][$month_label] = max(0, round($dummy_value, 2)); // Pastikan tidak negatif
        }
    }
}

$line_chart_labels = $months; // Bulan untuk sumbu X
$line_chart_datasets = [];

$category_colors_map = [
    'Entertainment' => 'rgb(255, 99, 132)',
    'Productivity' => 'rgb(54, 162, 235)',
    'Health & Fitness' => 'rgb(75, 192, 192)',
    'Education' => 'rgb(153, 102, 255)',
    'Utilities' => 'rgb(255, 159, 64)',
    'Finance' => 'rgb(201, 203, 207)', // Abu-abu
    'Gaming' => 'rgb(255, 205, 86)', // Kuning
    'News' => 'rgb(205, 92, 92)', // Merah bata
    'Lifestyle' => 'rgb(60, 179, 113)', // Hijau laut
    'Travel' => 'rgb(106, 90, 205)' // Biru keunguan
];

$fallback_colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCE',
    '#A1C9F4', '#8DE9DF', '#FFD700', '#DA70D6', '#8A2BE2', '#7FFF00', '#D2B48C'
];
$color_index_for_datasets = 0;

foreach ($all_categories as $category_name) {
    $data_for_category = [];
    foreach ($months as $month) {
        $data_for_category[] = $monthly_category_spending[$category_name][$month] ?? 0;
    }

    // Tetapkan warna yang konsisten untuk setiap kategori
    $color = $category_colors_map[$category_name] ?? $fallback_colors[$color_index_for_datasets % count($fallback_colors)];
    $color_index_for_datasets++;

    $line_chart_datasets[] = [
        'label' => $category_name,
        'data' => $data_for_category,
        'borderColor' => $color,
        'backgroundColor' => 'rgba(' . implode(',', sscanf($color, 'rgb(%d, %d, %d)')) . ', 0.2)', // Isian ringan
        'fill' => false,
        'tension' => 0.1 // Garis halus
    ];
}


// --- Data untuk App Usage Duration Distribution By Category (Pie Chart) ---
$app_distribution_query = "
    SELECT
        c.name AS category_name,
        SUM(ut.hours_used) AS total_hours_used
    FROM
        usage_tracking ut
    JOIN
        subscriptions s ON ut.subscription_id = s.id
    JOIN
        apps a ON s.app_id = a.id
    JOIN
        categories c ON a.category_id = c.id
    WHERE
        s.user_id = ?
    GROUP BY
        category_name
    ORDER BY
        total_hours_used DESC;
";

$stmt = mysqli_prepare($conn, $app_distribution_query);
if ($stmt === false) {
    die('mysqli_prepare failed for app_distribution_query: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pie_chart_labels = [];
$pie_chart_data = [];
$pie_chart_colors = [];
$color_index = 0;
// Menggunakan palet warna yang konsisten atau baru untuk pie chart
$pie_fixed_colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCE', '#A1C9F4', '#8DE9DF', '#FFD700'];

while ($row = mysqli_fetch_assoc($result)) {
    $pie_chart_labels[] = $row['category_name'];
    $pie_chart_data[] = (float)$row['total_hours_used'];
    $pie_chart_colors[] = $pie_fixed_colors[$color_index % count($pie_fixed_colors)];
    $color_index++;
}
mysqli_stmt_close($stmt);

// --- AI Insight & Recommendation (Integrasi Gemini) ---

// Ambil langganan aktif dengan kategori dan estimasi biaya bulanan
$active_subscriptions_query = "
    SELECT
        s.id,
        a.name AS app_name,
        c.name AS category_name,
        s.next_payment_date,
        s.payment_method,
        a.monthly_price,
        a.yearly_price,
        (CASE
            WHEN s.payment_method = 'Monthly' THEN a.monthly_price
            WHEN s.payment_method = 'Yearly' THEN a.yearly_price / 12
            ELSE 0
        END) AS estimated_monthly_cost
    FROM
        subscriptions s
    JOIN
        apps a ON s.app_id = a.id
    JOIN
        categories c ON a.category_id = c.id
    WHERE
        s.user_id = ? AND s.status = 'Active'
    ORDER BY
        estimated_monthly_cost DESC;
";

$stmt = mysqli_prepare($conn, $active_subscriptions_query);
if ($stmt === false) {
    die('mysqli_prepare failed for active_subscriptions_query: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$active_subscriptions_result = mysqli_stmt_get_result($stmt);

$active_subscriptions = [];
while ($row = mysqli_fetch_assoc($active_subscriptions_result)) {
    $active_subscriptions[] = $row;
}
mysqli_stmt_close($stmt);


// Identifikasi langganan untuk "pertimbangan opsi yang lebih baik" (misal: 2 termahal)
$subscriptions_for_consideration = [];
if (count($active_subscriptions) > 0) {
    $subscriptions_for_consideration[] = $active_subscriptions[0];
}
if (count($active_subscriptions) > 1) {
    $subscriptions_for_consideration[] = $active_subscriptions[1];
}

// Ambil semua aplikasi dan harganya berdasarkan kategori untuk opsi perbandingan
$all_apps_query = "
    SELECT
        a.name AS app_name,
        c.name AS category_name,
        a.monthly_price,
        a.yearly_price,
        a.available_cycles
    FROM
        apps a
    JOIN
        categories c ON a.category_id = c.id;
";
$all_apps_result = mysqli_query($conn, $all_apps_query);
$all_apps_by_category = [];
while ($row = mysqli_fetch_assoc($all_apps_result)) {
    $all_apps_by_category[$row['category_name']][] = $row;
}

// Ambil total pengeluaran bulanan saat ini
$total_monthly_spending_query = "
    SELECT
        SUM(CASE
            WHEN s.payment_method = 'Monthly' THEN a.monthly_price
            WHEN s.payment_method = 'Yearly' THEN a.yearly_price / 12
            ELSE 0
        END) AS total_current_monthly_spending
    FROM
        subscriptions s
    JOIN
        apps a ON s.app_id = a.id
    WHERE
        s.user_id = ? AND s.status = 'Active';
";

$stmt = mysqli_prepare($conn, $total_monthly_spending_query);
if ($stmt === false) {
    die('mysqli_prepare failed for total_monthly_spending_query: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_spending_row = mysqli_fetch_assoc($result);
$total_current_monthly_spending = $total_spending_row['total_current_monthly_spending'] ?? 0;
mysqli_stmt_close($stmt);

// Siapkan prompt untuk Gemini AI
$prompt_parts = [];
$prompt_parts[] = "As a financial assistant, analyze my subscription data and provide concise insights and actionable savings recommendations. Structure your response into distinct sections as requested below. Here's my current data:";

// Langganan Aktif
$prompt_parts[] = "\n--- My Active Subscriptions ---";
if (!empty($active_subscriptions)) {
    foreach ($active_subscriptions as $sub) {
        $price = $sub['payment_method'] == 'Monthly' ? $sub['monthly_price'] : $sub['yearly_price'];
        $prompt_parts[] = "- " . htmlspecialchars($sub['app_name']) . " (Category: " . htmlspecialchars($sub['category_name']) . ", Est. Monthly: $" . number_format($sub['estimated_monthly_cost'], 2) . ")";
    }
} else {
    $prompt_parts[] = "No active subscriptions found.";
}

// Langganan untuk Pertimbangan Opsi Lebih Baik (input untuk AI agar fokus)
$prompt_parts[] = "\n--- Subscriptions to Focus for Specific Recommendations ---";
if (!empty($subscriptions_for_consideration)) {
    foreach ($subscriptions_for_consideration as $app) {
        $prompt_parts[] = "- " . htmlspecialchars($app['app_name']) . " (Category: " . htmlspecialchars($app['category_name']) . ", Current Est. Monthly: $" . number_format($app['estimated_monthly_cost'], 2) . ").";
        
        // Tambahkan aplikasi alternatif dalam kategori yang sama untuk perbandingan (singkat)
        if (isset($all_apps_by_category[$app['category_name']])) {
            $alternatives_count = 0;
            $alternatives_list = [];
            foreach ($all_apps_by_category[$app['category_name']] as $alt_app) {
                if ($alt_app['app_name'] !== $app['app_name'] && $alternatives_count < 2) { // Batasi 2 alternatif
                    $alt_monthly_price = $alt_app['monthly_price'] ? "$" . number_format($alt_app['monthly_price'], 2) . "/mo" : "N/A";
                    $alternatives_list[] = htmlspecialchars($alt_app['app_name']) . " (" . $alt_monthly_price . ")";
                    $alternatives_count++;
                }
            }
            if (!empty($alternatives_list)) {
                $prompt_parts[] = "   *Consider these alternatives for comparison:* " . implode(", ", $alternatives_list) . ".";
            }
        }
    }
} else {
    $prompt_parts[] = "No specific high-cost subscriptions identified for immediate deep analysis.";
}

$prompt_parts[] = "\nMy total estimated current monthly spending on subscriptions is: $" . number_format($total_current_monthly_spending, 2) . ".";

$prompt_parts[] = "\nBased on this information, provide:
1.  Overall Insight: A very concise paragraph (1-2 sentences) summarizing my general spending habits and key areas.
2.  General Savings Recommendations: A short, bulleted list of broad tips for managing subscriptions (e.g., yearly billing, reviewing regularly).
3.  Recommendations for Specific Subscriptions: For each subscription listed under 'Subscriptions to Focus for Specific Recommendations', provide concrete suggestions for better options. This can include:
    * Switching to a cheaper alternative (mentioning names and estimated savings).
    * Changing payment frequency (e.g., monthly to yearly) with estimated savings.
    * Downgrading a plan if a cheaper tier is available for that app.
    Provide the potential monthly savings for each specific action.
4.  Estimated Total Potential Monthly Savings: A single estimated figure if all applicable recommendations are followed.

Maintain a friendly, helpful, and encouraging tone.
Ensure the output uses clear headings for each section. Do not include markdown headers (e.g., ##) in the response, just bolded text for section titles like 'Overall Insight:'. Use bullet points for lists.
";

$prompt = implode("\n", $prompt_parts);

$gemini_recommendation = "Loading savings recommendations..."; // Pesan loading default
$general_insight = "Loading insights...";
$general_recommendations = "Loading general recommendations...";
$specific_recommendations = "Loading specific recommendations...";
$total_estimated_savings = "Loading total savings...";


// PENTING: Atur Kunci API Gemini Anda di sini
// Ganti 'AIzaSyDyRtNBtwAViXvXDOXZV082BdD8UDZ1YKw' dengan kunci API Anda yang sebenarnya
$gemini_api_key = 'AIzaSyDyRtNBtwAViXvXDOXZV082BdD8UDZ1YKw';

// Fungsi untuk memanggil API Gemini
function callGeminiAPI($api_key, $prompt) {
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key;
    
    $headers = [
        'Content-Type: application/json',
    ];
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 1024,
        ]
    ];
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    curl_close($curl);
    
    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $curl_error
        ];
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        return [
            'success' => false,
            'error' => 'API Error (HTTP ' . $http_code . '): ' . ($error_data['error']['message'] ?? 'Unknown error'),
            'response' => $response
        ];
    }
    
    $response_data = json_decode($response, true);
    
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'success' => true,
            'text' => $response_data['candidates'][0]['content']['parts'][0]['text']
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Unexpected response format',
            'response' => $response
        ];
    }
}

// Panggil API Gemini jika kunci API sudah diatur
if (!empty($gemini_api_key) && $gemini_api_key !== 'YOUR_GEMINI_API_KEY_HERE') {
    $gemini_result = callGeminiAPI($gemini_api_key, $prompt);
    
    if ($gemini_result['success']) {
        $full_gemini_output = $gemini_result['text'];
        
        // --- Parse respons Gemini ke dalam bagian-bagian ---
        // Menggunakan regex untuk menangkap konten antar judul
        preg_match('/Overall Insight:(.*?)(General Savings Recommendations:|$)/s', $full_gemini_output, $matches);
        if (isset($matches[1])) {
            $general_insight = trim($matches[1]);
        }

        preg_match('/General Savings Recommendations:(.*?)(Recommendations for Specific Subscriptions:|$)/s', $full_gemini_output, $matches);
        if (isset($matches[1])) {
            $general_recommendations = trim($matches[1]);
        }

        preg_match('/Recommendations for Specific Subscriptions:(.*?)(Estimated Total Potential Monthly Savings:|$)/s', $full_gemini_output, $matches);
        if (isset($matches[1])) {
            $specific_recommendations = trim($matches[1]);
        }

        preg_match('/Estimated Total Potential Monthly Savings:(.*)/s', $full_gemini_output, $matches);
        if (isset($matches[1])) {
            $total_estimated_savings = trim($matches[1]);
        }

        // Format untuk ditampilkan - Hapus semua pemformatan Markdown
        $general_insight = cleanGeminiOutput($general_insight);
        $general_recommendations = cleanGeminiOutput($general_recommendations);
        $specific_recommendations = cleanGeminiOutput($specific_recommendations);
        $total_estimated_savings = cleanGeminiOutput($total_estimated_savings);

    } else {
        $general_insight = "Error getting AI recommendation: " . htmlspecialchars($gemini_result['error']);
        $general_recommendations = "";
        $specific_recommendations = "";
        $total_estimated_savings = "";
        // Log error untuk debugging (opsional)
        error_log("Gemini API Error: " . print_r($gemini_result, true));
    }
} else {
    $general_insight = "Please set your Gemini API Key in the code to get AI recommendations.";
    $general_recommendations = "";
    $specific_recommendations = "";
    $total_estimated_savings = "";
}

// Fungsi pembantu untuk membersihkan output Gemini dari Markdown
function cleanGeminiOutput($text) {
    $text = htmlspecialchars($text); // Pastikan HTML di-escape untuk keamanan
    $text = str_replace(['**', '*', '##', '#'], '', $text); // Hapus semua tanda bintang dan hash untuk Markdown
    $text = preg_replace('/^- (.*?)(\n|$)/m', '<li>$1</li>', $text); // Ubah bullet points ke list HTML
    
    // Perbaiki ul/li yang tidak lengkap jika ada
    if (strpos($text, '<li>') !== false && strpos($text, '<ul>') === false) {
        $text = '<ul>' . $text . '</ul>';
    }
    // Beberapa pembersihan tambahan untuk ul/li
    $text = str_replace("</ul><br />\n<li>", "<li>", $text); // Fix for newlines after ul
    $text = str_replace("</ul><br />", "</ul>", $text); // Fix for newlines after ul
    $text = str_replace("</li><br />", "</li>", $text); // Fix for newlines after li

    $text = nl2br($text); // Konversi newline yang tersisa ke <br>
    return $text;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FinSub - Insight & AI Recommendation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            overflow-y: auto;
            overflow-x: hidden; 
        }
        /* Basic styling for ul and li within the AI output sections */
        .ai-output ul {
            list-style: disc;
            margin-left: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .ai-output li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<?php include 'templates/navbar.php'; ?>

<section class="p-4 sm:p-6 max-w-6xl mx-auto">
    <h2 class="text-2xl font-semibold mb-6">Insight & AI Recommendation</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-md ai-output">
            <h3 class="text-lg font-semibold mb-4 text-blue-600">Overall Insight & General Recommendations</h3>
            <div class="space-y-4 text-sm text-gray-700">
                <?php if (!empty($general_insight)): ?>
                    <p><strong>Overall Insight:</strong><br><?= $general_insight ?></p>
                <?php endif; ?>
                <?php if (!empty($general_recommendations)): ?>
                    <p><strong>General Savings Recommendations:</strong><br><?= $general_recommendations ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md ai-output">
            <h3 class="text-lg font-semibold mb-4 text-green-600">Specific Subscription AI Recommendations</h3>
            <div class="space-y-4 text-sm text-gray-700">
                <?php if (!empty($specific_recommendations)): ?>
                    <p><?= $specific_recommendations ?></p>
                <?php else: ?>
                    <p>No specific recommendations available at this time or AI output could not be parsed.</p>
                <?php endif; ?>
                <?php if (!empty($total_estimated_savings)): ?>
                    <p><strong>Estimated Total Potential Monthly Savings:</strong> <?= $total_estimated_savings ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-semibold mb-4">Estimated Monthly Spending Per Category</h3>
            <div style="height: 300px;">
                <canvas id="spendingChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-semibold mb-4">App Usage Duration Distribution By Category</h3>
            <div style="height: 300px;">
                <canvas id="appDistributionChart"></canvas>
            </div>
        </div>
    </div>

</section>

<script>
    // Line Chart: Estimated Monthly Spending Per Category Trend
    const spendingCtx = document.getElementById('spendingChart').getContext('2d');
    const spendingChart = new Chart(spendingCtx, {
        type: 'line', // Changed back to line chart
        data: {
            labels: <?= json_encode($line_chart_labels) ?>, // Months for X-axis
            datasets: <?= json_encode($line_chart_datasets) ?> // Each category is a dataset
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true, // Show legend for multiple lines (categories)
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Estimated Monthly Spending (USD)'
                    },
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, ticks) {
                            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
                        }
                    }
                }
            }
        },
    });

    // Pie Chart: App Usage Duration Distribution By Category
    const appDistributionCtx = document.getElementById('appDistributionChart').getContext('2d');
    const appDistributionChart = new Chart(appDistributionCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($pie_chart_labels) ?>, // Now category names for usage
            datasets: [{
                data: <?= json_encode($pie_chart_data) ?>, // Now total_hours_used
                backgroundColor: <?= json_encode($pie_chart_colors) ?>,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                const total = context.dataset.data.reduce((acc, value) => acc + value, 0);
                                const percentage = (context.parsed / total * 100).toFixed(2) + '%';
                                label += context.parsed.toFixed(2) + ' hours (' + percentage + ')'; // Updated label for hours
                            }
                            return label;
                        }
                    }
                }
            }
        },
    });
</script>

</body>
</html>

<?php
// Bersihkan koneksi database
mysqli_close($conn);
?>