<?php

declare(strict_types=1);

// Input nilai guru dengan pengiriman notifikasi jika skor di bawah KKM.
require_once __DIR__ . '/../../app/config/bootstrap.php';

require_role(['guru']);

$stmt = $pdo->prepare('SELECT id_guru, nama, jenis_kelamin FROM tbl_guru WHERE id_user = ? LIMIT 1');
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

$stmt = $pdo->prepare('SELECT DISTINCT m.nama_mapel FROM tbl_jadwal j JOIN tbl_mapel m ON m.id_mapel = j.id_mapel WHERE j.id_guru = ? ORDER BY m.nama_mapel');
$stmt->execute([$guruId]);
$mapelAjarRows = $stmt->fetchAll();
$mapelAjarList = array_map(static fn(array $row): string => (string) $row['nama_mapel'], $mapelAjarRows);

$stmt = $pdo->prepare('SELECT k.id_kelas, k.nama_kelas, m.id_mapel, m.nama_mapel FROM tbl_jadwal j JOIN tbl_kelas k ON k.id_kelas = j.id_kelas JOIN tbl_mapel m ON m.id_mapel = j.id_mapel WHERE j.id_guru = ? ORDER BY k.nama_kelas, m.nama_mapel');
$stmt->execute([$guruId]);
$jadwalAjarRows = $stmt->fetchAll();

$mapelPerKelas = [];
$mapelPerKelasById = [];
foreach ($jadwalAjarRows as $jadwalRow) {
    $idKelas = (int) ($jadwalRow['id_kelas'] ?? 0);
    $idMapel = (int) ($jadwalRow['id_mapel'] ?? 0);
    $namaKelas = (string) ($jadwalRow['nama_kelas'] ?? '-');
    $namaMapel = (string) ($jadwalRow['nama_mapel'] ?? '-');

    if (!isset($mapelPerKelas[$namaKelas])) {
        $mapelPerKelas[$namaKelas] = [];
    }

    if (!in_array($namaMapel, $mapelPerKelas[$namaKelas], true)) {
        $mapelPerKelas[$namaKelas][] = $namaMapel;
    }

    if ($idKelas > 0 && $idMapel > 0) {
        if (!isset($mapelPerKelasById[$idKelas])) {
            $mapelPerKelasById[$idKelas] = [];
        }

        $exists = false;
        foreach ($mapelPerKelasById[$idKelas] as $mapelRow) {
            if ((int) $mapelRow['id_mapel'] === $idMapel) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $mapelPerKelasById[$idKelas][] = [
                'id_mapel' => $idMapel,
                'nama_mapel' => $namaMapel,
            ];
        }
    }
}

$totalKelasAjar = count($mapelPerKelas);
$allowedKelasIds = array_map(static fn(array $kelas): int => (int) $kelas['id_kelas'], $kelasList);

if (!$kelasList) {
    $error = 'Belum ada kelas yang diampu untuk guru ini.';
}

$selectedKelas = (int) (post_int('id_kelas', (int) ($_GET['id_kelas'] ?? ($kelasList[0]['id_kelas'] ?? 0))));
$jenis = (string) post_string('jenis_penilaian', get_string('jenis_penilaian', 'tugas'));

$baseYear = (int) date('Y');
$periodeOptions = [
    $baseYear . ' Genap',
    $baseYear . ' Ganjil',
    ($baseYear - 1) . ' Genap',
    ($baseYear - 1) . ' Ganjil',
];

$periode = post_string('periode', get_string('periode', ($periodeOptions[0] ?? (date('Y') . ' Genap'))));
if ($periode !== '' && !in_array($periode, $periodeOptions, true)) {
    array_unshift($periodeOptions, $periode);
}

$mapelList = $mapelPerKelasById[$selectedKelas] ?? [];

if (!$mapelList && $error === '') {
    $error = 'Belum ada mata pelajaran terjadwal untuk kelas yang dipilih.';
}

