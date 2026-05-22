-- Seed data demo untuk Supabase (PostgreSQL).
-- File ini menyiapkan data awal yang kompatibel dengan PostgreSQL.

-- Matikan check foreign key jika diperlukan (di Postgres biasanya pakai TRUNCATE CASCADE)
TRUNCATE TABLE tbl_notifikasi, tbl_nilai, tbl_kehadiran, tbl_jadwal, tbl_rate_limits, tbl_siswa, tbl_orangtua, tbl_guru, tbl_admin, tbl_mapel, tbl_kelas, tbl_users RESTART IDENTITY CASCADE;

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
SELECT CONCAT('ortu', LPAD(n::text, 3, '0')), '123456', 'orangtua'
FROM generate_series(1, 220) AS n;

-- Generate 220 akun siswa: siswa001 .. siswa220
INSERT INTO tbl_users (username, password, role)
SELECT CONCAT('siswa', LPAD(n::text, 3, '0')), '123456', 'siswa'
FROM generate_series(1, 220) AS n;

INSERT INTO tbl_orangtua (id_user, nama, jenis_kelamin, kontak)
SELECT
    u.id_user,
    CONCAT(
        CASE
            WHEN (CAST(SUBSTRING(u.username, 5) AS INTEGER) % 2) = 0 THEN
                (ARRAY['Aisyah', 'Bunga', 'Citra', 'Dewi', 'Eka', 'Fitri', 'Gita', 'Hana', 'Intan', 'Jihan'])[1 + ((CAST(SUBSTRING(u.username, 5) AS INTEGER) - 1) % 10)]
            ELSE
                (ARRAY['Arman', 'Budi', 'Cahyo', 'Darma', 'Edi', 'Feri', 'Guntur', 'Hari', 'Indra', 'Jamal'])[1 + ((CAST(SUBSTRING(u.username, 5) AS INTEGER) - 1) % 10)]
        END,
        ' ',
        (ARRAY['Anwar', 'Bagaskara', 'Cakrawala', 'Dharmawan', 'Erlangga', 'Firmanto', 'Gunadi', 'Hermawan', 'Iskandar', 'Jatmiko', 'Kusnadi'])[1 + FLOOR((CAST(SUBSTRING(u.username, 5) AS INTEGER) - 1) / 20.0)::INTEGER],
        ' ',
        (ARRAY['Santoso', 'Wijaya', 'Saputra', 'Nugroho', 'Pratama', 'Hidayat', 'Firmansyah', 'Setiawan', 'Permana', 'Kusuma', 'Ramadhan', 'Mahendra', 'Purnama', 'Wibowo', 'Laksono', 'Pamungkas', 'Syahputra', 'Rahmawan', 'Kurniawan', 'Fadilah'])[1 + ((CAST(SUBSTRING(u.username, 5) AS INTEGER) * 3 + 7) % 20)]
    ),
    CASE WHEN (CAST(SUBSTRING(u.username, 5) AS INTEGER) % 2) = 0 THEN 'P' ELSE 'L' END,
    CONCAT('08121', LPAD(SUBSTRING(u.username, 5), 7, '0'))
FROM tbl_users u
WHERE u.role = 'orangtua'
ORDER BY u.username;

INSERT INTO tbl_siswa (id_user, id_kelas, id_orangtua, nama, jenis_kelamin, nis)
SELECT
    su.id_user,
    CEIL(seq_num / 20.0),
    o.id_orangtua,
    CONCAT(
        CASE
            WHEN (seq_num % 2) = 0 THEN
                (ARRAY['Nadira', 'Almira', 'Celine', 'Nayla', 'Salsabila', 'Keisha', 'Aurel', 'Yumna', 'Intan', 'Azzahra'])[1 + ((seq_num - 1) % 10)]
            ELSE
                (ARRAY['Rafi', 'Dion', 'Fakhri', 'Rizwan', 'Bagus', 'Arvin', 'Fikran', 'Lintar', 'Davin', 'Nizam'])[1 + ((seq_num - 1) % 10)]
        END,
        ' ',
        (ARRAY['Akbar', 'Bintang', 'Cendana', 'Dirgantara', 'Elang', 'Fathir', 'Gemilang', 'Hananta', 'Irawan', 'Jelita', 'Kirana'])[1 + FLOOR((seq_num - 1) / 20.0)::INTEGER],
        ' ',
        (ARRAY['Pratama', 'Azzahra', 'Saputra', 'Ramadhan', 'Putri', 'Maulana', 'Lestari', 'Aditya', 'Safitri', 'Cahyani', 'Mahendra', 'Wicaksana', 'Permata', 'Nugraha', 'Pamela', 'Suryani', 'Kusuma', 'Herlambang', 'Puspita', 'Wijaksana'])[1 + ((seq_num * 5 + 9) % 20)]
    ) AS nama,
    CASE WHEN (seq_num % 2) = 0 THEN 'P' ELSE 'L' END AS jenis_kelamin,
    CONCAT('NIS', LPAD(seq_num::text, 4, '0')) AS nis
