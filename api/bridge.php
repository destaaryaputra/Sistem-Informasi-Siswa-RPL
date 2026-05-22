<?php

/**
 * Bridge script untuk Vercel.
 * script ini meneruskan request ke file yang ada di folder public.
 */

$file = $_GET['file'] ?? 'index.php';
if ($file === '') {
    $file = 'index.php';
}

// Keamanan: cegah traversal directory
$file = str_replace('..', '', $file);

$publicPath = __DIR__ . '/../public/' . $file;

// Jika file tidak ditemukan dan tidak punya ekstensi .php, coba tambah .php
if (!file_exists($publicPath) && !str_ends_with($file, '.php')) {
    $publicPath .= '.php';
}

if (file_exists($publicPath) && is_file($publicPath)) {
    // Set environment variable agar aplikasi tahu path aslinya jika perlu
    $_SERVER['SCRIPT_FILENAME'] = $publicPath;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim($file, '/');
    
    require $publicPath;
} else {
    http_response_code(404);
    echo "404 - Halaman tidak ditemukan di folder public: " . htmlspecialchars($file);
}
