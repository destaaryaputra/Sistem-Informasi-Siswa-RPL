-- Skema database utama portal siswa. Jalankan file ini terlebih dahulu saat reset/import.
CREATE DATABASE IF NOT EXISTS portal_siswa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_siswa;

DROP TABLE IF EXISTS tbl_notifikasi;
DROP TABLE IF EXISTS tbl_password_reset_tokens;
DROP TABLE IF EXISTS tbl_rate_limits;
DROP TABLE IF EXISTS tbl_nilai;
DROP TABLE IF EXISTS tbl_kehadiran;
DROP TABLE IF EXISTS tbl_jadwal;
DROP TABLE IF EXISTS tbl_siswa;
DROP TABLE IF EXISTS tbl_orangtua;
DROP TABLE IF EXISTS tbl_guru;
DROP TABLE IF EXISTS tbl_admin;
DROP TABLE IF EXISTS tbl_mapel;
DROP TABLE IF EXISTS tbl_kelas;
DROP TABLE IF EXISTS tbl_users;

CREATE TABLE tbl_users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'guru', 'siswa', 'orangtua') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
);

CREATE TABLE tbl_guru (
    id_guru INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin ENUM('L', 'P') NULL,
    email VARCHAR(100) NULL,
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
);

CREATE TABLE tbl_orangtua (
    id_orangtua INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin ENUM('L', 'P') NULL,
    kontak VARCHAR(100) NULL,
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
);

CREATE TABLE tbl_kelas (
    id_kelas INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(30) NOT NULL,
    tingkat ENUM('7', '8', '9', '10', '11', '12') NOT NULL
);

CREATE TABLE tbl_mapel (
    id_mapel INT AUTO_INCREMENT PRIMARY KEY,
    nama_mapel VARCHAR(100) NOT NULL
);

CREATE TABLE tbl_siswa (
    id_siswa INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL UNIQUE,
    id_kelas INT NULL,
    id_orangtua INT NULL,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin ENUM('L', 'P') NULL,
    nis VARCHAR(30) NOT NULL UNIQUE,
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id_kelas) ON DELETE SET NULL,
    FOREIGN KEY (id_orangtua) REFERENCES tbl_orangtua(id_orangtua) ON DELETE SET NULL
);

CREATE TABLE tbl_jadwal (
    id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    id_kelas INT NOT NULL,
    id_mapel INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL,
    jam VARCHAR(20) NOT NULL,
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id_guru) ON DELETE CASCADE,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id_kelas) ON DELETE CASCADE,
    FOREIGN KEY (id_mapel) REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE
);

CREATE TABLE tbl_kehadiran (
    id_kehadiran INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_guru INT NOT NULL,
    id_mapel INT NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('hadir', 'izin', 'sakit', 'alpa') NOT NULL,
    UNIQUE KEY uk_kehadiran_siswa_tanggal_mapel (id_siswa, tanggal, id_mapel),
    FOREIGN KEY (id_siswa) REFERENCES tbl_siswa(id_siswa) ON DELETE CASCADE,
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id_guru) ON DELETE CASCADE,
    FOREIGN KEY (id_mapel) REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE
);

CREATE TABLE tbl_nilai (
    id_nilai INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_mapel INT NOT NULL,
    id_guru INT NOT NULL,
    jenis_penilaian ENUM('tugas', 'kuis', 'uts', 'uas') NOT NULL,
    skor DECIMAL(5,2) NOT NULL,
    periode VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nilai_unique (id_siswa, id_mapel, jenis_penilaian, periode),
    FOREIGN KEY (id_siswa) REFERENCES tbl_siswa(id_siswa) ON DELETE CASCADE,
    FOREIGN KEY (id_mapel) REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE,
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id_guru) ON DELETE CASCADE
);

CREATE TABLE tbl_notifikasi (
    id_notifikasi INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    pesan VARCHAR(255) NOT NULL,
    tanggal DATE NOT NULL,
    dibaca BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
);

CREATE TABLE tbl_password_reset_tokens (
    id_reset BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_user (id_user),
    INDEX idx_reset_expires (expires_at),
    FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
);

