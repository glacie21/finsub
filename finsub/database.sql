-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS finsub;
USE finsub;

-- Buat table apps (skema awal)
CREATE TABLE IF NOT EXISTS apps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  -- category VARCHAR(50), -- This will be dropped later after normalization
  price DECIMAL(10,2), -- This will be deprecated after monthly_price/yearly_price
  billing_cycle ENUM('Monthly','Yearly'), -- This will be deprecated
  available_cycles VARCHAR(255), -- menyimpan list cycles, contoh 'Monthly,Yearly'
  monthly_price DECIMAL(10,2),
  yearly_price DECIMAL(10,2)
);

-- Insert data apps (skema awal)
INSERT INTO apps (name, price, billing_cycle, available_cycles, monthly_price, yearly_price) VALUES
('Netflix', 9.99, 'Monthly', 'Monthly,Yearly', 9.99, 99.99),
('YouTube Music', 4.99, 'Monthly', 'Monthly', 4.99, NULL),
('Google Drive', 19.99, 'Monthly', 'Monthly,Yearly', 19.99, 199.99),
('FitClub', 79.99, 'Yearly', 'Yearly', NULL, 79.99),
('Spotify', 7.99, 'Monthly', 'Monthly,Yearly', 7.99, 79.99),
('Disney+', 7.99, 'Monthly', 'Monthly', 7.99, NULL),
('Microsoft 365', 6.99, 'Monthly', 'Monthly,Yearly', 6.99, 69.99);

-- Buat table subscriptions
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  app_id INT,
  next_payment_date DATE,
  status ENUM('Active','Inactive'),
  payment_method ENUM('Monthly','Yearly'),
  FOREIGN KEY (app_id) REFERENCES apps(id)
);

-- Buat table subscription_logs
CREATE TABLE IF NOT EXISTS subscription_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subscription_id INT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  action VARCHAR(50),
  note TEXT
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- START -- Perubahan untuk Normalisasi Kategori yang lebih detail

-- 1. Buat Tabel `categories`
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) UNIQUE NOT NULL
);

-- 2. Isi Tabel `categories` dengan data yang lebih detail
INSERT INTO categories (name) VALUES
('Video Streaming'),
('Music Streaming'),
('Cloud Storage'),
('Health & Fitness'),
('Productivity Suite');

-- 3. Tambahkan kolom `category_id` ke tabel `apps`
ALTER TABLE apps
ADD COLUMN category_id INT;

-- 4. Perbarui `category_id` di tabel `apps` berdasarkan kategori yang lebih detail
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Video Streaming') WHERE name = 'Netflix';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Music Streaming') WHERE name = 'YouTube Music';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Cloud Storage') WHERE name = 'Google Drive';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Health & Fitness') WHERE name = 'FitClub';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Music Streaming') WHERE name = 'Spotify';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Video Streaming') WHERE name = 'Disney+';
UPDATE apps SET category_id = (SELECT id FROM categories WHERE name = 'Productivity Suite') WHERE name = 'Microsoft 365';


-- 5. Tambahkan Foreign Key Constraint
ALTER TABLE apps
ADD CONSTRAINT fk_apps_category
FOREIGN KEY (category_id) REFERENCES categories(id);

-- 6. Hapus kolom `category` lama dari tabel `apps` (jika sebelumnya ada dan belum dihapus)
-- ALTER TABLE apps DROP COLUMN category; -- Baris ini dikomentari karena kolom 'category' sudah dihapus dari skema awal

-- END -- Perubahan untuk Normalisasi Kategori

-- Add user_id to subscriptions table
ALTER TABLE subscriptions
ADD COLUMN user_id INT,
ADD CONSTRAINT fk_subscriptions_user
FOREIGN KEY (user_id) REFERENCES users(id);

-- ADDITION: Add columns to the subscriptions table for user-defined prices
ALTER TABLE subscriptions
ADD COLUMN user_monthly_price DECIMAL(10,2) NULL,
ADD COLUMN user_yearly_price DECIMAL(10,2) NULL;

-- New table for tracking spending logs
CREATE TABLE IF NOT EXISTS spending_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NOT NULL,
    app_id INT NOT NULL, -- Pastikan kolom ini ada
    amount DECIMAL(10, 2) NOT NULL,
    log_date DATE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transaction_date DATE NOT NULL, -- Pastikan kolom ini ada
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE -- Foreign Key ke apps
);

CREATE TABLE IF NOT EXISTS usage_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    hours_used DECIMAL(4,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usage (subscription_id, user_id, usage_date),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);