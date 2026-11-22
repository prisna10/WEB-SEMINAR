<?php

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../produk.php';
require_once __DIR__ . '/../maintenance.php';

if ($conn instanceof mysqli && isMaintenanceMode($conn)) {
    require __DIR__ . '/../maintenance_page.php';
    exit();
}

$databaseProducts = $conn instanceof mysqli ? getActiveProducts($conn) : [];
if ($databaseProducts === []) {
    $databaseProducts = getDefaultProducts();
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

require __DIR__ . '/../views/ringkasan.php';
