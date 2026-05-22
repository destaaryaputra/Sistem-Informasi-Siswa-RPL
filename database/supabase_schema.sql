-- Skema database untuk PostgreSQL (Supabase)
-- Jalankan di SQL Editor Supabase

-- Drop tabel jika sudah ada (urutannya penting karena foreign key)
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

-- 1. Users
CREATE TABLE tbl_users (
    id_user SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) CHECK (role IN ('admin', 'guru', 'siswa', 'orangtua')) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Admin
CREATE TABLE tbl_admin (
    id_admin SERIAL PRIMARY KEY,
    id_user INT NOT NULL UNIQUE REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL
);

-- 3. Guru
CREATE TABLE tbl_guru (
    id_guru SERIAL PRIMARY KEY,
    id_user INT NOT NULL UNIQUE REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin CHAR(1) CHECK (jenis_kelamin IN ('L', 'P')) NULL,
    email VARCHAR(100) NULL
);

-- 4. Orang Tua
CREATE TABLE tbl_orangtua (
    id_orangtua SERIAL PRIMARY KEY,
    id_user INT NOT NULL UNIQUE REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin CHAR(1) CHECK (jenis_kelamin IN ('L', 'P')) NULL,
    kontak VARCHAR(100) NULL
);

-- 5. Kelas
CREATE TABLE tbl_kelas (
    id_kelas SERIAL PRIMARY KEY,
    nama_kelas VARCHAR(30) NOT NULL,
    tingkat VARCHAR(2) CHECK (tingkat IN ('7', '8', '9', '10', '11', '12')) NOT NULL
);

-- 6. Mata Pelajaran
CREATE TABLE tbl_mapel (
    id_mapel SERIAL PRIMARY KEY,
    nama_mapel VARCHAR(100) NOT NULL
);

-- 7. Siswa
CREATE TABLE tbl_siswa (
    id_siswa SERIAL PRIMARY KEY,
    id_user INT NOT NULL UNIQUE REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    id_kelas INT NULL REFERENCES tbl_kelas(id_kelas) ON DELETE SET NULL,
    id_orangtua INT NULL REFERENCES tbl_orangtua(id_orangtua) ON DELETE SET NULL,
    nama VARCHAR(100) NOT NULL UNIQUE,
    jenis_kelamin CHAR(1) CHECK (jenis_kelamin IN ('L', 'P')) NULL,
    nis VARCHAR(30) NOT NULL UNIQUE
);

-- 8. Jadwal
CREATE TABLE tbl_jadwal (
    id_jadwal SERIAL PRIMARY KEY,
    id_guru INT NOT NULL REFERENCES tbl_guru(id_guru) ON DELETE CASCADE,
    id_kelas INT NOT NULL REFERENCES tbl_kelas(id_kelas) ON DELETE CASCADE,
    id_mapel INT NOT NULL REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE,
    hari VARCHAR(10) CHECK (hari IN ('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')) NOT NULL,
    jam VARCHAR(20) NOT NULL
);

-- 9. Kehadiran
CREATE TABLE tbl_kehadiran (
    id_kehadiran SERIAL PRIMARY KEY,
    id_siswa INT NOT NULL REFERENCES tbl_siswa(id_siswa) ON DELETE CASCADE,
    id_guru INT NOT NULL REFERENCES tbl_guru(id_guru) ON DELETE CASCADE,
    id_mapel INT NOT NULL REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE,
    tanggal DATE NOT NULL,
    status VARCHAR(10) CHECK (status IN ('hadir', 'izin', 'sakit', 'alpa')) NOT NULL,
    UNIQUE (id_siswa, tanggal, id_mapel)
);

-- 10. Nilai
CREATE TABLE tbl_nilai (
    id_nilai SERIAL PRIMARY KEY,
    id_siswa INT NOT NULL REFERENCES tbl_siswa(id_siswa) ON DELETE CASCADE,
    id_mapel INT NOT NULL REFERENCES tbl_mapel(id_mapel) ON DELETE CASCADE,
    id_guru INT NOT NULL REFERENCES tbl_guru(id_guru) ON DELETE CASCADE,
    jenis_penilaian VARCHAR(10) CHECK (jenis_penilaian IN ('tugas', 'kuis', 'uts', 'uas')) NOT NULL,
    skor DECIMAL(5,2) NOT NULL,
    periode VARCHAR(30) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_siswa, id_mapel, jenis_penilaian, periode)
);

-- 11. Notifikasi
CREATE TABLE tbl_notifikasi (
    id_notifikasi SERIAL PRIMARY KEY,
    id_user INT NOT NULL REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    pesan VARCHAR(255) NOT NULL,
    tanggal DATE NOT NULL,
    dibaca BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 12. Reset Password
CREATE TABLE tbl_password_reset_tokens (
    id_reset BIGSERIAL PRIMARY KEY,
    id_user INT NOT NULL REFERENCES tbl_users(id_user) ON DELETE CASCADE,
    email VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 13. Rate Limits
CREATE TABLE tbl_rate_limits (
    key_hash CHAR(64) PRIMARY KEY,
    bucket_label VARCHAR(64) NOT NULL,
    attempts_json TEXT NOT NULL,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Index untuk performa
CREATE INDEX idx_reset_user ON tbl_password_reset_tokens(id_user);
CREATE INDEX idx_reset_expires ON tbl_password_reset_tokens(expires_at);
CREATE INDEX idx_rate_limit_blocked_until ON tbl_rate_limits(blocked_until);
