<?php

declare(strict_types=1);

// Dashboard role-based yang menampilkan ringkasan sesuai jenis akun.
require_once __DIR__ . '/../src/config/bootstrap.php';

require_login();

$user = current_user();
$role = $user['role'];
$name = $user['username'];

if ($role === 'admin') {
    $stmt = $pdo->prepare('SELECT nama FROM tbl_admin WHERE id_user = ? LIMIT 1');
    $stmt->execute([$user['id_user']]);
    $row = $stmt->fetch();
    if ($row) {
        $name = $row['nama'];
    }
}

if ($role === 'guru') {
    $stmt = $pdo->prepare('SELECT id_guru, nama, jenis_kelamin FROM tbl_guru WHERE id_user = ? LIMIT 1');
    $stmt->execute([$user['id_user']]);
    $guru = $stmt->fetch();
    if ($guru) {
        $name = format_formal_name((string) $guru['nama'], (string) ($guru['jenis_kelamin'] ?? ''), (int) $guru['id_guru']);
    }
}

if ($role === 'siswa') {
    $stmt = $pdo->prepare('SELECT s.id_siswa, s.nama, s.nis, k.nama_kelas FROM tbl_siswa s LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas WHERE s.id_user = ? LIMIT 1');
    $stmt->execute([$user['id_user']]);
    $siswa = $stmt->fetch();
    if ($siswa) {
        $name = $siswa['nama'];
    }
}

if ($role === 'orangtua') {
    // Nama orang tua dipakai untuk sambutan di dashboard.
    $stmt = $pdo->prepare('SELECT id_orangtua, nama, jenis_kelamin FROM tbl_orangtua WHERE id_user = ? LIMIT 1');
    $stmt->execute([$user['id_user']]);
    $ortu = $stmt->fetch();
    if ($ortu) {
        $name = format_parent_name((string) $ortu['nama'], (string) ($ortu['jenis_kelamin'] ?? ''), (int) $ortu['id_orangtua']);
    }
}

$anakSearch = get_string('cari_anak');

if ($role === 'admin') {
    $bodyClass = 'dashboard-admin-page';
}

$title = 'Dasbor';
include __DIR__ . '/../src/includes/header.php';
?>
<div class="card hero-card dashboard-welcome">
    <h2>Selamat datang, <?= e($name) ?></h2>
    <p class="page-lead">Peran aktif: <span class="badge"><?= e(strtoupper($role)) ?></span></p>
    <?php if ($role === 'siswa' && isset($siswa)): ?>
        <div class="siswa-greeting-info">
            <span>NIS: <?= e($siswa['nis'] ?? '-') ?></span>
            <span>Kelas: <?= e($siswa['nama_kelas'] ?? '-') ?></span>
        </div>
    <?php endif; ?>
    <div class="action-links">
        <?php if ($role === 'admin'): ?>
            <a class="action-link" href="<?= e(url('admin/pengguna.php')) ?>">Kelola pengguna</a>
            <a class="action-link" href="<?= e(url('admin/akademik.php')) ?>">Atur data akademik</a>
        <?php endif; ?>
        <?php if ($role === 'guru'): ?>
            <a class="action-link" href="<?= e(url('guru/absensi.php')) ?>">Isi absensi</a>
            <a class="action-link" href="<?= e(url('guru/nilai.php')) ?>">Isi nilai</a>
        <?php endif; ?>
        <a class="action-link" href="<?= e(url('laporan.php')) ?>">Buka laporan</a>
    </div>
</div>

<div class="content-panels">

