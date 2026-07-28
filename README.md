# Portal Guru & Admin — SMA Negeri 1 Bumiayu

Aplikasi ini adalah hasil konversi desain UI (Tailwind CSS) menjadi aplikasi
web yang **benar-benar berfungsi**, menggunakan:

- **Tailwind CSS** (lewat CDN) — untuk seluruh tampilan/layout.
- **PHP native** — sebagai jembatan logika ke database (tanpa framework, murni PDO).
- **MySQL** (`lms_smansa`) — penyimpanan data siswa, nilai, jadwal, materi, dll.

Tidak ada langkah build/compile yang diperlukan. Cukup taruh di server PHP
(XAMPP/Laragon/cPanel) dan jalankan.

---

## 1. Struktur Folder

```
lms-smansa/
├── config/
│   └── database.php        ← Pengaturan koneksi ke MySQL (EDIT INI)
├── includes/                ← Komponen bersama (auth, sidebar, header, dst)
├── actions/                  ← Script pemroses form (simpan/hapus/upload)
├── uploads/
│   ├── materi/                ← File materi yang diunggah guru
│   └── avatar/                ← Foto profil pengguna
├── database/
│   └── lms_smansa.sql        ← Skema database + data contoh (WAJIB DI-IMPORT)
├── index.php                  ← Dashboard
├── login.php
├── data_siswa.php
├── materi.php
├── tugas_ujian.php
├── rekap_nilai.php
├── kelas_jadwal.php
├── pengaturan.php
└── export_nilai.php           ← Unduh rekap nilai sebagai CSV/Excel
```

## 2. Instalasi

1. **Salin folder ini** ke direktori server Anda, misalnya:
   - XAMPP: `htdocs/lms-smansa`
   - Laragon: `www/lms-smansa`
   - cPanel: `public_html/lms-smansa`

2. **Buat database** (jika belum ada):
   ```sql
   CREATE DATABASE lms_smansa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import skema + data contoh** lewat phpMyAdmin (tab *Import*, pilih file
   `database/lms_smansa.sql`) atau lewat terminal:
   ```bash
   mysql -u root -p lms_smansa < database/lms_smansa.sql
   ```
   File ini akan membuat semua tabel yang dibutuhkan **dan** mengisi beberapa
   data contoh (guru, siswa, kelas, jadwal, nilai) supaya Anda langsung bisa
   melihat tampilan terisi. Data contoh ini boleh dihapus/diganti kapan saja
   lewat menu **Data Siswa**, **Materi**, dll di aplikasi.

4. **Atur koneksi database** di `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lms_smansa');
   define('DB_USER', 'root');   // sesuaikan
   define('DB_PASS', '');       // sesuaikan
   ```

5. **Pastikan folder `uploads/` bisa ditulis** oleh web server:
   ```bash
   chmod -R 755 uploads/
   ```

6. Buka di browser: `http://localhost/lms-smansa/login.php`

## 3. Akun Contoh (Login)

Semua akun contoh memakai password: **`password123`**

| Email                                         | Peran  | Keterangan              |
|------------------------------------------------|--------|--------------------------|
| ahmad.syarifuddin@smanbumiayu.sch.id           | Guru   | Guru Matematika          |
| siti.nurhaliza@smanbumiayu.sch.id              | Guru   | Guru Fisika              |
| budi.hartono@smanbumiayu.sch.id                | Admin  | Kepala Tata Usaha        |

> ⚠️ **Ganti/hapus akun contoh ini** sebelum aplikasi dipakai sungguhan di
> sekolah. Tambahkan akun guru/admin asli lewat phpMyAdmin (kolom `password`
> harus di-hash dengan `password_hash()`, bukan teks biasa).

