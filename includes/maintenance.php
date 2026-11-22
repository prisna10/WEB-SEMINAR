<?php

function ensureMaintenanceTable(mysqli $conn): bool
{
    $sql = 'CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if (!$conn->query($sql)) {
        error_log('Gagal membuat tabel site_settings: ' . $conn->error);
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')"
    );
    if (!$stmt || !$stmt->execute()) {
        error_log('Gagal menyiapkan status maintenance: ' . ($stmt ? $stmt->error : $conn->error));
        if ($stmt) {
            $stmt->close();
        }
        return false;
    }
    $stmt->close();
    return true;
}

function isMaintenanceMode(mysqli $conn): bool
{
    if (!ensureMaintenanceTable($conn)) {
        return false;
    }
    $result = $conn->query(
        "SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode' LIMIT 1"
    );
    if (!$result) {
        error_log('Gagal membaca status maintenance: ' . $conn->error);
        return false;
    }
    $row = $result->fetch_assoc();
    $result->free();
    return isset($row['setting_value']) && $row['setting_value'] === '1';
}

function setMaintenanceMode(mysqli $conn, bool $enabled): bool
{
    if (!ensureMaintenanceTable($conn)) {
        return false;
    }
    $value = $enabled ? '1' : '0';
    $stmt = $conn->prepare(
        "UPDATE site_settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'"
    );
    if (!$stmt) {
        error_log('Gagal menyiapkan perubahan maintenance: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('s', $value);
    $success = $stmt->execute();
    if (!$success) {
        error_log('Gagal mengubah status maintenance: ' . $stmt->error);
    }
    $stmt->close();
    return $success;
}
