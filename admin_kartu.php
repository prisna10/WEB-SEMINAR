<?php

require 'config.php';
require 'koneksi.php';
require 'produk.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, "/");
session_start();

if (
    !isset($_SESSION['admin_logged_in'], $_SESSION['session_expires_at'])
    || $_SESSION['admin_logged_in'] !== true
    || time() >= $_SESSION['session_expires_at']
) {
    header('Location: login.php?expired=1');
    exit();
}

if (!$conn instanceof mysqli || !ensureProductTable($conn)) {
    http_response_code(503);
    exit('Database produk belum tersedia.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$message = '';
$messageType = 'success';

function productRedirect(string $message, string $type = 'success', array $oldInput = []): void
{
    $_SESSION['product_message'] = [$message, $type];
    if ($oldInput !== []) {
        $_SESSION['product_form_old'] = $oldInput;
    } else {
        unset($_SESSION['product_form_old']);
    }
    header('Location: admin_kartu.php');
    exit();
}

function uploadProductImage(string $field, bool $required): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            productRedirect('Pilih gambar kartu dari komputer Anda.', 'warning');
        }
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        productRedirect('Gambar gagal diunggah. Silakan pilih file lain.', 'warning');
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        productRedirect('Ukuran gambar maksimal 5 MB.', 'warning');
    }

    $imageInfo = @getimagesize($_FILES[$field]['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mimeType = $imageInfo['mime'] ?? '';
    if ($imageInfo === false || !isset($allowedMimeTypes[$mimeType])) {
        productRedirect('Format gambar harus JPG, PNG, GIF, atau WEBP.', 'warning');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        error_log('Gagal membuat folder upload gambar kartu.');
        productRedirect('Folder penyimpanan gambar belum tersedia.', 'danger');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {
        error_log('Gagal memindahkan file gambar kartu ke folder upload.');
        productRedirect('Gambar gagal disimpan. Silakan coba lagi.', 'danger');
    }

    return '../assets/uploads/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        productRedirect('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.', 'danger');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id === false || $id === null) {
            productRedirect('Kartu yang dipilih tidak valid.', 'danger');
        }
        $stmt = $conn->prepare('UPDATE kartu_produk SET aktif = 0 WHERE id = ?');
        if (!$stmt) {
            error_log('Gagal menyiapkan penghapusan kartu: ' . $conn->error);
            productRedirect('Kartu gagal dihapus. Silakan coba lagi.', 'danger');
        }
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();
        productRedirect($success ? 'Kartu berhasil dihapus.' : 'Kartu gagal dihapus.', $success ? 'success' : 'danger');
    }

    if ($action === 'save') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $kategori = trim((string)($_POST['kategori'] ?? ''));
        $badge = trim((string)($_POST['badge'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_INT);
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
        $isCreate = $id === false || $id === null;
        $oldInput = [
            'id' => $id === false ? '' : (string)$id,
            'kategori' => $kategori,
            'badge' => $badge,
            'nama' => $nama,
            'harga' => $harga === false ? '' : (string)$harga,
            'deskripsi' => $deskripsi,
        ];

        if (
            $kategori === '' || strlen($kategori) > 50
            || $badge === '' || strlen($badge) > 100
            || $nama === '' || strlen($nama) > 150
            || $deskripsi === '' || strlen($deskripsi) > 5000
            || $harga === false || $harga < 1 || $harga > 2147483647
        ) {
            productRedirect('Kategori, badge, nama, harga, dan deskripsi wajib diisi dengan format yang benar.', 'warning', $oldInput);
        }

        $gambar = uploadProductImage('gambar', $isCreate);
        if ($gambar === null && !$isCreate) {
            $existingStmt = $conn->prepare('SELECT gambar FROM kartu_produk WHERE id = ? LIMIT 1');
            if (!$existingStmt) {
                error_log('Gagal menyiapkan gambar kartu: ' . $conn->error);
                productRedirect('Data kartu tidak dapat dibaca. Silakan coba lagi.', 'danger', $oldInput);
            }
            $existingStmt->bind_param('i', $id);
            if (!$existingStmt->execute()) {
                error_log('Gagal membaca gambar kartu: ' . $existingStmt->error);
                $existingStmt->close();
                productRedirect('Data kartu tidak dapat dibaca. Silakan coba lagi.', 'danger', $oldInput);
            }
            $existingStmt->bind_result($gambar);
            if (!$existingStmt->fetch()) {
                $existingStmt->close();
                productRedirect('Kartu yang akan diubah tidak ditemukan.', 'warning', $oldInput);
            }
            $existingStmt->close();
        }

        if (!is_string($gambar) || $gambar === '') {
            productRedirect('Gambar kartu wajib dipilih atau sudah ada.', 'warning', $oldInput);
        }

        $duplicateStmt = $conn->prepare(
            'SELECT id FROM kartu_produk WHERE harga = ? AND aktif = 1 AND (? IS NULL OR id <> ?) LIMIT 1'
        );
        if (!$duplicateStmt) {
            error_log('Gagal menyiapkan pemeriksaan harga kartu: ' . $conn->error);
            productRedirect('Kartu tidak dapat divalidasi. Silakan coba lagi.', 'danger', $oldInput);
        }
        $duplicateStmt->bind_param('iii', $harga, $id, $id);
        if (!$duplicateStmt->execute()) {
            error_log('Gagal memeriksa harga kartu: ' . $duplicateStmt->error);
            $duplicateStmt->close();
            productRedirect('Kartu tidak dapat divalidasi. Silakan coba lagi.', 'danger', $oldInput);
        }
        $duplicateStmt->store_result();
        $duplicateExists = $duplicateStmt->num_rows > 0;
        $duplicateStmt->close();
        if ($duplicateExists) {
            productRedirect('Harga kartu harus berbeda karena dipakai sebagai kode produk.', 'warning', $oldInput);
        }

        if ($id === false || $id === null) {
            $stmt = $conn->prepare(
                'INSERT INTO kartu_produk (kategori, badge, nama, harga, deskripsi, gambar) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                error_log('Gagal menyiapkan penambahan kartu: ' . $conn->error);
                productRedirect('Kartu gagal disimpan. Silakan coba lagi.', 'danger', $oldInput);
            }
            $stmt->bind_param('sssiss', $kategori, $badge, $nama, $harga, $deskripsi, $gambar);
        } else {
            $stmt = $conn->prepare(
                'UPDATE kartu_produk SET kategori=?, badge=?, nama=?, harga=?, deskripsi=?, gambar=?, aktif=1 WHERE id=?'
            );
            if (!$stmt) {
                error_log('Gagal menyiapkan perubahan kartu: ' . $conn->error);
                productRedirect('Kartu gagal disimpan. Silakan coba lagi.', 'danger', $oldInput);
            }
            $stmt->bind_param('sssissi', $kategori, $badge, $nama, $harga, $deskripsi, $gambar, $id);
        }

        $success = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        if (!$success) {
            error_log('Gagal menyimpan kartu: ' . $error);
        }
        productRedirect(
            $success ? 'Kartu berhasil disimpan.' : 'Kartu gagal disimpan. Pastikan harga tidak duplikat.',
            $success ? 'success' : 'danger',
            $success ? [] : $oldInput
        );
    }
}

if (isset($_SESSION['product_message'])) {
    [$message, $messageType] = $_SESSION['product_message'];
    unset($_SESSION['product_message']);
}
$productFormOld = is_array($_SESSION['product_form_old'] ?? null) ? $_SESSION['product_form_old'] : [];
unset($_SESSION['product_form_old']);

$result = $conn->query(
    'SELECT id, kategori, badge, nama, harga, deskripsi, gambar
     FROM kartu_produk WHERE aktif = 1 ORDER BY id ASC'
);
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
    <title>Kelola Kartu Produk</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Kelola Kartu Produk</h1>
        <a href="data_pendaftar.php" class="btn btn-outline-primary">Kembali ke Data Pendaftar</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $escape($messageType) ?>"><?= $escape($message) ?></div>
    <?php endif; ?>

    <datalist id="badgeOptions">
        <option value="🎫 Tiket"></option>
        <option value="🌟 Tiket VIP"></option>
        <option value="👕 Merchandise"></option>
        <option value="🛍️ Merchandise"></option>
        <option value="📚 Edukasi"></option>
        <option value="🖼️ Aksesori"></option>
        <option value="🔥 Populer"></option>
        <option value="✨ Baru"></option>
        <option value="💡 Pilihan"></option>
        <option value="🎁 Promo"></option>
    </datalist>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">Tambah Kartu Baru</div>
        <form method="post" enctype="multipart/form-data" class="card-body row g-3">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <div class="col-md-4"><label class="form-label">Nama</label><input name="nama" maxlength="150" class="form-control" value="<?= $escape((string)($productFormOld['nama'] ?? '')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Kategori</label><input name="kategori" maxlength="50" class="form-control" placeholder="tiket" value="<?= $escape((string)($productFormOld['kategori'] ?? '')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Badge</label><input name="badge" maxlength="100" list="badgeOptions" class="form-control" placeholder="Pilih atau ketik, contoh: 🔥 Promo" value="<?= $escape((string)($productFormOld['badge'] ?? '')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Harga</label><input type="number" name="harga" min="1" max="2147483647" class="form-control" value="<?= $escape((string)($productFormOld['harga'] ?? '')) ?>" required></div>
            <div class="col-md-8"><label class="form-label">Gambar</label><input type="file" name="gambar" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control" required><small class="text-muted">Pilih JPG, PNG, GIF, atau WEBP (maksimal 5 MB).</small></div>
            <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="deskripsi" maxlength="5000" class="form-control" required><?= $escape((string)($productFormOld['deskripsi'] ?? '')) ?></textarea></div>
            <div class="col-12"><button class="btn btn-success">Tambah Kartu</button></div>
        </form>
    </div>

    <div class="row g-4">
        <?php foreach ($products as $product): ?>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <?php $imageUrl = ltrim(str_replace('../', '', $product['gambar']), '/'); ?>
                        <form method="post" enctype="multipart/form-data" class="row g-2">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                            <div class="col-8"><input name="nama" class="form-control" value="<?= $escape($product['nama']) ?>" required></div>
                            <div class="col-4"><input type="number" name="harga" min="1" class="form-control" value="<?= (int)$product['harga'] ?>" required></div>
                            <div class="col-6"><input name="kategori" class="form-control" value="<?= $escape($product['kategori']) ?>" required></div>
                            <div class="col-6"><input name="badge" list="badgeOptions" class="form-control" value="<?= $escape($product['badge']) ?>" required></div>
                            <div class="col-12">
                                <label class="form-label">Gambar saat ini</label>
                                <div class="mb-2"><img src="<?= $escape($imageUrl) ?>" alt="<?= $escape($product['nama']) ?>" style="max-height: 100px; max-width: 180px; object-fit: cover;"></div>
                                <input type="file" name="gambar" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control">
                                <small class="text-muted">Kosongkan jika tidak ingin mengganti gambar.</small>
                            </div>
                            <div class="col-12"><textarea name="deskripsi" class="form-control" required><?= $escape($product['deskripsi']) ?></textarea></div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                                <form method="post" onsubmit="return confirm('Hapus kartu ini?');">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                    <button class="btn btn-outline-danger">Hapus</button>
                                </form>
                            </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