CREATE TABLE tbl_rate_limits (
    key_hash CHAR(64) PRIMARY KEY,
    bucket_label VARCHAR(64) NOT NULL,
    attempts_json LONGTEXT NOT NULL,
    blocked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_blocked_until (blocked_until)
);
-- Seed data demo untuk portal siswa.
-- File ini menyiapkan 20 siswa per kelas (11 kelas = 220 siswa)
-- beserta data orang tua yang terhubung satu-satu.
USE portal_siswa;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE tbl_notifikasi;
TRUNCATE TABLE tbl_nilai;
TRUNCATE TABLE tbl_kehadiran;
TRUNCATE TABLE tbl_jadwal;
TRUNCATE TABLE tbl_rate_limits;
TRUNCATE TABLE tbl_siswa;
TRUNCATE TABLE tbl_orangtua;
TRUNCATE TABLE tbl_guru;
TRUNCATE TABLE tbl_admin;
TRUNCATE TABLE tbl_mapel;
TRUNCATE TABLE tbl_kelas;
TRUNCATE TABLE tbl_users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO tbl_users (username, password, role) VALUES
('admin1', '123456', 'admin'),
('guru1', '123456', 'guru'),
('guru2', '123456', 'guru'),
('guru3', '123456', 'guru'),
('guru4', '123456', 'guru'),
('guru5', '123456', 'guru'),
('guru6', '123456', 'guru'),
('guru7', '123456', 'guru'),
('guru8', '123456', 'guru'),
('guru9', '123456', 'guru'),
('guru10', '123456', 'guru');

INSERT INTO tbl_admin (id_user, nama, email)
SELECT id_user, 'Admin Sekolah', 'admin@sekolah.id'
FROM tbl_users
WHERE username = 'admin1';

INSERT INTO tbl_guru (id_user, nama, jenis_kelamin, email)
SELECT u.id_user, d.nama, d.jenis_kelamin, d.email
FROM tbl_users u
JOIN (
    SELECT 'guru1' AS username, 'Budi Santoso' AS nama, 'L' AS jenis_kelamin, 'budi@sekolah.id' AS email
    UNION ALL SELECT 'guru2', 'Sari Wulandari', 'P', 'sari@sekolah.id'
    UNION ALL SELECT 'guru3', 'Ahmad Fauzi', 'L', 'ahmad@sekolah.id'
    UNION ALL SELECT 'guru4', 'Rina Lestari', 'P', 'rina@sekolah.id'
    UNION ALL SELECT 'guru5', 'Dewi Anggraini', 'P', 'dewi@sekolah.id'
    UNION ALL SELECT 'guru6', 'Hendra Saputra', 'L', 'hendra@sekolah.id'
    UNION ALL SELECT 'guru7', 'Novi Marlina', 'P', 'novi@sekolah.id'
    UNION ALL SELECT 'guru8', 'Yoga Pratama', 'L', 'yoga@sekolah.id'
    UNION ALL SELECT 'guru9', 'Tari Kusuma', 'P', 'tari@sekolah.id'
    UNION ALL SELECT 'guru10', 'Fajar Nugroho', 'L', 'fajar@sekolah.id'
) d ON d.username = u.username;

INSERT INTO tbl_kelas (id_kelas, nama_kelas, tingkat) VALUES
(1, '7A', '7'),
(2, '7B', '7'),
(3, '7C', '7'),
(4, '8A', '8'),
(5, '8B', '8'),
(6, '9A', '9'),
(7, '9B', '9'),
(8, '10 IPA 1', '10'),
(9, '10 IPS 1', '10'),
(10, '11 IPA 1', '11'),
(11, '12 IPA 1', '12');

INSERT INTO tbl_mapel (id_mapel, nama_mapel) VALUES
(1, 'Matematika'),
(2, 'Bahasa Indonesia'),
(3, 'Bahasa Inggris'),
(4, 'IPA'),
(5, 'IPS'),
(6, 'PPKn'),
(7, 'Informatika'),
(8, 'Seni Budaya'),
(9, 'PJOK'),
(10, 'Pendidikan Agama'),
(11, 'Prakarya');

-- Generate 220 akun orang tua: ortu001 .. ortu220
INSERT INTO tbl_users (username, password, role)
WITH RECURSIVE seq AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 220
)
SELECT CONCAT('ortu', LPAD(n, 3, '0')), '123456', 'orangtua'
FROM seq;

-- Generate 220 akun siswa: siswa001 .. siswa220
INSERT INTO tbl_users (username, password, role)
WITH RECURSIVE seq AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 220
)
SELECT CONCAT('siswa', LPAD(n, 3, '0')), '123456', 'siswa'
FROM seq;

