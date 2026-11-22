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
    exit('Permintaan backup tidak valid.');
}

if (!$conn instanceof mysqli) {
    http_response_code(503);
    exit('Database tidak tersedia.');
}

$tablesResult = $conn->query('SHOW TABLES');
if (!$tablesResult) {
    error_log('Gagal membaca daftar tabel untuk backup: ' . $conn->error);
    http_response_code(500);
    exit('Backup gagal dibuat.');
}

$sqlPayload = "SET FOREIGN_KEY_CHECKS=0;\n\n";

while ($tableRow = $tablesResult->fetch_row()) {
    $table = (string)$tableRow[0];
    $escapedTable = '`' . str_replace('`', '``', $table) . '`';
    $createResult = $conn->query('SHOW CREATE TABLE ' . $escapedTable);
    if (!$createResult) {
        error_log('Gagal membaca struktur tabel backup: ' . $table . ': ' . $conn->error);
        $tablesResult->free();
        http_response_code(500);
        exit('Backup gagal dibuat.');
    }

    $createRow = $createResult->fetch_assoc();
    $createResult->free();
    $createSql = (string)($createRow['Create Table'] ?? '');
    if ($createSql === '') {
        continue;
    }

    $sqlPayload .= "DROP TABLE IF EXISTS {$escapedTable};\n{$createSql};\n";
    $dataResult = $conn->query('SELECT * FROM ' . $escapedTable);
    if (!$dataResult) {
        error_log('Gagal membaca data tabel backup: ' . $table . ': ' . $conn->error);
        $tablesResult->free();
        http_response_code(500);
        exit('Backup gagal dibuat.');
    }

    $fields = $dataResult->fetch_fields();
    while ($row = $dataResult->fetch_assoc()) {
        $columns = [];
        $values = [];
        foreach ($fields as $field) {
            $columns[] = '`' . str_replace('`', '``', $field->name) . '`';
            $value = $row[$field->name];
            $values[] = $value === null
                ? 'NULL'
                : "'" . $conn->real_escape_string((string)$value) . "'";
        }
        $sqlPayload .= 'INSERT INTO ' . $escapedTable
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
    }
    $dataResult->free();
    $sqlPayload .= "\n";
}

$tablesResult->free();
$sqlPayload .= "SET FOREIGN_KEY_CHECKS=1;\n";
$signature = hash_hmac('sha256', $sqlPayload, BACKUP_SIGNING_KEY);
$backup = "-- Backup database seminar\n";
$backup .= '-- Dibuat pada ' . date('Y-m-d H:i:s') . "\n";
$backup .= '-- Signature: ' . $signature . "\n\n";
$backup .= $sqlPayload;

$filename = 'backup-seminar-' . date('Y-m-d-His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($backup));
echo $backup;
