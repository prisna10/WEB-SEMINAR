<?php

require 'config.php';
require 'koneksi.php';
require 'admin_auth.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, "/");
session_start();

if (
    !isset($_SESSION['admin_logged_in'], $_SESSION['admin_is_main'])
    || $_SESSION['admin_logged_in'] !== true
) {
    header('Location: login.php');
    exit();
}

if (!$conn instanceof mysqli || !ensureAdminTable($conn)) {
    http_response_code(503);
    exit('Tabel akun admin belum tersedia.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$message = '';
$messageType = 'success';

function adminRedirect(string $message, string $type = 'success', array $oldInput = []): void
{
    $_SESSION['admin_users_message'] = [$message, $type];
    if ($oldInput !== []) {
        $_SESSION['admin_users_form_old'] = $oldInput;
    } else {
        unset($_SESSION['admin_users_form_old']);
    }
    header('Location: admin_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        adminRedirect('Permintaan tidak valid.', 'danger');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $newUsername = trim((string)($_POST['new_username'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        if (
            !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $newUsername)
            || strlen($newPassword) < 8
            || strlen($newPassword) > 255
        ) {
            adminRedirect('Username harus 3-80 karakter dan password 8-255 karakter.', 'warning', [
                'new_username' => $newUsername,
            ]);
        }

        $newIsAdmin = !empty($_SESSION['admin_is_main']) && isset($_POST['new_is_admin']) ? 1 : 0;
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO admin_users (username, password_hash, is_admin) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            error_log('Gagal menyiapkan penambahan akun: ' . $conn->error);
            adminRedirect('Akun gagal ditambahkan. Silakan coba lagi.', 'danger', ['new_username' => $newUsername]);
        }
        $stmt->bind_param('ssi', $newUsername, $passwordHash, $newIsAdmin);
        if ($stmt->execute()) {
            $stmt->close();
            adminRedirect($newIsAdmin === 1 ? 'Akun admin berhasil ditambahkan.' : 'Akun pengguna berhasil ditambahkan.');
        }
        $duplicate = $stmt->errno === 1062;
        error_log('Gagal menambahkan akun admin: ' . $stmt->error);
        $stmt->close();
        adminRedirect(
            $duplicate ? 'Username sudah digunakan.' : 'Akun gagal ditambahkan.',
            'danger'
        );
    }

    if ($id === false || $id === null) {
        adminRedirect('Akun yang dipilih tidak valid.', 'warning');
    }

    $stmt = $conn->prepare('SELECT username, is_main FROM admin_users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        error_log('Gagal menyiapkan pemeriksaan akun: ' . $conn->error);
        adminRedirect('Akun tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($username, $isMain);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) {
        adminRedirect('Akun tidak ditemukan.', 'warning');
    }
    $targetIsAdmin = false;
    $checkStmt = $conn->prepare('SELECT is_admin FROM admin_users WHERE id = ? LIMIT 1');
    if (!$checkStmt) {
        error_log('Gagal menyiapkan pemeriksaan hak akun: ' . $conn->error);
        adminRedirect('Hak akun tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkStmt->bind_result($targetIsAdmin);
    $checkStmt->fetch();
    $checkStmt->close();

    if (
        empty($_SESSION['admin_is_main'])
        && ((int)$isMain === 1 || (int)$targetIsAdmin === 1)
    ) {
        adminRedirect('Admin biasa hanya dapat mengubah akun pengguna biasa.', 'warning');
    }

    if ($action === 'update_account') {
        $updatedUsername = trim((string)($_POST['edit_username'] ?? ''));
        $updatedPassword = (string)($_POST['edit_password'] ?? '');
        if (
            !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $updatedUsername)
            || strlen($updatedPassword) < 8
            || strlen($updatedPassword) > 255
        ) {
            adminRedirect('Username harus 3-80 karakter dan password 8-255 karakter.', 'warning', [
                'edit_id' => (string)$id,
                'edit_username' => $updatedUsername,
            ]);
        }
        $updatedHash = password_hash($updatedPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare(
            'UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?'
        );
        if (!$updateStmt) {
            error_log('Gagal menyiapkan perubahan akun: ' . $conn->error);
            adminRedirect('Akun gagal diperbarui. Silakan coba lagi.', 'danger', [
                'edit_id' => (string)$id,
                'edit_username' => $updatedUsername,
            ]);
        }
        $updateStmt->bind_param('ssi', $updatedUsername, $updatedHash, $id);
        $success = $updateStmt->execute();
        $duplicate = $updateStmt->errno === 1062;
        $updateStmt->close();
        if (!$success) {
            adminRedirect($duplicate ? 'Username sudah digunakan.' : 'Akun gagal diperbarui.', 'danger', [
                'edit_id' => (string)$id,
                'edit_username' => $updatedUsername,
            ]);
        }
        if ((int)($_SESSION['admin_id'] ?? 0) === $id) {
            $_SESSION['admin_username'] = $updatedUsername;
        }
        adminRedirect('Nama dan password akun berhasil diperbarui.');
    }

    if ($action === 'toggle_admin') {
        if ((int)$isMain === 1) {
            adminRedirect('Admin utama tidak dapat diturunkan.', 'warning');
        }
        $stmt = $conn->prepare('UPDATE admin_users SET is_admin = NOT is_admin, aktif = 1 WHERE id = ? AND is_main = 0');
        if (!$stmt) {
            error_log('Gagal menyiapkan perubahan hak admin: ' . $conn->error);
            adminRedirect('Hak admin gagal diubah. Silakan coba lagi.', 'danger');
        }
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();
        adminRedirect($success ? 'Hak admin berhasil diubah.' : 'Hak admin gagal diubah.', $success ? 'success' : 'danger');
    }

    if ($action === 'delete') {
        if ((int)$isMain === 1) {
            adminRedirect('Admin utama tidak dapat dihapus.', 'warning');
        }
        $stmt = $conn->prepare('DELETE FROM admin_users WHERE id = ? AND is_main = 0');
        if (!$stmt) {
            error_log('Gagal menyiapkan penghapusan akun: ' . $conn->error);
            adminRedirect('Akun gagal dihapus. Silakan coba lagi.', 'danger');
        }
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();
        adminRedirect($success ? 'Akun berhasil dihapus.' : 'Akun gagal dihapus.', $success ? 'success' : 'danger');
    }
}

if (isset($_SESSION['admin_users_message'])) {
    [$message, $messageType] = $_SESSION['admin_users_message'];
    unset($_SESSION['admin_users_message']);
}
$adminFormOld = is_array($_SESSION['admin_users_form_old'] ?? null) ? $_SESSION['admin_users_form_old'] : [];
unset($_SESSION['admin_users_form_old']);

$result = $conn->query(
    'SELECT id, username, is_admin, is_main, aktif, dibuat_pada
     FROM admin_users ORDER BY is_main DESC, dibuat_pada ASC'
);
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
if ($result) {
    $result->free();
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun Admin</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Kelola Akun</h1>
        <a href="data_pendaftar.php" class="btn btn-outline-primary">Kembali</a>
    </div>
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $escape($messageType) ?>"><?= $escape($message) ?></div>
    <?php endif; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">Tambah Akun</div>
        <form method="post" class="card-body row g-3">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <label class="form-label">Username</label>
                <input type="text" name="new_username" class="form-control" minlength="3" maxlength="80" value="<?= $escape((string)($adminFormOld['new_username'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password</label>
                <input type="password" name="new_password" class="form-control" minlength="8" required>
            </div>
            <?php if (!empty($_SESSION['admin_is_main'])): ?>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="checkbox" name="new_is_admin" value="1" class="form-check-input" id="new_is_admin">
                    <label class="form-check-label" for="new_is_admin">Jadikan admin</label>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <button class="btn btn-success">
                    <?= !empty($_SESSION['admin_is_main']) ? 'Tambah Akun' : 'Tambah Pengguna' ?>
                </button>
            </div>
        </form>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>Username</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $escape($user['username']) ?></td>
                        <td>
                            <?php if ((int)$user['is_main'] === 1): ?>
                                <span class="badge bg-danger">Admin Utama</span>
                            <?php elseif ((int)$user['is_admin'] === 1): ?>
                                <span class="badge bg-success">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pengguna Terdaftar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($user['dibuat_pada']) ?></td>
                        <td>
                            <?php
                            $canEdit = !empty($_SESSION['admin_is_main'])
                                || ((int)$user['is_admin'] === 0 && (int)$user['is_main'] === 0);
                            ?>
                            <?php if ($canEdit): ?>
                                <details class="mb-2">
                                    <summary class="btn btn-sm btn-outline-primary">Ubah</summary>
                                    <form method="post" class="border rounded p-2 mt-2 text-start">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                        <input type="hidden" name="action" value="update_account">
                                        <input type="text" name="edit_username" class="form-control form-control-sm mb-2" maxlength="80" value="<?= $escape((string)($adminFormOld['edit_id'] ?? '') === (string)$user['id'] ? (string)($adminFormOld['edit_username'] ?? $user['username']) : $user['username']) ?>" required>
                                        <input type="password" name="edit_password" class="form-control form-control-sm mb-2" minlength="8" placeholder="Password baru" required>
                                        <button class="btn btn-sm btn-primary">Simpan</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['admin_is_main']) && (int)$user['is_main'] !== 1): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <button class="btn btn-sm btn-warning"><?= (int)$user['is_admin'] === 1 ? 'Turunkan' : 'Jadikan Admin' ?></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus akun ini?');">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            <?php elseif ((int)$user['is_main'] === 1): ?>
                                <span class="text-muted">Dilindungi</span>
                            <?php else: ?>
                                <span class="text-muted">Lihat saja</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