INSERT INTO tbl_orangtua (id_user, nama, jenis_kelamin, kontak)
SELECT
    u.id_user,
    CONCAT(
        IF(
            CAST(SUBSTRING(u.username, 5) AS UNSIGNED) % 2 = 0,
            ELT(1 + ((CAST(SUBSTRING(u.username, 5) AS UNSIGNED) - 1) % 10),
                'Aisyah', 'Bunga', 'Citra', 'Dewi', 'Eka',
                'Fitri', 'Gita', 'Hana', 'Intan', 'Jihan'
            ),
            ELT(1 + ((CAST(SUBSTRING(u.username, 5) AS UNSIGNED) - 1) % 10),
                'Arman', 'Budi', 'Cahyo', 'Darma', 'Edi',
                'Feri', 'Guntur', 'Hari', 'Indra', 'Jamal'
            )
        ),
        ' ',
        ELT(1 + FLOOR((CAST(SUBSTRING(u.username, 5) AS UNSIGNED) - 1) / 20),
            'Anwar', 'Bagaskara', 'Cakrawala', 'Dharmawan', 'Erlangga',
            'Firmanto', 'Gunadi', 'Hermawan', 'Iskandar', 'Jatmiko', 'Kusnadi'
        ),
        ' ',
        ELT(1 + MOD((CAST(SUBSTRING(u.username, 5) AS UNSIGNED) * 3) + 7, 20),
            'Santoso', 'Wijaya', 'Saputra', 'Nugroho', 'Pratama',
            'Hidayat', 'Firmansyah', 'Setiawan', 'Permana', 'Kusuma',
            'Ramadhan', 'Mahendra', 'Purnama', 'Wibowo', 'Laksono',
            'Pamungkas', 'Syahputra', 'Rahmawan', 'Kurniawan', 'Fadilah'
        )
    ),
    IF(CAST(SUBSTRING(u.username, 5) AS UNSIGNED) % 2 = 0, 'P', 'L'),
    CONCAT('08121', LPAD(CAST(SUBSTRING(u.username, 5) AS UNSIGNED), 7, '0'))
FROM tbl_users u
WHERE u.role = 'orangtua'
ORDER BY u.username;

INSERT INTO tbl_siswa (id_user, id_kelas, id_orangtua, nama, jenis_kelamin, nis)
SELECT
    su.id_user,
    CEIL(seq_num / 20),
    o.id_orangtua,
    CONCAT(
        IF(
            seq_num % 2 = 0,
            ELT(1 + ((seq_num - 1) % 10),
                'Nadira', 'Almira', 'Celine', 'Nayla', 'Salsabila',
                'Keisha', 'Aurel', 'Yumna', 'Intan', 'Azzahra'
            ),
            ELT(1 + ((seq_num - 1) % 10),
                'Rafi', 'Dion', 'Fakhri', 'Rizwan', 'Bagus',
                'Arvin', 'Fikran', 'Lintar', 'Davin', 'Nizam'
            )
        ),
        ' ',
        ELT(1 + FLOOR((seq_num - 1) / 20),
            'Akbar', 'Bintang', 'Cendana', 'Dirgantara', 'Elang',
            'Fathir', 'Gemilang', 'Hananta', 'Irawan', 'Jelita', 'Kirana'
        ),
        ' ',
        ELT(1 + MOD((seq_num * 5) + 9, 20),
            'Pratama', 'Azzahra', 'Saputra', 'Ramadhan', 'Putri',
            'Maulana', 'Lestari', 'Aditya', 'Safitri', 'Cahyani',
            'Mahendra', 'Wicaksana', 'Permata', 'Nugraha', 'Pamela',
            'Suryani', 'Kusuma', 'Herlambang', 'Puspita', 'Wijaksana'
        )
    ) AS nama,
    IF(seq_num % 2 = 0, 'P', 'L') AS jenis_kelamin,
    CONCAT('NIS', LPAD(seq_num, 4, '0')) AS nis
FROM (
    SELECT
        u.id_user,
        CAST(SUBSTRING(u.username, 6) AS UNSIGNED) AS seq_num,
        CONCAT('ortu', SUBSTRING(u.username, 6)) AS ortu_username
    FROM tbl_users u
    WHERE u.role = 'siswa'
) su
JOIN tbl_users pu ON pu.username = su.ortu_username
JOIN tbl_orangtua o ON o.id_user = pu.id_user
ORDER BY su.seq_num;

