<?php

declare(strict_types=1);

// Konfigurasi koneksi database Supabase (PostgreSQL).
// Prioritaskan environment variables (untuk Vercel/Production), fallback ke nilai default untuk local.
$host = getenv('DB_HOST') ?: 'aws-1-ap-southeast-1.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'postgres';
$dbUser = getenv('DB_USER') ?: 'postgres.lepbhducicrszlqfcvnz';
$dbPass = getenv('DB_PASS') ?: '+L6aHmcm&B7UxGF';

// Buat koneksi PDO agar query bisa memakai prepared statement.
try {
    // Tambahkan sslmode=require karena Supabase mewajibkan koneksi SSL di beberapa region/pooler.
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode=require";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan aplikasi dengan pesan yang jelas.
    http_response_code(500);
    $errMsg = $e->getMessage();
    die("Koneksi database gagal. Cek Environment Variables (ENV) dan pastikan database Supabase aktif. Error: {$errMsg}");
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
        "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1"
    );
    $stmt->execute([strtolower($table), strtolower($column)]);
    $cache[$key] = (bool) $stmt->fetchColumn();

    return $cache[$key];
}

// Pastikan tabel dan kolom fitur reset password tersedia (kompatibel untuk skema lama/baru).
function ensure_password_reset_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tbl_password_reset_tokens (
            id_reset BIGSERIAL PRIMARY KEY,
            id_user INT NOT NULL,
            email VARCHAR(100) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL,
            approved_at TIMESTAMP NULL,
            approved_by INT NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_user) REFERENCES tbl_users(id_user) ON DELETE CASCADE
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_user ON tbl_password_reset_tokens (id_user)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_expires ON tbl_password_reset_tokens (expires_at)');

    if (!db_column_exists($pdo, 'tbl_password_reset_tokens', 'approved_at')) {
        $pdo->exec('ALTER TABLE tbl_password_reset_tokens ADD COLUMN approved_at TIMESTAMP NULL');
    }

    if (!db_column_exists($pdo, 'tbl_password_reset_tokens', 'approved_by')) {
        $pdo->exec('ALTER TABLE tbl_password_reset_tokens ADD COLUMN approved_by INT NULL');
    }
}

// Pastikan tabel rate limit tersedia untuk proteksi brute-force lintas session.
function ensure_rate_limit_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tbl_rate_limits (
            key_hash CHAR(64) PRIMARY KEY,
            bucket_label VARCHAR(64) NOT NULL,
            attempts_json TEXT NOT NULL,
            blocked_until TIMESTAMP NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limit_blocked_until ON tbl_rate_limits (blocked_until)');
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
    // ensure_rate_limit_table($pdo); // Dinonaktifkan untuk performa. Pastikan tabel sudah dibuat manual.

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
         ON CONFLICT (key_hash) DO UPDATE SET
             bucket_label = EXCLUDED.bucket_label,
             attempts_json = EXCLUDED.attempts_json,
             blocked_until = EXCLUDED.blocked_until,
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
