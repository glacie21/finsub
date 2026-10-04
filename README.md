# FinSub — Subscription Manager

FinSub adalah aplikasi web berbasis PHP untuk membantu Anda memantau, mengelola, dan menganalisis langganan digital secara terpusat.

---

## Fitur Utama

- **Dashboard** — Ringkasan aktif langganan, estimasi pengeluaran bulanan
- **Manajemen Langganan** — Tambah, edit, hapus, dan filter langganan (Active/Inactive/All)
- **Detail Langganan** — Lihat detail harga, siklus bayar, dan grafik pemakaian bulanan
- **Tracking Pemakaian** — Catat jam pemakaian per bulan dan lihat efisiensi biaya per jam
- **Insight** — Visualisasi pengeluaran per kategori (Bar Chart & Pie Chart)
- **AI Insight (opsional)** — Slot integrasi AI tersedia di `insight.php` untuk menambahkan analisis otomatis
- **Notifikasi** — Bell notifikasi untuk langganan yang jatuh tempo dalam 30 hari ke depan
- **Profil** — Ubah username dan password

---

## Teknologi

| Layer | Stack |
|---|---|
| Backend | PHP (Procedural + MySQLi) |
| Database | MySQL |
| Frontend | HTML, TailwindCSS (CDN), Chart.js |
| Font | Inter (Google Fonts) |

---

## Cara Instalasi

### 1. Clone / Download Project

`ash
git clone https://github.com/your-username/finsub.git
`

### 2. Konfigurasi Database

- Buka phpMyAdmin atau MySQL client Anda
- Import file `finsub/database.sql` untuk membuat database dan mengisi data awal
- Pastikan nama database yang dipakai adalah `finsub`

### 3. Konfigurasi Koneksi Database

Koneksi database dibaca dari **environment variable**. Atur variabel berikut di server Anda:

| Variable | Default | Keterangan |
|---|---|---|
| `DB_HOST` | `localhost` | Host database |
| `DB_USER` | `root` | Username database |
| `DB_PASS` | *(kosong)* | Password database |
| `DB_NAME` | `finsub` | Nama database |

**XAMPP / Local:** Anda bisa langsung menggunakan default (root, tanpa password), tidak perlu mengatur environment variable.

### 4. Jalankan dengan Web Server

- Letakkan folder `finsub/` di dalam `htdocs` (XAMPP) atau `www` (WAMP/Laragon)
- Akses melalui browser: `http://localhost/finsub/`

---

## Struktur Folder

`
finsub-main/
├── finsub/
│   ├── assets/
│   │   └── icons/              # Icon aplikasi (Netflix, Spotify, dll.)
│   ├── css/
│   │   └── style.css           # CSS tambahan
│   ├── templates/
│   │   ├── navbar.php          # Navbar + notifikasi
│   │   ├── header.php          # Header HTML
│   │   └── footer.php          # Footer HTML
│   ├── auth.php                # Guard session
│   ├── config.php              # Koneksi database
│   ├── database.sql            # Skema & data awal database
│   ├── login.php               # Halaman login
│   ├── register.php            # Halaman registrasi
│   ├── logout.php              # Proses logout
│   ├── homepage.php            # Landing page
│   ├── dashboard.php           # Dashboard ringkasan
│   ├── index.php               # Daftar & kelola langganan
│   ├── add_subscription.php    # Tambah langganan (standalone)
│   ├── edit_subscription.php   # Edit langganan (standalone)
│   ├── delete_subscription.php # Hapus langganan
│   ├── detail_subscription.php # Detail + usage tracking
│   ├── update_subscription.php # Update via JSON API
│   ├── set_inactive.php        # Set langganan menjadi Inactive
│   ├── insight.php             # Halaman insight & grafik
│   ├── profile.php             # Halaman profil pengguna
│   ├── update_profile.php      # Proses update profil
│   └── landingpage.php         # (Alternatif landing page)
└── README.md
`

---

## Menambahkan Integrasi AI (Opsional)

File `insight.php` sudah menyediakan **slot/placeholder** untuk integrasi AI.

Cari bagian berikut di dalam file tersebut:

`php
// ====================== AI INSIGHT PLACEHOLDER ======================
`

Anda bebas mengintegrasikan layanan AI pilihan Anda, misalnya:

- **Google Gemini** — https://ai.google.dev/
- **OpenAI ChatGPT** — https://platform.openai.com/
- **Anthropic Claude** — https://www.anthropic.com/

**Contoh alur integrasi:**

`php
// Contoh menggunakan Gemini API
\ = getenv('YOUR_AI_API_KEY'); // Simpan di environment variable
\  = "Analyze my subscriptions: " . json_encode(\);

// Panggil API, lalu:
\ = "Hasil analisis dari AI...";
`

> ⚠️ **Jangan hardcode API Key di dalam kode.** Selalu gunakan environment variable atau file `.env`.

---

## Menambahkan Aplikasi Baru

Untuk menambahkan aplikasi baru (selain yang sudah ada), insert data ke tabel `apps` dan `categories`:

`sql
-- Tambah kategori baru (jika belum ada)
INSERT INTO categories (name) VALUES ('Social Media');

-- Tambah aplikasi baru
INSERT INTO apps (name, available_cycles, monthly_price, yearly_price, category_id)
VALUES ('Instagram', 'Monthly', 4.99, NULL, (SELECT id FROM categories WHERE name = 'Social Media'));
`

Kemudian tambahkan icon berformat `.png` di folder `finsub/assets/icons/` dengan nama file menggunakan **huruf kecil tanpa spasi** (contoh: `instagram.png`).

---

## Screenshot Halaman

| Halaman | URL |
|---|---|
| Landing Page | `/homepage.php` |
| Dashboard | `/dashboard.php` |
| Langganan | `/index.php` |
| Insight | `/insight.php` |
| Profil | `/profile.php` |

---

## Lisensi

Project ini dibuat untuk keperluan pembelajaran. Bebas digunakan dan dimodifikasi.
