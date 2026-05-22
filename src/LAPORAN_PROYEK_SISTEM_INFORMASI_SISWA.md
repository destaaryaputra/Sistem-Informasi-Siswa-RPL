# LAPORAN PROYEK
## Pengembangan Portal Pemantauan Kehadiran Dan Prestasi Akademik Siswa Berbasis Web Untuk SMP/SMA Sederajat

Disusun untuk memenuhi tugas mata kuliah Rekayasa Perangkat Lunak.

Dosen pengampu:
- Fadil Firdian, M.PdT.
- Ghea Chandra Surawan, M.Pd.

Disusun oleh:
- Nama: Desta Arya Putra
- NPM: 2413025058
- Kelas: 2024 B
- Hari/Tanggal: Kamis, 02 April 2026

PROGRAM STUDI PENDIDIKAN TEKNOLOGI INFORMASI  
JURUSAN PENDIDIKAN MATEMATIKA DAN ILMU PENGETAHUAN ALAM  
FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN  
UNIVERSITAS LAMPUNG  
2026

---

## DAFTAR ISI

1. BAB I PENDAHULUAN
2. BAB II ANALISIS DAN PERANCANGAN SISTEM
3. BAB III IMPLEMENTASI SISTEM
4. BAB IV PENUTUP
5. DAFTAR PUSTAKA

---

## DAFTAR GAMBAR

1. Gambar 2.1 UML Diagram
2. Gambar 2.2 Activity Diagram
3. Gambar 2.3 DFD Level 0
4. Gambar 2.4 DFD Level 1
5. Gambar 2.5 ERD
6. Gambar 2.6 Class Diagram
7. Gambar 3.1 Halaman Login
8. Gambar 3.2 Halaman Dashboard Admin
9. Gambar 3.3 Halaman Dashboard Guru
10. Gambar 3.4 Halaman Dashboard Orang Tua Siswa
11. Gambar 3.5 Halaman Dashboard Siswa
12. Gambar 3.6 Halaman Input Kehadiran
13. Gambar 3.7 Halaman Input Nilai
14. Gambar 3.8 Halaman Notifikasi
15. Gambar 3.9 Halaman Notifikasi Khusus Admin
16. Gambar 3.10 Halaman Tambah/Hapus User
17. Gambar 3.11 Halaman Update Database
18. Gambar 3.12 Database Siswa
19. Gambar 3.13 Database Guru
20. Gambar 3.14 Database Kehadiran
21. Gambar 3.15 Database Nilai
22. Gambar 3.16 Export Laporan Excel
23. Gambar 3.17 Mode Print Laporan

---

## DAFTAR TABEL

1. Tabel 2.1 Kebutuhan Fungsional Sistem
2. Tabel 2.2 Kebutuhan Non-Fungsional Sistem
3. Tabel 2.3 Struktur Tabel Database
4. Tabel 3.1 Hasil Pengujian Fungsional

---

## BAB I PENDAHULUAN

### A. Latar Belakang

Seiring berkembangnya teknologi informasi, kegiatan administrasi sekolah dituntut untuk berjalan lebih efisien dan terintegrasi. Pada praktiknya, sebagian sekolah tingkat SMP/SMA masih menghadapi kendala pada pencatatan absensi, pengelolaan nilai, dan pemantauan prestasi siswa. Absensi sering dicatat secara manual menggunakan buku hadir, sedangkan data nilai tersimpan terpisah pada dokumen pribadi guru. Kondisi ini menyebabkan fragmentasi data dan menyulitkan evaluasi perkembangan siswa secara menyeluruh.

Permasalahan tersebut berdampak pada keterlambatan rekap, tingginya risiko human error, dan minimnya transparansi informasi untuk orang tua. Tanpa sistem terpusat, proses monitoring cenderung reaktif karena informasi kritis baru diketahui setelah terjadi akumulasi masalah.

