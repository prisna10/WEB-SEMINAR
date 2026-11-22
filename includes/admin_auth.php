<?php

function ensureAdminTable(mysqli $conn): bool
{
    $sql = 'CREATE TABLE IF NOT EXISTS admin_users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        is_main TINYINT(1) NOT NULL DEFAULT 0,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if (!$conn->query($sql)) {
        error_log('Gagal membuat tabel admin_users: ' . $conn->error);
        return false;
    }

    $mainUsername = ADMIN_USERNAME;
    $stmt = $conn->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $mainUsername);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    if ($exists) {
        return true;
    }

    $passwordHash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        'INSERT INTO admin_users (username, password_hash, is_admin, is_main) VALUES (?, ?, 1, 1)'
    );
    $stmt->bind_param('ss', $mainUsername, $passwordHash);
    $success = $stmt->execute();
    if (!$success) {
        error_log('Gagal membuat akun admin utama: ' . $stmt->error);
    }
    $stmt->close();
    return $success;
}

function authenticateAdmin(mysqli $conn, string $username, string $password): ?array
{
    try {
        $stmt = prepareDatabaseStatement(
            $conn,
            'SELECT id, username, password_hash, is_main
             FROM admin_users WHERE username = ? AND is_admin = 1 AND aktif = 1 LIMIT 1'
        );
    } catch (Throwable $exception) {
        error_log('Gagal menyiapkan autentikasi admin: ' . $exception->getMessage());
        return null;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $storedUsername, $passwordHash, $isMain);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found || !password_verify($password, $passwordHash)) {
        return null;
    }
    return [
        'id' => (int)$id,
        'username' => $storedUsername,
        'is_main' => (bool)$isMain,
    ];
}