<?php if ($role === 'admin'): ?>
    <?php
    $adminStatRow = $pdo->query(
        'SELECT
            (SELECT COUNT(*) FROM tbl_users) AS total_pengguna,
            (SELECT COUNT(*) FROM tbl_guru) AS total_guru,
            (SELECT COUNT(*) FROM tbl_orangtua) AS total_orangtua,
            (SELECT COUNT(*) FROM tbl_siswa) AS total_siswa,
            (SELECT COUNT(*) FROM tbl_kelas) AS total_kelas,
            (SELECT COUNT(*) FROM tbl_mapel) AS total_mapel,
            (SELECT COUNT(*) FROM tbl_jadwal) AS total_jadwal'
    )->fetch() ?: [];

    $stats = [
        [
            'label' => 'Total Pengguna',
            'value' => (int) ($adminStatRow['total_pengguna'] ?? 0),
            'target' => url('admin/pengguna.php#daftar-user'),
        ],
        [
            'label' => 'Total Guru',
            'value' => (int) ($adminStatRow['total_guru'] ?? 0),
            'target' => url('admin/pengguna.php#daftar-user'),
        ],
        [
            'label' => 'Total Orang Tua',
            'value' => (int) ($adminStatRow['total_orangtua'] ?? 0),
            'target' => url('admin/pengguna.php#data-orangtua'),
        ],
        [
            'label' => 'Total Siswa',
            'value' => (int) ($adminStatRow['total_siswa'] ?? 0),
            'target' => url('admin/pengguna.php#data-siswa'),
        ],
        [
            'label' => 'Total Kelas',
            'value' => (int) ($adminStatRow['total_kelas'] ?? 0),
            'target' => url('admin/akademik.php#daftar-kelas'),
        ],
        [
            'label' => 'Total Mata Pelajaran',
            'value' => (int) ($adminStatRow['total_mapel'] ?? 0),
            'target' => url('admin/akademik.php#daftar-mapel'),
        ],
        [
            'label' => 'Total Jadwal',
            'value' => (int) ($adminStatRow['total_jadwal'] ?? 0),
            'target' => url('admin/akademik.php#daftar-jadwal'),
        ],
    ];
    ?>
    <section class="card panel-span-12">
        <h2>Panel Kontrol Administrator</h2>
        <div class="grid">
            <?php foreach ($stats as $stat): ?>
                <a class="stat stat-link" href="<?= e((string) $stat['target']) ?>">
                    <div><?= e((string) $stat['label']) ?></div>
                    <div class="value"><?= e((string) $stat['value']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($role === 'guru' && isset($guru['id_guru'])): ?>
    <?php
    $idGuru = (int) $guru['id_guru'];

    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM tbl_kehadiran WHERE id_guru = ? AND tanggal = CURRENT_DATE) AS absen_hari_ini,
            (SELECT COUNT(*) FROM tbl_nilai WHERE id_guru = ?) AS total_nilai,
            (SELECT COUNT(DISTINCT id_kelas) FROM tbl_jadwal WHERE id_guru = ?) AS kelas_diampu'
    );
    $stmt->execute([$idGuru, $idGuru, $idGuru]);
    $guruStats = $stmt->fetch() ?: [];

    $absenHariIni = (int) ($guruStats['absen_hari_ini'] ?? 0);
    $totalNilai = (int) ($guruStats['total_nilai'] ?? 0);
    $kelasDiampu = (int) ($guruStats['kelas_diampu'] ?? 0);
    ?>
    <section class="card panel-span-12">
        <h2>Ringkasan Guru</h2>
        <div class="grid">
            <div class="stat">
                <div>Absensi Hari Ini</div>
                <div class="value"><?= e((string) $absenHariIni) ?></div>
            </div>
            <div class="stat">
                <div>Nilai Tersimpan</div>
                <div class="value"><?= e((string) $totalNilai) ?></div>
            </div>
            <div class="stat">
                <div>Kelas yang Diampu</div>
                <div class="value"><?= e((string) $kelasDiampu) ?></div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($role === 'siswa' && isset($siswa['id_siswa'])): ?>
    <?php
    $idSiswa = (int) $siswa['id_siswa'];

    $stmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
            COUNT(*) AS total_absen
         FROM tbl_kehadiran
         WHERE id_siswa = ?"
    );
    $stmt->execute([$idSiswa]);
    $kehadiranStats = $stmt->fetch() ?: [];
    $hadir = (int) ($kehadiranStats['hadir'] ?? 0);
    $totalAbsen = (int) ($kehadiranStats['total_absen'] ?? 0);

    $stmt = $pdo->prepare('SELECT AVG(skor) FROM tbl_nilai WHERE id_siswa = ?');
    $stmt->execute([$idSiswa]);
    $rataNilai = (float) ($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare('SELECT m.nama_mapel, n.jenis_penilaian, n.skor, n.periode FROM tbl_nilai n JOIN tbl_mapel m ON m.id_mapel = n.id_mapel WHERE n.id_siswa = ? ORDER BY n.jenis_penilaian, n.id_nilai DESC LIMIT 20');
    $stmt->execute([$idSiswa]);
    $nilaiTerbaru = $stmt->fetchAll();
    
    // Kelompokkan per jenis_penilaian, ambil yang terbaru
    $nilaiByType = [];
    foreach ($nilaiTerbaru as $n) {
        $key = $n['jenis_penilaian'];
        if (!isset($nilaiByType[$key])) {
            $nilaiByType[$key] = [];
        }
        $nilaiByType[$key][] = $n;
    }
    ?>
    <section class="card panel-span-12">
        <h2>Nilai Terbaru</h2>
        <div class="table-wrap siswa-nilai-wrap">
        <table>
            <thead>
            <tr>
                <th>Jenis</th>
                <th>Mata Pelajaran</th>
                <th>Periode</th>
                <th>Skor</th>
            </tr>
            </thead>
            <tbody>
            <?php 
            $typeLabels = ['tugas' => 'Tugas', 'kuis' => 'Kuis', 'uts' => 'UTS', 'uas' => 'UAS'];
            foreach ($typeLabels as $type => $label): 
                if (!isset($nilaiByType[$type])) continue;
                $count = 0;
                foreach ($nilaiByType[$type] as $n):
                    if ($count >= 3) break; // Tampilkan max 3 per jenis
                    $count++;
            ?>
                <tr>
                    <td><?= $count === 1 ? e($label) : '' ?></td>
                    <td><?= e($n['nama_mapel']) ?></td>
                    <td><?= e($n['periode']) ?></td>
                    <td><?= e((string) $n['skor']) ?></td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($role === 'orangtua' && isset($ortu['id_orangtua'])): ?>
    <?php
    // Ambil nama semua anak yang terhubung ke akun orang tua ini.
    $stmt = $pdo->prepare('SELECT nama FROM tbl_siswa WHERE id_orangtua = ? ORDER BY nama ASC');
    $stmt->execute([(int) $ortu['id_orangtua']]);
    $namaAnakRows = $stmt->fetchAll();
    $namaAnakList = array_map(static fn(array $row): string => (string) $row['nama'], $namaAnakRows);

    $sqlAnak = 'SELECT s.id_siswa, s.nama, s.nis, k.nama_kelas FROM tbl_siswa s LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas WHERE s.id_orangtua = ?';
    $paramsAnak = [(int) $ortu['id_orangtua']];

    if ($anakSearch !== '') {
        $sqlAnak .= ' AND (s.nama LIKE ? OR s.nis LIKE ?)';
        $paramsAnak[] = '%' . $anakSearch . '%';
        $paramsAnak[] = '%' . $anakSearch . '%';
    }

    $sqlAnak .= ' ORDER BY s.nama ASC';

    $stmt = $pdo->prepare($sqlAnak);
    $stmt->execute($paramsAnak);
    $anakList = $stmt->fetchAll();

    $anakStats = [];
    $nilaiAnak = [];
    if ($anakList) {
        $anakIds = array_map(static fn(array $anak): int => (int) $anak['id_siswa'], $anakList);
        $anakIds = array_values(array_unique($anakIds));

        if ($anakIds) {
            $placeholders = implode(',', array_fill(0, count($anakIds), '?'));

            $stmt = $pdo->prepare(
                "SELECT
                    id_siswa,
                    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
                    COUNT(*) AS total_absen
                 FROM tbl_kehadiran
                 WHERE id_siswa IN ($placeholders)
                 GROUP BY id_siswa"
            );
            $stmt->execute($anakIds);
            foreach ($stmt->fetchAll() as $row) {
                $anakStats[(int) $row['id_siswa']] = [
                    'hadir' => (int) ($row['hadir'] ?? 0),
                    'total_absen' => (int) ($row['total_absen'] ?? 0),
                ];
            }

            $stmt = $pdo->prepare(
                "SELECT id_siswa, AVG(skor) AS rata_nilai
                 FROM tbl_nilai
                 WHERE id_siswa IN ($placeholders)
                 GROUP BY id_siswa"
            );
            $stmt->execute($anakIds);
            foreach ($stmt->fetchAll() as $row) {
                $nilaiAnak[(int) $row['id_siswa']] = (float) ($row['rata_nilai'] ?? 0);
            }
        }
    }
    ?>
    <section class="card panel-span-12">
        <h2>Pemantauan Anak</h2>
        <?php if ($namaAnakList): ?>
            <p class="page-lead">Selamat datang. Anda masuk sebagai orang tua dari siswa berikut: <strong><?= e(implode(', ', $namaAnakList)) ?></strong>.</p>
        <?php else: ?>
            <p class="page-lead">Selamat datang. Akun orang tua ini belum terhubung ke data siswa.</p>
        <?php endif; ?>
        <form method="get" class="grid filter-grid" id="anak-search-form">
            <div>
                <label for="cari_anak">Cari anak</label>
                <input id="cari_anak" name="cari_anak" value="<?= e($anakSearch) ?>" placeholder="Cari nama atau NIS" autocomplete="off">
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit">Cari</button>
            </div>
        </form>
        <?php if (!$anakList): ?>
            <p>Belum ada data anak yang terhubung dengan akun ini.</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nama Anak</th>
                    <th>NIS</th>
                    <th>Kelas</th>
                    <th>Hadir</th>
                    <th>Total Absensi</th>
                    <th>Rata-Rata Nilai</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($anakList as $anak): ?>
                    <?php
                    $idAnak = (int) $anak['id_siswa'];
                    $hadirAnak = (int) ($anakStats[$idAnak]['hadir'] ?? 0);
                    $totalAbsenAnak = (int) ($anakStats[$idAnak]['total_absen'] ?? 0);
                    $avgAnak = (float) ($nilaiAnak[$idAnak] ?? 0);
                    ?>
                    <tr>
                        <td><?= e($anak['nama']) ?></td>
                        <td><?= e($anak['nis']) ?></td>
                        <td><?= e($anak['nama_kelas'] ?? '-') ?></td>
                        <td><?= e((string) $hadirAnak) ?></td>
                        <td><?= e((string) $totalAbsenAnak) ?></td>
                        <td><?= e(number_format($avgAnak, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

</div>

<?php if ($role === 'orangtua'): ?>
<script>
(() => {
    const form = document.getElementById('anak-search-form');
    const input = document.getElementById('cari_anak');
    if (!form || !input) {
        return;
    }

    let timer = null;
    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            if (input.value.trim() === '') {
                const url = new URL(window.location.href);
                url.searchParams.delete('cari_anak');
                window.location.href = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
                return;
            }

            form.submit();
        }, 250);
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../src/includes/footer.php'; ?>
