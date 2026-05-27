<?php

declare(strict_types=1);

// Modul admin untuk menambah dan menghapus user sesuai role.
require_once __DIR__ . '/../../src/config/bootstrap.php';

require_role(['admin']);

$message = '';
$error = '';
$currentUserId = (int) (current_user()['id_user'] ?? 0);

// Hapus user berdasarkan ID sambil menjaga agar akun admin aktif tidak terhapus.
function delete_user_by_id(PDO $pdo, int $idUser, int $currentUserId): void
{
    if ($idUser <= 0) {
        throw new RuntimeException('ID user tidak valid.');
    }

    if ($idUser === $currentUserId) {
        throw new RuntimeException('Akun yang sedang dipakai tidak bisa dihapus.');
    }

    $stmt = $pdo->prepare('SELECT role FROM tbl_users WHERE id_user = ? LIMIT 1');
    $stmt->execute([$idUser]);
    $role = $stmt->fetchColumn();

    if ($role === false) {
        throw new RuntimeException('Pengguna tidak ditemukan.');
    }

    $parentUserId = null;

    if ($role === 'siswa') {
        $stmt = $pdo->prepare('SELECT s.id_orangtua, o.id_user AS id_user_orangtua FROM tbl_siswa s LEFT JOIN tbl_orangtua o ON o.id_orangtua = s.id_orangtua WHERE s.id_user = ? LIMIT 1');
        $stmt->execute([$idUser]);
        $parentData = $stmt->fetch();
        if ($parentData && !empty($parentData['id_user_orangtua'])) {
            $parentUserId = (int) $parentData['id_user_orangtua'];
        }
    }

    $pdo->beginTransaction();

    try {
        // ON DELETE CASCADE di database akan menghapus data turunan yang terhubung.
        $stmt = $pdo->prepare('DELETE FROM tbl_users WHERE id_user = ?');
        $stmt->execute([$idUser]);

        if ($role === 'siswa' && $parentUserId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_siswa s JOIN tbl_orangtua o ON o.id_orangtua = s.id_orangtua WHERE o.id_user = ?');
            $stmt->execute([$parentUserId]);
            $sisaAnak = (int) $stmt->fetchColumn();

            if ($sisaAnak === 0) {
                $stmt = $pdo->prepare('DELETE FROM tbl_users WHERE id_user = ?');
                $stmt->execute([$parentUserId]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$kelasList = $pdo->query('SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC')->fetchAll();
$ortuList = $pdo->query('SELECT id_orangtua, nama, jenis_kelamin FROM tbl_orangtua ORDER BY nama ASC')->fetchAll();
$searchUser = get_string('q');
$roleFilter = get_string('role');
$allowedRoles = ['admin', 'guru', 'siswa', 'orangtua'];

function current_page_number(string $key): int
{
    $page = get_int($key, 1);
    return $page > 1 ? $page : 1;
}

$roleFilter = get_enum('role', $allowedRoles, '');

ensure_password_reset_table($pdo);

if (request_method_is('POST')) {
    if (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif (post_int('approve_reset_request_id') > 0) {
        try {
            $idReset = post_int('approve_reset_request_id');

            if ($idReset <= 0) {
                throw new RuntimeException('ID permintaan reset tidak valid.');
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            // Update token_hash, approved_at, dan reset expiry ke 24 jam dari sekarang (PostgreSQL syntax).
            $stmt = $pdo->prepare("UPDATE tbl_password_reset_tokens SET approved_at = NOW(), approved_by = ?, token_hash = ?, expires_at = NOW() + INTERVAL '24 hours' WHERE id_reset = ? AND used_at IS NULL AND approved_at IS NULL AND expires_at > NOW()");
            $stmt->execute([$currentUserId, $tokenHash, $idReset]);

            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Permintaan reset tidak ditemukan atau sudah diproses.');
            }

            // Ambil data untuk notifikasi dan email.
            $stmt = $pdo->prepare('SELECT pr.email, u.id_user, u.username FROM tbl_password_reset_tokens pr JOIN tbl_users u ON u.id_user = pr.id_user WHERE pr.id_reset = ? LIMIT 1');
            $stmt->execute([$idReset]);
            $resetData = $stmt->fetch();

            if ($resetData) {
                $idTargetUser = (int) $resetData['id_user'];
                $targetEmail = (string) $resetData['email'];
                $targetUsername = (string) $resetData['username'];

                // In-app notification
                $notifStmt = $pdo->prepare('INSERT INTO tbl_notifikasi (id_user, pesan, tanggal) VALUES (?, ?, CURRENT_DATE)');
                $notifStmt->execute([$idTargetUser, 'Permintaan reset password Anda sudah disetujui admin. Silakan cek email Anda untuk link reset.']);

                // Kirim email beneran
                $resetLink = absolute_url('reset-password.php?token=' . $token);
                $subject = 'Reset Password Disetujui - Sistem Informasi Siswa';
                $body = "Halo " . $targetUsername . ",\n\n" .
                        "Permintaan reset password Anda telah disetujui oleh Administrator.\n\n" .
                        "Silakan klik tautan di bawah ini untuk mengatur ulang password akun Anda:\n" .
                        $resetLink . "\n\n" .
                        "Tautan ini berlaku selama 24 jam ke depan. Jika Anda tidak merasa melakukan permintaan ini, silakan abaikan email ini atau hubungi Admin sekolah untuk keamanan akun Anda.\n\n" .
                        "Terima kasih,\n" .
                        "Tim Sistem Informasi Siswa";
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $headers .= "From: noreply@sistem-informasi-siswa.local\r\n";

                $sent = @mail($targetEmail, $subject, $body, $headers);
                if (!$sent) {
                    // Log link jika email gagal dikirim (misal: di localhost tanpa SMTP)
                    $dir = __DIR__ . '/../../app/storage';
                    if (!is_dir($dir)) @mkdir($dir, 0775, true);
                    $logLine = date('Y-m-d H:i:s') . ' | ' . $targetEmail . ' | ' . $resetLink . PHP_EOL;
                    @file_put_contents($dir . '/reset_mail_debug.log', $logLine, FILE_APPEND);
                    $message = 'Permintaan reset password berhasil disetujui. Email gagal dikirim, link dicatat di log debug.';
                } else {
                    $message = 'Permintaan reset password berhasil disetujui dan email telah dikirim ke pengguna.';
                }
            } else {
                $message = 'Permintaan reset password berhasil disetujui.';
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyetujui permintaan reset password: ' . $e->getMessage();
        }
    } elseif (post_int('delete_user_id') > 0) {
        try {
            $idUser = post_int('delete_user_id');
            delete_user_by_id($pdo, $idUser, $currentUserId);
            $message = 'Pengguna berhasil dihapus.';
        } catch (Throwable $e) {
            $error = 'Gagal menghapus pengguna.';
        }
    } else {
        $username = post_string('username');
        $passwordValue = $_POST['password'] ?? '';
        $password = is_scalar($passwordValue) ? (string) $passwordValue : '';
        $role = post_enum('role', $allowedRoles, '');
        $nama = post_string('nama');
        $jenisKelamin = post_enum('jenis_kelamin', ['', 'L', 'P'], '');
        $kontak = post_string('kontak');
        $nis = post_string('nis');
        $idKelas = post_nullable_int('id_kelas');
        $idOrangtua = post_nullable_int('id_orangtua');

        if ($username === '' || $password === '' || $nama === '' || $role === '') {
            $error = 'Data wajib belum lengkap.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO tbl_users (username, password, role) VALUES (?, ?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), $role]);
                $idUser = (int) $pdo->lastInsertId();

                if ($role === 'admin') {
                    $stmt = $pdo->prepare('INSERT INTO tbl_admin (id_user, nama, email) VALUES (?, ?, ?)');
                    $stmt->execute([$idUser, $nama, $kontak !== '' ? $kontak : null]);
                }

                if ($role === 'guru') {
                    $stmt = $pdo->prepare('INSERT INTO tbl_guru (id_user, nama, jenis_kelamin, email) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$idUser, $nama, $jenisKelamin !== '' ? $jenisKelamin : null, $kontak !== '' ? $kontak : null]);
                }

                if ($role === 'orangtua') {
                    $stmt = $pdo->prepare('INSERT INTO tbl_orangtua (id_user, nama, jenis_kelamin, kontak) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$idUser, $nama, $jenisKelamin !== '' ? $jenisKelamin : null, $kontak !== '' ? $kontak : null]);
                }

                if ($role === 'siswa') {
                    if ($nis === '' || $idKelas === null) {
                        throw new RuntimeException('NIS dan kelas wajib diisi untuk peran siswa.');
                    }
                    $stmt = $pdo->prepare('INSERT INTO tbl_siswa (id_user, id_kelas, id_orangtua, nama, jenis_kelamin, nis) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$idUser, $idKelas, $idOrangtua, $nama, $jenisKelamin !== '' ? $jenisKelamin : null, $nis]);
                }

                $pdo->commit();
                $message = 'Pengguna berhasil ditambahkan.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Gagal menambahkan pengguna. Username atau nama kemungkinan sudah dipakai.';
            }
        }
    }
}

$usersSql = 'SELECT id_user, username, role, created_at FROM tbl_users WHERE 1=1';
$usersParams = [];

if ($searchUser !== '') {
    $usersSql .= ' AND username LIKE ?';
    $usersParams[] = '%' . $searchUser . '%';
}

if ($roleFilter !== '') {
    $usersSql .= ' AND role = ?';
    $usersParams[] = $roleFilter;
}

$usersSql .= ' ORDER BY username ASC';

$usersPerPage = 15;
$usersPage = current_page_number('page_users');
$usersCountSql = 'SELECT COUNT(*) FROM tbl_users WHERE 1=1';
$usersCountParams = [];

if ($searchUser !== '') {
    $usersCountSql .= ' AND username LIKE ?';
    $usersCountParams[] = '%' . $searchUser . '%';
}

if ($roleFilter !== '') {
    $usersCountSql .= ' AND role = ?';
    $usersCountParams[] = $roleFilter;
}

$stmt = $pdo->prepare($usersCountSql);
$stmt->execute($usersCountParams);
$usersTotal = (int) $stmt->fetchColumn();
$usersTotalPages = max(1, (int) ceil($usersTotal / $usersPerPage));
$usersPage = min($usersPage, $usersTotalPages);
$usersOffset = ($usersPage - 1) * $usersPerPage;

$stmt = $pdo->prepare($usersSql . ' LIMIT ' . $usersPerPage . ' OFFSET ' . $usersOffset);
$stmt->execute($usersParams);
$users = $stmt->fetchAll();

$ortuPerPage = 10;
$ortuPage = current_page_number('page_ortu');
$stmt = $pdo->query('SELECT COUNT(*) FROM tbl_orangtua');
$ortuTotal = (int) $stmt->fetchColumn();
$ortuTotalPages = max(1, (int) ceil($ortuTotal / $ortuPerPage));
$ortuPage = min($ortuPage, $ortuTotalPages);
$ortuOffset = ($ortuPage - 1) * $ortuPerPage;

$stmt = $pdo->prepare('SELECT o.id_orangtua, u.username, o.nama, o.jenis_kelamin, o.kontak, u.id_user FROM tbl_orangtua o JOIN tbl_users u ON u.id_user = o.id_user ORDER BY o.id_orangtua ASC LIMIT ' . $ortuPerPage . ' OFFSET ' . $ortuOffset);
$stmt->execute();
$orangtuaData = $stmt->fetchAll();

$siswaPerPage = 10;
$siswaPage = current_page_number('page_siswa');
$stmt = $pdo->query('SELECT COUNT(*) FROM tbl_siswa');
$siswaTotal = (int) $stmt->fetchColumn();
$siswaTotalPages = max(1, (int) ceil($siswaTotal / $siswaPerPage));
$siswaPage = min($siswaPage, $siswaTotalPages);
$siswaOffset = ($siswaPage - 1) * $siswaPerPage;

$stmt = $pdo->prepare("SELECT s.id_siswa, u.username, s.nama, s.jenis_kelamin, s.nis, k.nama_kelas, o.nama AS nama_orangtua, o.jenis_kelamin AS jk_orangtua, u.id_user FROM tbl_siswa s JOIN tbl_users u ON u.id_user = s.id_user LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas LEFT JOIN tbl_orangtua o ON o.id_orangtua = s.id_orangtua ORDER BY s.id_siswa ASC LIMIT $siswaPerPage OFFSET $siswaOffset");
$stmt->execute();
$siswaData = $stmt->fetchAll();

function pagination_links(string $baseUrl, string $pageKey, int $currentPage, int $totalPages, array $extraParams = []): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $buildUrl = static function (int $page) use ($baseUrl, $pageKey, $extraParams): string {
        $params = array_merge($extraParams, [$pageKey => $page]);
        return $baseUrl . '?' . http_build_query($params);
    };

    $links = '<div class="pagination">';
    if ($currentPage > 1) {
        $links .= '<a class="badge filter-print js-pagination-link" href="' . e($buildUrl($currentPage - 1)) . '">Sebelumnya</a>';
    }

    $links .= '<span class="footer-note">Halaman ' . e((string) $currentPage) . ' dari ' . e((string) $totalPages) . '</span>';

    if ($currentPage < $totalPages) {
        $links .= '<a class="badge filter-print js-pagination-link" href="' . e($buildUrl($currentPage + 1)) . '">Berikutnya</a>';
    }

    $links .= '</div>';
    return $links;
}
$resetRequests = $pdo->query("SELECT pr.id_reset, pr.email, pr.created_at, pr.expires_at, u.username, u.role
    FROM tbl_password_reset_tokens pr
    JOIN tbl_users u ON u.id_user = pr.id_user
    WHERE pr.used_at IS NULL AND pr.approved_at IS NULL AND pr.expires_at > NOW()
    ORDER BY pr.created_at DESC")->fetchAll();

$title = 'Manajemen Pengguna';
include __DIR__ . '/../../src/includes/header.php';
?>
<section class="card">
    <h2>Tambah Pengguna</h2>
    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div>
            <label for="username">Username</label>
            <input id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
        </div>
        <div>
            <label for="role">Peran</label>
            <select id="role" name="role" required>
                <option value="">- Pilih Peran -</option>
                <option value="admin">Admin</option>
                <option value="guru">Guru</option>
                <option value="siswa">Siswa</option>
                <option value="orangtua">Orang Tua</option>
            </select>
        </div>
        <div>
            <label for="nama">Nama</label>
            <input id="nama" name="nama" required>
        </div>
        <div>
            <label for="jenis_kelamin">Jenis Kelamin (guru/siswa/orang tua)</label>
            <select id="jenis_kelamin" name="jenis_kelamin">
                <option value="">- Pilih Jenis Kelamin -</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
            </select>
        </div>
        <div>
            <label for="kontak">Email/Kontak</label>
            <input id="kontak" name="kontak">
        </div>
        <div>
            <label for="nis">NIS (khusus siswa)</label>
            <input id="nis" name="nis">
        </div>
        <div>
            <label for="id_kelas">Kelas (khusus siswa)</label>
            <select id="id_kelas" name="id_kelas">
                <option value="">- Pilih Kelas -</option>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= e((string) $kelas['id_kelas']) ?>"><?= e($kelas['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="id_orangtua">Orang Tua (opsional siswa)</label>
            <select id="id_orangtua" name="id_orangtua">
                <option value="">- Pilih Orang Tua -</option>
                <?php foreach ($ortuList as $ortu): ?>
                    <option value="<?= e((string) $ortu['id_orangtua']) ?>"><?= e(format_parent_name((string) $ortu['nama'], (string) ($ortu['jenis_kelamin'] ?? ''), (int) $ortu['id_orangtua'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-compact">Simpan Pengguna</button>
        </div>
    </form>
</section>

<section class="card" id="approval-reset-password">
    <h2>Persetujuan Reset Password</h2>
    <p class="footer-note">Setujui permintaan reset password dari pengguna. Link reset baru aktif setelah disetujui.</p>
    <?php if (!$resetRequests): ?>
        <p>Tidak ada permintaan reset yang menunggu persetujuan.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID Request</th>
                <th>Username</th>
                <th>Peran</th>
                <th>Email Verifikasi</th>
                <th>Dibuat</th>
                <th>Batas Waktu</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($resetRequests as $request): ?>
                <tr>
                    <td><?= e((string) $request['id_reset']) ?></td>
                    <td><?= e((string) $request['username']) ?></td>
                    <td><span class="badge"><?= e(strtoupper((string) $request['role'])) ?></span></td>
                    <td><?= e((string) $request['email']) ?></td>
                    <td><?= e((string) $request['created_at']) ?></td>
                    <td><?= e((string) $request['expires_at']) ?></td>
                    <td>
                        <form method="post" data-confirm="Setujui permintaan reset password ini?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="approve_reset_request_id" value="<?= e((string) $request['id_reset']) ?>">
                            <button type="submit">Setujui</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" id="daftar-user">
    <h2>Daftar Pengguna</h2>
    <form method="get" class="filter-toolbar" style="margin-bottom: 10px;">
        <div class="filter-field">
            <label for="q">Cari Username</label>
            <input id="q" name="q" value="<?= e($searchUser) ?>" placeholder="Cari username...">
        </div>
        <div class="filter-field">
            <label for="role_filter">Filter Peran</label>
            <select id="role_filter" name="role">
                <option value="">Semua Peran</option>
                <?php foreach ($allowedRoles as $filterRole): ?>
                    <option value="<?= e($filterRole) ?>" <?= $roleFilter === $filterRole ? 'selected' : '' ?>><?= e(strtoupper($filterRole)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit">Terapkan</button>
            <a class="badge filter-print" href="<?= e(url('admin/pengguna.php#daftar-user')) ?>">Reset</a>
        </div>
    </form>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Peran</th>
            <th>Tanggal Dibuat</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e((string) $u['id_user']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><span class="badge"><?= e(strtoupper($u['role'])) ?></span></td>
                <td><?= e($u['created_at']) ?></td>
                <td>
                    <?php if ((int) $u['id_user'] !== $currentUserId): ?>
                        <form method="post" data-confirm="Hapus pengguna ini? Data terkait akan ikut terhapus sesuai relasi.">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="delete_user_id" value="<?= e((string) $u['id_user']) ?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    <?php else: ?>
                        <span class="badge">Aktif</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= pagination_links(url('admin/pengguna.php'), 'page_users', $usersPage, $usersTotalPages, ['q' => $searchUser, 'role' => $roleFilter]) ?>
</section>

<section class="card" id="data-orangtua">
    <h2>Data Orang Tua</h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Nama</th>
            <th>Jenis Kelamin</th>
            <th>Kontak</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orangtuaData as $ortu): ?>
            <tr>
                <td><?= e((string) $ortu['id_orangtua']) ?></td>
                <td><?= e($ortu['username']) ?></td>
                <td><?= e(format_parent_name((string) $ortu['nama'], (string) ($ortu['jenis_kelamin'] ?? ''), (int) $ortu['id_orangtua'])) ?></td>
                <td><?= e(($ortu['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : (($ortu['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : '-')) ?></td>
                <td><?= e((string) ($ortu['kontak'] ?? '-')) ?></td>
                <td>
                    <?php if ((int) $ortu['id_user'] !== $currentUserId): ?>
                        <form method="post" data-confirm="Hapus user orang tua ini? Data terkait akan ikut terhapus sesuai relasi.">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="delete_user_id" value="<?= e((string) $ortu['id_user']) ?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    <?php else: ?>
                        <span class="badge">Aktif</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= pagination_links(url('admin/pengguna.php'), 'page_ortu', $ortuPage, $ortuTotalPages, ['q' => $searchUser, 'role' => $roleFilter, 'page_users' => $usersPage]) ?>
</section>

<section class="card" id="data-siswa">
    <h2>Data Siswa</h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Nama</th>
            <th>Jenis Kelamin</th>
            <th>NIS</th>
            <th>Kelas</th>
            <th>Orang Tua</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($siswaData as $siswa): ?>
            <tr>
                <td><?= e((string) $siswa['id_siswa']) ?></td>
                <td><?= e($siswa['username']) ?></td>
                <td><?= e($siswa['nama']) ?></td>
                <td><?= e(($siswa['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : (($siswa['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : '-')) ?></td>
                <td><?= e($siswa['nis']) ?></td>
                <td><?= e((string) ($siswa['nama_kelas'] ?? '-')) ?></td>
                <td>
                    <?= !empty($siswa['nama_orangtua'])
                        ? e(format_parent_name((string) $siswa['nama_orangtua'], (string) ($siswa['jk_orangtua'] ?? '')))
                        : '-' ?>
                </td>
                <td>
                    <?php if ((int) $siswa['id_user'] !== $currentUserId): ?>
                        <form method="post" data-confirm="Hapus user siswa ini? Data terkait akan ikut terhapus sesuai relasi.">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="delete_user_id" value="<?= e((string) $siswa['id_user']) ?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    <?php else: ?>
                        <span class="badge">Aktif</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= pagination_links(url('admin/pengguna.php'), 'page_siswa', $siswaPage, $siswaTotalPages, ['q' => $searchUser, 'role' => $roleFilter, 'page_users' => $usersPage]) ?>
</section>
<script>
(() => {
    const parser = new DOMParser();
    let isSwapping = false;

    const swapSectionFromUrl = async (link) => {
        if (isSwapping) {
            return;
        }

        const currentSection = link.closest('section.card[id]');
        if (!currentSection) {
            window.location.href = link.href;
            return;
        }

        isSwapping = true;
        currentSection.classList.add('is-loading');
        const previousTop = currentSection.getBoundingClientRect().top;

        try {
            const response = await fetch(link.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            const html = await response.text();
            const doc = parser.parseFromString(html, 'text/html');
            const replacement = doc.getElementById(currentSection.id);

            if (!replacement) {
                throw new Error('Section not found');
            }

            currentSection.replaceWith(replacement);

            const newSection = document.getElementById(replacement.id);
            if (newSection) {
                const newTop = newSection.getBoundingClientRect().top;
                window.scrollBy({ top: newTop - previousTop, left: 0, behavior: 'auto' });
            }

            history.replaceState(null, '', link.href);
        } catch (err) {
            window.location.href = link.href;
        } finally {
            isSwapping = false;
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a.js-pagination-link');
        if (!link) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        swapSectionFromUrl(link);
    });
})();
</script>
<?php include __DIR__ . '/../../src/includes/footer.php'; ?>
