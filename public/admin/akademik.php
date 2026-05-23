<?php

declare(strict_types=1);

// Modul admin untuk kelola data akademik.
require_once __DIR__ . '/../../src/config/bootstrap.php';

require_role(['admin']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(post_string('csrf_token'))) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = post_string('action');

        try {
        if ($action === 'delete_kelas') {
            // Hapus kelas; siswa yang terkait akan mengikuti aturan foreign key database.
            $idKelas = post_int('id_kelas');
            if ($idKelas <= 0) {
                throw new RuntimeException('ID kelas tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM tbl_kelas WHERE id_kelas = ?');
            $stmt->execute([$idKelas]);
            $message = 'Data kelas berhasil dihapus.';
        }

        if ($action === 'delete_mapel') {
            // Hapus mapel; relasi jadwal, kehadiran, dan nilai bisa ikut terdampak.
            $idMapel = post_int('id_mapel');
            if ($idMapel <= 0) {
                throw new RuntimeException('ID mata pelajaran tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM tbl_mapel WHERE id_mapel = ?');
            $stmt->execute([$idMapel]);
            $message = 'Data mata pelajaran berhasil dihapus.';
        }

        if ($action === 'delete_jadwal') {
            // Hapus jadwal tanpa mengubah master data kelas/guru/mapel.
            $idJadwal = post_int('id_jadwal');
            if ($idJadwal <= 0) {
                throw new RuntimeException('ID jadwal tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM tbl_jadwal WHERE id_jadwal = ?');
            $stmt->execute([$idJadwal]);
            $message = 'Jadwal berhasil dihapus.';
        }

        if ($action === 'add_kelas') {
            $namaKelas = post_string('nama_kelas');
            $tingkat = post_string('tingkat');
            if ($namaKelas === '' || $tingkat === '') {
                throw new RuntimeException('Nama kelas dan tingkat wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO tbl_kelas (nama_kelas, tingkat) VALUES (?, ?)');
            $stmt->execute([$namaKelas, $tingkat]);
            $message = 'Data kelas berhasil ditambahkan.';
        }

        if ($action === 'add_mapel') {
            $namaMapel = post_string('nama_mapel');
            if ($namaMapel === '') {
                throw new RuntimeException('Nama mata pelajaran wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO tbl_mapel (nama_mapel) VALUES (?)');
            $stmt->execute([$namaMapel]);
            $message = 'Data mata pelajaran berhasil ditambahkan.';
        }

        if ($action === 'add_jadwal') {
            $idGuru = post_int('id_guru');
            $idKelas = post_int('id_kelas');
            $idMapel = post_int('id_mapel');
            $hari = post_string('hari');
            $jam = post_string('jam');
            if ($idGuru <= 0 || $idKelas <= 0 || $idMapel <= 0 || $hari === '' || $jam === '') {
                throw new RuntimeException('Semua field jadwal wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO tbl_jadwal (id_guru, id_kelas, id_mapel, hari, jam) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$idGuru, $idKelas, $idMapel, $hari, $jam]);
            $message = 'Jadwal berhasil ditambahkan.';
        }
        } catch (Throwable $e) {
            $error = 'Operasi data akademik gagal diproses.';
        }
    }
}

$kelasList = $pdo->query('SELECT id_kelas, nama_kelas, tingkat FROM tbl_kelas ORDER BY id_kelas ASC')->fetchAll();
$mapelList = $pdo->query('SELECT id_mapel, nama_mapel FROM tbl_mapel ORDER BY id_mapel ASC')->fetchAll();
$guruList = $pdo->query('SELECT id_guru, nama FROM tbl_guru ORDER BY nama')->fetchAll();

$jadwalList = $pdo->query(
    'SELECT j.id_jadwal, g.nama AS guru, k.nama_kelas, m.nama_mapel, j.hari, j.jam
     FROM tbl_jadwal j
     JOIN tbl_guru g ON g.id_guru = j.id_guru
     JOIN tbl_kelas k ON k.id_kelas = j.id_kelas
     JOIN tbl_mapel m ON m.id_mapel = j.id_mapel
    ORDER BY j.id_jadwal ASC'
)->fetchAll();

$title = 'Data Akademik';
include __DIR__ . '/../../src/includes/header.php';
?>
<section class="card">
    <h2>Manajemen Data Akademik</h2>
    <p>Modul ini menyelaraskan data kelas, mata pelajaran, dan jadwal agar tetap konsisten.</p>
    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>
</section>

<div class="content-panels akademik-add-panels">
    <section class="card panel-span-6" id="tambah-kelas">
        <h2>Tambah Kelas</h2>
        <form method="post" class="grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_kelas">
            <div>
                <label for="nama_kelas">Nama Kelas</label>
                <input id="nama_kelas" name="nama_kelas" required>
            </div>
            <div>
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" required>
                    <option value="7">7</option>
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                    <option value="11">11</option>
                    <option value="12">12</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-compact">Simpan Kelas</button>
            </div>
        </form>
    </section>

    <section class="card panel-span-6" id="tambah-mapel">
        <h2>Tambah Mata Pelajaran</h2>
        <form method="post" class="grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_mapel">
            <div>
                <label for="nama_mapel">Nama Mata Pelajaran</label>
                <input id="nama_mapel" name="nama_mapel" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-compact">Simpan Mata Pelajaran</button>
            </div>
        </form>
    </section>
</div>

<section class="card" id="tambah-jadwal">
    <h2>Tambah Jadwal</h2>
    <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_jadwal">
        <div>
            <label for="id_guru">Guru</label>
            <select id="id_guru" name="id_guru" required>
                <option value="">- Pilih Guru -</option>
                <?php foreach ($guruList as $guru): ?>
                    <option value="<?= e((string) $guru['id_guru']) ?>"><?= e($guru['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="id_kelas">Kelas</label>
            <select id="id_kelas" name="id_kelas" required>
                <option value="">- Pilih Kelas -</option>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= e((string) $kelas['id_kelas']) ?>"><?= e($kelas['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="id_mapel">Mata Pelajaran</label>
            <select id="id_mapel" name="id_mapel" required>
                <option value="">- Pilih Mata Pelajaran -</option>
                <?php foreach ($mapelList as $mapel): ?>
                    <option value="<?= e((string) $mapel['id_mapel']) ?>"><?= e($mapel['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="hari">Hari</label>
            <select id="hari" name="hari" required>
                <option>Senin</option>
                <option>Selasa</option>
                <option>Rabu</option>
                <option>Kamis</option>
                <option>Jumat</option>
                <option>Sabtu</option>
            </select>
        </div>
        <div>
            <label for="jam">Jam</label>
            <input id="jam" name="jam" placeholder="07:30-09:00" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-compact">Simpan Jadwal</button>
        </div>
    </form>
</section>

<div class="content-panels akademik-list-panels">
<section class="card panel-span-6 akademik-table-card" id="daftar-kelas">
    <h2>Daftar Kelas</h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>ID</th>
            <th>Nama Kelas</th>
            <th>Tingkat</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $noKelas = 1; ?>
        <?php foreach ($kelasList as $kelas): ?>
            <tr>
                <td><?= e((string) $noKelas++) ?></td>
                <td><?= e((string) $kelas['id_kelas']) ?></td>
                <td><?= e($kelas['nama_kelas']) ?></td>
                <td><?= e($kelas['tingkat']) ?></td>
                <td>
                    <form method="post" data-confirm="Hapus kelas ini? Siswa akan tetap ada tetapi kelasnya akan kosong.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_kelas">
                        <input type="hidden" name="id_kelas" value="<?= e((string) $kelas['id_kelas']) ?>">
                        <button type="submit" class="danger">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="card panel-span-6 akademik-table-card" id="daftar-mapel">
    <h2>Daftar Mata Pelajaran</h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>ID</th>
            <th>Mata Pelajaran</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $noMapel = 1; ?>
        <?php foreach ($mapelList as $mapel): ?>
            <tr>
                <td><?= e((string) $noMapel++) ?></td>
                <td><?= e((string) $mapel['id_mapel']) ?></td>
                <td><?= e($mapel['nama_mapel']) ?></td>
                <td>
                    <form method="post" data-confirm="Hapus mata pelajaran ini? Data jadwal, kehadiran, dan nilai terkait akan ikut terhapus.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_mapel">
                        <input type="hidden" name="id_mapel" value="<?= e((string) $mapel['id_mapel']) ?>">
                        <button type="submit" class="danger">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
</div>

<section class="card" id="daftar-jadwal">
    <h2>Daftar Jadwal</h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>ID</th>
            <th>Guru</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th>Hari</th>
            <th>Jam</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $noJadwal = 1; ?>
        <?php foreach ($jadwalList as $jadwal): ?>
            <tr>
                <td><?= e((string) $noJadwal++) ?></td>
                <td><?= e((string) $jadwal['id_jadwal']) ?></td>
                <td><?= e($jadwal['guru']) ?></td>
                <td><?= e($jadwal['nama_kelas']) ?></td>
                <td><?= e($jadwal['nama_mapel']) ?></td>
                <td><?= e($jadwal['hari']) ?></td>
                <td><?= e($jadwal['jam']) ?></td>
                <td>
                    <form method="post" data-confirm="Hapus jadwal ini?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_jadwal">
                        <input type="hidden" name="id_jadwal" value="<?= e((string) $jadwal['id_jadwal']) ?>">
                        <button type="submit" class="danger">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php include __DIR__ . '/../../src/includes/footer.php'; ?>
