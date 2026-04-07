<?php
include 'auth.php';
include 'config.php';

// Security headers (opsional tapi bagus)
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Amankan session
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

if (!$user_id) {
    die('Terjadi kesalahan. Silakan login kembali.');
}

// ====================== QUERY 1 ======================
$current_month_spending_query = "
    SELECT c.name AS category_name,
        SUM(CASE
            WHEN s.payment_method = 'Monthly' THEN a.monthly_price
            WHEN s.payment_method = 'Yearly' THEN a.yearly_price / 12
            ELSE 0
        END) AS estimated_monthly_cost
    FROM subscriptions s
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ? AND s.status = 'Active'
    GROUP BY category_name;
";

$stmt = mysqli_prepare($conn, $current_month_spending_query);
if (!$stmt) {
    error_log(mysqli_error($conn));
    die('Terjadi kesalahan pada sistem.');
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$current_month_spending = [];
$all_categories = [];

while ($row = mysqli_fetch_assoc($result)) {
    $current_month_spending[$row['category_name']] = (float)$row['estimated_monthly_cost'];
    $all_categories[] = $row['category_name'];
}
mysqli_stmt_close($stmt);

// ====================== DUMMY DATA ======================
$num_months = 6;
$months = [];
$monthly_category_spending = [];

for ($i = $num_months - 1; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = $month;

    foreach ($all_categories as $cat) {
        $monthly_category_spending[$cat][$month] =
            ($i == 0)
            ? ($current_month_spending[$cat] ?? 0)
            : max(0, rand(5, 50));
    }
}

// ====================== QUERY PIE ======================
$app_distribution_query = "
    SELECT c.name AS category_name,
           SUM(ut.hours_used) AS total_hours_used
    FROM usage_tracking ut
    JOIN subscriptions s ON ut.subscription_id = s.id
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ?
    GROUP BY category_name;
";

$stmt = mysqli_prepare($conn, $app_distribution_query);
if (!$stmt) {
    error_log(mysqli_error($conn));
    die('Terjadi kesalahan.');
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$pie_chart_labels = [];
$pie_chart_data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pie_chart_labels[] = $row['category_name'];
    $pie_chart_data[] = (float)$row['total_hours_used'];
}
mysqli_stmt_close($stmt);

// ====================== ALL APPS (FIXED) ======================
$all_apps_query = "
    SELECT a.name AS app_name, c.name AS category_name,
           a.monthly_price, a.yearly_price
    FROM apps a
    JOIN categories c ON a.category_id = c.id;
";

$stmt = mysqli_prepare($conn, $all_apps_query);
if (!$stmt) {
    error_log(mysqli_error($conn));
    die('Terjadi kesalahan.');
}

mysqli_stmt_execute($stmt);
$all_apps_result = mysqli_stmt_get_result($stmt);

// ====================== GEMINI ======================
$gemini_api_key = getenv('GEMINI_API_KEY');

function callGeminiAPI($api_key, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $api_key;

    $data = [
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($curl);

    if (!$response) {
        return ['success' => false, 'error' => 'API tidak merespon'];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'JSON invalid'];
    }

    curl_close($curl);

    return [
        'success' => true,
        'text' => $decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''
    ];
}

// ====================== OUTPUT CLEANER ======================
function cleanGeminiOutput($text) {
    $text = htmlspecialchars($text);
    $text = str_replace(['**', '*', '#'], '', $text);
    return nl2br($text);
}

// ====================== CALL AI ======================
$general_insight = "";
if (!empty($gemini_api_key)) {
    $result = callGeminiAPI($gemini_api_key, "Analyze my subscriptions");

    if ($result['success']) {
        $general_insight = cleanGeminiOutput($result['text']);
    } else {
        error_log($result['error']);
        $general_insight = "AI tidak tersedia saat ini.";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>FinSub</title>
</head>
<body>

<h2>Insight</h2>
<p><?= $general_insight ?></p>

</body>
</html>

<?php mysqli_close($conn); ?>
