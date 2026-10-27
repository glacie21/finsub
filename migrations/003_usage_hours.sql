-- Migrasi untuk database FinSub yang SUDAH berjalan (instalasi baru cukup import database.sql).
-- Jalankan setelah 002_auto_renew.sql.
USE finsub;

-- DECIMAL(4,2) hanya menampung sampai 99.99 jam, padahal aplikasi mengizinkan input 0-744 jam per bulan.
ALTER TABLE usage_tracking
MODIFY COLUMN hours_used DECIMAL(5,2) NOT NULL;