$allowedMapelIds = array_map(static fn(array $mapel): int => (int) $mapel['id_mapel'], $mapelList);
$selectedMapel = (int) (post_int('id_mapel', (int) ($_GET['id_mapel'] ?? ($mapelList[0]['id_mapel'] ?? 0))));
$mapelIdValid = in_array($selectedMapel, $allowedMapelIds, true);
if (!$mapelIdValid) {
    $selectedMapel = (int) ($mapelList[0]['id_mapel'] ?? 0);
}
$periodHint = 'Pilih periode semester, misalnya 2026 Genap atau 2026 Ganjil.';

$stmt = $pdo->prepare('SELECT id_siswa, nama, nis FROM tbl_siswa WHERE id_kelas = ? ORDER BY nama ASC');
$stmt->execute([$selectedKelas]);
$siswaList = $stmt->fetchAll();
$allowedSiswaIds = array_map(static fn(array $siswa): int => (int) $siswa['id_siswa'], $siswaList);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scores = $_POST['skor'] ?? [];

    if (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif ($selectedKelas <= 0 || $selectedMapel <= 0 || $periode === '') {
        $error = 'Kelas, mata pelajaran, dan periode wajib diisi.';
    } elseif (!in_array($selectedKelas, $allowedKelasIds, true)) {
        $error = 'Kelas yang dipilih tidak sesuai dengan jadwal guru.';
    } elseif (!in_array($selectedMapel, $allowedMapelIds, true)) {
        $error = 'Mata pelajaran yang dipilih tidak sesuai dengan kelas dan jadwal guru.';
    } elseif (!in_array($periode, $periodeOptions, true)) {
        $error = 'Periode tidak valid, silakan pilih dari daftar periode.';
    } else {
        $savedCount = 0;
        $stmtUpsert = $pdo->prepare(
            'INSERT INTO tbl_nilai (id_siswa, id_mapel, id_guru, jenis_penilaian, skor, periode) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE skor = VALUES(skor), id_guru = VALUES(id_guru)'
        );

        $stmtSiswaUser = $pdo->prepare('SELECT id_user, id_orangtua FROM tbl_siswa WHERE id_siswa = ? LIMIT 1');
        $stmtOrtuUser = $pdo->prepare('SELECT id_user FROM tbl_orangtua WHERE id_orangtua = ? LIMIT 1');
        $stmtNotif = $pdo->prepare('INSERT INTO tbl_notifikasi (id_user, pesan, tanggal) VALUES (?, ?, CURDATE())');

        foreach ($scores as $idSiswa => $skor) {
            if ($skor === '') {
                continue;
            }

            if (!in_array((int) $idSiswa, $allowedSiswaIds, true)) {
                continue;
            }

            $nilai = (float) $skor;
            if ($nilai < 0 || $nilai > 100) {
                continue;
            }

            $stmtUpsert->execute([(int) $idSiswa, $selectedMapel, $guruId, $jenis, $nilai, $periode]);
            $savedCount++;

            if ($nilai < 75) {
                $stmtSiswaUser->execute([(int) $idSiswa]);
                $siswaData = $stmtSiswaUser->fetch();

                $mapelNama = '-';
                foreach ($mapelList as $m) {
                    if ((int) $m['id_mapel'] === $selectedMapel) {
                        $mapelNama = (string) $m['nama_mapel'];
                        break;
                    }
                }

                $pesan = sprintf('Peringatan: Nilai %s (%s) = %.2f berada di bawah KKM.', $mapelNama, strtoupper($jenis), $nilai);

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

        if ($savedCount > 0) {
            $message = sprintf('Data nilai berhasil disimpan (%d siswa).', $savedCount);
        } else {
            $error = 'Tidak ada nilai yang disimpan. Isi minimal satu skor valid (0-100).';
        }
    }
}

$nilaiExisting = [];
if ($selectedKelas > 0 && $selectedMapel > 0 && $periode !== '') {
    $stmt = $pdo->prepare('SELECT n.id_siswa, n.skor FROM tbl_nilai n JOIN tbl_siswa s ON s.id_siswa = n.id_siswa WHERE s.id_kelas = ? AND n.id_mapel = ? AND n.jenis_penilaian = ? AND n.periode = ?');
    $stmt->execute([$selectedKelas, $selectedMapel, $jenis, $periode]);
    foreach ($stmt->fetchAll() as $row) {
        $nilaiExisting[(int) $row['id_siswa']] = $row['skor'];
    }
}

$title = 'Input Nilai';
include __DIR__ . '/../../app/includes/header.php';
?>
<section class="card">
    <h2>Input Nilai Siswa</h2>
    <div class="info-box">
        <p><strong>Panduan cepat:</strong></p>
        <p><strong>Mata pelajaran yang Anda ampu:</strong> <?= $mapelAjarList ? e(implode(', ', $mapelAjarList)) : '-' ?></p>
        <p><strong>Total mata pelajaran yang diampu:</strong> <?= e((string) count($mapelAjarList)) ?></p>
        <p><strong>Kelas diampu:</strong> <?= e((string) $totalKelasAjar) ?> kelas</p>
        <?php if ($mapelPerKelas): ?>
            <p><strong>Rincian mata pelajaran per kelas:</strong></p>
            <ul class="teaching-scope-list">
                <?php foreach ($mapelPerKelas as $namaKelas => $daftarMapel): ?>
                    <li><strong><?= e($namaKelas) ?>:</strong> <?= e(implode(', ', $daftarMapel)) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p>Sistem menyimpan nilai berdasarkan kombinasi siswa + mata pelajaran + jenis penilaian + periode.</p>
        <p>Periode menggunakan format sederhana, misalnya: 2026 Genap atau 2026 Ganjil.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="grid nilai-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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
            <select id="id_mapel" name="id_mapel" required <?= !$mapelList ? 'disabled' : '' ?>>
                <?php foreach ($mapelList as $mapel): ?>
                    <option value="<?= e((string) $mapel['id_mapel']) ?>" <?= (int) $mapel['id_mapel'] === $selectedMapel ? 'selected' : '' ?>>
                        <?= e($mapel['nama_mapel']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="jenis_penilaian">Jenis Penilaian</label>
            <select id="jenis_penilaian" name="jenis_penilaian" required>
                <option value="tugas" <?= $jenis === 'tugas' ? 'selected' : '' ?>>Tugas</option>
                <option value="kuis" <?= $jenis === 'kuis' ? 'selected' : '' ?>>Kuis</option>
                <option value="uts" <?= $jenis === 'uts' ? 'selected' : '' ?>>UTS</option>
                <option value="uas" <?= $jenis === 'uas' ? 'selected' : '' ?>>UAS</option>
            </select>
        </div>

        <div>
            <label for="periode">Periode</label>
            <select id="periode" name="periode" required>
                <?php foreach ($periodeOptions as $periodeOption): ?>
                    <option value="<?= e($periodeOption) ?>" <?= $periodeOption === $periode ? 'selected' : '' ?>><?= e($periodeOption) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-hint" id="periode-hint"><?= e($periodHint) ?></p>
        </div>

        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>NIS</th>
                <th>Nama</th>
                <th>Skor (0-100)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($siswaList as $siswa): ?>
                <tr>
                    <td><?= e($siswa['nis']) ?></td>
                    <td><?= e($siswa['nama']) ?></td>
                    <td>
                        <input type="number" min="0" max="100" step="0.01" name="skor[<?= e((string) $siswa['id_siswa']) ?>]" value="<?= e((string) ($nilaiExisting[(int) $siswa['id_siswa']] ?? '')) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="nilai-actions">
            <button class="btn-compact" type="submit" <?= !$kelasList || !$mapelList ? 'disabled' : '' ?>>Simpan Nilai</button>
        </div>
    </form>
</section>
<script>
(() => {
    const jenisSelect = document.getElementById('jenis_penilaian');
    const hint = document.getElementById('periode-hint');

    if (!jenisSelect || !hint) {
        return;
    }

    const updateHint = () => {
        hint.textContent = 'Pilih periode semester, misalnya 2026 Genap atau 2026 Ganjil.';
    };

    jenisSelect.addEventListener('change', updateHint);
    updateHint();
})();
</script>
<?php include __DIR__ . '/../../app/includes/footer.php'; ?>