INSERT INTO tbl_jadwal (id_guru, id_kelas, id_mapel, hari, jam)
SELECT g.id_guru, x.id_kelas, x.id_mapel, x.hari, x.jam
FROM (
    SELECT 'guru1' AS guru_username, 1 AS id_kelas, 1 AS id_mapel, 'Senin' AS hari, '07:30-09:00' AS jam
    UNION ALL SELECT 'guru2', 1, 2, 'Selasa', '09:30-11:00'
    UNION ALL SELECT 'guru3', 2, 3, 'Rabu', '07:30-09:00'
    UNION ALL SELECT 'guru4', 2, 4, 'Kamis', '09:30-11:00'
    UNION ALL SELECT 'guru5', 3, 5, 'Jumat', '07:30-09:00'
    UNION ALL SELECT 'guru6', 3, 6, 'Senin', '10:00-11:30'
    UNION ALL SELECT 'guru7', 4, 7, 'Selasa', '07:30-09:00'
    UNION ALL SELECT 'guru8', 5, 8, 'Rabu', '10:00-11:30'
    UNION ALL SELECT 'guru9', 6, 9, 'Kamis', '07:30-09:00'
    UNION ALL SELECT 'guru10', 7, 10, 'Jumat', '10:00-11:30'
    UNION ALL SELECT 'guru1', 8, 11, 'Senin', '12:30-14:00'
    UNION ALL SELECT 'guru2', 9, 1, 'Selasa', '12:30-14:00'
    UNION ALL SELECT 'guru3', 10, 2, 'Rabu', '12:30-14:00'
    UNION ALL SELECT 'guru4', 11, 3, 'Kamis', '12:30-14:00'
    UNION ALL SELECT 'guru5', 8, 4, 'Jumat', '12:30-14:00'
    UNION ALL SELECT 'guru6', 9, 5, 'Senin', '14:00-15:30'
    UNION ALL SELECT 'guru7', 10, 6, 'Selasa', '14:00-15:30'
    UNION ALL SELECT 'guru8', 11, 7, 'Rabu', '14:00-15:30'
    UNION ALL SELECT 'guru9', 8, 8, 'Kamis', '14:00-15:30'
    UNION ALL SELECT 'guru10', 9, 9, 'Jumat', '14:00-15:30'
) x
JOIN tbl_guru g
JOIN tbl_users u ON u.id_user = g.id_user AND u.username = x.guru_username;

SET @semester := '2026 Genap';

-- Data absensi semester genap untuk seluruh siswa berdasarkan jadwal kelasnya.
INSERT INTO tbl_kehadiran (id_siswa, id_guru, id_mapel, tanggal, status)
WITH RECURSIVE pertemuan_absen AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM pertemuan_absen WHERE n < 8
)
SELECT
    s.id_siswa,
    j.id_guru,
    j.id_mapel,
    DATE_ADD('2026-01-13', INTERVAL (pa.n - 1) * 7 DAY) AS tanggal,
    CASE
        WHEN MOD(s.id_siswa + j.id_mapel + pa.n, 17) = 0 THEN 'alpa'
        WHEN MOD(s.id_siswa + j.id_mapel + pa.n, 11) = 0 THEN 'izin'
        WHEN MOD(s.id_siswa + j.id_mapel + pa.n, 9) = 0 THEN 'sakit'
        ELSE 'hadir'
    END AS status
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas
JOIN pertemuan_absen pa;

-- Nilai tugas semester genap untuk seluruh siswa berdasarkan mapel yang dijadwalkan di kelasnya.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'tugas',
    ROUND(65 + MOD((s.id_siswa * 3) + (j.id_mapel * 5), 36), 2) AS skor,
    @semester AS periode
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai kuis semester genap.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'kuis',
    ROUND(68 + MOD((s.id_siswa * 2) + (j.id_mapel * 4), 33), 2) AS skor,
    @semester AS periode
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai UTS semester genap.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'uts',
    ROUND(67 + MOD((s.id_siswa * 5) + (j.id_mapel * 7), 34), 2) AS skor,
    @semester AS periode
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai UAS semester genap.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'uas',
    ROUND(66 + MOD((s.id_siswa * 7) + (j.id_mapel * 3), 35), 2) AS skor,
    @semester AS periode
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Notifikasi untuk nilai di bawah KKM (75) ke siswa dan orang tua.
INSERT INTO tbl_notifikasi (id_user, pesan, tanggal)
SELECT
    su.id_user,
    CONCAT('Peringatan: Nilai ', m.nama_mapel, ' (', UPPER(n.jenis_penilaian), ') = ', CAST(n.skor AS CHAR), ' berada di bawah KKM.'),
    CURDATE()
FROM tbl_nilai n
JOIN tbl_siswa s ON s.id_siswa = n.id_siswa
JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
JOIN tbl_users su ON su.id_user = s.id_user
WHERE n.skor < 75;

INSERT INTO tbl_notifikasi (id_user, pesan, tanggal)
SELECT
    pu.id_user,
    CONCAT('Peringatan: Nilai ', m.nama_mapel, ' (', UPPER(n.jenis_penilaian), ') = ', CAST(n.skor AS CHAR), ' berada di bawah KKM.'),
    CURDATE()
FROM tbl_nilai n
JOIN tbl_siswa s ON s.id_siswa = n.id_siswa
JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
JOIN tbl_orangtua o ON o.id_orangtua = s.id_orangtua
JOIN tbl_users pu ON pu.id_user = o.id_user
WHERE n.skor < 75;