Berdasarkan kondisi tersebut, proyek ini mengembangkan portal pemantauan berbasis web yang mengintegrasikan data absensi, nilai, laporan, dan notifikasi akademik dalam satu sistem. Sistem juga dirancang dengan akses berbasis peran agar admin, guru, siswa, dan orang tua memperoleh fitur sesuai kebutuhan masing-masing.

### B. Tujuan

1. Mempermudah guru dalam pemantauan siswa melalui digitalisasi absensi dan nilai.
2. Menyediakan dashboard integratif bagi admin, guru, siswa, dan orang tua.
3. Meningkatkan keterlibatan orang tua melalui akses pemantauan data anak.
4. Meningkatkan efisiensi administrasi sekolah melalui pengelolaan data terpusat.
5. Menyediakan fitur pemulihan akun (lupa password) yang aman dengan approval admin.

### C. Manfaat

1. Bagi sekolah: administrasi akademik lebih terstruktur dan terdokumentasi.
2. Bagi guru: proses input absensi dan nilai lebih cepat dan konsisten.
3. Bagi siswa: akses transparan terhadap progres kehadiran dan nilai.
4. Bagi orang tua: pemantauan data anak lebih mudah tanpa harus datang ke sekolah.

### D. Batasan Proyek Akhir

1. Sistem dibangun berbasis web menggunakan PHP, JavaScript, CSS, dan MySQL.
2. Modul utama meliputi manajemen pengguna, kehadiran, nilai, jadwal, laporan, notifikasi, dan reset password.
3. Pengguna sistem dibatasi pada admin, guru, siswa, dan orang tua.
4. Tidak mencakup modul e-learning (materi/tugas online) dan keuangan sekolah.
5. Notifikasi akademik otomatis difokuskan pada:
   - Alpa >= 3 kali dalam bulan berjalan.
   - Nilai < 75.
6. Sistem menyediakan panel admin untuk tambah/hapus data master.
7. Sistem menyediakan fitur update database dari antarmuka web admin.

### E. Pembagian Kerja Tim

Proyek ini merupakan tugas individu. Seluruh pengembangan dikerjakan oleh satu orang (Desta Arya Putra), mencakup:

1. Analisis kebutuhan dan studi literatur.
2. Perancangan UI/UX dan basis data.
3. Implementasi kode (frontend dan backend).
4. Pengujian sistem dan dokumentasi laporan.

---

## BAB II ANALISIS DAN PERANCANGAN SISTEM

### A. Analisis Sistem Yang Sedang Berjalan

Sistem manual yang masih digunakan pada sebagian sekolah menyebabkan data absensi dan nilai terpisah, sulit direkap, serta rawan kehilangan data. Orang tua juga tidak memperoleh informasi secara cepat ketika terjadi penurunan kedisiplinan atau prestasi anak. Hal ini menunjukkan kebutuhan terhadap sistem terintegrasi yang mampu menyediakan data akademik secara cepat dan konsisten.

Pengumpulan kebutuhan proyek dilakukan melalui:

1. Studi literatur terkait sistem informasi akademik berbasis web.
2. Observasi proses administrasi dan alur data akademik.
3. Wawancara kebutuhan fungsional pengguna (admin/guru/siswa/orang tua).

### B. Metode Pengembangan Sistem

Pengembangan sistem menggunakan model SDLC Waterfall karena kebutuhan fungsional sudah dapat didefinisikan sejak awal dan alur kerja relatif jelas.

Tahapan yang dilakukan:

1. Analisis kebutuhan.
2. Perancangan sistem.
3. Implementasi.
4. Pengujian.
5. Pemeliharaan.

### C. Perancangan Sistem

Perancangan dilakukan menggunakan diagram UML, Activity Diagram, DFD, ERD, dan Class Diagram untuk memetakan aktor, alur proses, aliran data, serta struktur relasi basis data.

Ringkasan peran aktor:

1. Admin: kelola pengguna, data akademik, approval reset password, dan monitoring notifikasi sistem.
2. Guru: input absensi dan nilai.
3. Siswa: melihat ringkasan akademik pribadi.
4. Orang tua: memantau data anak dan notifikasi akademik.

### D. Rancangan Input Dan Output Sistem

#### 1. Rancangan Input

1. Input data pengguna oleh admin.
2. Input data kehadiran oleh guru (kelas, mapel, tanggal, status).
3. Input data nilai oleh guru (jenis penilaian dan periode).
4. Input data master (kelas, mapel, jadwal) oleh admin.
5. Input permintaan reset password oleh pengguna.
6. Input approval reset password oleh admin.

#### 2. Rancangan Output

1. Dashboard admin: ringkasan data dan kontrol modul.
2. Dashboard guru: ringkasan absensi/nilai dan kelas diampu.
3. Dashboard siswa: ringkasan kehadiran dan nilai.
4. Dashboard orang tua: pemantauan data anak (termasuk pencarian nama/NIS).
5. Laporan: rekap absensi, detail ketidakhadiran, rekap nilai.
6. Notifikasi: notifikasi personal dan notifikasi sistem admin.

### E. Rancangan Database

Database menggunakan model relasional dengan tabel utama:

1. tbl_users
2. tbl_admin
3. tbl_guru
4. tbl_orangtua
5. tbl_siswa
6. tbl_kelas
7. tbl_mapel
8. tbl_jadwal
9. tbl_kehadiran
10. tbl_nilai
11. tbl_notifikasi
12. tbl_password_reset_tokens
13. tbl_rate_limits

Relasi penting:

1. tbl_users berelasi 1:1 dengan tabel profil role.
2. tbl_siswa berelasi ke tbl_kelas dan tbl_orangtua.
3. tbl_jadwal menjadi penghubung guru-kelas-mapel.
4. tbl_kehadiran berelasi ke siswa-guru-mapel.
5. tbl_nilai berelasi ke siswa-guru-mapel.
6. tbl_notifikasi berelasi ke tbl_users.
7. tbl_password_reset_tokens berelasi ke tbl_users dan menyimpan status approval reset.

---

## BAB III IMPLEMENTASI SISTEM

### A. Produk Akhir Sistem

Teknologi yang digunakan:

1. Backend: PHP native.
2. Frontend: HTML, CSS, JavaScript.
3. Database: MySQL.
4. Environment: XAMPP (Apache + MySQL + PHP).

#### 1. Implementasi Antarmuka

1. Login multi-role dengan session-based access control.
2. Dashboard berbeda sesuai role.
3. Input absensi dan nilai oleh guru.
4. Notifikasi akademik otomatis (alpa dan nilai di bawah KKM).
5. Notifikasi sistem khusus admin (validasi kesehatan data).
6. Manajemen pengguna dan data akademik oleh admin.
7. Update database dari panel admin.
8. Export laporan ke Excel (.xls).
9. Preview print laporan di dalam halaman.
10. Reset password dengan approval admin.
11. Proteksi keamanan dasar seperti CSRF, session hardening, dan rate limit login/reset password.

#### 2. Implementasi Basis Data

Implementasi basis data mengikuti rancangan ERD dengan foreign key untuk menjaga integritas data. Tabel inti yang digunakan meliputi data pengguna, profil role, akademik, transaksi absensi/nilai, notifikasi, dan token reset password.

Aturan bisnis yang diimplementasikan:

1. Kehadiran unik per siswa + tanggal + mapel.
2. Nilai unik per siswa + mapel + jenis + periode.
3. Notifikasi otomatis saat alpa mencapai ambang batas.
4. Notifikasi otomatis saat nilai di bawah KKM.
5. Token reset password bersifat single-use, memiliki masa berlaku, dan menunggu approval admin.
6. Rate limit login dan reset password disimpan pada tabel tersendiri agar proteksi berlaku lintas session.

### B. Hasil Pengujian Sistem

