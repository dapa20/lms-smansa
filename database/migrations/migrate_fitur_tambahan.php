<?php
/**
 * Migrasi tabel-tabel untuk fitur tambahan LMS SMANSA.
 * Tabel-tabel ini digunakan oleh fitur-fitur yang sebelumnya berstatus
 * "Sedang Dibangun" pada halaman Data Siswa, Kelas & Jadwal, dan Rekap Nilai.
 *
 * Cara pakai:
 *   1. Buka http://localhost/lms-smansa/database/migrations/migrate_fitur_tambahan.php
 *      lewat browser, ATAU
 *   2. Jalankan lewat terminal:
 *      php database/migrations/migrate_fitur_tambahan.php
 *
 * Aman dijalankan berulang — script ini hanya membuat tabel jika belum ada
 * (CREATE TABLE IF NOT EXISTS), sehingga tidak akan menimpa data existing.
 */
require_once __DIR__ . '/../../config/database.php';

$queries = [

    // =================================================================
    // DATA SISWA — Struktur Organisasi Kelas
    // =================================================================
    "CREATE TABLE IF NOT EXISTS struktur_kelas (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kelas_id    INT UNSIGNED NOT NULL,
        siswa_id    INT UNSIGNED NOT NULL,
        jabatan     VARCHAR(50)  NOT NULL COMMENT 'Contoh: Ketua Kelas, Wakil Ketua, Sekretaris, Bendahara',
        urutan      TINYINT UNSIGNED NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_siswa_jabatan (kelas_id, siswa_id),
        CONSTRAINT fk_struktur_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_struktur_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // DATA SISWA — Catatan Wali Kelas
    // =================================================================
    "CREATE TABLE IF NOT EXISTS catatan_wali (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id    INT UNSIGNED NOT NULL,
        kelas_id    INT UNSIGNED NOT NULL,
        catatan     TEXT         NOT NULL,
        jenis       ENUM('positif','perhatian','pelanggaran','lainnya') NOT NULL DEFAULT 'perhatian',
        dibuat_oleh INT UNSIGNED NOT NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_catatan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_catatan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_catatan_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // DATA SISWA — Poin Siswa (Pelanggaran / Prestasi)
    // =================================================================
    "CREATE TABLE IF NOT EXISTS poin_siswa (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id    INT UNSIGNED NOT NULL,
        kelas_id    INT UNSIGNED NOT NULL,
        jenis       ENUM('pelanggaran','prestasi') NOT NULL DEFAULT 'pelanggaran',
        poin        INT          NOT NULL DEFAULT 0 COMMENT 'Negatif untuk pelanggaran, positif untuk prestasi',
        kategori    VARCHAR(100) NOT NULL COMMENT 'Contoh: Terlambat, Tidak membawa buku, Juara kelas',
        keterangan  VARCHAR(255) DEFAULT NULL,
        tanggal     DATE         NOT NULL,
        dibuat_oleh INT UNSIGNED NOT NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_poin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_poin_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_poin_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // KELAS & JADWAL — Jurnal Mengajar
    // =================================================================
    "CREATE TABLE IF NOT EXISTS jurnal_mengajar (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        jadwal_id     INT UNSIGNED NOT NULL,
        guru_id       INT UNSIGNED NOT NULL,
        kelas_id      INT UNSIGNED NOT NULL,
        mapel_id      INT UNSIGNED NOT NULL,
        tanggal       DATE         NOT NULL,
        jam_mulai     TIME         NOT NULL,
        jam_selesai   TIME         NOT NULL,
        materi        VARCHAR(255) NOT NULL,
        kegiatan      TEXT         DEFAULT NULL COMMENT 'Metode / kegiatan pembelajaran',
        catatan       TEXT         DEFAULT NULL COMMENT 'Catatan / hambatan / hal istimewa',
        siswa_hadir   INT UNSIGNED DEFAULT 0,
        siswa_izin    INT UNSIGNED DEFAULT 0,
        siswa_sakit   INT UNSIGNED DEFAULT 0,
        siswa_alpa    INT UNSIGNED DEFAULT 0,
        created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_jurnal_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal_mengajar(id) ON DELETE CASCADE,
        CONSTRAINT fk_jurnal_guru   FOREIGN KEY (guru_id)   REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_jurnal_kelas  FOREIGN KEY (kelas_id)  REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_jurnal_mapel  FOREIGN KEY (mapel_id)  REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
        INDEX idx_jurnal_tanggal (tanggal),
        INDEX idx_jurnal_guru_tgl (guru_id, tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // REKAP NILAI — KKM per Mata Pelajaran
    // =================================================================
    "CREATE TABLE IF NOT EXISTS kkm_mapel (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mapel_id        INT UNSIGNED NOT NULL,
        kelas_id        INT UNSIGNED NOT NULL COMMENT 'KKM bisa berbeda per kelas',
        kkm             DECIMAL(5,2) NOT NULL DEFAULT 75,
        bobot_tugas     DECIMAL(5,2) NOT NULL DEFAULT 30,
        bobot_uts       DECIMAL(5,2) NOT NULL DEFAULT 30,
        bobot_uas       DECIMAL(5,2) NOT NULL DEFAULT 40,
        deskripsi       VARCHAR(255) DEFAULT NULL,
        updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_kkm (mapel_id, kelas_id),
        CONSTRAINT fk_kkm_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
        CONSTRAINT fk_kkm_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // REKAP NILAI — Indikator Pencapaian
    // =================================================================
    "CREATE TABLE IF NOT EXISTS indikator_nilai (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mapel_id        INT UNSIGNED NOT NULL,
        kelas_tingkat   ENUM('X','XI','XII') NOT NULL,
        semester        ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
        kode_indikator  VARCHAR(20) NOT NULL COMMENT 'Contoh: IPK-1, IPK-2',
        deskripsi       VARCHAR(500) NOT NULL,
        urutan          TINYINT UNSIGNED NOT NULL DEFAULT 1,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_indikator_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // REKAP NILAI — Nilai Sikap Spiritual & Sosial
    // =================================================================
    "CREATE TABLE IF NOT EXISTS nilai_sikap (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id      INT UNSIGNED NOT NULL,
        kelas_id      INT UNSIGNED NOT NULL,
        jenis         ENUM('spiritual','sosial') NOT NULL,
        semester      ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
        tahun_ajaran  VARCHAR(20) NOT NULL DEFAULT '2024/2025',
        predikat      ENUM('Sangat Baik','Baik','Cukup','Perlu Bimbingan') NOT NULL DEFAULT 'Baik',
        deskripsi     TEXT DEFAULT NULL,
        dibuat_oleh   INT UNSIGNED NOT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sikap (siswa_id, kelas_id, jenis, semester, tahun_ajaran),
        CONSTRAINT fk_sikap_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_sikap_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_sikap_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // REKAP NILAI — Prestasi Siswa
    // =================================================================
    "CREATE TABLE IF NOT EXISTS prestasi_siswa (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id        INT UNSIGNED NOT NULL,
        kelas_id        INT UNSIGNED NOT NULL,
        jenis_prestasi  ENUM('akademik','non-akademik','olahraga','seni','lainnya') NOT NULL DEFAULT 'akademik',
        nama_prestasi   VARCHAR(200) NOT NULL,
        tingkat         ENUM('Sekolah','Kecamatan','Kabupaten','Provinsi','Nasional','Internasional') NOT NULL DEFAULT 'Sekolah',
        peringkat       VARCHAR(50) DEFAULT NULL COMMENT 'Juara 1 / 2 / 3 / Harapan / Peserta',
        tahun           YEAR NOT NULL,
        keterangan      VARCHAR(255) DEFAULT NULL,
        dibuat_oleh     INT UNSIGNED NOT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_prestasi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_prestasi_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_prestasi_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // DATA SISWA — Kenaikan Kelas
    // =================================================================
    "CREATE TABLE IF NOT EXISTS kenaikan_kelas (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id        INT UNSIGNED NOT NULL,
        kelas_asal_id   INT UNSIGNED NOT NULL,
        kelas_tujuan_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = tinggal kelas / tidak naik',
        tahun_ajaran    VARCHAR(20) NOT NULL,
        status          ENUM('naik','tinggal','lulus','pindah') NOT NULL DEFAULT 'naik',
        catatan         VARCHAR(255) DEFAULT NULL,
        dibuat_oleh     INT UNSIGNED NOT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_kenaikan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_kenaikan_asal FOREIGN KEY (kelas_asal_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_kenaikan_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // =================================================================
    // ARSIP RAPOR — Metadata Rapor yang sudah dicetak
    // =================================================================
    "CREATE TABLE IF NOT EXISTS arsip_rapor (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        siswa_id      INT UNSIGNED NOT NULL,
        kelas_id      INT UNSIGNED NOT NULL,
        semester      ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
        tahun_ajaran  VARCHAR(20) NOT NULL,
        file_arsip    VARCHAR(255) DEFAULT NULL COMMENT 'Path/nama file rapor yang diarsipkan (opsional)',
        rata_rata     DECIMAL(5,2) DEFAULT NULL,
        peringkat     INT UNSIGNED DEFAULT NULL,
        status        ENUM('cetak','diarsipkan') NOT NULL DEFAULT 'cetak',
        catatan       VARCHAR(255) DEFAULT NULL,
        dibuat_oleh   INT UNSIGNED NOT NULL,
        created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_rapor (siswa_id, kelas_id, semester, tahun_ajaran),
        CONSTRAINT fk_arsip_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        CONSTRAINT fk_arsip_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_arsip_user  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

$ok = 0; $gagal = 0;
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        $ok++;
    } catch (Throwable $e) {
        $gagal++;
        echo "[GAGAL] " . $e->getMessage() . "\n";
    }
}

echo "====\n";
echo "[OK] $ok tabel berhasil disiapkan.\n";
if ($gagal > 0) {
    echo "[GAGAL] $gagal tabel bermasalah.\n";
}
echo "====\n";