<?php

require 'config.php';
require 'koneksi.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, '/');
session_start();

if (
    !isset($_SESSION['admin_logged_in'], $_SESSION['session_expires_at'])
    || $_SESSION['admin_logged_in'] !== true
    || time() >= (int)$_SESSION['session_expires_at']
) {
    http_response_code(401);
    exit('Sesi admin tidak valid atau sudah berakhir.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

$csrfToken = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || !hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Permintaan import tidak valid.');
}

if (!$conn instanceof mysqli) {
    http_response_code(503);
    exit('Database tidak tersedia.');
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['update_notification'] = [
        'message' => 'File backup gagal diunggah.',
        'type' => 'danger',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

$file = $_FILES['backup_file'];
$extension = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if ($extension !== 'sql' || (int)$file['size'] < 1 || (int)$file['size'] > 10 * 1024 * 1024) {
    $_SESSION['update_notification'] = [
        'message' => 'File harus berformat .sql dan berukuran maksimal 10 MB.',
        'type' => 'warning',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

$sql = file_get_contents((string)$file['tmp_name']);
if ($sql === false || trim($sql) === '') {
    $_SESSION['update_notification'] = [
        'message' => 'File backup kosong atau tidak dapat dibaca.',
        'type' => 'danger',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

if (strpos($sql, "-- Backup database seminar\n") !== 0) {
    $_SESSION['update_notification'] = [
        'message' => 'Format backup tidak dikenali. Gunakan file hasil tombol Backup.',
        'type' => 'warning',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

$signatureLine = [];
if (!preg_match('/\A-- Backup database seminar\n-- Dibuat pada [^\r\n]+\n-- Signature: ([a-f0-9]{64})\n\n/s', $sql, $signatureLine)) {
    $_SESSION['update_notification'] = [
        'message' => 'Backup tidak memiliki tanda tangan yang valid. Unduh ulang dari web ini.',
        'type' => 'warning',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

$sqlPayload = substr($sql, (int)strlen($signatureLine[0]));
$expectedSignature = hash_hmac('sha256', $sqlPayload, BACKUP_SIGNING_KEY);
if (!hash_equals($expectedSignature, $signatureLine[1])) {
    $_SESSION['update_notification'] = [
        'message' => 'Backup ditolak karena isi file telah diubah atau bukan dari web ini.',
        'type' => 'danger',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

if (!$conn->multi_query($sqlPayload)) {
    error_log('Gagal mengimport backup: ' . $conn->error);
    while ($conn->more_results()) {
        $conn->next_result();
    }
    $_SESSION['update_notification'] = [
        'message' => 'Import gagal. Pastikan file berasal dari backup web ini.',
        'type' => 'danger',
    ];
    header('Location: data_pendaftar.php');
    exit();
}

while ($conn->more_results()) {
    if (!$conn->next_result()) {
        error_log('Gagal menyelesaikan import backup: ' . $conn->error);
        $_SESSION['update_notification'] = [
            'message' => 'Import gagal saat memproses data.',
            'type' => 'danger',
        ];
        header('Location: data_pendaftar.php');
        exit();
    }
}

$_SESSION['update_notification'] = [
    'message' => 'Backup berhasil diimport ke database.',
    'type' => 'success',
];
header('Location: data_pendaftar.php');
exit();
