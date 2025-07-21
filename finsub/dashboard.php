<?php
include 'auth.php';
include 'config.php';

$user_id = $_SESSION['user_id'];

$query_summary = mysqli_prepare($conn, "
  SELECT
    SUM(CASE WHEN s.status='Active' THEN 1 ELSE 0 END) as active_total,
    SUM(CASE WHEN s.status='Active' THEN
        CASE
            WHEN s.payment_method = 'Monthly' THEN COALESCE(s.user_monthly_price, a.monthly_price)
            WHEN s.payment_method = 'Yearly' THEN COALESCE(s.user_yearly_price, a.yearly_price) / 12
            ELSE 0
        END
    ELSE 0 END) as month_spending
  FROM subscriptions s
  JOIN apps a ON s.app_id = a.id
  WHERE s.user_id = ?
");
mysqli_stmt_bind_param($query_summary, "i", $user_id);
mysqli_stmt_execute($query_summary);
$summary_result = mysqli_stmt_get_result($query_summary);
$summary = mysqli_fetch_assoc($summary_result);

$active = $summary['active_total'] ?? 0;
$month_spending = $summary['month_spending'] ?? 0;
$next_month_estimate = $month_spending; // Assuming next month's estimate is based on current active subs

$stmt_subs = mysqli_prepare($conn, "
  SELECT s.*, a.name, a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price, s.next_payment_date, s.status, c.name AS category_name
  FROM subscriptions s
  JOIN apps a ON s.app_id = a.id
  JOIN categories c ON a.category_id = c.id
  WHERE s.user_id = ?
  ORDER BY s.next_payment_date ASC
");
mysqli_stmt_bind_param($stmt_subs, "i", $user_id);
mysqli_stmt_execute($stmt_subs);
$result_subs = mysqli_stmt_get_result($stmt_subs);

$stmt_upcoming = mysqli_prepare($conn, "
  SELECT s.*, a.name, a.monthly_price AS default_monthly_price, a.yearly_price AS default_yearly_price, s.next_payment_date, s.payment_method
  FROM subscriptions s
  JOIN apps a ON s.app_id = a.id
  WHERE s.user_id = ? AND s.status = 'Active'
  ORDER BY s.next_payment_date ASC
  LIMIT 1
");
mysqli_stmt_bind_param($stmt_upcoming, "i", $user_id);
mysqli_stmt_execute($stmt_upcoming);
$upcoming_result = mysqli_stmt_get_result($stmt_upcoming);
$next = mysqli_fetch_assoc($upcoming_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | FinSub</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<?php include 'templates/navbar.php'; ?>

<section class="p-4 md:p-6 max-w-7xl mx-auto space-y-10">

  <div class="flex justify-between items-center">
    <h1 class="text-xl md:text-3xl font-bold text-gray-800">Dashboard</h1>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
    <div class="bg-white p-4 md:p-6 rounded-xl shadow flex items-center gap-4">
      <div class="bg-blue-100 text-blue-600 rounded-full p-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M3 14h18M3 18h18" />
        </svg>
      </div>
      <div>
        <p class="text-gray-500 text-sm">Active Subscriptions</p>
        <p class="text-xl md:text-2xl font-bold mt-1"><?= htmlspecialchars($active) ?></p>
      </div>
    </div>

    <div class="bg-white p-4 md:p-6 rounded-xl shadow flex items-center gap-4">
      <div class="bg-green-100 text-green-600 rounded-full p-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3m0-6v6m0 0l3-3m-3 3l-3-3" />
        </svg>
      </div>
      <div>
        <p class="text-gray-500 text-sm">This Month's Spending</p>
        <p class="text-xl md:text-2xl font-bold mt-1">$<?= number_format($month_spending, 2) ?></p>
      </div>
    </div>

    <div class="bg-white p-4 md:p-6 rounded-xl shadow flex items-center gap-4">
      <div class="bg-yellow-100 text-yellow-600 rounded-full p-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-5a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <p class="text-gray-500 text-sm">Next Month's Estimate</p>
        <p class="text-xl md:text-2xl font-bold mt-1">$<?= number_format($next_month_estimate, 2) ?></p>
      </div>
    </div>
  </div>

  <div>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg md:text-xl font-semibold">Subscriptions List</h2>
      <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Manage</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="min-w-full text-sm md:text-base">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-4 text-left">Name</th>
            <th class="p-4 text-left">Amount</th>
            <th class="p-4 text-left">Billing Cycle</th>
            <th class="p-4 text-left">Next Payment</th>
            <th class="p-4 text-left">Status</th>
            <th class="p-4 text-left"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($result_subs) > 0) { while($row = mysqli_fetch_assoc($result_subs)) {
            // Determine the price: prioritize user-defined price, else use default app price
            $current_price = ($row['payment_method'] == 'Monthly')
                             ? ($row['user_monthly_price'] !== NULL ? $row['user_monthly_price'] : $row['default_monthly_price'])
                             : ($row['user_yearly_price'] !== NULL ? $row['user_yearly_price'] : $row['default_yearly_price']);
            ?>
          <tr class="border-t hover:bg-gray-50">
            <td class="p-4">
              <div class="flex items-center gap-3">
                <img src="assets/icons/<?= strtolower(str_replace(' ', '', htmlspecialchars($row['name']))) ?>.png"
                     onerror="this.src='assets/icons/default.png'"
                     alt="<?= htmlspecialchars($row['name']) ?>"
                     class="w-9 h-9 rounded-md border border-gray-200 p-1">
                <div>
                  <p class="font-semibold text-gray-900 app-name"><?= htmlspecialchars($row['name']) ?></p>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($row['category_name']) ?></p>
                </div>
              </div>
            </td>
            <td class="p-4">$<?= number_format($current_price, 2) ?></td>
            <td class="p-4">
              <span class="inline-block px-3 py-1 rounded-full text-xs font-medium billing-cycle
                <?= $row['payment_method'] == 'Monthly' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' ?>">
                <?= htmlspecialchars($row['payment_method']) ?>
              </span>
            </td>
            <td class="p-4"><?= date('j F', strtotime($row['next_payment_date'])) ?></td>
            <td class="p-4">
              <span class="px-2 py-1 rounded-full text-xs <?= $row['status']=='Active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>
            <td class="p-4">
              <a href="detail_subscription.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:underline">Detail</a>
            </td>
          </tr>
          <?php }} else { ?>
          <tr>
            <td colspan="6" class="p-4 text-center text-gray-500">No subscriptions yet.</td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($next) {
    // Determine the price for the upcoming notification: prioritize user-defined price, else use default app price
    $next_price = ($next['payment_method'] == 'Monthly')
                  ? ($next['user_monthly_price'] !== NULL ? $next['user_monthly_price'] : $next['default_monthly_price'])
                  : ($next['user_yearly_price'] !== NULL ? $next['user_yearly_price'] : $next['default_yearly_price']);
    ?>
  <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 text-sm rounded-md flex items-center gap-2">
    <span>💡</span>
    <p>
      <?= htmlspecialchars($next['name']) ?> will charge
      <span class="font-bold">$<?= number_format($next_price, 2) ?></span> on
      <span class="font-bold"><?= date('j F', strtotime($next['next_payment_date'])) ?></span>.
      Make sure your balance is sufficient.
    </p>
  </div>
  <?php } ?>

</section>

</body>
</html>