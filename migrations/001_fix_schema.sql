-- Migrasi untuk database FinSub yang SUDAH berjalan (instalasi baru cukup import database.sql).
-- Jalankan sekali melalui phpMyAdmin atau MySQL client.
USE finsub;

-- 1. Kolom payment_method sebenarnya berisi siklus tagihan (Monthly/Yearly): ganti nama menjadi billing_cycle
ALTER TABLE subscriptions
CHANGE payment_method billing_cycle ENUM('Monthly','Yearly');

-- 2. Pengaturan pengingat per langganan (dipakai set_reminder.php dan notifikasi navbar).
--    Default 30 hari = sama dengan perilaku notifikasi sebelumnya.
ALTER TABLE subscriptions
ADD COLUMN reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
ADD COLUMN reminder_days_before INT NOT NULL DEFAULT 30;
