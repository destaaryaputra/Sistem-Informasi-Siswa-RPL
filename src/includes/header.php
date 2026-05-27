<?php

declare(strict_types=1);

// Header bersama untuk seluruh halaman setelah user login.
require_once __DIR__ . '/../config/bootstrap.php';

$user = current_user();
$title = $title ?? 'EduTrack';
$notifikasiList = [];
$notifikasiCount = 0;
$adminSystemAlertCount = 0;
$adminResetRequestCount = 0;
$showNotifMenu = $user !== null;

// Ambil notifikasi milik user aktif untuk badge dan tampilan ringkas.
if ($showNotifMenu && isset($pdo)) {
    $hasReadColumn = db_column_exists($pdo, 'tbl_notifikasi', 'dibaca');

    if ($hasReadColumn) {
        $stmt = $pdo->prepare('SELECT pesan, tanggal FROM tbl_notifikasi WHERE id_user = ? AND dibaca = FALSE ORDER BY id_notifikasi DESC LIMIT 5');
        $stmt->execute([(int) $user['id_user']]);
        $notifikasiList = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_notifikasi WHERE id_user = ? AND dibaca = FALSE');
        $stmt->execute([(int) $user['id_user']]);
        $notifikasiCount = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare('SELECT pesan, tanggal FROM tbl_notifikasi WHERE id_user = ? ORDER BY id_notifikasi DESC LIMIT 5');
        $stmt->execute([(int) $user['id_user']]);
        $notifikasiList = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_notifikasi WHERE id_user = ?');
        $stmt->execute([(int) $user['id_user']]);
        $notifikasiCount = (int) $stmt->fetchColumn();
    }

    // Untuk admin, tambahkan notifikasi kesehatan data sistem ke badge.
    if (($user['role'] ?? '') === 'admin') {
        try {
            ensure_password_reset_table($pdo);
            $adminResetRequestCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM tbl_password_reset_tokens
                 WHERE used_at IS NULL AND approved_at IS NULL AND expires_at > NOW()"
            )->fetchColumn();
            $adminSystemAlertCount += $adminResetRequestCount;
        } catch (Throwable $e) {
            $adminResetRequestCount = 0;
        }

        $adminChecks = [
            'SELECT COUNT(*) FROM tbl_siswa WHERE id_orangtua IS NULL',
            'SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas IS NULL',
            "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_siswa s ON s.id_user = u.id_user WHERE u.role = 'siswa' AND s.id_siswa IS NULL",
            "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_guru g ON g.id_user = u.id_user WHERE u.role = 'guru' AND g.id_guru IS NULL",
            "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_orangtua o ON o.id_user = u.id_user WHERE u.role = 'orangtua' AND o.id_orangtua IS NULL",
            'SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM tbl_jadwal',
        ];

        foreach ($adminChecks as $checkSql) {
            $count = (int) $pdo->query($checkSql)->fetchColumn();
            if ($count > 0) {
                $adminSystemAlertCount += $count;
            }
        }
    }
}

// Admin hanya melihat notifikasi sistem; role lain tetap memakai notifikasi pribadi.
$totalNotifBadge = (($user['role'] ?? '') === 'admin')
    ? $adminSystemAlertCount
    : $notifikasiCount;

$currentPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$isNotifikasi = str_ends_with($currentPath, '/notifikasi.php');
$isDasbor = str_ends_with($currentPath, '/dasbor.php');
$isLaporan = str_ends_with($currentPath, '/laporan.php');
$isAdminPengguna = str_ends_with($currentPath, '/admin/pengguna.php');
$isAdminAkademik = str_ends_with($currentPath, '/admin/akademik.php');
$isGuruAbsensi = str_ends_with($currentPath, '/guru/absensi.php');
$isGuruNilai = str_ends_with($currentPath, '/guru/nilai.php');
$bodyClasses = [];
if ($isLaporan) {
    $bodyClasses[] = 'report-page';
}
if (isset($bodyClass) && is_string($bodyClass) && trim($bodyClass) !== '') {
    $bodyClasses[] = trim($bodyClass);
}
$bodyClass = implode(' ', array_values(array_unique($bodyClasses)));
$styleVersion = (string) (@filemtime(__DIR__ . '/../../public/assets/style.css') ?: time());
$notifLabel = (($user['role'] ?? '') === 'admin') ? 'Notifikasi Sistem' : 'Notifikasi';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <style>
        html {
            overflow-y: scroll;
            scrollbar-gutter: stable;
        }
    </style>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/style.css?v=' . $styleVersion)) ?>">
<link rel="stylesheet" href="<?= e(url('assets/edu-alert.css')) ?>">
<script src="<?= e(url('assets/edu-alert.js')) ?>"></script>

</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand-lockup">
            <div class="brand-logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 10L12 5L2 10L12 15L22 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 12.5V15C6 15 8.5 17 12 17C15.5 17 18 15 18 15V12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 10V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="brand-text">
                <p class="brand-kicker">EduTrack</p>
                <h1 style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin: 0; letter-spacing: 0.02em;">SISTEM INFORMASI SISWA</h1>
            </div>
        </div>
        <?php if ($user): ?>
            <div class="topbar-meta">
                <?php if ($showNotifMenu): ?>
                    <a class="notif-link topbar-notif <?= $isNotifikasi ? 'active' : '' ?>" href="<?= e(url('notifikasi.php')) ?>" aria-label="Buka notifikasi saya">
                        <span class="notif-icon" aria-hidden="true">🔔</span>
                        <span><?= e($notifLabel) ?></span>
                        <?php if ($totalNotifBadge > 0): ?>
                            <span class="notif-badge"><?= e((string) $totalNotifBadge) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('keluar.php')) ?>" class="topbar-logout">Keluar</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php if ($user): ?>
<div class="topnav-shell">
    <div class="container">
        <nav class="topnav">
            <a class="<?= $isDasbor ? 'active' : '' ?>" href="<?= e(url('dasbor.php')) ?>">Dasbor</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $isAdminPengguna ? 'active' : '' ?>" href="<?= e(url('admin/pengguna.php')) ?>">Manajemen Pengguna</a>
                <a class="<?= $isAdminAkademik ? 'active' : '' ?>" href="<?= e(url('admin/akademik.php')) ?>">Data Akademik</a>
            <?php endif; ?>
            <?php if ($user['role'] === 'guru'): ?>
                <a class="<?= $isGuruAbsensi ? 'active' : '' ?>" href="<?= e(url('guru/absensi.php')) ?>">Input Absensi</a>
                <a class="<?= $isGuruNilai ? 'active' : '' ?>" href="<?= e(url('guru/nilai.php')) ?>">Input Nilai</a>
            <?php endif; ?>
            <a class="<?= $isLaporan ? 'active' : '' ?>" href="<?= e(url('laporan.php')) ?>">Laporan</a>
        </nav>
    </div>
</div>
<?php endif; ?>
<main class="container app-content">
