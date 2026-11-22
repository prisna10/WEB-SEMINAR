<?php

mysqli_report(MYSQLI_REPORT_OFF);

$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalRequest = $requestHost === ''
    || strpos($requestHost, 'localhost') === 0
    || strpos($requestHost, '127.0.0.1') === 0;


$host = getenv('DB_HOST') ?: ($isLocalRequest ? 'localhost' : 'sql204.infinityfree.com');
$user = getenv('DB_USER') ?: ($isLocalRequest ? 'root' : 'if0_43090046');
$pass = getenv('DB_PASSWORD') ?: ($isLocalRequest ? '' : 'PrisnaPbw');
$db   = getenv('DB_NAME') ?: ($isLocalRequest ? 'db_seminar' : 'if0_43090046_db_seminar');

$conn = null;
$database_error = null;

function prepareDatabaseStatement(mysqli $connection, string $sql): mysqli_stmt
{
    try {
        $statement = $connection->prepare($sql);
        if (!$statement) {
            throw new RuntimeException($connection->error);
        }
        return $statement;
    } catch (Throwable $exception) {
        error_log('Gagal menyiapkan query database: ' . $exception->getMessage());
        throw $exception;
    }
}

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