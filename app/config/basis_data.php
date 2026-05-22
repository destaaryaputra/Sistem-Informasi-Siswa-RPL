<?php

declare(strict_types=1);

// Konfigurasi koneksi database utama yang dipakai semua halaman aplikasi.
$host = '127.0.0.1';
$dbName = 'portal_siswa';
$dbUser = 'root';
$dbPass = '';

// Buat koneksi PDO agar query bisa memakai prepared statement.
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan aplikasi dengan pesan yang jelas.
    http_response_code(500);
    die('Koneksi database gagal. Pastikan MySQL aktif dan database sudah dibuat.');
}

// Cek keberadaan kolom untuk fallback kompatibilitas skema lama.
function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool) $stmt->fetchColumn();

    return $cache[$key];
}

// Pastikan tabel dan kolom fitur reset password tersedia (kompatibel untuk skema lama/baru).
function ensure_password_reset_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tbl_password_reset_tokens (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if (!db_column_exists($pdo, 'tbl_password_reset_tokens', 'approved_at')) {
        $pdo->exec('ALTER TABLE tbl_password_reset_tokens ADD COLUMN approved_at DATETIME NULL AFTER used_at');
    }

    if (!db_column_exists($pdo, 'tbl_password_reset_tokens', 'approved_by')) {
        $pdo->exec('ALTER TABLE tbl_password_reset_tokens ADD COLUMN approved_by INT NULL AFTER approved_at');
    }
}

// Pastikan tabel rate limit tersedia untuk proteksi brute-force lintas session.
function ensure_rate_limit_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tbl_rate_limits (
            key_hash CHAR(64) PRIMARY KEY,
            bucket_label VARCHAR(64) NOT NULL,
            attempts_json LONGTEXT NOT NULL,
            blocked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_rate_limit_blocked_until (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

// Ubah key rate limit menjadi hash agar aman disimpan.
function rate_limit_storage_key(string $key): string
{
    return hash('sha256', $key);
}

// Normalisasi daftar timestamp dari JSON yang disimpan di database.
function rate_limit_decode_attempts(string $attemptsJson): array
{
    $decoded = json_decode($attemptsJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $value) {
        if (is_int($value)) {
            $normalized[] = $value;
            continue;
        }

        if (is_string($value) && ctype_digit($value)) {
            $normalized[] = (int) $value;
        }
    }

    return $normalized;
}

// Ambil status rate limit dari database.
function rate_limit_db_status(PDO $pdo, string $key, int $maxAttempts, int $windowSeconds): array
{
    ensure_rate_limit_table($pdo);

    $now = time();
    $storageKey = rate_limit_storage_key($key);
    $stmt = $pdo->prepare('SELECT attempts_json, blocked_until FROM tbl_rate_limits WHERE key_hash = ? LIMIT 1');
    $stmt->execute([$storageKey]);
    $row = $stmt->fetch();

    $attempts = [];
    $blockedUntil = 0;

    if ($row) {
        $attempts = rate_limit_decode_attempts((string) ($row['attempts_json'] ?? '[]'));
        $blockedUntil = (int) strtotime((string) ($row['blocked_until'] ?? ''));
        if ($blockedUntil <= 0) {
            $blockedUntil = 0;
        }
    }

    $attempts = array_values(array_filter($attempts, static fn ($value) => is_int($value) && ($now - $value) < $windowSeconds));
    $blocked = $blockedUntil > $now;

    return [
        'blocked' => $blocked,
        'remaining_seconds' => $blocked ? ($blockedUntil - $now) : 0,
        'remaining_attempts' => max(0, $maxAttempts - count($attempts)),
    ];
}

// Catat kegagalan dan aktifkan blok sementara bila batas tercapai.
function rate_limit_db_register_failure(PDO $pdo, string $key, int $maxAttempts, int $windowSeconds, int $lockSeconds): array
{
    ensure_rate_limit_table($pdo);

    $now = time();
    $storageKey = rate_limit_storage_key($key);
    $stmt = $pdo->prepare('SELECT attempts_json, blocked_until FROM tbl_rate_limits WHERE key_hash = ? LIMIT 1');
    $stmt->execute([$storageKey]);
    $row = $stmt->fetch();

    $attempts = [];
    $blockedUntil = 0;

    if ($row) {
        $attempts = rate_limit_decode_attempts((string) ($row['attempts_json'] ?? '[]'));
        $blockedUntil = (int) strtotime((string) ($row['blocked_until'] ?? ''));
        if ($blockedUntil <= 0) {
            $blockedUntil = 0;
        }
    }

    $attempts = array_values(array_filter($attempts, static fn ($value) => is_int($value) && ($now - $value) < $windowSeconds));
    $attempts[] = $now;

    if (count($attempts) >= $maxAttempts) {
        $blockedUntil = $now + $lockSeconds;
        $attempts = [];
    }

    $upsert = $pdo->prepare(
        'INSERT INTO tbl_rate_limits (key_hash, bucket_label, attempts_json, blocked_until)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             bucket_label = VALUES(bucket_label),
             attempts_json = VALUES(attempts_json),
             blocked_until = VALUES(blocked_until),
             updated_at = CURRENT_TIMESTAMP'
    );
    $upsert->execute([
        $storageKey,
        substr($key, 0, 64),
        json_encode($attempts, JSON_UNESCAPED_SLASHES),
        $blockedUntil > 0 ? date('Y-m-d H:i:s', $blockedUntil) : null,
    ]);

    return rate_limit_db_status($pdo, $key, $maxAttempts, $windowSeconds);
}

// Hapus data rate limit saat proses berhasil.
function rate_limit_db_clear(PDO $pdo, string $key): void
{
    ensure_rate_limit_table($pdo);
    $stmt = $pdo->prepare('DELETE FROM tbl_rate_limits WHERE key_hash = ?');
    $stmt->execute([rate_limit_storage_key($key)]);
}
