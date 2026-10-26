<?php
// Perpanjang / akhiri langganan yang jatuh tempo untuk SEMUA user.
// Jalankan sekali sehari dari command line, contoh:
//   Linux (crontab)        : 5 0 * * * php /path/to/finsub/cron/renew_subscriptions.php
//   Windows (Task Scheduler): C:\xampp\php\php.exe D:\path\to\finsub\cron\renew_subscriptions.php
//
// Halaman web juga menjalankan proses yang sama untuk user yang sedang login sebagai cadangan,
// jadi aman bila cron terlewat atau dijalankan berkali-kali.

// Tolak akses lewat browser
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

$summary = renew_due_subscriptions($conn);

echo date('Y-m-d H:i:s') . " renewed={$summary['renewed']} expired={$summary['expired']}" . PHP_EOL;