Pengujian dilakukan dengan metode Black Box Testing pada fitur utama.

Skenario dan hasil:

1. Login multi-role.
   - Hasil: pengguna diarahkan ke dashboard sesuai role.
2. Input absensi guru.
   - Hasil: data tersimpan sesuai validasi dan aturan unik.
3. Input nilai guru.
   - Hasil: data nilai tersimpan dan notifikasi dibuat jika nilai < 75.
4. Notifikasi alpa.
   - Hasil: notifikasi terkirim saat alpa >= 3 kali dalam bulan berjalan.
5. Manajemen data admin.
   - Hasil: tambah/hapus data berjalan sesuai modul.
6. Update database via admin.
   - Hasil: skema dan data awal berhasil dimuat ulang.
7. Lupa password.
   - Hasil: request reset tercatat, menunggu approval admin, lalu reset password dapat dilakukan.
8. Export laporan Excel.
   - Hasil: laporan dapat diunduh dalam format `.xls` sesuai filter yang dipilih.
9. Print laporan.
   - Hasil: laporan dapat ditampilkan dalam mode preview print tanpa meninggalkan halaman utama.
10. Notifikasi sistem admin.
   - Hasil: admin menerima notifikasi validasi data sistem secara otomatis.
11. Rate limit keamanan.
   - Hasil: percobaan login dan reset password dibatasi untuk mencegah abuse.

Kesimpulan pengujian:

Secara umum fitur inti berjalan baik sesuai perancangan. Sistem telah memenuhi kebutuhan fungsional utama untuk skala proyek mata kuliah.

---

## BAB IV PENUTUP

### A. Kesimpulan

Berdasarkan analisis, perancangan, implementasi, dan pengujian, proyek portal pemantauan kehadiran dan prestasi akademik siswa berbasis web ini berhasil memenuhi tujuan utama. Sistem mampu mengintegrasikan data absensi, nilai, notifikasi, dan pemulihan akun dalam satu platform terpusat dengan akses berbasis role.

Sistem juga membantu efisiensi administrasi, mempercepat alur kerja guru, meningkatkan transparansi informasi untuk siswa dan orang tua, serta menambah keamanan operasional melalui CSRF protection, session hardening, dan approval reset password oleh admin.

Sistem juga sudah menyediakan export laporan Excel, mode print yang tetap berada di halaman laporan, serta rate limit untuk login dan permintaan reset password sebagai lapisan keamanan tambahan.

### B. Saran

1. Menambahkan export laporan PDF jika dibutuhkan untuk kebutuhan administrasi formal.
2. Menambahkan visualisasi analitik (grafik tren absensi/nilai).
3. Meningkatkan integrasi notifikasi email produksi (SMTP).
4. Menambahkan audit log aktivitas pengguna.
5. Melakukan pengujian lanjutan (performance testing dan security testing).
6. Uji implementasi pada lingkungan sekolah dengan jumlah pengguna lebih besar.

---

## DAFTAR PUSTAKA

1. Arriqsa, M., dkk. Sistem Absensi Siswa Berbasis Web untuk SD Musika.
2. Hendini, A., & Kurniawati, D. (2024). Sistem Informasi Monitoring Orang Tua Terhadap Kehadiran Anak di Sekolah Berbasis Website.
3. Istiqomah, dkk. (2024). Sistem Informasi Absensi Siswa Berbasis Web di SMKS Teladan Pematangsiantar.
4. Kholisotul Muawanah, dkk. (2025). Penerapan Absensi Digital Menggunakan QR Code Berbasis Website.
5. Riza Fachramdhan, dkk. Aplikasi Absen Murid Berbasis Web Dengan QR Code.
6. Studi, P., dkk. (2024). Kajian Sistem Absensi Berbasis Digital.
7. PHP Manual. https://www.php.net/docs.php
8. MySQL Documentation. https://dev.mysql.com/doc/
9. PDO Documentation. https://www.php.net/manual/en/book.pdo.php
