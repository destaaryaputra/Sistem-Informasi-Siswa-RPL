<?php

declare(strict_types=1);

// Halaman login: validasi akun lalu simpan data user ke session.
require_once __DIR__ . '/../src/config/bootstrap.php';

if (is_logged_in()) {
    header('Location: ' . url('dasbor.php'));
    exit;
}

$error = '';
$message = '';
$styleVersion = (string) (@filemtime(__DIR__ . '/assets/style.css') ?: time());

if (get_string('reset') === 'success') {
    $message = 'Password berhasil direset. Silakan login dengan password baru.';
}

$loginRateKey = 'login:' . client_identity();
$loginRateMaxAttempts = 6;
$loginRateWindowSeconds = 300;
$loginRateLockSeconds = 300;

if (request_method_is('POST')) {
    $rateFailureRecorded = false;
    $username = post_string('username');
    $loginRateKeys = [
        'login:' . client_identity(),
        'login:' . client_identity() . ':' . strtolower($username !== '' ? $username : 'guest'),
    ];
    $rateStatus = ['blocked' => false, 'remaining_seconds' => 0, 'remaining_attempts' => $loginRateMaxAttempts];

    foreach ($loginRateKeys as $rateKey) {
        $currentStatus = rate_limit_db_status($pdo, $rateKey, $loginRateMaxAttempts, $loginRateWindowSeconds);
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
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $wait . ' detik.';
    } elseif (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Sesi tidak valid. Silakan refresh halaman dan coba lagi.';
        $rateFailureRecorded = true;
    }

    $passwordValue = $_POST['password'] ?? '';
    $password = is_scalar($passwordValue) ? (string) $passwordValue : '';

    if ($error !== '') {
        $username = '';
        $password = '';
    }

    $user = null;
    $isValid = false;

    if ($error === '') {
        // Cari user berdasarkan username untuk proses autentikasi.
        $stmt = $pdo->prepare('SELECT id_user, username, password, role FROM tbl_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $storedPassword = (string) $user['password'];
            if (str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')) {
                // Password modern dicek langsung dengan password_verify.
                $isValid = password_verify($password, $storedPassword);
            } else {
                // Seed lama masih plaintext, jadi diverifikasi lalu di-upgrade ke hash.
                $isValid = hash_equals($storedPassword, $password);
                if ($isValid) {
                    $rehash = password_hash($password, PASSWORD_BCRYPT);
                    $update = $pdo->prepare('UPDATE tbl_users SET password = ? WHERE id_user = ?');
                    $update->execute([$rehash, (int) $user['id_user']]);
                }
            }
        }
    }

    if ($error === '' && $user && $isValid) {
        foreach ($loginRateKeys as $rateKey) {
            rate_limit_db_clear($pdo, $rateKey);
        }
        session_regenerate_id(true);
        // Simpan identitas minimal yang dibutuhkan sepanjang session.
        $_SESSION['user'] = [
            'id_user' => (int) $user['id_user'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
        header('Location: ' . url('dasbor.php'));
        exit;
    }

    if ($error === '') {
        foreach ($loginRateKeys as $rateKey) {
            $rateStatus = rate_limit_db_register_failure($pdo, $rateKey, $loginRateMaxAttempts, $loginRateWindowSeconds, $loginRateLockSeconds);
        }
        $rateFailureRecorded = true;
        if ($rateStatus['blocked']) {
            $wait = max(1, (int) $rateStatus['remaining_seconds']);
            $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $wait . ' detik.';
        } else {
            $remaining = max(0, (int) $rateStatus['remaining_attempts']);
            $error = 'Username atau password salah. Sisa percobaan: ' . $remaining . '.';
        }
    }

    if ($error !== '' && !$rateFailureRecorded) {
        foreach ($loginRateKeys as $rateKey) {
            rate_limit_db_register_failure($pdo, $rateKey, $loginRateMaxAttempts, $loginRateWindowSeconds, $loginRateLockSeconds);
        }
    }

}

?><!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - EduTrack</title>
    <style>
        html {
            overflow-y: scroll;
            scrollbar-gutter: stable;
        }
    </style>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/style.css?v=' . $styleVersion)) ?>">
    <noscript>
        <style>
            body.login-portal.login-preload .login-header,
            body.login-portal.login-preload .login-main {
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
            }
        </style>
    </noscript>
</head>
<body class="login-portal login-preload">
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
        <section class="login-card" aria-label="Panel login portal siswa">
            <div class="login-left">
                <h2>Selamat Datang di Portal Sekolah Anda.</h2>
                <p>Kelola kehadiran, nilai, dan laporan Anda dengan mudah.</p>
                <div class="login-feature-box">
                    <div class="login-feature-item">
                        <div class="login-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 19.5C4.6 16.9 6.9 15 9.6 15H14.4C17.1 15 19.4 16.9 20 19.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                        </div>
                        <div>
                            <strong>Akses Berbasis Peran</strong>
                            <span>Hak akses dibedakan untuk admin, guru, siswa, dan orang tua.</span>
                        </div>
                    </div>
                    <div class="login-feature-item">
                        <div class="login-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.5 5.5H19.5V18.5H4.5V5.5Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M8 15L10.6 12.4L12.8 14.2L16 11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <strong>Dashboard Terintegrasi</strong>
                            <span>Ringkasan kehadiran dan nilai tersedia dalam satu tampilan efisien.</span>
                        </div>
                    </div>
                    <div class="login-feature-item">
                        <div class="login-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 16.8C6 18.6 7.5 20 9.3 20H14.7C16.5 20 18 18.6 18 16.8V11.2C18 7.8 15.3 5 12 5C8.7 5 6 7.8 6 11.2V16.8Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M9.5 4.5C9.8 3.6 10.8 3 12 3C13.2 3 14.2 3.6 14.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <strong>Notifikasi Otomatis</strong>
                            <span>Peringatan dikirim saat terdeteksi anomali kehadiran atau nilai.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="login-right">
                <h3>Masuk ke Portal</h3>
                <p class="sub">Silakan gunakan akun sekolah Anda untuk melanjutkan.</p>
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
                        <div class="login-input-wrap">
                            <div class="icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M5 18.5C5.7 15.9 8 14 10.8 14H13.2C16 14 18.3 15.9 19 18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <input type="text" id="username" name="username" required autocomplete="username" placeholder="username@sekolah.edu">
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-input-wrap">
                            <div class="icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M8 11V8.5C8 6.6 9.6 5 11.5 5H12.5C14.4 5 16 6.6 16 8.5V11" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </div>
                            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Password">
                             <button type="button" id="togglePassword" class="password-toggle" aria-label="Tampilkan password">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                             </button>
                        </div>
                    </div>
                     <button class="login-submit" type="submit">Masuk</button>
                </form>
                <p class="auth-help-links"><a href="<?= e(url('lupa-password.php')) ?>">Lupa password?</a></p>
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
<script>
(() => {
    // Password Toggle Logic
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            
            // Update Icon
            if (isPassword) {
                eyeIcon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                `;
                toggleBtn.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                `;
                toggleBtn.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    }

    const body = document.body;
    let revealed = false;

    const initScrollReveal = () => {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const targets = Array.from(document.querySelectorAll([
            '.login-header-row',
            '.login-left',
            '.login-feature-item',
            '.login-right',
            '.login-field',
            '.login-submit'
        ].join(',')));

        if (!targets.length) {
            return;
        }

        const assignDirection = (el) => {
            const rect = el.getBoundingClientRect();
            const vh = window.innerHeight || document.documentElement.clientHeight;
            const center = rect.top + (rect.height / 2);

            if (center < vh * 0.33) {
                el.dataset.reveal = 'top';
            } else if (center > vh * 0.67) {
                el.dataset.reveal = 'bottom';
            } else {
                el.dataset.reveal = 'middle';
            }
        };

        targets.forEach((el, i) => {
            el.classList.add('scroll-reveal');
            el.style.setProperty('--reveal-delay', (i % 4) * 55 + 'ms');
            assignDirection(el);
        });

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    assignDirection(entry.target);
                    entry.target.classList.add('is-visible');
                } else {
                    entry.target.classList.remove('is-visible');
                }
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -2% 0px'
        });

        targets.forEach((el) => revealObserver.observe(el));
    };

    const reveal = () => {
        if (revealed) {
            return;
        }
        revealed = true;
        body.classList.add('login-ready');
        body.classList.remove('login-preload');
        initScrollReveal();
    };

    if (document.readyState === 'complete') {
        requestAnimationFrame(reveal);
        return;
    }

    window.addEventListener('load', () => {
        requestAnimationFrame(reveal);
    }, { once: true });

    // Fallback jika event load terlambat
    window.setTimeout(reveal, 600);
})();
</script>
</body>
</html>
