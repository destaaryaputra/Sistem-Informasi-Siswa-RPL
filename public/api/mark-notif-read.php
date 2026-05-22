<?php

declare(strict_types=1);

// API endpoint untuk menandai notifikasi sebagai sudah dibaca.
require_once __DIR__ . '/../../app/config/bootstrap.php';

require_login();

header('Content-Type: application/json');

if (!request_method_is('POST')) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
    exit;
}

if (!verify_csrf_token(post_string('csrf_token'))) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$user = current_user();
$idNotifikasi = post_int('id_notifikasi', 0);

if ($idNotifikasi <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID notifikasi tidak valid.']);
    exit;
}

try {
    if (!db_column_exists($pdo, 'tbl_notifikasi', 'dibaca')) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Fitur tanda dibaca belum tersedia. Jalankan pembaruan skema database.']);
        exit;
    }

    // Verifikasi notifikasi milik user yang sedang login
    $stmt = $pdo->prepare('SELECT id_user FROM tbl_notifikasi WHERE id_notifikasi = ? LIMIT 1');
    $stmt->execute([$idNotifikasi]);
    $notif = $stmt->fetch();

    if (!$notif || (int) $notif['id_user'] !== (int) $user['id_user']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan atau bukan milik Anda.']);
        exit;
    }

    // Update status dibaca
    $stmt = $pdo->prepare('UPDATE tbl_notifikasi SET dibaca = TRUE WHERE id_notifikasi = ?');
    $stmt->execute([$idNotifikasi]);

    echo json_encode(['status' => 'success', 'message' => 'Notifikasi sudah ditandai sebagai dibaca.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan internal server.']);
}
