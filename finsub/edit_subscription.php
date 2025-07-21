<?php
include 'config.php';
include 'templates/navbar.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<script>alert('No subscription selected'); window.location='index.php';</script>";
    exit;
}

// Query subscription dan app terkait
$stmt = mysqli_prepare($conn, "
  SELECT subscriptions.*, apps.name, apps.billing_cycle, apps.available_cycles 
  FROM subscriptions 
  JOIN apps ON subscriptions.app_id = apps.id 
  WHERE subscriptions.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>alert('Subscription not found'); window.location='index.php';</script>";
    exit;
}

// Parse available cycles
$available_cycles = array_map('trim', explode(',', $data['available_cycles']));

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $next_payment_date = $_POST['next_payment_date'];
    $billing_cycle = $_POST['billing_cycle'];

    // Validasi input
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_payment_date) || !in_array($billing_cycle, ['Monthly','Yearly'])) {
        echo "<script>alert('Invalid input');</script>";
    } else {
        $update_stmt = mysqli_prepare($conn, "
          UPDATE subscriptions 
          SET next_payment_date = ?, payment_method = ? 
          WHERE id = ?
        ");
        mysqli_stmt_bind_param($update_stmt, "ssi", $next_payment_date, $billing_cycle, $id);
        $exec = mysqli_stmt_execute($update_stmt);

        if ($exec) {
            echo "<script>alert('Subscription updated successfully'); window.location='index.php';</script>";
        } else {
            echo "<script>alert('Failed to update subscription');</script>";
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Subscription - FinSub</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

  <section class="min-h-screen flex flex-col justify-center items-center px-4 py-10">
    <div class="bg-white w-full max-w-md rounded-2xl shadow p-6 space-y-6">
      <h1 class="text-2xl font-bold text-center text-blue-600">Edit Subscription</h1>

      <form method="POST" class="space-y-4">
        <!-- App Name -->
        <div>
          <label class="block mb-1 text-sm font-medium">App Name</label>
          <input type="text" value="<?= htmlspecialchars($data['name']) ?>" class="border px-3 py-2 rounded w-full bg-gray-100" readonly>
        </div>

        <!-- Payment Cycle -->
        <div>
          <label class="block mb-1 text-sm font-medium">Billing Cycle</label>
          <div class="grid grid-cols-2 gap-3">
            <!-- Monthly Option -->
            <label class="flex items-center gap-2 border p-2 rounded 
              <?= in_array('Monthly', $available_cycles) ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-200 cursor-not-allowed' ?>">
              <input type="radio" name="billing_cycle" value="Monthly"
                <?= (in_array('Monthly', $available_cycles)) ? '' : 'disabled' ?>
                <?= ($data['payment_method'] == 'Monthly') ? 'checked' : '' ?>>
              <span>Monthly</span>
            </label>

            <!-- Yearly Option -->
            <label class="flex items-center gap-2 border p-2 rounded 
              <?= in_array('Yearly', $available_cycles) ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-200 cursor-not-allowed' ?>">
              <input type="radio" name="billing_cycle" value="Yearly"
                <?= (in_array('Yearly', $available_cycles)) ? '' : 'disabled' ?>
                <?= ($data['payment_method'] == 'Yearly') ? 'checked' : '' ?>>
              <span>Yearly</span>
            </label>
          </div>
        </div>

        <!-- Next Payment Date -->
        <div>
          <label class="block mb-1 text-sm font-medium">Next Payment Date</label>
          <input type="date" name="next_payment_date" value="<?= htmlspecialchars($data['next_payment_date']) ?>" class="border px-3 py-2 rounded w-full" required>
        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
          <a href="index.php" class="flex-1 bg-gray-200 text-center py-2 rounded hover:bg-gray-300">Cancel</a>
          <button type="submit" onclick="return confirm('Are you sure you want to update this subscription?')" class="flex-1 bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Update</button>
        </div>
      </form>
    </div>
  </section>

</body>
</html>
