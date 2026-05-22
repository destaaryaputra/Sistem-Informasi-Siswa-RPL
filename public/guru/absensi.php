<?php

declare(strict_types=1);

// Form input absensi guru dengan validasi kelas, mapel, dan notifikasi otomatis.
require_once __DIR__ . '/../../src/config/bootstrap.php';

require_role(['guru']);

$stmt = $pdo->prepare('SELECT id_guru, nama FROM tbl_guru WHERE id_user = ? LIMIT 1');
$stmt->execute([current_user()['id_user']]);
$guru = $stmt->fetch();

if (!$guru) {
    die('Profil guru tidak ditemukan.');
}

$guruId = (int) $guru['id_guru'];
$message = '';
$error = '';

$stmt = $pdo->prepare('SELECT DISTINCT k.id_kelas, k.nama_kelas FROM tbl_jadwal j JOIN tbl_kelas k ON k.id_kelas = j.id_kelas WHERE j.id_guru = ? ORDER BY k.nama_kelas');
$stmt->execute([$guruId]);
$kelasList = $stmt->fetchAll();
$allowedKelasIds = array_map(static fn(array $kelas): int => (int) $kelas['id_kelas'], $kelasList);

if (!$kelasList) {
    $error = 'Belum ada kelas yang diampu untuk guru ini.';
}

$selectedKelas = (int) (post_int('id_kelas', (int) ($_GET['id_kelas'] ?? ($kelasList[0]['id_kelas'] ?? 0))));
$selectedTanggal = post_string('tanggal', get_string('tanggal', date('Y-m-d')));
$formAction = post_string('form_action');

$mapelList = [];
if ($selectedKelas > 0 && in_array($selectedKelas, $allowedKelasIds, true)) {
    $stmt = $pdo->prepare('SELECT DISTINCT m.id_mapel, m.nama_mapel FROM tbl_jadwal j JOIN tbl_mapel m ON m.id_mapel = j.id_mapel WHERE j.id_guru = ? AND j.id_kelas = ? ORDER BY m.nama_mapel');
    $stmt->execute([$guruId, $selectedKelas]);
    $mapelList = $stmt->fetchAll();
}

$allowedMapelIds = array_map(static fn(array $mapel): int => (int) $mapel['id_mapel'], $mapelList);
$selectedMapel = (int) (post_int('id_mapel', (int) ($_GET['id_mapel'] ?? ($mapelList[0]['id_mapel'] ?? 0))));

if ($selectedKelas > 0 && !$mapelList && $error === '') {
    $error = 'Belum ada mata pelajaran terjadwal untuk kelas yang dipilih.';
}

