<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('dasbor.php'));
    exit;
}

$styleVersion = (string) (@filemtime(__DIR__ . '/assets/style.css') ?: time());
$message = '';
$error = '';
$resetRateMaxAttempts = 5;
$resetRateWindowSeconds = 600;
$resetRateLockSeconds = 600;

if (request_method_is('POST')) {
    $rateFailureRecorded = false;
    $username = post_string('username');
    $resetRateKeys = [
        'forgot-password:' . client_identity(),
        'forgot-password:' . client_identity() . ':' . strtolower($username !== '' ? $username : 'guest'),
    ];
    $rateStatus = ['blocked' => false, 'remaining_seconds' => 0, 'remaining_attempts' => $resetRateMaxAttempts];

    foreach ($resetRateKeys as $rateKey) {
        $currentStatus = rate_limit_db_status($pdo, $rateKey, $resetRateMaxAttempts, $resetRateWindowSeconds);
        if ($currentStatus['blocked']) {
            $rateStatus = $currentStatus;
            break;
        }
        if ($currentStatus['remaining_attempts'] < $rateStatus['remaining_attempts']) {
            $rateStatus = $currentStatus;
        }
    }

    if ($rateStatus['blocked']) {
        $wait = max(1, (int) $rateStatus['remaining_seconds']);
        $error = 'Terlalu banyak permintaan reset password. Coba lagi dalam ' . $wait . ' detik.';
    } elseif (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
        foreach ($resetRateKeys as $rateKey) {
            rate_limit_db_register_failure($pdo, $rateKey, $resetRateMaxAttempts, $resetRateWindowSeconds, $resetRateLockSeconds);
        }
        $rateFailureRecorded = true;
    } else {
        $contact = post_string('email');
        $email = strtolower($contact);
        $phone = normalize_phone_number($contact);

        if ($username === '' || $contact === '') {
            $error = 'Username dan email atau no HP wajib diisi.';
        } elseif ($phone === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email atau no HP tidak valid.';
        } else {
            try {
                ensure_password_reset_table($pdo);

                $stmt = $pdo->prepare(
                    "SELECT
                        u.id_user,
                        u.username,
                        a.email AS admin_email,
                        g.email AS guru_email,
                        o.kontak AS orangtua_kontak,
                        ortu_siswa.kontak AS siswa_orangtua_kontak
                    FROM tbl_users u
                    LEFT JOIN tbl_admin a ON a.id_user = u.id_user
                    LEFT JOIN tbl_guru g ON g.id_user = u.id_user
                    LEFT JOIN tbl_orangtua o ON o.id_user = u.id_user
                    LEFT JOIN tbl_siswa s ON s.id_user = u.id_user
                    LEFT JOIN tbl_orangtua ortu_siswa ON ortu_siswa.id_orangtua = s.id_orangtua
                    WHERE LOWER(u.username) = LOWER(?)
                    LIMIT 1"
                );
                $stmt->execute([$username]);
                $account = $stmt->fetch();

                $contactMatched = false;
                $debugReason = '';
                if ($account) {
                    $candidates = [
                        (string) ($account['admin_email'] ?? ''),
                        (string) ($account['guru_email'] ?? ''),
                        (string) ($account['orangtua_kontak'] ?? ''),
                        (string) ($account['siswa_orangtua_kontak'] ?? ''),
                    ];

                    foreach ($candidates as $candidate) {
                        if ($candidate === '') {
                            continue;
                        }

                        $candidateEmail = strtolower($candidate);
                        $candidatePhone = normalize_phone_number($candidate);

                        if ($phone !== '' && $candidatePhone !== '' && hash_equals($candidatePhone, $phone)) {
                            $contactMatched = true;
                            break;
                        }

                        if ($phone === '' && filter_var($candidateEmail, FILTER_VALIDATE_EMAIL) && hash_equals($candidateEmail, $email)) {
                            $contactMatched = true;
                            break;
                        }
                    }

                    if (!$contactMatched) {
                        $debugReason = 'Kontak tidak cocok. Kandidat di database: ' . implode(', ', array_filter($candidates));
                    }
                } else {
                    $debugReason = 'Username tidak ditemukan.';
                }

                if ($account && $contactMatched) {
                    $idUser = (int) $account['id_user'];
                    // Gunakan placeholder hash untuk permintaan yang baru masuk.
                    // Token asli baru akan dibuat dan dikirim saat admin menyetujui.
                    $placeholderHash = hash('sha256', 'pending-' . bin2hex(random_bytes(16)) . '-' . time());

                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('UPDATE tbl_password_reset_tokens SET used_at = NOW() WHERE id_user = ? AND used_at IS NULL');
                    $stmt->execute([$idUser]);

                    $stmt = $pdo->prepare("INSERT INTO tbl_password_reset_tokens (id_user, email, token_hash, expires_at, approved_at, approved_by) VALUES (?, ?, ?, NOW() + INTERVAL '24 hours', NULL, NULL)");
                    $stmt->execute([$idUser, $email, $placeholderHash]);

                    $adminIds = $pdo->query("SELECT id_user FROM tbl_users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                    if ($adminIds) {
                        $notifStmt = $pdo->prepare('INSERT INTO tbl_notifikasi (id_user, pesan, tanggal) VALUES (?, ?, CURRENT_DATE)');
                        foreach ($adminIds as $adminId) {
                            $notifStmt->execute([(int) $adminId, 'Permintaan reset password baru dari akun: ' . $username]);
                        }
                    }

                    $pdo->commit();

                    $message = 'Permintaan reset password Anda telah diterima. Silakan tunggu persetujuan admin. Jika disetujui, link reset akan dikirim ke email Anda.';

                    foreach ($resetRateKeys as $rateKey) {
                        rate_limit_db_clear($pdo, $rateKey);
                    }
                } else {
                    // Pesan tetap dibuat netral agar tidak membocorkan keberadaan akun.
                    $message = 'Permintaan reset password telah diterima. Jika data akun cocok, admin akan segera memproses permintaan Anda.';
                    foreach ($resetRateKeys as $rateKey) {
                        rate_limit_db_register_failure($pdo, $rateKey, $resetRateMaxAttempts, $resetRateWindowSeconds, $resetRateLockSeconds);
                    }
                    $rateFailureRecorded = true;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Gagal memproses permintaan reset password.';
            }
        }

        if ($error !== '' && !$rateFailureRecorded) {
            foreach ($resetRateKeys as $rateKey) {
                rate_limit_db_register_failure($pdo, $rateKey, $resetRateMaxAttempts, $resetRateWindowSeconds, $resetRateLockSeconds);
            }
        }
    }
}

?><!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - EduTrack</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/style.css?v=' . $styleVersion)) ?>">
</head>
<body class="login-portal login-ready">
<header class="login-header">
    <div class="login-shell login-header-row">
        <div class="login-brand">
            <div class="login-shield" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 10L12 5L2 10L12 15L22 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 12.5V15C6 15 8.5 17 12 17C15.5 17 18 15 18 15V12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 10V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="login-brand-text">
                <h1>EDUTRACK</h1>
                <p>Sistem Informasi Siswa Terintegrasi</p>
            </div>
        </div>
    </div>
</header>

<main class="login-main">
    <div class="login-shell">
        <section class="login-card" aria-label="Form lupa password">
            <div class="login-left">
                <h2>Reset Password</h2>
                <p>Masukkan username dan email atau no HP verifikasi. Jika cocok, sistem akan mengirim link reset password ke email Anda.</p>
            </div>
            <div class="login-right">
                <?php if ($message): ?>
                    <div class="alert success"><?= e($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="login-alert"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="login-field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username">
                    </div>
                    <div class="login-field">
                        <label for="email">Email atau No. HP Verifikasi</label>
                        <input type="email" id="email" name="email" required autocomplete="email">
                    </div>
                    <button class="login-submit" type="submit">Kirim Link Reset</button>
                </form>
                <p class="auth-help-links"><a href="<?= e(url('masuk.php')) ?>">Kembali ke login</a></p>
            </div>
        </section>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p><strong>EduTrack</strong></p>
        <p class="footer-note">Sistem Informasi Siswa Terintegrasi</p>
    </div>
</footer>
</body>
</html>
