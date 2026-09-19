-- =====================================================================
-- SKEMA DATABASE: lms_smansa
-- Portal Guru & Admin - SMA Negeri 1 Bumiayu
-- =====================================================================
-- Cara pakai:
--   1. Buat database (jika belum ada):  CREATE DATABASE lms_smansa;
--   2. Import file ini:  mysql -u root -p lms_smansa < database/lms_smansa.sql
--      atau lewat phpMyAdmin: pilih database lms_smansa > Import > pilih file ini
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabel: users
-- Akun untuk login (Admin & Guru). NIP dipakai sebagai identitas pegawai,
-- tapi login memakai email (sesuai form di halaman Masuk).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap        VARCHAR(150)        NOT NULL,
    email               VARCHAR(150)        NOT NULL UNIQUE,
    password            VARCHAR(255)        NOT NULL,
    nip                 VARCHAR(30)         DEFAULT NULL,
    role                ENUM('admin','guru') NOT NULL DEFAULT 'guru',
    mapel_keahlian      VARCHAR(100)        DEFAULT NULL COMMENT 'Contoh: Guru Matematika',
    foto                VARCHAR(255)        DEFAULT NULL,
    bio                 TEXT                DEFAULT NULL,
    notif_email         TINYINT(1)          NOT NULL DEFAULT 1,
    notif_push          TINYINT(1)          NOT NULL DEFAULT 1,
    notif_sms           TINYINT(1)          NOT NULL DEFAULT 0,
    status              ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: kelas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kelas          VARCHAR(50)         NOT NULL COMMENT 'Contoh: X MIPA 1',
    tingkat             ENUM('X','XI','XII') NOT NULL,
    program             ENUM('MIPA','IPS')  NOT NULL,
    wali_kelas_id       INT UNSIGNED        DEFAULT NULL,
    tahun_ajaran        VARCHAR(20)         NOT NULL DEFAULT '2024/2025',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kelas_wali FOREIGN KEY (wali_kelas_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: mata_pelajaran
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mata_pelajaran (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_mapel          VARCHAR(20)         NOT NULL UNIQUE,
    nama_mapel          VARCHAR(100)        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: siswa
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS siswa (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nis                 VARCHAR(20)         NOT NULL,
    nisn                VARCHAR(20)         NOT NULL,
    nama_lengkap        VARCHAR(150)        NOT NULL,
    jenis_kelamin       ENUM('L','P')       NOT NULL DEFAULT 'L',
    kelas_id            INT UNSIGNED        DEFAULT NULL,
    foto                VARCHAR(255)        DEFAULT NULL,
    status              ENUM('aktif','pindah','lulus') NOT NULL DEFAULT 'aktif',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_nisn (nisn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: kehadiran (dipakai utk menghitung % kehadiran di Data Siswa)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kehadiran (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id            INT UNSIGNED        NOT NULL,
    kelas_id            INT UNSIGNED        NOT NULL,
    tanggal             DATE                NOT NULL,
    status              ENUM('hadir','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
    dicatat_oleh        INT UNSIGNED        DEFAULT NULL,
    CONSTRAINT fk_kehadiran_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_kehadiran_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_absen_harian (siswa_id, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: jadwal_mengajar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jadwal_mengajar (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id             INT UNSIGNED        NOT NULL,
    mapel_id            INT UNSIGNED        NOT NULL,
    kelas_id            INT UNSIGNED        NOT NULL,
    hari                ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL COMMENT 'Hari dalam pola mingguan (dipakai untuk jadwal reguler)',
    tanggal             DATE                DEFAULT NULL COMMENT 'Tanggal spesifik, dipakai untuk event sekali-jalan seperti ujian/rapat',
    jam_mulai           TIME                NOT NULL,
    jam_selesai         TIME                NOT NULL,
    ruang               VARCHAR(50)         DEFAULT NULL,
    jenis               ENUM('reguler','ujian','rapat','lainnya') NOT NULL DEFAULT 'reguler',
    keterangan          VARCHAR(255)        DEFAULT NULL,
    semester            ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
    tahun_ajaran        VARCHAR(20)         NOT NULL DEFAULT '2024/2025',
    CONSTRAINT fk_jadwal_guru  FOREIGN KEY (guru_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_jadwal_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: materi (Materi Pembelajaran)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS materi (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul               VARCHAR(200)        NOT NULL,
    mapel_id            INT UNSIGNED        NOT NULL,
    kelas_tingkat       ENUM('X','XI','XII') NOT NULL,
    semester            ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
    tipe_file           ENUM('pdf','video','docx','pptx','xlsx','lainnya') NOT NULL DEFAULT 'lainnya',
    nama_file           VARCHAR(255)        NOT NULL COMMENT 'nama file fisik di folder uploads/materi',
    nama_file_asli      VARCHAR(255)        DEFAULT NULL,
    ukuran_file         BIGINT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'ukuran dalam bytes',
    diunggah_oleh       INT UNSIGNED        NOT NULL,
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_materi_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_materi_user  FOREIGN KEY (diunggah_oleh) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: tugas_ujian (Tugas, Kuis, UTS, UAS)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tugas_ujian (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul               VARCHAR(200)        NOT NULL,
    jenis               ENUM('tugas','kuis','uts','uas') NOT NULL DEFAULT 'tugas',
    mapel_id            INT UNSIGNED        NOT NULL,
    kelas_id            INT UNSIGNED        NOT NULL,
    deskripsi           TEXT                DEFAULT NULL,
    tanggal_deadline    DATETIME            NOT NULL,
    dibuat_oleh         INT UNSIGNED        NOT NULL,
    status              ENUM('aktif','selesai') NOT NULL DEFAULT 'aktif',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tugas_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_tugas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tugas_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: pengumpulan_tugas (submission siswa per tugas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengumpulan_tugas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tugas_id            INT UNSIGNED        NOT NULL,
    siswa_id            INT UNSIGNED        NOT NULL,
    waktu_kumpul        DATETIME            DEFAULT NULL,
    file_jawaban        VARCHAR(255)        DEFAULT NULL,
    nilai               DECIMAL(5,2)        DEFAULT NULL,
    status              ENUM('belum','terkumpul','terlambat','dinilai') NOT NULL DEFAULT 'belum',
    CONSTRAINT fk_pengumpulan_tugas FOREIGN KEY (tugas_id) REFERENCES tugas_ujian(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengumpulan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_pengumpulan (tugas_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: nilai (Rekap Nilai Akhir per siswa / mapel / semester)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nilai (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id            INT UNSIGNED        NOT NULL,
    mapel_id            INT UNSIGNED        NOT NULL,
    kelas_id            INT UNSIGNED        NOT NULL,
    semester            ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
    tahun_ajaran        VARCHAR(20)         NOT NULL DEFAULT '2024/2025',
    tugas_1             DECIMAL(5,2)        DEFAULT NULL,
    tugas_2             DECIMAL(5,2)        DEFAULT NULL,
    tugas_3             DECIMAL(5,2)        DEFAULT NULL,
    uts                 DECIMAL(5,2)        DEFAULT NULL,
    uas                 DECIMAL(5,2)        DEFAULT NULL,
    catatan             VARCHAR(255)        DEFAULT NULL,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_nilai_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_nilai_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_nilai_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_nilai (siswa_id, mapel_id, semester, tahun_ajaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel: pengumuman
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengumuman (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul               VARCHAR(200)        DEFAULT 'Pengumuman',
    kepada              VARCHAR(255)        DEFAULT 'Semua',
    isi                 TEXT                NOT NULL,
    kategori            ENUM('penting','informasi') NOT NULL DEFAULT 'informasi',
    dibuat_oleh         INT UNSIGNED        NOT NULL,
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pengumuman_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATA CONTOH (SEED DATA) -- boleh dihapus/diganti sesuai data asli sekolah
-- Password default semua akun contoh: password123
-- =====================================================================

-- Password untuk SEMUA akun contoh di bawah ini: password123
INSERT INTO users (nama_lengkap, email, password, nip, role, mapel_keahlian, bio) VALUES
('Drs. Ahmad Syarifuddin', 'ahmad.syarifuddin@smanbumiayu.sch.id', '$2y$10$cW82MhslsX4deuxWNYt3DOYeYIKKbV8V48BAwfw.PmPHuLer2k5bm', '197508172005011003', 'guru', 'Guru Matematika', 'Pengajar Matematika dengan pengalaman lebih dari 15 tahun. Fokus pada metode pembelajaran interaktif dan logis.'),
('Siti Nurhaliza, S.Pd', 'siti.nurhaliza@smanbumiayu.sch.id', '$2y$10$cW82MhslsX4deuxWNYt3DOYeYIKKbV8V48BAwfw.PmPHuLer2k5bm', '198203102008012005', 'guru', 'Guru Fisika', 'Pengajar Fisika, aktif membina tim olimpiade sains sekolah.'),
('Budi Hartono, M.Pd', 'budi.hartono@smanbumiayu.sch.id', '$2y$10$cW82MhslsX4deuxWNYt3DOYeYIKKbV8V48BAwfw.PmPHuLer2k5bm', '197001011999031002', 'admin', 'Kepala Tata Usaha', 'Menangani administrasi akademik dan operasional portal sekolah.');

INSERT INTO mata_pelajaran (kode_mapel, nama_mapel) VALUES
('MTK', 'Matematika'),
('FIS', 'Fisika'),
('KIM', 'Kimia'),
('BIO', 'Biologi'),
('ING', 'Bahasa Inggris');

INSERT INTO kelas (nama_kelas, tingkat, program, wali_kelas_id, tahun_ajaran) VALUES
('X MIPA 1', 'X', 'MIPA', 1, '2024/2025'),
('X MIPA 2', 'X', 'MIPA', 2, '2024/2025'),
('X MIPA 3', 'X', 'MIPA', 1, '2024/2025'),
('X IPS 1', 'X', 'IPS', NULL, '2024/2025'),
('XI MIPA 1', 'XI', 'MIPA', 1, '2024/2025'),
('XI MIPA 2', 'XI', 'MIPA', 2, '2024/2025'),
('XI IPS 2', 'XI', 'IPS', NULL, '2024/2025'),
('XII MIPA 1', 'XII', 'MIPA', 1, '2024/2025'),
('XII MIPA 3', 'XII', 'MIPA', 2, '2024/2025'),
('XII MIPA 4', 'XII', 'MIPA', 1, '2024/2025');

INSERT INTO siswa (nis, nisn, nama_lengkap, jenis_kelamin, kelas_id, status) VALUES
('1920101', '0021345678', 'Budi Santoso', 'L', 8, 'aktif'),
('1920102', '0021345679', 'Siti Aminah', 'P', 7, 'aktif'),
('1920103', '0021345680', 'Ahmad Wijaya', 'L', 3, 'aktif'),
('1920104', '0051234567', 'Aditya Pratama', 'L', 1, 'aktif'),
('1920105', '0051234568', 'Budi Santoso Nugraha', 'L', 1, 'aktif'),
('1920106', '0051234569', 'Citra Lestari', 'P', 1, 'aktif'),
('1920107', '0051234570', 'Dewi Maharani', 'P', 1, 'aktif'),
('1920108', '0051234571', 'Eko Prasetyo', 'L', 1, 'aktif'),
('1920109', '0051234572', 'Fitriani Rahayu', 'P', 2, 'aktif'),
('1920110', '0051234573', 'Galih Setiawan', 'L', 2, 'aktif');

INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, hari, tanggal, jam_mulai, jam_selesai, ruang, jenis, keterangan, semester, tahun_ajaran) VALUES
-- Jadwal reguler (berulang tiap minggu, kolom tanggal dikosongkan)
(1, 1, 1, 'Senin', NULL, '07:30:00', '09:00:00', 'Ruang 204', 'reguler', 'Trigonometri Dasar', 'Ganjil', '2024/2025'),
(1, 1, 9, 'Senin', NULL, '09:15:00', '10:45:00', 'Ruang 301', 'reguler', 'Kalkulus Lanjut', 'Ganjil', '2024/2025'),
(1, 1, 3, 'Senin', NULL, '07:00:00', '08:30:00', 'Ruang 105', 'reguler', 'Trigonometri Dasar', 'Ganjil', '2024/2025'),
(1, 1, 2, 'Senin', NULL, '08:30:00', '10:00:00', 'Ruang 204', 'reguler', 'Trigonometri Dasar', 'Ganjil', '2024/2025'),
(1, 1, 6, 'Senin', NULL, '08:30:00', '10:00:00', 'Ruang 210', 'reguler', 'Matematika Peminatan', 'Ganjil', '2024/2025'),
(1, 1, 8, 'Senin', NULL, '08:30:00', '10:00:00', 'Ruang 301', 'reguler', 'Matematika Peminatan', 'Ganjil', '2024/2025'),
(1, 1, 5, 'Senin', NULL, '10:30:00', '12:00:00', 'Ruang 210', 'reguler', 'Matematika Peminatan', 'Ganjil', '2024/2025'),
(1, 1, 4, 'Senin', NULL, '10:30:00', '12:00:00', 'Ruang 106', 'reguler', 'Trigonometri Dasar', 'Ganjil', '2024/2025'),
(1, 1, 1, 'Selasa', NULL, '07:00:00', '08:30:00', 'Ruang 204', 'reguler', 'Latihan Soal', 'Ganjil', '2024/2025'),
(2, 2, 1, 'Rabu', NULL, '09:15:00', '10:45:00', 'Lab. Fisika', 'reguler', 'Hukum Newton', 'Ganjil', '2024/2025'),
-- Rapat koordinasi (berulang tiap minggu, tanpa tanggal spesifik)
(1, 1, 1, 'Senin', NULL, '11:00:00', '12:30:00', 'Ruang Guru', 'rapat', 'Rapat Koordinasi Mingguan', 'Ganjil', '2024/2025'),
-- Jadwal ujian & pengawasan (event sekali-jalan dengan tanggal spesifik, relatif ke hari ini agar selalu "akan datang")
(1, 1, 9, 'Rabu', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00', '12:00:00', 'Ruang 12', 'ujian', 'PTS Ganjil - Matematika (XII MIPA 3)', 'Ganjil', '2024/2025'),
(2, 2, 9, 'Senin', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '07:30:00', '09:00:00', 'Ruang 12', 'ujian', 'PTS Ganjil - Fisika (XII MIPA 3)', 'Ganjil', '2024/2025'),
(2, 2, 4, 'Rabu', DATE_ADD(CURDATE(), INTERVAL 9 DAY), '10:00:00', '11:30:00', 'Ruang 05', 'ujian', 'PTS Ganjil - B. Inggris (X IPS 1)', 'Ganjil', '2024/2025');

INSERT INTO materi (judul, mapel_id, kelas_tingkat, semester, tipe_file, nama_file, nama_file_asli, ukuran_file, diunggah_oleh) VALUES
('Trigonometri Lanjut: Sinus & Cosinus', 1, 'XI', 'Ganjil', 'pdf', 'contoh_trigonometri.pdf', 'Modul_Trigonometri_V1.pdf', 4404019, 1),
('Hukum Newton II: Dinamika Gerak', 2, 'X', 'Ganjil', 'video', 'contoh_newton.mp4', 'Video_Hukum_Newton.mp4', 134217728, 2),
('Kalkulus Integral: Luas Daerah', 1, 'XII', 'Genap', 'docx', 'contoh_kalkulus.docx', 'Kalkulus_Integral.docx', 1887436, 1),
('Struktur Sel Tumbuhan', 4, 'XI', 'Ganjil', 'pdf', 'contoh_sel.pdf', 'Struktur_Sel_Tumbuhan.pdf', 8912896, 1),
('Persamaan Reaksi Kimia', 3, 'X', 'Ganjil', 'pptx', 'contoh_kimia.pptx', 'Persamaan_Reaksi_Kimia.pptx', 12687360, 1);

INSERT INTO tugas_ujian (id, judul, jenis, mapel_id, kelas_id, deskripsi, tanggal_deadline, dibuat_oleh, status) VALUES
(1, 'Kuis Tengah Semester Kalkulus', 'kuis', 1, 1, 'Kuis tengah semester materi kalkulus dasar.', DATE_ADD(NOW(), INTERVAL 1 DAY), 1, 'aktif'),
(2, 'Laporan Praktikum Fisika', 'tugas', 2, 1, 'Laporan praktikum hukum Newton II.', DATE_ADD(NOW(), INTERVAL 7 DAY), 2, 'aktif'),
(3, 'PR Aljabar Bab 3', 'tugas', 1, 1, 'Latihan aljabar bab 3.', DATE_SUB(NOW(), INTERVAL 3 DAY), 1, 'selesai');

-- Simulasi status pengumpulan siswa kelas X MIPA 1 (kelas_id = 1, 5 siswa aktif)
INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, waktu_kumpul, nilai, status) VALUES
(1, 4, NOW(), NULL, 'terkumpul'),
(1, 5, NOW(), NULL, 'terkumpul'),
(1, 6, NOW(), NULL, 'terkumpul'),
(1, 7, NOW(), NULL, 'terkumpul'),
(2, 4, NOW(), NULL, 'terkumpul'),
(3, 4, DATE_SUB(NOW(), INTERVAL 4 DAY), 88, 'dinilai'),
(3, 5, DATE_SUB(NOW(), INTERVAL 4 DAY), 75, 'dinilai'),
(3, 6, DATE_SUB(NOW(), INTERVAL 4 DAY), 95, 'dinilai'),
(3, 7, DATE_SUB(NOW(), INTERVAL 4 DAY), 82, 'dinilai'),
(3, 8, DATE_SUB(NOW(), INTERVAL 4 DAY), 70, 'dinilai');

INSERT INTO nilai (siswa_id, mapel_id, kelas_id, semester, tahun_ajaran, tugas_1, tugas_2, tugas_3, uts, uas, catatan) VALUES
(4, 1, 1, 'Ganjil', '2024/2025', 85, 90, 88, 82, 86, NULL),
(5, 1, 1, 'Ganjil', '2024/2025', 75, 78, 80, 65, 72, 'Perlu remedial UTS'),
(6, 1, 1, 'Ganjil', '2024/2025', 95, 98, 100, 96, 98, 'Nilai tertinggi di kelas'),
(7, 1, 1, 'Ganjil', '2024/2025', 88, NULL, 92, 85, 89, 'T2 belum dikumpulkan');

INSERT INTO pengumuman (judul, isi, kategori, dibuat_oleh) VALUES
('Persiapan Ujian Akhir Semester Genap 2024', 'Mohon seluruh Bapak/Ibu guru segera mengumpulkan draf soal UAS paling lambat tanggal 30 Mei 2024 melalui portal ini.', 'penting', 3),
('Update Sistem Presensi Wajah Guru', 'Akan dilakukan pemeliharaan sistem presensi pada hari Sabtu ini pukul 14:00 WIB. Presensi dialihkan sementara ke manual.', 'informasi', 3);
-- Data kehadiran contoh (20 hari sekolah terakhir) untuk beberapa siswa X MIPA 1 & X MIPA 2
INSERT INTO kehadiran (siswa_id, kelas_id, tanggal, status) VALUES
(4, 1, "2026-06-11", "hadir"),
(5, 1, "2026-06-11", "hadir"),
(6, 1, "2026-06-11", "hadir"),
(7, 1, "2026-06-11", "sakit"),
(8, 1, "2026-06-11", "hadir"),
(9, 2, "2026-06-11", "hadir"),
(10, 2, "2026-06-11", "hadir"),
(4, 1, "2026-06-12", "hadir"),
(5, 1, "2026-06-12", "hadir"),
(6, 1, "2026-06-12", "sakit"),
(7, 1, "2026-06-12", "hadir"),
(8, 1, "2026-06-12", "hadir"),
(9, 2, "2026-06-12", "hadir"),
(10, 2, "2026-06-12", "sakit"),
(4, 1, "2026-06-15", "hadir"),
(5, 1, "2026-06-15", "hadir"),
(6, 1, "2026-06-15", "hadir"),
(7, 1, "2026-06-15", "hadir"),
(8, 1, "2026-06-15", "alpa"),
(9, 2, "2026-06-15", "hadir"),
(10, 2, "2026-06-15", "hadir"),
(4, 1, "2026-06-16", "hadir"),
(5, 1, "2026-06-16", "hadir"),
(6, 1, "2026-06-16", "hadir"),
(7, 1, "2026-06-16", "hadir"),
(8, 1, "2026-06-16", "hadir"),
(9, 2, "2026-06-16", "sakit"),
(10, 2, "2026-06-16", "hadir"),
(4, 1, "2026-06-17", "hadir"),
(5, 1, "2026-06-17", "hadir"),
(6, 1, "2026-06-17", "hadir"),
(7, 1, "2026-06-17", "hadir"),
(8, 1, "2026-06-17", "hadir"),
(9, 2, "2026-06-17", "hadir"),
(10, 2, "2026-06-17", "hadir"),
(4, 1, "2026-06-18", "hadir"),
(5, 1, "2026-06-18", "hadir"),
(6, 1, "2026-06-18", "hadir"),
(7, 1, "2026-06-18", "alpa"),
(8, 1, "2026-06-18", "alpa"),
(9, 2, "2026-06-18", "hadir"),
(10, 2, "2026-06-18", "hadir"),
(4, 1, "2026-06-19", "hadir"),
(5, 1, "2026-06-19", "hadir"),
(6, 1, "2026-06-19", "hadir"),
(7, 1, "2026-06-19", "sakit"),
(8, 1, "2026-06-19", "hadir"),
(9, 2, "2026-06-19", "hadir"),
(10, 2, "2026-06-19", "hadir"),
(4, 1, "2026-06-22", "hadir"),
(5, 1, "2026-06-22", "hadir"),
(6, 1, "2026-06-22", "hadir"),
(7, 1, "2026-06-22", "hadir"),
(8, 1, "2026-06-22", "hadir"),
(9, 2, "2026-06-22", "hadir"),
(10, 2, "2026-06-22", "hadir"),
(4, 1, "2026-06-23", "hadir"),
(5, 1, "2026-06-23", "hadir"),
(6, 1, "2026-06-23", "hadir"),
(7, 1, "2026-06-23", "hadir"),
(8, 1, "2026-06-23", "hadir"),
(9, 2, "2026-06-23", "hadir"),
(10, 2, "2026-06-23", "hadir"),
(4, 1, "2026-06-24", "hadir"),
(5, 1, "2026-06-24", "hadir"),
(6, 1, "2026-06-24", "hadir"),
(7, 1, "2026-06-24", "hadir"),
(8, 1, "2026-06-24", "hadir"),
(9, 2, "2026-06-24", "hadir"),
(10, 2, "2026-06-24", "hadir"),
(4, 1, "2026-06-25", "hadir"),
(5, 1, "2026-06-25", "hadir"),
(6, 1, "2026-06-25", "hadir"),
(7, 1, "2026-06-25", "sakit"),
(8, 1, "2026-06-25", "hadir"),
(9, 2, "2026-06-25", "hadir"),
(10, 2, "2026-06-25", "hadir"),
(4, 1, "2026-06-26", "hadir"),
(5, 1, "2026-06-26", "hadir"),
(6, 1, "2026-06-26", "hadir"),
(7, 1, "2026-06-26", "hadir"),
(8, 1, "2026-06-26", "hadir"),
(9, 2, "2026-06-26", "hadir"),
(10, 2, "2026-06-26", "hadir"),
(4, 1, "2026-06-29", "hadir"),
(5, 1, "2026-06-29", "hadir"),
(6, 1, "2026-06-29", "hadir"),
(7, 1, "2026-06-29", "hadir"),
(8, 1, "2026-06-29", "sakit"),
(9, 2, "2026-06-29", "hadir"),
(10, 2, "2026-06-29", "hadir"),
(4, 1, "2026-06-30", "hadir"),
(5, 1, "2026-06-30", "hadir"),
(6, 1, "2026-06-30", "hadir"),
(7, 1, "2026-06-30", "izin"),
(8, 1, "2026-06-30", "hadir"),
(9, 2, "2026-06-30", "hadir"),
(10, 2, "2026-06-30", "hadir"),
(4, 1, "2026-07-01", "hadir"),
(5, 1, "2026-07-01", "hadir"),
(6, 1, "2026-07-01", "sakit"),
(7, 1, "2026-07-01", "hadir"),
(8, 1, "2026-07-01", "hadir"),
(9, 2, "2026-07-01", "hadir"),
(10, 2, "2026-07-01", "hadir"),
(4, 1, "2026-07-02", "hadir"),
(5, 1, "2026-07-02", "hadir"),
(6, 1, "2026-07-02", "hadir"),
(7, 1, "2026-07-02", "hadir"),
(8, 1, "2026-07-02", "alpa"),
(9, 2, "2026-07-02", "hadir"),
(10, 2, "2026-07-02", "hadir"),
(4, 1, "2026-07-03", "hadir"),
(5, 1, "2026-07-03", "hadir"),
(6, 1, "2026-07-03", "hadir"),
(7, 1, "2026-07-03", "hadir"),
(8, 1, "2026-07-03", "hadir"),
(9, 2, "2026-07-03", "hadir"),
(10, 2, "2026-07-03", "alpa"),
(4, 1, "2026-07-06", "hadir"),
(5, 1, "2026-07-06", "hadir"),
(6, 1, "2026-07-06", "hadir"),
(7, 1, "2026-07-06", "hadir"),
(8, 1, "2026-07-06", "hadir"),
(9, 2, "2026-07-06", "hadir"),
(10, 2, "2026-07-06", "hadir"),
(4, 1, "2026-07-07", "hadir"),
(5, 1, "2026-07-07", "hadir"),
(6, 1, "2026-07-07", "hadir"),
(7, 1, "2026-07-07", "hadir"),
(8, 1, "2026-07-07", "alpa"),
(9, 2, "2026-07-07", "hadir"),
(10, 2, "2026-07-07", "alpa"),
(4, 1, "2026-07-08", "hadir"),
(5, 1, "2026-07-08", "hadir"),
(6, 1, "2026-07-08", "hadir"),
(7, 1, "2026-07-08", "sakit"),
(8, 1, "2026-07-08", "hadir"),
(9, 2, "2026-07-08", "hadir"),
(10, 2, "2026-07-08", "hadir");
