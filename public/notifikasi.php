<?php

declare(strict_types=1);

// Halaman inbox notifikasi pribadi untuk user yang sedang login.
require_once __DIR__ . '/../app/config/bootstrap.php';

require_login();

$user = current_user();
$pageTitle = (($user['role'] ?? '') === 'admin') ? 'Notifikasi Sistem' : 'Notifikasi Saya';

// Ambil notifikasi hanya milik akun yang aktif saat ini.
$hasReadColumn = db_column_exists($pdo, 'tbl_notifikasi', 'dibaca');

if ($hasReadColumn) {
    $stmt = $pdo->prepare('SELECT id_notifikasi, pesan, tanggal, dibaca FROM tbl_notifikasi WHERE id_user = ? ORDER BY id_notifikasi DESC LIMIT 50');
    $stmt->execute([$user['id_user']]);
    $notifs = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id_notifikasi, pesan, tanggal FROM tbl_notifikasi WHERE id_user = ? ORDER BY id_notifikasi DESC LIMIT 50');
    $stmt->execute([$user['id_user']]);
    $notifs = $stmt->fetchAll();
    foreach ($notifs as &$notifRow) {
        $notifRow['dibaca'] = 0;
    }
    unset($notifRow);
}

// Notifikasi khusus admin untuk mendeteksi masalah data/sistem secara cepat.
$adminSystemNotifs = [];
if (($user['role'] ?? '') === 'admin') {
    $checks = [
        [
            'sql' => 'SELECT COUNT(*) FROM tbl_siswa WHERE id_orangtua IS NULL',
            'message' => 'Ada %d siswa belum terhubung ke orang tua.',
            'type' => 'error',
        ],
        [
            'sql' => 'SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas IS NULL',
            'message' => 'Ada %d siswa belum memiliki kelas.',
            'type' => 'error',
        ],
        [
            'sql' => "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_siswa s ON s.id_user = u.id_user WHERE u.role = 'siswa' AND s.id_siswa IS NULL",
            'message' => 'Ada %d akun berperan siswa tanpa profil siswa.',
            'type' => 'error',
        ],
        [
            'sql' => "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_guru g ON g.id_user = u.id_user WHERE u.role = 'guru' AND g.id_guru IS NULL",
            'message' => 'Ada %d akun berperan guru tanpa profil guru.',
            'type' => 'error',
        ],
        [
            'sql' => "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_orangtua o ON o.id_user = u.id_user WHERE u.role = 'orangtua' AND o.id_orangtua IS NULL",
            'message' => 'Ada %d akun berperan orang tua tanpa profil orang tua.',
            'type' => 'error',
        ],
        [
            'sql' => 'SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM tbl_jadwal',
            'message' => 'Data jadwal masih kosong. Guru tidak bisa input absensi/nilai sebelum jadwal tersedia.',
            'type' => 'warning',
        ],
    ];

    foreach ($checks as $check) {
        $count = (int) $pdo->query($check['sql'])->fetchColumn();
        if ($count > 0) {
            $pesan = str_contains($check['message'], '%d')
                ? sprintf($check['message'], $count)
                : $check['message'];
            $adminSystemNotifs[] = [
                'tanggal' => date('Y-m-d'),
                'pesan' => $pesan,
                'tipe' => $check['type'],
            ];
        }
    }
}

// Tombol kembali diarahkan ke halaman internal aplikasi agar aman dari redirect eksternal.
$backCandidate = get_string('back');
if ($backCandidate === '') {
    $backCandidate = (string) ($_SERVER['HTTP_REFERER'] ?? '');
}

$fallbackBack = url('dasbor.php');
$referrer = $fallbackBack;

if ($backCandidate !== '') {
    $normalizedBack = normalize_internal_path($backCandidate);
    if ($normalizedBack !== '') {
        $basePrefix = BASE_URL . '/';
        if (str_starts_with($normalizedBack, $basePrefix)) {
            $relativePath = ltrim(substr($normalizedBack, strlen(BASE_URL)), '/');
            $referrer = url($relativePath);
        }
    }
}

