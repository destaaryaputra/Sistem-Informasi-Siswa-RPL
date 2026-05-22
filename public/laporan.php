<?php

declare(strict_types=1);

// Halaman laporan untuk rekap absensi, nilai, detail ketidakhadiran, dan notifikasi.
require_once __DIR__ . '/../src/config/bootstrap.php';

require_login();

$user = current_user();
$role = (string) ($user['role'] ?? '');

function report_scope_from_user(PDO $pdo, array $user): array
{
    $scope = [
        'role' => (string) ($user['role'] ?? ''),
        'id_guru' => 0,
        'id_siswa' => 0,
        'id_orangtua' => 0,
    ];

    $userId = (int) ($user['id_user'] ?? 0);
    if ($userId <= 0) {
        return $scope;
    }

    if ($scope['role'] === 'guru') {
        $stmt = $pdo->prepare('SELECT id_guru FROM tbl_guru WHERE id_user = ? LIMIT 1');
        $stmt->execute([$userId]);
        $scope['id_guru'] = (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($scope['role'] === 'siswa') {
        $stmt = $pdo->prepare('SELECT id_siswa FROM tbl_siswa WHERE id_user = ? LIMIT 1');
        $stmt->execute([$userId]);
        $scope['id_siswa'] = (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($scope['role'] === 'orangtua') {
        $stmt = $pdo->prepare('SELECT id_orangtua FROM tbl_orangtua WHERE id_user = ? LIMIT 1');
        $stmt->execute([$userId]);
        $scope['id_orangtua'] = (int) ($stmt->fetchColumn() ?: 0);
    }

    return $scope;
}

function report_apply_scope_filter(string $sql, array &$params, array $scope, string $teacherAlias, bool $allowNullTeacher = false): string
{
    if ($scope['role'] === 'guru') {
        if ($allowNullTeacher) {
            $sql .= ' AND (' . $teacherAlias . '.id_guru = ? OR ' . $teacherAlias . '.id_guru IS NULL)';
        } else {
            $sql .= ' AND ' . $teacherAlias . '.id_guru = ?';
        }
        $params[] = (int) ($scope['id_guru'] ?? 0);
    }

    if ($scope['role'] === 'siswa') {
        $sql .= ' AND s.id_siswa = ?';
        $params[] = (int) ($scope['id_siswa'] ?? 0);
    }

    if ($scope['role'] === 'orangtua') {
        $sql .= ' AND s.id_orangtua = ?';
        $params[] = (int) ($scope['id_orangtua'] ?? 0);
    }

    return $sql;
}

$scope = report_scope_from_user($pdo, $user);
$idSiswaScope = (int) ($scope['id_siswa'] ?? 0);

// Filter laporan dipakai admin dan guru; role lain dibatasi sesuai data miliknya.
$filterKelas = get_int('id_kelas', 0);
$filterMapel = get_int('id_mapel', 0);
$filterPeriode = get_string('periode');
$printMode = get_string('print') === '1';
$exportMode = get_string('export') === 'excel';
$printEmbedded = $printMode && (get_string('embed') === '1');
if ($printMode) {
    $bodyClass = 'report-print-mode';
}

$kelasList = $pdo->query('SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC')->fetchAll();
$mapelList = $pdo->query('SELECT id_mapel, nama_mapel FROM tbl_mapel ORDER BY nama_mapel ASC')->fetchAll();
$periodeList = $pdo->query('SELECT DISTINCT periode FROM tbl_nilai ORDER BY periode DESC')->fetchAll(PDO::FETCH_COLUMN);

$sqlAbsensi = "SELECT s.nama, s.nis, k.nama_kelas,
SUM(CASE WHEN kh.status='hadir' THEN 1 ELSE 0 END) AS hadir,
SUM(CASE WHEN kh.status='izin' THEN 1 ELSE 0 END) AS izin,
SUM(CASE WHEN kh.status='sakit' THEN 1 ELSE 0 END) AS sakit,
SUM(CASE WHEN kh.status='alpa' THEN 1 ELSE 0 END) AS alpa,
COUNT(kh.id_kehadiran) AS total
FROM tbl_siswa s
LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas
LEFT JOIN tbl_kehadiran kh ON kh.id_siswa = s.id_siswa
WHERE 1=1";
$paramsAbsensi = [];

$sqlNilai = "SELECT s.nama, s.nis, k.nama_kelas, m.nama_mapel,
AVG(n.skor) AS rata_nilai
FROM tbl_siswa s
LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas
LEFT JOIN tbl_nilai n ON n.id_siswa = s.id_siswa
LEFT JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
WHERE 1=1";
$paramsNilai = [];

if ($filterPeriode !== '') {
    $sqlNilai .= ' AND n.periode = ?';
    $paramsNilai[] = $filterPeriode;
}

$sqlAbsensi = report_apply_scope_filter($sqlAbsensi, $paramsAbsensi, $scope, 'kh', true);
$sqlNilai = report_apply_scope_filter($sqlNilai, $paramsNilai, $scope, 'n', true);

if ($filterKelas > 0 && in_array($role, ['admin', 'guru'], true)) {
    $sqlAbsensi .= ' AND s.id_kelas = ?';
    $paramsAbsensi[] = $filterKelas;

    $sqlNilai .= ' AND s.id_kelas = ?';
    $paramsNilai[] = $filterKelas;
}

if ($filterMapel > 0 && in_array($role, ['admin', 'guru'], true)) {
    $sqlAbsensi .= ' AND kh.id_mapel = ?';
    $paramsAbsensi[] = $filterMapel;
}

if ($filterMapel > 0 && in_array($role, ['admin', 'guru'], true)) {
    $sqlNilai .= ' AND n.id_mapel = ?';
    $paramsNilai[] = $filterMapel;
}

$sqlAbsensi .= ' GROUP BY s.id_siswa, s.nama, s.nis, k.nama_kelas ORDER BY s.nama ASC';
$sqlNilai .= ' GROUP BY s.id_siswa, s.nama, s.nis, k.nama_kelas, m.nama_mapel ORDER BY s.nama ASC';

$stmt = $pdo->prepare($sqlAbsensi);
$stmt->execute($paramsAbsensi);
$absensiRows = $stmt->fetchAll();

$stmt = $pdo->prepare($sqlNilai);
$stmt->execute($paramsNilai);
$nilaiRows = $stmt->fetchAll();

$nilaiSiswaMatrix = [];

if ($role === 'siswa' && $idSiswaScope > 0) {
    $sqlNilaiSiswa = 'SELECT m.nama_mapel, n.jenis_penilaian, n.skor
    FROM tbl_nilai n
    JOIN tbl_mapel m ON m.id_mapel = n.id_mapel
    WHERE n.id_siswa = ?';
    $paramsNilaiSiswa = [$idSiswaScope];

    if ($filterPeriode !== '') {
        $sqlNilaiSiswa .= ' AND n.periode = ?';
        $paramsNilaiSiswa[] = $filterPeriode;
    }

    $sqlNilaiSiswa .= ' ORDER BY m.nama_mapel ASC, n.created_at ASC, n.id_nilai ASC';

    $stmt = $pdo->prepare($sqlNilaiSiswa);
    $stmt->execute($paramsNilaiSiswa);
    $nilaiSiswaRows = $stmt->fetchAll();

    foreach ($nilaiSiswaRows as $nilaiRow) {
        $namaMapel = (string) ($nilaiRow['nama_mapel'] ?? '-');

        if (!isset($nilaiSiswaMatrix[$namaMapel])) {
            $nilaiSiswaMatrix[$namaMapel] = [
                'tugas' => [],
                'uts' => null,
                'uas' => null,
            ];
        }

        $jenis = (string) ($nilaiRow['jenis_penilaian'] ?? '');
        $skor = (float) ($nilaiRow['skor'] ?? 0);

        if ($jenis === 'tugas') {
            $nilaiSiswaMatrix[$namaMapel]['tugas'][] = $skor;
            continue;
        }

        if ($jenis === 'uts') {
            $nilaiSiswaMatrix[$namaMapel]['uts'] = $skor;
            continue;
        }

        if ($jenis === 'uas') {
            $nilaiSiswaMatrix[$namaMapel]['uas'] = $skor;
        }
    }
}

$sqlAbsensiDetail = "SELECT s.nama, s.nis, k.nama_kelas, m.nama_mapel, kh.tanggal, kh.status
FROM tbl_kehadiran kh
JOIN tbl_siswa s ON s.id_siswa = kh.id_siswa
LEFT JOIN tbl_kelas k ON k.id_kelas = s.id_kelas
LEFT JOIN tbl_mapel m ON m.id_mapel = kh.id_mapel
WHERE kh.status IN ('izin', 'sakit', 'alpa')";
$paramsAbsensiDetail = [];

$sqlAbsensiDetail = report_apply_scope_filter($sqlAbsensiDetail, $paramsAbsensiDetail, $scope, 'kh', false);

if ($filterKelas > 0 && in_array($role, ['admin', 'guru'], true)) {
    $sqlAbsensiDetail .= ' AND s.id_kelas = ?';
    $paramsAbsensiDetail[] = $filterKelas;
}

if ($filterMapel > 0 && in_array($role, ['admin', 'guru'], true)) {
    $sqlAbsensiDetail .= ' AND kh.id_mapel = ?';
    $paramsAbsensiDetail[] = $filterMapel;
}

$sqlAbsensiDetail .= ' ORDER BY kh.tanggal DESC, s.nama ASC LIMIT 200';

$stmt = $pdo->prepare($sqlAbsensiDetail);
$stmt->execute($paramsAbsensiDetail);
$absensiDetailRows = $stmt->fetchAll();

$notifs = [];
if (in_array($role, ['siswa', 'orangtua'], true)) {
    $stmt = $pdo->prepare('SELECT pesan, tanggal FROM tbl_notifikasi WHERE id_user = ? ORDER BY id_notifikasi DESC LIMIT 20');
    $stmt->execute([$user['id_user']]);
    $notifs = $stmt->fetchAll();
}

if ($exportMode) {
    $filename = 'laporan-akademik-' . date('Ymd-His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Akademik</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        h1, h2 { margin: 0 0 8px; }
        p { margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 16px; }
        th, td { border: 1px solid #777; padding: 6px 8px; text-align: left; }
        th { background: #e8f1fb; }
    </style>
</head>
<body>
    <h1>Laporan Akademik</h1>
    <p>Dibuat: <?= e(date('Y-m-d H:i:s')) ?></p>
    <p>Filter: Kelas=<?= e((string) ($filterKelas > 0 ? $filterKelas : 'Semua')) ?> | Mapel=<?= e((string) ($filterMapel > 0 ? $filterMapel : 'Semua')) ?> | Periode=<?= e($filterPeriode !== '' ? $filterPeriode : 'Semua') ?></p>

    <h2>Rekap Kehadiran</h2>
    <table>
        <thead>
        <tr>
            <th>Nama</th>
            <th>NIS</th>
            <th>Kelas</th>
            <th>Hadir</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpa</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($absensiRows as $row): ?>
            <tr>
                <td><?= e((string) $row['nama']) ?></td>
                <td><?= e((string) $row['nis']) ?></td>
                <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                <td><?= e((string) $row['hadir']) ?></td>
                <td><?= e((string) $row['izin']) ?></td>
                <td><?= e((string) $row['sakit']) ?></td>
                <td><?= e((string) $row['alpa']) ?></td>
                <td><?= e((string) $row['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Detail Ketidakhadiran</h2>
    <table>
        <thead>
        <tr>
            <th>Tanggal</th>
            <th>Nama</th>
            <th>NIS</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($absensiDetailRows as $row): ?>
            <tr>
                <td><?= e((string) $row['tanggal']) ?></td>
                <td><?= e((string) $row['nama']) ?></td>
                <td><?= e((string) $row['nis']) ?></td>
                <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                <td><?= e((string) ($row['nama_mapel'] ?? '-')) ?></td>
                <td><?= e((string) ucfirst((string) $row['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Rekap Nilai</h2>
    <?php if ($role === 'siswa'): ?>
        <table>
            <thead>
            <tr>
                <th>Mata Pelajaran</th>
                <th>Tugas</th>
                <th>UTS</th>
                <th>UAS</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($nilaiSiswaMatrix as $namaMapel => $nilaiMapel): ?>
                <tr>
                    <td><?= e((string) $namaMapel) ?></td>
                    <?php
                    $tugasItems = [];
                    foreach ($nilaiMapel['tugas'] as $index => $nilaiTugas) {
                        $tugasItems[] = 'Tugas ' . ((string) ($index + 1)) . ': ' . number_format((float) $nilaiTugas, 2);
                    }
                    ?>
                    <td><?= $tugasItems ? e(implode(' | ', $tugasItems)) : '-' ?></td>
                    <td><?= $nilaiMapel['uts'] !== null ? e(number_format((float) $nilaiMapel['uts'], 2)) : '-' ?></td>
                    <td><?= $nilaiMapel['uas'] !== null ? e(number_format((float) $nilaiMapel['uas'], 2)) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Nilai</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($nilaiRows as $row): ?>
                <tr>
                    <td><?= e((string) $row['nama']) ?></td>
                    <td><?= e((string) $row['nis']) ?></td>
                    <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                    <td><?= e((string) ($row['nama_mapel'] ?? '-')) ?></td>
                    <td><?= $row['rata_nilai'] !== null ? e(number_format((float) $row['rata_nilai'], 2)) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (in_array($role, ['siswa', 'orangtua'], true)): ?>
    <h2>Notifikasi Saya</h2>
    <table>
        <thead>
        <tr>
            <th>Tanggal</th>
            <th>Pesan</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($notifs as $notif): ?>
            <tr>
                <td><?= e((string) $notif['tanggal']) ?></td>
                <td><?= e((string) $notif['pesan']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
<?php
    exit;
}

if ($printMode) {
    $returnUrl = url('laporan.php?' . http_build_query(array_filter([
        'id_kelas' => $filterKelas ?: null,
        'id_mapel' => $filterMapel ?: null,
        'periode' => $filterPeriode !== '' ? $filterPeriode : null,
    ])));
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        html, body { background: #ffffff !important; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
        h2 { margin: 0 0 8px; font-size: 14px; }
        section { margin: 0 0 14px; page-break-inside: avoid; }
        table { border-collapse: collapse; width: 100%; table-layout: auto; }
        th, td {
            border: 1px solid #666;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        th { background: #e8f1fb; }

        @media print {
            html, body {
                background: #ffffff !important;
            }
        }
    </style>
</head>
<body>
    <section>
        <h2>Rekap Kehadiran</h2>
        <table>
            <thead>
            <tr>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Hadir</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Alpa</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($absensiRows as $row): ?>
                <tr>
                    <td><?= e((string) $row['nama']) ?></td>
                    <td><?= e((string) $row['nis']) ?></td>
                    <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                    <td><?= e((string) $row['hadir']) ?></td>
                    <td><?= e((string) $row['izin']) ?></td>
                    <td><?= e((string) $row['sakit']) ?></td>
                    <td><?= e((string) $row['alpa']) ?></td>
                    <td><?= e((string) $row['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Detail Ketidakhadiran per Mata Pelajaran</h2>
        <table>
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($absensiDetailRows as $row): ?>
                <tr>
                    <td><?= e((string) $row['tanggal']) ?></td>
                    <td><?= e((string) $row['nama']) ?></td>
                    <td><?= e((string) $row['nis']) ?></td>
                    <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                    <td><?= e((string) ($row['nama_mapel'] ?? '-')) ?></td>
                    <td><?= e((string) ucfirst((string) $row['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Rekap Nilai</h2>
        <?php if ($role === 'siswa'): ?>
            <table>
                <thead>
                <tr>
                    <th>Mata Pelajaran</th>
                    <th>Tugas</th>
                    <th>UTS</th>
                    <th>UAS</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($nilaiSiswaMatrix as $namaMapel => $nilaiMapel): ?>
                    <tr>
                        <td><?= e((string) $namaMapel) ?></td>
                        <?php
                        $tugasItems = [];
                        foreach ($nilaiMapel['tugas'] as $index => $nilaiTugas) {
                            $tugasItems[] = 'Tugas ' . ((string) ($index + 1)) . ': ' . number_format((float) $nilaiTugas, 2);
                        }
                        ?>
                        <td><?= $tugasItems ? e(implode(' | ', $tugasItems)) : '-' ?></td>
                        <td><?= $nilaiMapel['uts'] !== null ? e(number_format((float) $nilaiMapel['uts'], 2)) : '-' ?></td>
                        <td><?= $nilaiMapel['uas'] !== null ? e(number_format((float) $nilaiMapel['uas'], 2)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>NIS</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Nilai</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($nilaiRows as $row): ?>
                    <tr>
                        <td><?= e((string) $row['nama']) ?></td>
                        <td><?= e((string) $row['nis']) ?></td>
                        <td><?= e((string) ($row['nama_kelas'] ?? '-')) ?></td>
                        <td><?= e((string) ($row['nama_mapel'] ?? '-')) ?></td>
                        <td><?= $row['rata_nilai'] !== null ? e(number_format((float) $row['rata_nilai'], 2)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if (in_array($role, ['siswa', 'orangtua'], true)): ?>
    <section>
        <h2>Notifikasi Saya</h2>
        <table>
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>Pesan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($notifs as $notif): ?>
                <tr>
                    <td><?= e((string) $notif['tanggal']) ?></td>
                    <td><?= e((string) $notif['pesan']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <?php if (!$printEmbedded): ?>
    <script>
        (function () {
            var returnUrl = <?= json_encode($returnUrl, JSON_UNESCAPED_SLASHES) ?>;
            var hasHandledClose = false;

            function finishPrintFlow() {
                if (hasHandledClose) {
                    return;
                }
                hasHandledClose = true;

                if (window.opener && !window.opener.closed) {
                    window.close();
                    return;
                }

                window.location.replace(returnUrl);
            }

            window.addEventListener('afterprint', function () {
                finishPrintFlow();
            });

            window.addEventListener('load', function () {
                window.print();
            });

            // Fallback untuk browser yang tidak selalu men-trigger afterprint.
            window.addEventListener('focus', function () {
                setTimeout(function () {
                    if (!document.hidden) {
                        finishPrintFlow();
                    }
                }, 300);
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
<?php
    exit;
}

$title = 'Laporan Akademik';
include __DIR__ . '/../src/includes/header.php';
?>
<div class="content-panels report-stack">
<section class="card panel-span-12 report-header-card">
    <h2>Laporan Absensi dan Nilai Akademik</h2>
    <p class="page-lead">Gunakan filter untuk melihat laporan per kelas, mata pelajaran, dan periode dengan lebih terarah.</p>
    <div class="action-links" style="margin-bottom: 10px;">
        <a class="action-link" href="<?= e(url('laporan.php?' . http_build_query(array_filter([
            'id_kelas' => $filterKelas ?: null,
            'id_mapel' => $filterMapel ?: null,
            'periode' => $filterPeriode !== '' ? $filterPeriode : null,
            'export' => 'excel',
        ])))) ?>">Export Excel</a>
        <a class="action-link" href="<?= e(url('laporan.php?' . http_build_query(array_filter([
            'id_kelas' => $filterKelas ?: null,
            'id_mapel' => $filterMapel ?: null,
            'periode' => $filterPeriode !== '' ? $filterPeriode : null,
            'print' => '1',
        ])))) ?>" class="js-open-print-modal" rel="noopener">Mode Cetak</a>
    </div>
    <?php if (in_array($role, ['admin', 'guru'], true)): ?>
        <form method="get" class="filter-toolbar">
            <div class="filter-field">
                <label for="id_kelas">Filter Kelas</label>
                <select id="id_kelas" name="id_kelas">
                    <option value="0">Semua Kelas</option>
                    <?php foreach ($kelasList as $kelas): ?>
                        <option value="<?= e((string) $kelas['id_kelas']) ?>" <?= (int) $kelas['id_kelas'] === $filterKelas ? 'selected' : '' ?>>
                            <?= e($kelas['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="id_mapel">Filter Mata Pelajaran</label>
                <select id="id_mapel" name="id_mapel">
                    <option value="0">Semua Mata Pelajaran</option>
                    <?php foreach ($mapelList as $mapel): ?>
                        <option value="<?= e((string) $mapel['id_mapel']) ?>" <?= (int) $mapel['id_mapel'] === $filterMapel ? 'selected' : '' ?>>
                            <?= e($mapel['nama_mapel']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="periode">Filter Periode</label>
                <select id="periode" name="periode">
                    <option value="">Semua Periode</option>
                    <?php foreach ($periodeList as $periodeOption): ?>
                        <option value="<?= e((string) $periodeOption) ?>" <?= (string) $periodeOption === $filterPeriode ? 'selected' : '' ?>>
                            <?= e((string) $periodeOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Terapkan Filter</button>
                <a class="badge filter-print" href="<?= e(url('laporan.php?' . http_build_query(array_filter([
                    'id_kelas' => $filterKelas ?: null,
                    'id_mapel' => $filterMapel ?: null,
                    'periode' => $filterPeriode !== '' ? $filterPeriode : null,
                ])))) ?>">Reset Filter</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="card panel-span-6">
    <h2>Rekap Kehadiran</h2>
    <p class="footer-note">Ringkasan status hadir, izin, sakit, dan alpa. Total = jumlah semua rekaman kehadiran.</p>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Nama</th>
            <th>NIS</th>
            <th>Kelas</th>
            <th>Hadir</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpa</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($absensiRows as $row): ?>
            <tr>
                <td><?= e($row['nama']) ?></td>
                <td><?= e($row['nis']) ?></td>
                <td><?= e($row['nama_kelas'] ?? '-') ?></td>
                <td><?= e((string) $row['hadir']) ?></td>
                <td><?= e((string) $row['izin']) ?></td>
                <td><?= e((string) $row['sakit']) ?></td>
                <td><?= e((string) $row['alpa']) ?></td>
                <td><?= e((string) $row['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="card panel-span-6">
    <h2>Detail Ketidakhadiran per Mata Pelajaran</h2>
    <p class="footer-note">Menampilkan catatan tidak hadir (izin, sakit, alpa) beserta mata pelajaran.</p>
    <?php if (!$absensiDetailRows): ?>
        <p>Tidak ada data ketidakhadiran pada filter saat ini.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($absensiDetailRows as $row): ?>
                <tr>
                    <td><?= e($row['tanggal']) ?></td>
                    <td><?= e($row['nama']) ?></td>
                    <td><?= e($row['nis']) ?></td>
                    <td><?= e($row['nama_kelas'] ?? '-') ?></td>
                    <td><?= e($row['nama_mapel'] ?? '-') ?></td>
                    <td><?= e(ucfirst((string) $row['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="card panel-span-6">
    <h2>Rekap Nilai</h2>
    <?php if ($role === 'siswa'): ?>
        <p class="footer-note">Menampilkan nilai per mata pelajaran. Detail Tugas 1, 2, 3, dan seterusnya ada di kolom Tugas.</p>
        <?php if (!$nilaiSiswaMatrix): ?>
            <p>Belum ada data nilai untuk filter saat ini.</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Mata Pelajaran</th>
                    <th>Tugas</th>
                    <th>UTS</th>
                    <th>UAS</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($nilaiSiswaMatrix as $namaMapel => $nilaiMapel): ?>
                    <tr>
                        <td><?= e($namaMapel) ?></td>
                        <?php
                        $tugasItems = [];
                        foreach ($nilaiMapel['tugas'] as $index => $nilaiTugas) {
                            $tugasItems[] = 'Tugas ' . ((string) ($index + 1)) . ': ' . number_format((float) $nilaiTugas, 2);
                        }
                        ?>
                        <td><?= $tugasItems ? e(implode(' | ', $tugasItems)) : '-' ?></td>
                        <td><?= $nilaiMapel['uts'] !== null ? e(number_format((float) $nilaiMapel['uts'], 2)) : '-' ?></td>
                        <td><?= $nilaiMapel['uas'] !== null ? e(number_format((float) $nilaiMapel['uas'], 2)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="footer-note">Nilai per siswa dan mata pelajaran.</p>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Nilai</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($nilaiRows as $row): ?>
                <tr>
                    <td><?= e($row['nama']) ?></td>
                    <td><?= e($row['nis']) ?></td>
                    <td><?= e($row['nama_kelas'] ?? '-') ?></td>
                    <td><?= e($row['nama_mapel'] ?? '-') ?></td>
                    <td><?= $row['rata_nilai'] !== null ? e(number_format((float) $row['rata_nilai'], 2)) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php if (in_array($role, ['siswa', 'orangtua'], true)): ?>
<section class="card panel-span-6" id="notifikasi-saya">
    <h2>Notifikasi Saya</h2>
    <p class="footer-note">Informasi peringatan akademik dan kedisiplinan terbaru.</p>
    <?php if (!$notifs): ?>
        <p>Belum ada notifikasi.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>Pesan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($notifs as $notif): ?>
                <tr>
                    <td><?= e($notif['tanggal']) ?></td>
                    <td><?= e($notif['pesan']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
<div id="print-preview-modal" style="position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 1200;">
    <div id="print-preview-backdrop" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);"></div>
    <div role="dialog" aria-modal="true" aria-label="Preview mode cetak" style="position: relative; width: min(1100px, 94vw); height: min(86vh, 900px); background: #fff; border-radius: 14px; border: 1px solid rgba(17,34,51,.18); box-shadow: 0 20px 44px rgba(15,23,42,.28); display: flex; flex-direction: column; overflow: hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding: 12px 14px; border-bottom: 1px solid rgba(17,34,51,.14); background:#f8fbff;">
            <strong style="color:#1e3a8a;">Mode Cetak</strong>
            <div style="display:flex; gap:8px;">
                <button type="button" id="print-preview-run" class="btn-compact">Cetak Sekarang</button>
                <button type="button" id="print-preview-close" class="btn-compact danger">Tutup</button>
            </div>
        </div>
        <iframe id="print-preview-frame" title="Preview Cetak" style="flex:1; border:0; width:100%; background:#fff;"></iframe>
    </div>
</div>
<script>
(() => {
    const printLink = document.querySelector('a.js-open-print-modal');
    const modal = document.getElementById('print-preview-modal');
    const frame = document.getElementById('print-preview-frame');
    const closeBtn = document.getElementById('print-preview-close');
    const printBtn = document.getElementById('print-preview-run');
    const backdrop = document.getElementById('print-preview-backdrop');

    if (!printLink || !modal || !frame || !closeBtn || !printBtn || !backdrop) {
        return;
    }

    const openModal = (href) => {
        const url = new URL(href, window.location.origin);
        url.searchParams.set('embed', '1');
        frame.src = url.toString();
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.style.display = 'none';
        frame.src = 'about:blank';
        document.body.style.overflow = '';
    };

    printLink.addEventListener('click', (event) => {
        event.preventDefault();
        openModal(printLink.href);
    });

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    printBtn.addEventListener('click', () => {
        if (!frame.contentWindow) {
            return;
        }
        frame.contentWindow.focus();
        frame.contentWindow.print();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
})();
</script>
<?php include __DIR__ . '/../src/includes/footer.php'; ?>
