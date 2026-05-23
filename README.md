# Sistem Informasi Siswa (Portal Akademik) 🏫

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15.0%2B-blue.svg)
![Supabase](https://img.shields.io/badge/Supabase-Cloud-green.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

Portal Pemantauan Kehadiran dan Prestasi Akademik Siswa berbasis web yang dirancang untuk mempermudah administrasi sekolah (SMP/SMA sederajat). Sistem ini menggunakan **Supabase (PostgreSQL)** sebagai infrastruktur database cloud.

## 🚀 Fitur Utama

- **Multi-Role Login**: Akses khusus untuk Admin, Guru, Siswa, dan Orang Tua.
- **Manajemen Akademik**:
    - **Guru**: Input absensi harian dan nilai (tugas, kuis, UTS, UAS).
    - **Admin**: Kelola data pengguna, kelas, mata pelajaran, dan jadwal pelajaran.
- **Sistem Notifikasi Otomatis**:
    - Peringatan jika siswa Alpa ≥ 3 kali dalam sebulan.
    - Peringatan jika nilai di bawah KKM (75).
- **Pelaporan Lengkap**: Rekap absensi dan nilai yang bisa di-**export ke Excel** atau di-**print** langsung.
- **Keamanan Cloud**: Proteksi CSRF, Session Hardening, Rate Limiting login menggunakan database cloud yang aman.
- **Dashboard Interaktif**: Ringkasan data yang berbeda untuk setiap peran pengguna.

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP Native (PDO pgsql)
- **Database**: PostgreSQL (Supabase Cloud)
- **Frontend**: HTML5, Vanilla CSS3, JavaScript (ES6)
- **Deployment**: Local Server (XAMPP/Apache) + Cloud DB (Supabase)

## 📦 Struktur Folder

```text
├── src/            # Logika inti (config, includes)
├── public/         # Halaman UI, CSS, dan API
└── vercel.json     # Konfigurasi deploy Vercel
```

## ⚙️ Instalasi (Supabase + Local)

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/destaaryaputra/Sistem-Informasi-Siswa.git
   ```
2. **Setup Supabase**:
   - Buat proyek baru di [Supabase](https://supabase.com/).
   - Buat tabel sesuai kebutuhan aplikasi di SQL Editor Supabase.
3. **Konfigurasi PHP**:
   - Buka `src/config/basis_data.php`.
   - Masukkan **Password Database** Supabase kamu pada variabel `$dbPass`.
4. **Jalankan**:
   - Pindahkan folder ke `htdocs` XAMPP.
   - Buka: `http://localhost/Sistem%20Informasi%20Siswa-RPL/`

## ☁️ Deploy ke Vercel (PHP)

1. **Project Settings**:
   - **Framework Preset**: `Other`
   - **Root Directory**: **Biarkan kosong** (atau set ke repo root). Jangan set ke `public` karena folder `src` akan tertinggal.
   - **Build Command**: kosong
   - **Output Directory**: kosong
2. **Environment Variables**:
   - Pastikan kamu sudah menambahkan `DB_HOST`, `DB_USER`, `DB_PASS`, dll. di dashboard Vercel sesuai dengan kredensial Supabase kamu.
3. **Deployment**:
   - Vercel akan secara otomatis menggunakan `vercel.json` yang ada di root untuk mengarahkan trafik ke folder `public`.

## 🔐 Akun Demo

| Role | Username | Password |
| :--- | :--- | :--- |
| **Admin** | `admin1` | `123456` |
| **Guru** | `guru1` s/d `guru10` | `123456` |
| **Siswa** | `siswa001` s/d `siswa220` | `123456` |
| **Orang Tua** | `ortu001` s/d `ortu220` | `123456` |

## 📝 Catatan Proyek
Proyek ini dikembangkan sebagai tugas mata kuliah **Rekayasa Perangkat Lunak** di Universitas Lampung.

**Pengembang**: [Desta Arya Putra](https://github.com/destaaryaputra)