$selectedMapelNama = '-';
foreach ($mapelList as $mapel) {
    if ((int) $mapel['id_mapel'] === $selectedMapel) {
        $selectedMapelNama = $mapel['nama_mapel'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'save_absensi') {
    // Simpan absensi per siswa untuk tanggal dan mapel yang dipilih.
    $statuses = $_POST['status'] ?? [];
    if (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif ($selectedKelas <= 0 || $selectedMapel <= 0 || !$selectedTanggal) {
        $error = 'Kelas, mata pelajaran, dan tanggal wajib diisi.';
    } elseif (!in_array($selectedKelas, $allowedKelasIds, true)) {
        $error = 'Kelas yang dipilih tidak sesuai dengan jadwal guru.';
    } elseif (!in_array($selectedMapel, $allowedMapelIds, true)) {
        $error = 'Mata pelajaran yang dipilih tidak sesuai dengan kelas dan jadwal guru.';
    } else {
        $stmtSiswaUser = $pdo->prepare('SELECT id_user, id_orangtua FROM tbl_siswa WHERE id_siswa = ? LIMIT 1');
        $stmtOrtuUser = $pdo->prepare('SELECT id_user FROM tbl_orangtua WHERE id_orangtua = ? LIMIT 1');
        $stmtNotif = $pdo->prepare('INSERT INTO tbl_notifikasi (id_user, pesan, tanggal) VALUES (?, ?, CURRENT_DATE)');
        $stmtCountAlpa = $pdo->prepare(
            "SELECT COUNT(*) FROM tbl_kehadiran
             WHERE id_siswa = ?
               AND status = 'alpa'
               AND TO_CHAR(tanggal, 'YYYY-MM') = TO_CHAR(?::DATE, 'YYYY-MM')"
        );

        $stmt = $pdo->prepare(
            'INSERT INTO tbl_kehadiran (id_siswa, id_guru, id_mapel, tanggal, status) VALUES (?, ?, ?, ?, ?) ON CONFLICT (id_siswa, tanggal, id_mapel) DO UPDATE SET id_guru = EXCLUDED.id_guru, status = EXCLUDED.status'
        );

        foreach ($statuses as $idSiswa => $status) {
            if (!in_array($status, ['hadir', 'izin', 'sakit', 'alpa'], true)) {
                continue;
            }
            $siswaId = (int) $idSiswa;
            $stmt->execute([$siswaId, $guruId, $selectedMapel, $selectedTanggal, $status]);

            if ($status === 'alpa') {
                $stmtSiswaUser->execute([$siswaId]);
                $siswaData = $stmtSiswaUser->fetch();
                $stmtCountAlpa->execute([$siswaId, $selectedTanggal]);
                $totalAlpaBulanIni = (int) $stmtCountAlpa->fetchColumn();

                if ($totalAlpaBulanIni >= 3) {
                    // Jika alpa sudah tiga kali sebulan, kirim peringatan ke siswa dan orang tua.
                    $pesan = sprintf(
                        'Peringatan: Kehadiran tidak normal, ALPA pada mata pelajaran %s. Total ALPA bulan ini sudah %d kali.',
                        $selectedMapelNama,
                        $totalAlpaBulanIni
                    );

                    if (!empty($siswaData['id_user'])) {
                        $stmtNotif->execute([(int) $siswaData['id_user'], $pesan]);
                    }
                    if (!empty($siswaData['id_orangtua'])) {
                        $stmtOrtuUser->execute([(int) $siswaData['id_orangtua']]);
                        $ortuUser = $stmtOrtuUser->fetch();
                        if (!empty($ortuUser['id_user'])) {
                            $stmtNotif->execute([(int) $ortuUser['id_user'], $pesan]);
                        }
                    }
                }
            }
        }

        $message = 'Data kehadiran berhasil disimpan.';
    }
}

$stmt = $pdo->prepare('SELECT id_siswa, nama, nis FROM tbl_siswa WHERE id_kelas = ? ORDER BY nama ASC');
$stmt->execute([$selectedKelas]);
$siswaList = $stmt->fetchAll();

$existingStatus = [];
if ($selectedKelas > 0 && $selectedMapel > 0 && $selectedTanggal) {
    $stmt = $pdo->prepare('SELECT k.id_siswa, k.status FROM tbl_kehadiran k JOIN tbl_siswa s ON s.id_siswa = k.id_siswa WHERE s.id_kelas = ? AND k.id_mapel = ? AND k.tanggal = ?');
    $stmt->execute([$selectedKelas, $selectedMapel, $selectedTanggal]);
    foreach ($stmt->fetchAll() as $row) {
        $existingStatus[(int) $row['id_siswa']] = $row['status'];
    }
}

$title = 'Input Kehadiran';
include __DIR__ . '/../../src/includes/header.php';
?>
<section class="card">
    <h2>Input Kehadiran</h2>
    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" class="grid filter-grid">
        <div>
            <label for="id_kelas">Kelas</label>
            <select id="id_kelas" name="id_kelas" required>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= e((string) $kelas['id_kelas']) ?>" <?= (int) $kelas['id_kelas'] === $selectedKelas ? 'selected' : '' ?>>
                        <?= e($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="id_mapel">Mata Pelajaran</label>
            <select id="id_mapel" name="id_mapel" required>
                <?php foreach ($mapelList as $mapel): ?>
                    <option value="<?= e((string) $mapel['id_mapel']) ?>" <?= (int) $mapel['id_mapel'] === $selectedMapel ? 'selected' : '' ?>>
                        <?= e($mapel['nama_mapel']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="tanggal">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" value="<?= e($selectedTanggal) ?>" required>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Tampilkan Daftar Siswa</button>
        </div>
    </form>

    <?php if ($selectedKelas > 0 && $selectedMapel > 0): ?>
    <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="save_absensi">
        <input type="hidden" name="id_kelas" value="<?= e((string) $selectedKelas) ?>">
        <input type="hidden" name="id_mapel" value="<?= e((string) $selectedMapel) ?>">
        <input type="hidden" name="tanggal" value="<?= e($selectedTanggal) ?>">

        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>NIS</th>
                <th>Nama</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($siswaList as $siswa): ?>
                <?php $statusDefault = $existingStatus[(int) $siswa['id_siswa']] ?? 'hadir'; ?>
                <tr>
                    <td><?= e($siswa['nis']) ?></td>
                    <td><?= e($siswa['nama']) ?></td>
                    <td>
                        <select name="status[<?= e((string) $siswa['id_siswa']) ?>]">
                            <option value="hadir" <?= $statusDefault === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="izin" <?= $statusDefault === 'izin' ? 'selected' : '' ?>>Izin</option>
                            <option value="sakit" <?= $statusDefault === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                            <option value="alpa" <?= $statusDefault === 'alpa' ? 'selected' : '' ?>>Alpa</option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="form-actions">
            <button class="btn-compact" type="submit" <?= (!$kelasList || !$mapelList) ? 'disabled' : '' ?>>Simpan Kehadiran</button>
        </div>
    </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../../src/includes/footer.php'; ?>