$title = $pageTitle;
include __DIR__ . '/../app/includes/header.php';
?>

    <div class="notification-page">
        <section class="card notification-panel" id="notifikasi-saya">
            <div class="section-heading notification-panel-head">
                <div>
                    <p class="brand-kicker">Pusat Pemberitahuan</p>
                    <h2><?= e(($user['role'] ?? '') === 'admin'
                        ? 'Notifikasi Sistem (' . count($adminSystemNotifs) . ')'
                        : 'Notifikasi Saya (' . count($notifs) . ')') ?></h2>
                </div>
                <a href="<?= e($referrer) ?>" class="action-link notification-back-link">Kembali</a>
            </div>

            <?php if (($user['role'] ?? '') !== 'admin'): ?>
                <?php if (!$notifs): ?>
                    <div class="empty-state">
                        <strong>Belum ada notifikasi.</strong>
                        <p>Semua pemberitahuan baru akan muncul di halaman ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="notif-table">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Pesan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($notifs as $notif): ?>
                                <tr class="notif-row <?= (bool) $notif['dibaca'] ? 'notif-read' : 'notif-unread' ?>" data-notif-id="<?= e((string) $notif['id_notifikasi']) ?>">
                                    <td class="notif-status">
                                        <?php if (!(bool) $notif['dibaca']): ?>
                                            <span class="badge unread-badge">Baru</span>
                                        <?php else: ?>
                                            <span class="status-text read">✓</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="notif-date"><?= e($notif['tanggal']) ?></td>
                                    <td class="notif-message"><?= e($notif['pesan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <?php if (!$adminSystemNotifs): ?>
                    <div class="empty-state">
                        <strong>Tidak ada kesalahan sistem yang terdeteksi saat ini.</strong>
                        <p>Semua data utama sudah terlihat normal.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Pesan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($adminSystemNotifs as $notif): ?>
                                <tr>
                                    <td><?= e($notif['tanggal']) ?></td>
                                    <td><span class="badge <?= e($notif['tipe']) ?>"><?= e(strtoupper($notif['tipe'])) ?></span></td>
                                    <td><?= e($notif['pesan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

<script>
(() => {
    const hasReadColumn = <?= $hasReadColumn ? 'true' : 'false' ?>;
    const csrfToken = '<?= e(csrf_token()) ?>';
    if (!hasReadColumn) {
        return;
    }

    const notifRows = document.querySelectorAll('.notif-row');
    
    notifRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', async function() {
            const notifId = this.dataset.notifId;
            
            if (!notifId || this.classList.contains('notif-read')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('id_notifikasi', notifId);
            formData.append('csrf_token', csrfToken);
            
            try {
                const response = await fetch('<?= e(url('api/mark-notif-read.php')) ?>', {
                    method: 'POST',
                    body: formData,
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Ubah styling notifikasi menjadi read
                    this.classList.remove('notif-unread');
                    this.classList.add('notif-read');
                    
                    // Update badge di halaman notifikasi
                    const badge = this.querySelector('.unread-badge');
                    if (badge) {
                        badge.remove();
                    }
                    
                    const statusCell = this.querySelector('.notif-status');
                    if (statusCell && !statusCell.querySelector('.status-text')) {
                        statusCell.innerHTML = '<span class="status-text read">✓</span>';
                    }
                    
                    // Update badge di header
                    const headerBadge = document.querySelector('.notif-badge');
                    if (headerBadge) {
                        let currentCount = parseInt(headerBadge.textContent, 10) || 0;
                        currentCount = Math.max(0, currentCount - 1);
                        
                        if (currentCount === 0) {
                            headerBadge.remove();
                        } else {
                            headerBadge.textContent = currentCount;
                        }
                    }
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        });
    });
})();
</script>

<?php include __DIR__ . '/../app/includes/footer.php'; ?>
