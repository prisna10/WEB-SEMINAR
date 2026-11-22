<?php
require 'config.php';
include 'koneksi.php';
require 'inventory.php';

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

class RegistrationException extends RuntimeException
{
    public int $status;

    public function __construct(int $status, string $message)
    {
        $this->status = $status;
        parent::__construct($message);
    }
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
$produk = [
    50000,
    100000,
    75000,
    35000,
    45000,
    30000,
];

if (!is_string($nama_input) || !is_string($email_input) || !is_string($pesan_input)) {
    respond(400, false, 'Data pendaftaran tidak valid. Periksa kembali formulir Anda.');
}

$nama = trim($nama_input);
$email = trim($email_input);
$pesan = trim($pesan_input);

if (
    strlen($nama) < MIN_NAME_LENGTH
    || strlen($nama) > MAX_NAME_LENGTH
    || strlen($email) > 254
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($pesan) > MAX_MESSAGE_LENGTH
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

$lockHeld = false;

try {
    $lockAcquired = lockProductInventory($conn, $layanan);
    if ($lockAcquired === null) {
        throw new RuntimeException('Stok produk tidak dapat diperiksa. Silakan coba lagi.');
    }
    if (!$lockAcquired) {
        throw new RegistrationException(503, 'Pemesanan sedang ramai. Silakan coba lagi.');
    }
    $lockHeld = true;

    $stmt = $conn->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM pendaftaran WHERE layanan = ?');
    if (!$stmt) {
        throw new RuntimeException('Stok produk tidak dapat diperiksa. Silakan coba lagi.');
    }

    $stmt->bind_param('i', $layanan);
    $stmt->execute();
    $stmt->bind_result($jumlahTerjual);
    $stmt->fetch();
    $stmt->close();

    $sisaStok = MAX_ITEMS_PER_PRODUCT - (int)$jumlahTerjual;
    if ($sisaStok <= 0) {
        throw new RegistrationException(409, 'Maaf, produk ini sudah habis.');
    }
    if ($jumlah > $sisaStok) {
        throw new RegistrationException(409, 'Stok tersisa hanya ' . $sisaStok . ' item.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO pendaftaran (nama, email, layanan, jumlah, pesan) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Pendaftaran gagal disimpan. Silakan coba lagi.');
    }

    $stmt->bind_param('ssiis', $nama, $email, $layanan, $jumlah, $pesan);
    $stmt->execute();
    $stmt->close();
    $response = [200, true, 'Pendaftaran berhasil.'];
} catch (RegistrationException $exception) {
    $response = [$exception->status, false, $exception->getMessage()];
} catch (Throwable $exception) {
    error_log('Gagal memproses pendaftaran: ' . $exception->getMessage());
    $response = [500, false, 'Pendaftaran gagal diproses. Silakan coba lagi.'];
} finally {
    if ($lockHeld) {
        unlockProductInventory($conn, $layanan);
    }
    $conn->close();
}

respond($response[0], $response[1], $response[2]);