Perbedaan hak akses:
- **Guru**: hanya melihat & mengelola data yang berkaitan dengan tugasnya sendiri —
  - Kelas & Jadwal: **lihat saja** (tidak bisa tambah/hapus, itu tugas Admin)
  - Data Siswa: **lihat saja** siswa di kelas yang ia ajar, plus bisa **mencatat kehadiran** harian
  - Materi, Tugas & Ujian, Rekap Nilai: kelola penuh, tapi **dibatasi hanya untuk kelas/mapel yang ia ajar** dan **materi/tugas miliknya sendiri**
- **Admin**: kontrol penuh di seluruh sekolah —
  - CRUD Data Siswa (tambah/edit/hapus)
  - CRUD Data Guru (lewat tab "Data Guru" di halaman Data Siswa — tambah akun guru/admin baru, reset password, ubah status aktif/nonaktif)
  - CRUD Kelas & Jadwal (jadwal mengajar mingguan + jadwal ujian)
  - Melihat data nilai/materi/tugas di semua kelas

Semua pembatasan ini diterapkan di dua lapis: disembunyikan dari tampilan (UI) **dan** divalidasi ulang di sisi server (setiap file di folder `actions/`), jadi tidak bisa diakali dengan mengetik alamat URL secara langsung.

## 4. Fitur per Halaman

| Halaman              | Fitur                                                                 |
|-----------------------|------------------------------------------------------------------------|
| **Dashboard**         | Ringkasan statistik, jadwal hari ini, materi terbaru, pengumuman       |
| **Data Siswa**        | *Guru*: lihat siswa kelas sendiri + catat kehadiran harian. *Admin*: CRUD siswa + tab **Data Guru** (CRUD akun guru/admin) |
| **Materi Pembelajaran** | Upload file (PDF/Word/PPT/Excel/Video), filter, unduh; edit/hapus dibatasi milik sendiri untuk Guru |
| **Tugas & Ujian**     | Buat tugas/kuis/UTS/UAS, lihat status pengumpulan, input nilai per siswa|
| **Rekap Nilai**       | Input nilai (Tugas 1-3, UTS, UAS), nilai akhir otomatis, ekspor CSV — Guru dibatasi hanya kelas/mapel yang ia ajar |
| **Kelas & Jadwal**    | *Admin*: kelola jadwal mengajar mingguan & jadwal ujian. *Guru*: lihat saja |
| **Pengaturan**        | Edit profil + foto, ubah kata sandi, preferensi notifikasi             |

### Rumus Nilai Akhir
```
Nilai Akhir = 30% × rata-rata(Tugas 1, Tugas 2, Tugas 3) + 30% × UTS + 40% × UAS
```
Bobot ini bisa diubah di fungsi `hitungNilaiAkhir()` pada `includes/functions.php`.

## 5. Keamanan yang Sudah Diterapkan

- Password disimpan ter-hash (`password_hash` / bcrypt), tidak pernah teks biasa.
- Semua query database memakai **prepared statement** (PDO) — aman dari SQL Injection.
- Semua output ke halaman di-escape dengan `htmlspecialchars()` — aman dari XSS.
- Session di-regenerasi setiap login berhasil — mencegah session fixation.
- Folder `config/`, `includes/`, `database/` diblokir dari akses langsung via `.htaccess`.
- Folder `uploads/` tidak bisa menjalankan file PHP meski ada yang berhasil diunggah.
- Validasi tipe & ukuran file saat upload materi (maks 50MB) dan foto profil (maks 2MB).

## 6. Catatan

- Data contoh (siswa, nilai, jadwal, kehadiran) hanya untuk demo — silakan
  diganti dengan data sekolah yang sebenarnya.
- Tombol "Ekspor ke Excel" pada Rekap Nilai menghasilkan file **CSV** (bisa
  dibuka langsung oleh Microsoft Excel / Google Sheets).
- Desain visual (warna, tipografi, spacing) dipusatkan satu tempat di
  `includes/head.php` — ubah di situ untuk mengubah tampilan seluruh portal
  sekaligus.
