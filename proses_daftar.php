<?php
require 'config.php';
include 'koneksi.php';
require 'inventory.php';
require 'produk.php';

header('Content-Type: application/json; charset=utf-8');

if (!$conn instanceof mysqli) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database sedang tidak tersedia. Pastikan MySQL XAMPP aktif.',
    ]);
    exit();
}

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Metode permintaan tidak diizinkan.');
}

$nama_input = $_POST['nama'] ?? null;
$email_input = $_POST['email'] ?? null;
$pesan_input = $_POST['pesan'] ?? '';
$persetujuan = $_POST['persetujuan'] ?? null;
$layanan = filter_input(INPUT_POST, 'layanan', FILTER_VALIDATE_INT);
$jumlah = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);
$produk = [];
if ($conn instanceof mysqli) {
    foreach (getActiveProducts($conn) as $product) {
        $produk[] = $product->getPrice();
    }
}

if (!is_string($nama_input) || !is_string($email_input) || !is_string($pesan_input)) {
    respond(400, false, 'Data pendaftaran tidak valid. Periksa kembali formulir Anda.');
}

$nama = trim($nama_input);
$email = trim($email_input);
$pesan = trim($pesan_input);

if (
    $nama === ''
    || strlen($nama) < 3
    || strlen($nama) > 150
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 254
    || strlen($pesan) > 1000
    || $layanan === false
    || $layanan === null
    || !in_array($layanan, $produk, true)
    || $jumlah === false
    || $jumlah === null
    || $jumlah < 1
    || $persetujuan !== '1'
) {
    respond(400, false, 'Data pendaftaran tidak valid. Periksa kembali formulir Anda.');
}

if ($jumlah > MAX_ITEMS_PER_PRODUCT) {
    respond(400, false, 'Maksimal pemesanan adalah 100 item untuk setiap produk.');
}

$lockAcquired = lockProductInventory($conn, $layanan);
if ($lockAcquired === null) {
    respond(500, false, 'Stok produk tidak dapat diperiksa. Silakan coba lagi.');
}
if (!$lockAcquired) {
    respond(503, false, 'Pemesanan sedang ramai. Silakan coba lagi.');
}

$stmt = $conn->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM pendaftaran WHERE layanan = ?');
if (!$stmt) {
    error_log('Gagal menyiapkan pemeriksaan stok: ' . $conn->error);
    unlockProductInventory($conn, $layanan);
    respond(500, false, 'Stok produk tidak dapat diperiksa. Silakan coba lagi.');
}

$stmt->bind_param('i', $layanan);
if (!$stmt->execute()) {
    error_log('Gagal memeriksa stok produk: ' . $stmt->error);
    $stmt->close();
    unlockProductInventory($conn, $layanan);
    respond(500, false, 'Stok produk tidak dapat diperiksa. Silakan coba lagi.');
}

$stmt->bind_result($jumlahTerjual);
$stmt->fetch();
$stmt->close();
$sisaStok = MAX_ITEMS_PER_PRODUCT - (int)$jumlahTerjual;

if ($sisaStok <= 0) {
    unlockProductInventory($conn, $layanan);
    respond(409, false, 'Maaf, produk ini sudah habis.');
}
if ($jumlah > $sisaStok) {
    unlockProductInventory($conn, $layanan);
    respond(409, false, 'Stok tersisa hanya ' . $sisaStok . ' item.');
}

$stmt = $conn->prepare(
    'INSERT INTO pendaftaran (nama, email, layanan, jumlah, pesan) VALUES (?, ?, ?, ?, ?)'
);
if (!$stmt) {
    error_log('Gagal menyiapkan penyimpanan pendaftaran: ' . $conn->error);
    unlockProductInventory($conn, $layanan);
    respond(500, false, 'Pendaftaran gagal disimpan. Silakan coba lagi.');
}

$stmt->bind_param('ssiis', $nama, $email, $layanan, $jumlah, $pesan);
if (!$stmt->execute()) {
    error_log('Gagal menyimpan pendaftaran: ' . $stmt->error);
    $stmt->close();
    unlockProductInventory($conn, $layanan);
    respond(500, false, 'Pendaftaran gagal disimpan. Silakan coba lagi.');
}

$stmt->close();
unlockProductInventory($conn, $layanan);
$conn->close();

respond(200, true, 'Pendaftaran berhasil.');