FROM (
    SELECT
        u.id_user,
        CAST(SUBSTRING(u.username, 6) AS INTEGER) AS seq_num,
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
JOIN tbl_guru g ON g.id_guru > 0 -- dummy join to get g
JOIN tbl_users u ON u.id_user = g.id_user AND u.username = x.guru_username;

-- Data absensi semester genap.
INSERT INTO tbl_kehadiran (id_siswa, id_guru, id_mapel, tanggal, status)
SELECT
    s.id_siswa,
    j.id_guru,
    j.id_mapel,
    ('2026-01-13'::DATE + (pa.n - 1) * INTERVAL '7 days')::DATE AS tanggal,
    CASE
        WHEN ((s.id_siswa + j.id_mapel + pa.n) % 17) = 0 THEN 'alpa'
        WHEN ((s.id_siswa + j.id_mapel + pa.n) % 11) = 0 THEN 'izin'
        WHEN ((s.id_siswa + j.id_mapel + pa.n) % 9) = 0 THEN 'sakit'
        ELSE 'hadir'
    END AS status
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas
CROSS JOIN (SELECT n FROM generate_series(1, 8) AS n) AS pa;

-- Nilai tugas.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'tugas',
    ROUND(65 + ((s.id_siswa * 3 + j.id_mapel * 5) % 36), 2) AS skor,
    '2026 Genap'
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai kuis.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'kuis',
    ROUND(68 + ((s.id_siswa * 2 + j.id_mapel * 4) % 33), 2) AS skor,
    '2026 Genap'
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai UTS.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'uts',
    ROUND(67 + ((s.id_siswa * 5 + j.id_mapel * 7) % 34), 2) AS skor,
    '2026 Genap'
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Nilai UAS.
INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode)
SELECT
    s.id_siswa,
    j.id_mapel,
    j.id_guru,
    'uas',
    ROUND(66 + ((s.id_siswa * 7 + j.id_mapel * 3) % 35), 2) AS skor,
    '2026 Genap'
FROM tbl_siswa s
JOIN tbl_jadwal j ON j.id_kelas = s.id_kelas;

-- Notifikasi nilai rendah.
INSERT INTO tbl_notifikasi (id_user, pesan, tanggal)
SELECT
    su.id_user,
    CONCAT('Peringatan: Nilai ', m.nama_mapel, ' (', UPPER(n.jenis_penilaian), ') = ', n.skor::text, ' berada di bawah KKM.'),
    CURRENT_DATE
FROM tbl_nilai n
JOIN tbl_siswa s ON s.id_siswa = n.id_siswa
JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
JOIN tbl_users su ON su.id_user = s.id_user
WHERE n.skor < 75;

INSERT INTO tbl_notifikasi (id_user, pesan, tanggal)
SELECT
    pu.id_user,
    CONCAT('Peringatan: Nilai ', m.nama_mapel, ' (', UPPER(n.jenis_penilaian), ') = ', n.skor::text, ' berada di bawah KKM.'),
    CURRENT_DATE
FROM tbl_nilai n
JOIN tbl_siswa s ON s.id_siswa = n.id_siswa
JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
JOIN tbl_orangtua o ON o.id_orangtua = s.id_orangtua
JOIN tbl_users pu ON pu.id_user = o.id_user
WHERE n.skor < 75;
