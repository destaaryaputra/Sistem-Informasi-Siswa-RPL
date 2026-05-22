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

// Jika path yang diminta berakhir dengan slash, asumsikan index.php
if (str_ends_with($file, '/')) {
    $file .= 'index.php';
}

$publicPath = __DIR__ . '/../public/' . $file;

// Jika file tidak ditemukan, coba periksa apakah itu direktori
if (!file_exists($publicPath) && file_exists($publicPath . '/index.php')) {
    $publicPath .= '/index.php';
}

// Jika masih tidak ditemukan dan tidak punya ekstensi .php, coba tambah .php
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
