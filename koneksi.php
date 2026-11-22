<?php

mysqli_report(MYSQLI_REPORT_OFF);

$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalRequest = $requestHost === ''
    || strpos($requestHost, 'localhost') === 0
    || strpos($requestHost, '127.0.0.1') === 0;

$host = getenv('DB_HOST') ?: ($isLocalRequest ? '127.0.0.1' : '');
$user = getenv('DB_USER') ?: ($isLocalRequest ? 'root' : '');
$pass = getenv('DB_PASSWORD') ?: '';
$db = getenv('DB_NAME') ?: ($isLocalRequest ? 'db_seminar' : '');

$conn = null;
$database_error = null;

if ($host !== '' && $user !== '' && $db !== '') {
    $connection = @new mysqli($host, $user, $pass, $db);
    if (!$connection->connect_error) {
        $conn = $connection;
        $conn->set_charset('utf8mb4');
    } else {
        $database_error = $connection->connect_error;
    }
} else {
    $database_error = 'Konfigurasi database belum tersedia.';
}
?>