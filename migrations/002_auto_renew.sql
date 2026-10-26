-- Migrasi untuk database FinSub yang SUDAH berjalan (instalasi baru cukup import database.sql).
-- Jalankan setelah 001_fix_schema.sql.
USE finsub;

-- Perpanjangan otomatis: 0 = dibatalkan user, langganan berakhir (Inactive) di next_payment_date.
-- Semua langganan yang ada tetap diperpanjang otomatis seperti sebelumnya.
ALTER TABLE subscriptions
ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 1;
