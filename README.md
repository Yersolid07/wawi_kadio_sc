<div align="center">
  <h1>🌴 Wawi Kadio - Reservation & POS System</h1>

  <p>
    Sistem manajemen cerdas untuk <b>Wawi Kadio</b> terintegrasi penuh dengan Reservasi Online, <i>Point of Sales (POS)</i>, Manajemen Inventaris, hingga <i>Kitchen Display System (KDS)</i>.
  </p>

  <p>
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"></a>
    <a href="https://reactjs.org"><img src="https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB" alt="React"></a>
    <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
    <a href="https://tailwindcss.com"><img src="https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS"></a>
    <a href="https://www.mysql.com/"><img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"></a>
  </p>
</div>

<br>

## ✨ Fitur Unggulan

<details>
  <summary>🛒 <b>1. Point of Sales (POS) Kasir</b> <i>(Klik untuk detail)</i></summary>
  <p>Antarmuka kasir yang super responsif untuk pemesanan langsung (Dine-in/Takeaway). Terintegrasi dengan perhitungan kembalian, cetak struk via printer thermal Bluetooth/USB, dan manajemen antrian meja.</p>
</details>

<details>
  <summary>📅 <b>2. Reservasi Tempat (Online Booking)</b></summary>
  <p>Pelanggan dapat melakukan <i>booking</i> fasilitas secara mandiri melalui halaman publik katalog. Mendukung sistem keranjang (cart), riwayat transaksi, hingga cetak tiket reservasi berformat PDF lengkap dengan sistem <b>QR Code Validasi</b>.</p>
</details>

<details>
  <summary>🍳 <b>3. Kitchen Display System (KDS)</b></summary>
  <p>Layar digital khusus dapur dengan fitur <i>auto-refresh</i> tanpa henti. Memisahkan pesanan dalam kolom status ("Pesanan Baru", "Sedang Dimasak", "Siap Disajikan") untuk meminimalisir kesalahan koki saat kondisi ramai.</p>
</details>

<details>
  <summary>📦 <b>4. Manajemen Inventaris (Stock)</b></summary>
  <p>Sistem pendataan barang masuk/keluar secara <i>real-time</i>, melacak sisa stok bahan baku (makanan/fasilitas) dan pencatatan riwayat transaksi gudang yang sistematis.</p>
</details>

<details>
  <summary>👥 <b>5. Multi-Role Management System</b></summary>
  <p>Pengaturan hak akses mutlak (Admin, Staff Kasir, Dapur/Koki, dan Pelanggan) menggunakan pustaka canggih <code>Spatie Permission</code>, mencegah peretasan antar pengguna.</p>
</details>

<details>
  <summary>📊 <b>6. Laporan & Ekspor Dokumen Otomatis</b></summary>
  <p>Laporan pendapatan/penjualan cerdas. Mendukung ekspor otomatis ke format <b>Excel (XLSX)</b> dan <b>PDF</b> untuk keperluan rekapitulasi harian, mingguan, maupun bulanan.</p>
</details>

---

## 💻 Teknologi di Balik Layar (Tech Stack)

Sistem ini dibangun dengan arsitektur terkini untuk memastikan kecepatan *(blazing fast)* dan keamanan *(security first)*.

- **Backend:** Laravel 11.x (PHP 8.2+)
- **Frontend:** React 18 (Vite) + Inertia.js Server-Side Routing
- **Styling UI:** Tailwind CSS (Modern Glassmorphism & Fluid UI)
- **Database:** MySQL / MariaDB
- **Autentikasi:** Laravel Breeze
- **PDF/Excel Engine:** `barryvdh/laravel-dompdf`, `maatwebsite/excel`

---

## 🚀 Panduan Instalasi Cepat (Development Lokal)

Ikuti langkah-langkah praktis berikut untuk menjalankan Wawi Kadio di komputer Anda.

### 1. Kloning Repositori
Buka terminal Anda (Git Bash / PowerShell) dan jalankan:
```bash
git clone https://github.com/Yersolid07/wawi_kadio_sc.git
cd wawi_kadio_sc
```

### 2. Install Inti Sistem (PHP & Node.js)
Pastikan Anda sudah menginstal **Composer** dan **Node.js**, kemudian ketik:
```bash
composer install
npm install
```

### 3. Konfigurasi Lingkungan Rahasia (.env)
Salin konfigurasi dasar lalu ubah data `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan database lokal Anda (misal: MySQL / XAMPP):
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Bangun Skema Database & Data Awal
Ini akan membuat semua tabel sekaligus memasukkan data Admin/Staff palsu (*dummy*) untuk Anda *testing*:
```bash
php artisan migrate --seed
php artisan storage:link
```

### 5. Hidupkan Mesin (Run Server)
Buka **2 terminal yang berbeda**, lalu ketik masing-masing:

**Terminal 1 (Backend API):**
```bash
php artisan serve
```

**Terminal 2 (Frontend Hot-Reload):**
```bash
npm run dev
```

Buka `http://localhost:8000` di *browser* kesayangan Anda. Wawi Kadio siap dioperasikan! 🎉

---

## 🔐 Akun Akses Siap Pakai (Dummy Accounts)

Jika Anda menjalankan langkah `migrate --seed`, Anda bisa langsung masuk (Login) menggunakan data berikut:

| Peran (Role) | Email | Password |
|---|---|---|
| **👑 Administrator** | `admin@wawikadio.com` | `password` |
| **👩‍💼 Staff / Kasir** | `staff@wawikadio.com` | `password` |
| **🙍‍♂️ Pelanggan**| `customer@wawikadio.com`| `password` |

---

## 🔒 Catatan Khusus untuk Deployment (Server Production)

Proyek ini telah lulus *Security Audit* penuh, *Test-Driven Development* (TDD 100% Pass), dan siap digunakan untuk skala industri (*Production Ready*) di VPS atau **AAPanel/CPanel**. 
Sebelum sistem Anda rilis ke pelanggan asli, **wajib** jalankan komando optimalisasi cache:

```bash
composer install --optimize-autoloader --no-dev
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan route:cache
```

<br>

<div align="center">
  <sub>Dibangun dengan dedikasi penuh ❤️ untuk revolusi digital <b>Wawi Kadio</b>.</sub>
</div>
