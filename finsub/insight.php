<?php
include 'auth.php';
include 'config.php';

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Amankan session
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    die('Terjadi kesalahan. Silakan login kembali.');
}

// ====================== QUERY 1 ======================
$current_month_spending = [];
$all_categories = [];

$stmt = mysqli_prepare($conn, "
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
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $cat = $row['category_name'] ?? '';
                $cost = isset($row['estimated_monthly_cost']) ? (float)$row['estimated_monthly_cost'] : 0;

                if ($cat !== '') {
                    $current_month_spending[$cat] = $cost;
                    $all_categories[] = $cat;
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

// ====================== DUMMY DATA ======================
$num_months = 6;
$months = [];
$monthly_category_spending = [];

for ($i = $num_months - 1; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = $month;

    foreach ($all_categories as $cat) {
        $monthly_category_spending[$cat][$month] =
            ($i === 0)
            ? ($current_month_spending[$cat] ?? 0)
            : max(0, rand(5, 50));
    }
}

// ====================== QUERY PIE ======================
$pie_chart_labels = [];
$pie_chart_data = [];

$stmt = mysqli_prepare($conn, "
    SELECT c.name AS category_name,
           SUM(ut.hours_used) AS total_hours_used
    FROM usage_tracking ut
    JOIN subscriptions s ON ut.subscription_id = s.id
    JOIN apps a ON s.app_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE s.user_id = ?
    GROUP BY category_name;
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $pie_chart_labels[] = $row['category_name'] ?? '';
                $pie_chart_data[] = isset($row['total_hours_used']) ? (float)$row['total_hours_used'] : 0;
            }
        }
    } else {
        error_log(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
} else {
    error_log(mysqli_error($conn));
}

// ====================== ALL APPS ======================
$all_apps_result = null;

$stmt = mysqli_prepare($conn, "
    SELECT a.name AS app_name, c.name AS category_name,
           a.monthly_price, a.yearly_price
    FROM apps a
    JOIN categories c ON a.category_id = c.id;
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
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($response === false || $http_code !== 200) {
        curl_close($curl);
        return ['success' => false, 'error' => 'API gagal'];
    }

    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        curl_close($curl);
        return ['success' => false, 'error' => 'JSON invalid'];
    }

    curl_close($curl);

    return [
        'success' => true,
        'text' => $decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''
    ];
}

// ====================== CLEAN OUTPUT ======================
function cleanGeminiOutput($text) {
    $safe = htmlspecialchars($text ?? '');
    $safe = str_replace(['**', '*', '#'], '', $safe);
    return nl2br($safe);
}

// ====================== CALL AI ======================
$general_insight = "Tidak ada insight.";

if (!empty($gemini_api_key)) {
    $ai = callGeminiAPI($gemini_api_key, "Analyze my subscriptions");

    if ($ai['success'] && !empty($ai['text'])) {
        $general_insight = cleanGeminiOutput($ai['text']);
    } else {
        error_log($ai['error'] ?? 'Unknown AI error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinSub</title>
</head>
<body>

<h2>Insight</h2>
<p><?= $general_insight ?></p>

</body>
</html>

<?php mysqli_close($conn); ?>
