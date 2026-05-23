<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('dasbor.php'));
    exit;
}

$styleVersion = (string) (@filemtime(__DIR__ . '/assets/style.css') ?: time());
$error = '';
$message = '';
$token = get_string('token');
if ($token === '') {
    $token = post_string('token');
}

function get_reset_row(PDO $pdo, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
           'SELECT pr.id_reset, pr.id_user, pr.expires_at, pr.used_at, pr.approved_at, u.username
         FROM tbl_password_reset_tokens pr
         JOIN tbl_users u ON u.id_user = pr.id_user
            WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);

    $row = $stmt->fetch();
    return $row ?: null;
}

try {
    ensure_password_reset_table($pdo);
} catch (Throwable $e) {
    $error = 'Fitur reset password belum siap. Hubungi admin.';
}

$resetRow = $error === '' ? get_reset_row($pdo, $token) : null;
$canReset = false;

if ($error === '' && $token !== '' && !$resetRow) {
    $error = 'Link reset tidak valid.';
} elseif ($resetRow) {
    if ($resetRow['used_at'] !== null) {
        $error = 'Link reset sudah pernah digunakan.';
    } elseif (strtotime((string) $resetRow['expires_at']) <= time()) {
        $error = 'Link reset sudah kedaluwarsa.';
    } elseif ($resetRow['approved_at'] === null) {
        $message = 'Permintaan reset Anda masih menunggu persetujuan admin.';
    } else {
        $canReset = true;
    }
}

if (request_method_is('POST') && $error === '') {
    if (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!$canReset || !$resetRow) {
        $error = 'Link reset belum siap digunakan.';
    } else {
        $passwordValue = $_POST['password'] ?? '';
        $passwordConfirmValue = $_POST['password_confirm'] ?? '';
        $password = is_scalar($passwordValue) ? (string) $passwordValue : '';
        $passwordConfirm = is_scalar($passwordConfirmValue) ? (string) $passwordConfirmValue : '';

        if (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif (!hash_equals($password, $passwordConfirm)) {
            $error = 'Konfirmasi password tidak sama.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('UPDATE tbl_users SET password = ? WHERE id_user = ?');
                $stmt->execute([password_hash($password, PASSWORD_BCRYPT), (int) $resetRow['id_user']]);

                $stmt = $pdo->prepare('UPDATE tbl_password_reset_tokens SET used_at = NOW() WHERE id_reset = ?');
                $stmt->execute([(int) $resetRow['id_reset']]);

                $pdo->commit();

                header('Location: ' . url('masuk.php?reset=success'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Gagal menyimpan password baru.';
            }
        }
    }
}

?><!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Password - Sistem Informasi Siswa</title>
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
                <h1>SISTEM INFORMASI SISWA</h1>
                <p>Atur ulang password akun Anda</p>
            </div>
        </div>
    </div>
</header>

<main class="login-main">
    <div class="login-shell">
        <section class="login-card" aria-label="Form reset password">
            <div class="login-left">
                <h2>Password Baru</h2>
                <p>Link reset hanya berlaku satu kali dan akan kedaluwarsa otomatis.</p>
            </div>
            <div class="login-right">
                <?php if ($message): ?>
                    <div class="alert success"><?= e($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="login-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($canReset && $resetRow): ?>
                    <p class="sub">Akun: <strong><?= e((string) $resetRow['username']) ?></strong></p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="login-field">
                            <label for="password">Password Baru</label>
                            <div class="login-input-wrap">
                                <div class="icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                        <path d="M8 11V8.5C8 6.6 9.6 5 11.5 5H12.5C14.4 5 16 6.6 16 8.5V11" stroke="currentColor" stroke-width="1.8"/>
                                    </svg>
                                </div>
                                <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Password Baru">
                                <button type="button" class="password-toggle" data-toggle="password" aria-label="Tampilkan password">
                                    <svg class="eye-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="login-field">
                            <label for="password_confirm">Konfirmasi Password</label>
                            <div class="login-input-wrap">
                                <div class="icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                        <path d="M8 11V8.5C8 6.6 9.6 5 11.5 5H12.5C14.4 5 16 6.6 16 8.5V11" stroke="currentColor" stroke-width="1.8"/>
                                    </svg>
                                </div>
                                <input type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password" placeholder="Konfirmasi Password">
                                <button type="button" class="password-toggle" data-toggle="password_confirm" aria-label="Tampilkan password">
                                    <svg class="eye-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button class="login-submit" type="submit">Simpan Password</button>
                    </form>
                <?php else: ?>
                    <p class="sub">Silakan tunggu persetujuan admin atau minta link reset baru.</p>
                <?php endif; ?>

                <p class="auth-help-links"><a href="<?= e(url('lupa-password.php')) ?>">Minta link reset baru</a> · <a href="<?= e(url('masuk.php')) ?>">Kembali ke login</a></p>
            </div>
        </section>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p><strong>EduTrack Sekolah</strong></p>
        <p class="footer-note">Sistem pemantauan kehadiran, nilai, dan notifikasi akademik secara terintegrasi.</p>
    </div>
</footer>

<script>
(() => {
    // Password Toggle Logic
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-toggle');
            const input = document.getElementById(inputId);
            const eyeIcon = btn.querySelector('.eye-icon');
            
            if (input && eyeIcon) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                
                if (isPassword) {
                    eyeIcon.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    `;
                    btn.setAttribute('aria-label', 'Sembunyikan password');
                } else {
                    eyeIcon.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    `;
                    btn.setAttribute('aria-label', 'Tampilkan password');
                }
            }
        });
    });
})();
</script>
</body>
</html>
