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

```bash
git clone https://github.com/glacie21/fnsub.git
```

### 2. Konfigurasi Database

- Buka phpMyAdmin atau MySQL client Anda
- Import file `database.sql` untuk membuat database dan mengisi data awal
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

- Letakkan folder project (misalnya `fnsub/`) di dalam `htdocs` (XAMPP) atau `www` (WAMP/Laragon)
- Akses melalui browser menggunakan nama folder tersebut, misalnya `http://localhost/fnsub/`

---

## Struktur Folder

```text
fnsub/
├── assets/
│   └── icons/                  # Icon aplikasi (Netflix, Spotify, dll.)
├── css/
│   └── style.css               # CSS tambahan
├── templates/
│   ├── navbar.php              # Navbar + notifikasi
│   ├── header.php              # Header HTML
│   └── footer.php              # Footer HTML
├── add_subscription.php        # Tambah langganan
├── auth.php                    # Guard session
├── config.php                  # Koneksi database
├── dashboard.php               # Dashboard ringkasan
├── database.sql                # Skema & data awal database
├── delete_subscription.php     # Hapus langganan
├── detail_subscription.php     # Detail + usage tracking
├── edit_subscription.php       # Edit langganan
├── homepage.php                # Landing page
├── index.php                   # Daftar & kelola langganan
├── insight.php                 # Halaman insight & grafik
├── landingpage.php             # Alternatif landing page
├── login.php                   # Halaman login
├── logout.php                  # Proses logout
├── profile.php                 # Halaman profil pengguna
├── register.php                # Halaman registrasi
├── set_inactive.php            # Set langganan menjadi Inactive
├── set_reminder.php            # Atur pengingat langganan
├── update_profile.php          # Proses update profil
├── update_subscription.php     # Update via JSON API
└── README.md
```

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

Kemudian tambahkan icon berformat `.png` di folder `assets/icons/` dengan nama file menggunakan **huruf kecil tanpa spasi** (contoh: `instagram.png`).

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
