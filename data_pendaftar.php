<?php
require 'config.php';
require 'koneksi.php';
require 'admin_auth.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, "/");
session_start();

if (
    isset($_SESSION['admin_logged_in'])
    && $_SESSION['admin_logged_in'] === true
    && (!isset($_SESSION['session_expires_at']) || time() >= $_SESSION['session_expires_at'])
) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}

// -----------------------------------------------------
// Pintu masuk admin menggunakan URL data_pendaftar.php
// -----------------------------------------------------
if (
    !isset($_SESSION['admin_logged_in'], $_SESSION['session_expires_at'])
    || $_SESSION['admin_logged_in'] !== true
) {
    if (!defined('ADMIN_LOGIN_PAGE')) {
        header('Location: login.php');
        exit();
    }

    $loginError = '';
    $loginSuccess = '';
    $expiredMessage = isset($_GET['expired']) ? 'Sesi Anda telah berakhir. Silakan login kembali.' : '';

    if ($conn instanceof mysqli && !ensureAdminTable($conn)) {
        $loginError = 'Sistem akun belum tersedia. Pastikan database aktif.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn instanceof mysqli) {
        $action = $_POST['action'] ?? 'login';
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($action === 'register') {
            $confirmation = (string)($_POST['password_confirmation'] ?? '');
            if (
                !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)
                || strlen($password) < 8
                || strlen($password) > 255
                || $password !== $confirmation
            ) {
                $loginError = 'Username hanya boleh 3-80 karakter dan password minimal 8 karakter.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
                if (!$stmt) {
                    error_log('Gagal menyiapkan pendaftaran akun: ' . $conn->error);
                    $loginError = 'Pendaftaran gagal. Silakan coba lagi.';
                } else {
                    $stmt->bind_param('ss', $username, $passwordHash);
                    if ($stmt->execute()) {
                        $loginSuccess = 'Pendaftaran berhasil. Tunggu admin utama mengangkat akun Anda menjadi admin.';
                    } elseif ($stmt->errno === 1062) {
                        $loginError = 'Username sudah digunakan.';
                    } else {
                        error_log('Gagal mendaftarkan akun: ' . $stmt->error);
                        $loginError = 'Pendaftaran gagal. Silakan coba lagi.';
                    }
                    $stmt->close();
                }
            }
        } else {
            $admin = authenticateAdmin($conn, $username, $password);
            if ($admin !== null) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_is_main'] = $admin['is_main'];
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['session_expires_at'] = time() + SESSION_TIMEOUT_SECONDS;
                header('Location: data_pendaftar.php');
                exit();
            }
            $loginError = 'Username atau password salah, atau akun belum diberi hak admin.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panel Admin - Seminar UHW</title>
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                min-height: 100vh;
                background: linear-gradient(135deg, #071b3a, #0d6efd);
            }
            .admin-entry {
                max-width: 430px;
                border: 0;
                border-radius: 24px;
                overflow: hidden;
                animation: entry-up .7s ease both;
            }
            .admin-icon { animation: icon-float 3s ease-in-out infinite; }
            @keyframes entry-up {
                from { opacity: 0; transform: translateY(24px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes icon-float {
                0%, 100% { transform: translateY(0) rotate(-4deg); }
                50% { transform: translateY(-7px) rotate(4deg); }
            }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center p-3">
        <main class="card shadow-lg admin-entry w-100">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="fas fa-user-shield fa-3x text-primary admin-icon"></i>
                    <h1 class="h3 fw-bold mt-3 mb-1">Panel Admin</h1>
                    <p class="text-muted mb-0">Login untuk membuka panel administrasi</p>
                </div>
                <?php if ($expiredMessage): ?>
                    <div class="alert alert-warning py-2"><?= htmlspecialchars($expiredMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($loginSuccess): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($loginSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($loginError): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div id="loginSection">
                <form method="POST" id="adminLoginForm">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control form-control-lg" minlength="3" maxlength="80" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control form-control-lg" required>
                    </div>
                    <button class="btn btn-primary btn-lg rounded-pill w-100 fw-bold" type="submit">
                        <i class="fas fa-right-to-bracket me-2"></i>Masuk ke Panel
                    </button>
                </form>
                </div>
                <button type="button" class="btn btn-link w-100 mt-2" id="toggleRegister">Belum punya akun? Daftar</button>
                <form method="POST" id="registerForm" class="d-none mt-3" hidden>
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="username" class="form-control mb-2" minlength="3" maxlength="80" placeholder="Username baru" required>
                    <input type="password" name="password" class="form-control mb-2" minlength="8" maxlength="255" placeholder="Password (min. 8 karakter)" required>
                    <input type="password" name="password_confirmation" class="form-control mb-3" minlength="8" maxlength="255" placeholder="Ulangi password" required>
                    <button class="btn btn-success rounded-pill w-100 fw-bold" type="submit">Daftar Akun</button>
                </form>
                <a href="index.html" class="btn btn-outline-secondary rounded-pill w-100 mt-3">
                    <i class="fas fa-house me-2"></i>Kembali ke Dashboard Utama
                </a>
            </div>
        </main>
        <script>
            const toggleRegister = document.getElementById('toggleRegister');
            const loginSection = document.getElementById('loginSection');
            const registerForm = document.getElementById('registerForm');
            toggleRegister.addEventListener('click', () => {
                registerForm.classList.toggle('d-none');
                registerForm.hidden = registerForm.classList.contains('d-none');
                loginSection.hidden = !registerForm.hidden;
                toggleRegister.textContent = registerForm.hidden ? 'Belum punya akun? Daftar' : 'Kembali ke Login';
            });
        </script>
    </body>
    </html>
    <?php exit();
}

function redirectWithUpdateNotification(string $message, string $type, array $oldInput = []): void
{
    $_SESSION['update_notification'] = [
        'message' => $message,
        'type' => $type,
    ];
    if ($oldInput !== []) {
        $_SESSION['update_form_old'] = $oldInput;
    } else {
        unset($_SESSION['update_form_old']);
    }
    header('Location: data_pendaftar.php');
    exit();
}

require_once 'produk.php';
require_once 'maintenance.php';

// ... (sisa kode di bawahnya biarkan tetap sama) ...
$update_notification = $_SESSION['update_notification'] ?? null;
unset($_SESSION['update_notification']);
$form_old = is_array($_SESSION['update_form_old'] ?? null) ? $_SESSION['update_form_old'] : [];
unset($_SESSION['update_form_old']);

if (!$conn instanceof mysqli) {
    http_response_code(503);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Database tidak tersedia</title>';
    echo '<link rel="stylesheet" href="assets/css/bootstrap.min.css"></head><body class="bg-light">';
    echo '<main class="container py-5"><div class="alert alert-warning shadow-sm">';
    echo '<h1 class="h4">Database belum tersedia</h1>';
    echo '<p class="mb-0">Pastikan MySQL XAMPP aktif dan konfigurasi database sudah benar.</p>';
    echo '</div></main></body></html>';
    exit();
}

if (!ensureProductTable($conn)) {
    http_response_code(503);
    exit('Katalog produk belum tersedia.');
}
$maintenance_mode = isMaintenanceMode($conn);
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_action'])) {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        redirectWithUpdateNotification('Permintaan maintenance tidak valid.', 'danger');
    }
    $enable = $_POST['maintenance_action'] === 'enable';
    if (!setMaintenanceMode($conn, $enable)) {
        redirectWithUpdateNotification('Status maintenance gagal diubah.', 'danger');
    }
    redirectWithUpdateNotification(
        $enable ? 'Website sekarang dalam mode maintenance.' : 'Website sudah dinyalakan kembali.',
        'success'
    );
}
$produk_katalog = getActiveProducts($conn);
$produk_valid = array_map(
    static fn(Product $product): int => $product->getPrice(),
    $produk_katalog
);

// -----------------------------------------------------
// 1. FITUR HAPUS DATA (SATUAN)
// -----------------------------------------------------
if(isset($_GET['hapus'])) {
    $waktu_hapus = $_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM pendaftaran WHERE waktu_daftar = ?");
    $stmt->bind_param("s", $waktu_hapus);
    
    if ($stmt->execute()) {
        header("Location: data_pendaftar.php");
        exit();
    } else {
        echo "<script>alert('Gagal menghapus data!');</script>";
    }
    $stmt->close();
}

// -----------------------------------------------------
// 2. FITUR HAPUS DATA MASSAL (BANYAK SEKALIGUS)
// -----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_hapus_massal'])) {
    if (!empty($_POST['hapus_pilih'])) {
        $data_terpilih = $_POST['hapus_pilih']; // Array dari checkbox
        
        // Membuat string parameter "?, ?, ?" sesuai jumlah data yang dipilih
        $placeholders = implode(',', array_fill(0, count($data_terpilih), '?'));
        
        // Mempersiapkan statement delete
        $stmt = $conn->prepare("DELETE FROM pendaftaran WHERE waktu_daftar IN ($placeholders)");
        
        // Menentukan tipe data string ("s") sesuai jumlah data
        $types = str_repeat('s', count($data_terpilih));
        
        // Binding parameter dinamis
        $stmt->bind_param($types, ...$data_terpilih);
        
        if ($stmt->execute()) {
            echo "<script>alert('Data yang dipilih berhasil dihapus!'); window.location.href='data_pendaftar.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data massal!');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Pilih minimal satu data untuk dihapus!'); window.location.href='data_pendaftar.php';</script>";
    }
}

// -----------------------------------------------------
// 3. FITUR TAMBAH PENDAFTAR
// -----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btn_tambah'])) {
    require 'inventory.php';

    $nama_input = $_POST['tambah_nama'] ?? null;
    $email_input = $_POST['tambah_email'] ?? null;
    $pesan_input = $_POST['tambah_pesan'] ?? '';
    $layanan_baru = filter_input(INPUT_POST, 'tambah_layanan', FILTER_VALIDATE_INT);
    $jumlah_baru = filter_input(INPUT_POST, 'tambah_jumlah', FILTER_VALIDATE_INT);
    if (!is_string($nama_input) || !is_string($email_input) || !is_string($pesan_input)) {
        redirectWithUpdateNotification('Data pendaftar tidak valid. Periksa kembali formulir.', 'warning');
    }
    $nama_baru = trim($nama_input);
    $email_baru = trim($email_input);
    $pesan_baru = trim($pesan_input);
    if (
        $nama_baru === ''
        || strlen($nama_baru) < 3
        || strlen($nama_baru) > 150
        || !filter_var($email_baru, FILTER_VALIDATE_EMAIL)
        || strlen($email_baru) > 254
        || strlen($pesan_baru) > 1000
        || $layanan_baru === false
        || $layanan_baru === null
        || !in_array($layanan_baru, $produk_valid, true)
        || $jumlah_baru === false
        || $jumlah_baru === null
        || $jumlah_baru < 1
        || $jumlah_baru > MAX_ITEMS_PER_PRODUCT
    ) {
        redirectWithUpdateNotification('Nama (3-150 karakter), email, produk, jumlah, dan pesan harus diisi dengan format yang benar.', 'warning', [
            'tambah_nama' => $nama_baru,
            'tambah_email' => $email_baru,
            'tambah_layanan' => $layanan_baru === false ? '' : (string)$layanan_baru,
            'tambah_jumlah' => $jumlah_baru === false ? '' : (string)$jumlah_baru,
            'tambah_pesan' => $pesan_baru,
        ]);
    }

    $lockAcquired = lockProductInventory($conn, $layanan_baru);
    if ($lockAcquired !== true) {
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }

    $stmt = $conn->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM pendaftaran WHERE layanan = ?');
    if (!$stmt) {
        error_log('Gagal menyiapkan pemeriksaan stok pendaftar baru: ' . $conn->error);
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param('i', $layanan_baru);
    if (!$stmt->execute()) {
        error_log('Gagal memeriksa stok pendaftar baru: ' . $stmt->error);
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_result($jumlahTerpakai);
    $stmt->fetch();
    $stmt->close();

    $sisaStok = MAX_ITEMS_PER_PRODUCT - (int)$jumlahTerpakai;
    if ($sisaStok <= 0 || $jumlah_baru > $sisaStok) {
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification(
            $sisaStok <= 0 ? 'Produk ini sudah habis.' : 'Stok tersisa hanya ' . $sisaStok . ' item.',
            'warning'
        );
    }

    $stmt = $conn->prepare(
        'INSERT INTO pendaftaran (nama, email, layanan, jumlah, pesan) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('Gagal menyiapkan penyimpanan pendaftar baru: ' . $conn->error);
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Pendaftar tidak dapat disimpan. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param('ssiis', $nama_baru, $email_baru, $layanan_baru, $jumlah_baru, $pesan_baru);

    if ($stmt->execute()) {
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        unset($_SESSION['update_form_old']);
        redirectWithUpdateNotification('Pendaftar berhasil ditambahkan!', 'success');
    }

    error_log('Gagal menyimpan pendaftar baru: ' . $stmt->error);
    $stmt->close();
    unlockProductInventory($conn, $layanan_baru);
    redirectWithUpdateNotification('Pendaftar gagal ditambahkan. Silakan coba lagi.', 'danger');
}

// -----------------------------------------------------
// 4. FITUR EDIT (UPDATE) DATA
// -----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_update'])) {
    require 'inventory.php';

    $nama_baru = trim((string)($_POST['edit_nama'] ?? ''));
    $email_baru = trim((string)($_POST['edit_email'] ?? ''));
    $layanan_baru = filter_input(INPUT_POST, 'edit_layanan', FILTER_VALIDATE_INT);
    $jumlah_baru = filter_input(INPUT_POST, 'edit_jumlah', FILTER_VALIDATE_INT);
    $pesan_baru = trim((string)($_POST['edit_pesan'] ?? ''));
    $waktu_acuan = trim((string)($_POST['waktu_lama'] ?? ''));

    if (
        $nama_baru === ''
        || strlen($nama_baru) < 3
        || strlen($nama_baru) > 150
        || !filter_var($email_baru, FILTER_VALIDATE_EMAIL)
        || strlen($email_baru) > 254
        || strlen($pesan_baru) > 1000
        || $waktu_acuan === ''
        || $layanan_baru === false
        || $layanan_baru === null
        || !in_array($layanan_baru, $produk_valid, true)
        || $jumlah_baru === false
        || $jumlah_baru === null
        || $jumlah_baru < 1
        || $jumlah_baru > MAX_ITEMS_PER_PRODUCT
    ) {
        redirectWithUpdateNotification('Nama, email, produk, jumlah, pesan, dan waktu pendaftar harus diisi dengan format yang benar.', 'warning', [
            'edit_nama' => $nama_baru,
            'edit_email' => $email_baru,
            'edit_layanan' => $layanan_baru === false ? '' : (string)$layanan_baru,
            'edit_jumlah' => $jumlah_baru === false ? '' : (string)$jumlah_baru,
            'edit_pesan' => $pesan_baru,
            'waktu_lama' => $waktu_acuan,
        ]);
    }

    $lockAcquired = lockProductInventory($conn, $layanan_baru);
    if ($lockAcquired !== true) {
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }

    $stmt = $conn->prepare(
        'SELECT nama, email, layanan, jumlah, pesan FROM pendaftaran WHERE waktu_daftar = ?'
    );
    if (!$stmt) {
        error_log('Gagal menyiapkan pemeriksaan data edit: ' . $conn->error);
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Data tidak dapat diperbarui. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param('s', $waktu_acuan);
    if (!$stmt->execute()) {
        error_log('Gagal memeriksa data edit: ' . $stmt->error);
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Data tidak dapat diperbarui. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_result($nama_lama, $email_lama, $layanan_lama, $jumlah_lama, $pesan_lama);
    $data_ditemukan = $stmt->fetch();
    $stmt->close();

    if (!$data_ditemukan) {
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Data peserta tidak ditemukan.', 'danger');
    }

    if (
        $nama_baru === $nama_lama
        && $email_baru === $email_lama
        && $layanan_baru === (int)$layanan_lama
        && $jumlah_baru === (int)$jumlah_lama
        && $pesan_baru === $pesan_lama
    ) {
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Tidak ada perubahan yang disimpan.', 'info');
    }

    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(jumlah), 0) FROM pendaftaran WHERE layanan = ? AND waktu_daftar <> ?'
    );
    if (!$stmt) {
        error_log('Gagal menyiapkan pemeriksaan kapasitas edit: ' . $conn->error);
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param('is', $layanan_baru, $waktu_acuan);
    if (!$stmt->execute()) {
        error_log('Gagal memeriksa kapasitas edit: ' . $stmt->error);
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Stok produk tidak dapat diperiksa. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_result($jumlahTerpakai);
    $stmt->fetch();
    $stmt->close();

    $totalBaru = (int)$jumlahTerpakai + $jumlah_baru;
    if ($totalBaru > MAX_ITEMS_PER_PRODUCT) {
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Perubahan ditolak. Batas stok produk adalah 100 item.', 'warning');
    }

    $stmt = $conn->prepare("UPDATE pendaftaran SET nama=?, email=?, layanan=?, jumlah=?, pesan=? WHERE waktu_daftar=?");
    if (!$stmt) {
        error_log('Gagal menyiapkan pembaruan data: ' . $conn->error);
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Data tidak dapat diperbarui. Silakan coba lagi.', 'danger');
    }
    $stmt->bind_param("ssiiss", $nama_baru, $email_baru, $layanan_baru, $jumlah_baru, $pesan_baru, $waktu_acuan);

    if ($stmt->execute()) {
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Data berhasil diperbarui!', 'success');
    } else {
        error_log('Gagal memperbarui data: ' . $stmt->error);
        $stmt->close();
        unlockProductInventory($conn, $layanan_baru);
        redirectWithUpdateNotification('Gagal memperbarui data!', 'danger');
    }
}

// Mapping data layanan untuk menampilkan jenis tiket dengan nama yang lebih jelas
$jenis_tiket_map = [];
foreach ($produk_katalog as $product) {
    $jenis_tiket_map[(string)$product->getPrice()] = $product->getName();
}

// Mengambil semua data dari tabel pendaftaran
$sql = "SELECT * FROM pendaftaran ORDER BY waktu_daftar DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pendaftar Seminar</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --admin-primary: #1769e0;
            --admin-ink: #172033;
            --admin-muted: #687386;
            --admin-border: #e5eaf2;
        }

        body {
            color: var(--admin-ink);
            min-height: 100vh;
            background:
                radial-gradient(circle at 8% 12%, rgba(52, 152, 219, .18), transparent 30%),
                radial-gradient(circle at 92% 8%, rgba(118, 75, 162, .16), transparent 28%),
                linear-gradient(135deg, #eef5ff 0%, #f7f3ff 48%, #e8f4f5 100%) !important;
            background-attachment: fixed;
        }

        .admin-shell {
            max-width: 1440px;
        }

        .admin-statusbar {
            min-height: 42px;
            justify-content: flex-end !important;
            gap: .75rem;
        }

        .admin-card {
            overflow: hidden;
            border: 1px solid var(--admin-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 12px 35px rgba(27, 44, 75, .10) !important;
        }

        .admin-card-header {
            background: linear-gradient(110deg, #1769e0, #0d8bf2);
            gap: 1rem;
        }

        .admin-card-title {
            letter-spacing: -.02em;
            white-space: nowrap;
        }

        .admin-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .5rem;
        }

        .admin-actions .btn {
            margin: 0 !important;
            border: 0;
            min-height: 36px;
            font-size: .82rem;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .admin-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(15, 33, 65, .18);
        }

        .admin-table {
            margin-bottom: 0;
        }

        .admin-table thead th {
            padding: 1rem .75rem;
            color: #dce8f8;
            background: #17212f;
            border: 0;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .admin-table tbody td {
            padding: .8rem .75rem;
            border-color: var(--admin-border);
            vertical-align: middle;
        }

        .admin-table tbody tr {
            transition: background-color .18s ease;
        }

        .admin-table tbody tr:hover {
            background-color: #eef5ff;
        }

        .admin-table .btn {
            min-width: 72px;
        }

        .session-badge,
        .maintenance-form .btn {
            box-shadow: 0 4px 12px rgba(27, 44, 75, .12);
        }

        @media (max-width: 992px) {
            .admin-card-header {
                align-items: flex-start !important;
                flex-direction: column;
            }

            .admin-actions {
                justify-content: flex-start;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .admin-shell {
                margin-top: 1.5rem !important;
            }

            .admin-actions .btn {
                flex: 1 1 auto;
            }

            .admin-statusbar {
                justify-content: flex-end !important;
                align-items: flex-end !important;
                flex-direction: row;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="bg-light">

    <?php $session_deadline = $_SESSION['session_expires_at']; ?>
    <div class="container admin-shell mt-5 mb-5">
        <div class="admin-statusbar d-flex justify-content-between align-items-center mb-3">
            <form method="post" class="d-inline" onsubmit="return confirm('Ubah status maintenance website?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="maintenance_action" value="<?php echo $maintenance_mode ? 'disable' : 'enable'; ?>" class="btn btn-sm <?php echo $maintenance_mode ? 'btn-success' : 'btn-secondary'; ?> rounded-pill fw-bold">
                    <i class="fas fa-tools me-1"></i>
                    <?php echo $maintenance_mode ? 'Nyalakan Website' : 'Maintenance'; ?>
                </button>
            </form>
            <span id="session-countdown" class="session-badge badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                <i class="fas fa-clock me-1"></i> Sesi aktif: 05:00
            </span>
        </div>
        <form action="backup.php" method="post" id="formBackup" class="d-none">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        </form>
        <div class="card admin-card shadow border-0">
            
            <!-- FORM UNTUK HAPUS MASSAL (Membungkus tabel) -->
            <form action="data_pendaftar.php" method="POST" id="formHapusMassal">
                
                <div class="card-header admin-card-header text-white d-flex justify-content-between align-items-center p-3 p-lg-4">
                    <h4 class="admin-card-title mb-0 fw-bold"><i class="fas fa-users me-2"></i>Daftar Peserta Seminar</h4>
                    <div class="admin-actions">
                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 me-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPendaftar">
                            <i class="fas fa-user-plus me-1"></i> Tambah Pendaftar
                        </button>
                        <a href="admin_kartu.php" class="btn btn-sm btn-info fw-bold px-3 me-2 rounded-pill shadow-sm text-dark">Kelola Kartu</a>
                        <a href="admin_users.php" class="btn btn-sm btn-warning fw-bold px-3 me-2 rounded-pill shadow-sm">Kelola Akun</a>
                        <button type="submit" form="formBackup" class="btn btn-sm btn-secondary fw-bold px-3 me-2 rounded-pill shadow-sm" onclick="return confirm('Unduh backup database sekarang?');">
                            <i class="fas fa-download me-1"></i> Backup
                        </button>
                        <button type="button" class="btn btn-sm btn-dark fw-bold px-3 me-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportBackup">
                            <i class="fas fa-upload me-1"></i> Import
                        </button>
                        <!-- Tombol Hapus Massal -->
                        <button type="submit" name="btn_hapus_massal" class="btn btn-sm btn-danger fw-bold px-3 me-2 rounded-pill shadow-sm" onclick="return confirm('Yakin ingin menghapus semua data yang dipilih?');">
                            <i class="fas fa-trash-alt me-1"></i> Hapus Terpilih
                        </button>
                        <a href="index.html" class="btn btn-sm btn-light fw-bold text-primary px-3 rounded-pill shadow-sm me-2">Web Utama</a>
                        <a href="logout.php" class="btn btn-sm btn-outline-light fw-bold px-3 rounded-pill shadow-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table admin-table table-hover table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <!-- Checkbox Master (Pilih Semua) -->
                                    <th class="py-3 px-3 text-center" style="width: 50px;">
                                        <input class="form-check-input shadow-sm" type="checkbox" id="checkAll">
                                    </th>
                                    <th class="py-3">No</th>
                                    <th class="py-3">Nama Lengkap</th>
                                    <th class="py-3">Email</th>
                                    <th class="py-3">Jenis Tiket</th>
                                    <th class="py-3">Total Harga</th>
                                    <th class="py-3 text-center">Jml</th>
                                    <th class="py-3">Pesan Tambahan</th>
                                    <th class="py-3">Waktu Daftar</th>
                                    <th class="py-3 text-center">Aksi (Edit / Hapus)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    $no = 1;
                                    while($row = $result->fetch_assoc()) {
                                        $waktu_asli = $row['waktu_daftar'];
                                        
                                        echo "<tr>";
                                        
                                        // Checkbox Satuan per baris (TANDA KUTIP SUDAH DIPERBAIKI DI SINI)
                                        echo "<td class='px-3 text-center'>
                                                <input class='form-check-input checkItem shadow-sm' type='checkbox' name='hapus_pilih[]' value='" . htmlspecialchars($waktu_asli) . "'>
                                              </td>";
                                              
                                        echo "<td class='fw-bold'>" . $no++ . "</td>";
                                        echo "<td><span class='fw-semibold'>" . htmlspecialchars($row['nama']) . "</span></td>";
                                        echo "<td>" . htmlspecialchars($row['email']) . "</td>";

                                        $nama_tiket = $jenis_tiket_map[(string)$row['layanan']] ?? 'Lainnya';
                                        echo "<td><span class='badge bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25'>" . htmlspecialchars($nama_tiket) . "</span></td>";

                                        $total_harga = (int)$row['layanan'] * (int)$row['jumlah'];
                                        echo "<td><span class='badge bg-success bg-opacity-10 text-success fs-6 border border-success border-opacity-25'>Rp " . number_format($total_harga, 0, ',', '.') . "</span></td>";
                                        echo "<td class='text-center fw-bold'>" . $row['jumlah'] . "</td>";
                                        
                                        $pesan = htmlspecialchars($row['pesan']);
                                        $pesan_singkat = strlen($pesan) > 30 ? substr($pesan, 0, 30) . "..." : $pesan;
                                        echo "<td><small class='text-muted'>" . $pesan_singkat . "</small></td>";
                                        
                                        echo "<td><small class='text-secondary'><i class='far fa-clock me-1'></i>" . date('d-M-Y H:i', strtotime($waktu_asli)) . "</small></td>";
                                        
                                        echo "<td class='text-center'>";
                                        
                                        // Tombol Edit
                                        echo "<button type='button' class='btn btn-warning btn-sm rounded-pill px-3 shadow-sm me-1 fw-bold' 
                                              onclick=\"bukaModalEdit('" . addslashes($row['nama']) . "', '" . addslashes($row['email']) . "', '" . addslashes($row['layanan']) . "', '" . addslashes($row['jumlah']) . "', '" . addslashes($row['pesan']) . "', '" . urlencode($waktu_asli) . "')\">
                                              <i class='fas fa-edit'></i> Edit</button>";
                                              
                                        // Tombol Hapus Satuan
                                        echo "<a href='data_pendaftar.php?hapus=" . urlencode($waktu_asli) . "' 
                                              class='btn btn-danger btn-sm rounded-pill px-3 shadow-sm fw-bold' 
                                              onclick=\"return confirm('Yakin ingin menghapus data atas nama " . htmlspecialchars($row['nama']) . "?');\">
                                              <i class='fas fa-trash-alt'></i> Hapus</a>";
                                              
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='10' class='text-center py-5 text-muted'><i class='fas fa-box-open fs-1 d-block mb-3 text-black-50'></i>Belum ada pendaftar seminar.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
            <!-- Akhir Form Hapus Massal -->
            
        </div>
    </div>

    <div class="modal fade" id="modalTambahPendaftar" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
          <div class="modal-header bg-success text-white border-0" style="border-radius: 12px 12px 0 0;">
            <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Tambah Pendaftar</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="data_pendaftar.php" method="POST">
            <div class="modal-body p-4">
              <div class="mb-3">
                <label for="tambah_nama" class="form-label fw-bold">Nama Lengkap</label>
                <input type="text" class="form-control" id="tambah_nama" name="tambah_nama" minlength="3" maxlength="150" value="<?= htmlspecialchars((string)($form_old['tambah_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="mb-3">
                <label for="tambah_email" class="form-label fw-bold">Email</label>
                <input type="email" class="form-control" id="tambah_email" name="tambah_email" maxlength="254" value="<?= htmlspecialchars((string)($form_old['tambah_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="mb-3">
                <label for="tambah_layanan" class="form-label fw-bold">Jenis Tiket / Produk</label>
                <select class="form-select" id="tambah_layanan" name="tambah_layanan" required>
                  <option value="">-- Pilih Produk/Layanan --</option>
                  <?php foreach ($produk_katalog as $product): ?>
                  <option value="<?= $product->getPrice() ?>" <?= (string)($form_old['tambah_layanan'] ?? '') === (string)$product->getPrice() ? 'selected' : '' ?>><?= htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="tambah_jumlah" class="form-label fw-bold">Jumlah</label>
                <input type="number" class="form-control" id="tambah_jumlah" name="tambah_jumlah" min="1" max="100" step="1" value="<?= htmlspecialchars((string)($form_old['tambah_jumlah'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="mb-3">
                <label for="tambah_pesan" class="form-label fw-bold">Pesan Tambahan</label>
                <textarea class="form-control" id="tambah_pesan" name="tambah_pesan" maxlength="1000" rows="3"><?= htmlspecialchars((string)($form_old['tambah_pesan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="btn_tambah" class="btn btn-success rounded-pill px-4 fw-bold">Simpan Pendaftar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL POP-UP UNTUK EDIT DATA -->
    <div class="modal fade" id="modalEditData" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
          <div class="modal-header bg-warning border-0" style="border-radius: 12px 12px 0 0;">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Data Peserta</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <form action="data_pendaftar.php" method="POST" id="formEditData">
              <div class="modal-body p-4">
                  <input type="hidden" id="input_waktu_lama" name="waktu_lama">
                  <div class="mb-3">
                      <label class="form-label fw-bold">Nama Lengkap</label>
                      <input type="text" class="form-control" id="input_edit_nama" name="edit_nama" minlength="3" maxlength="150" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Email</label>
                      <input type="email" class="form-control" id="input_edit_email" name="edit_email" maxlength="254" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Jenis Tiket / Produk</label>
                      <select class="form-select" id="input_edit_layanan" name="edit_layanan" required>
                          <option value="">-- Pilih Produk/Layanan --</option>
                          <?php foreach ($produk_katalog as $product): ?>
                            <option value="<?= $product->getPrice() ?>"><?= htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8') ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Jumlah</label>
                      <input type="number" class="form-control" id="input_edit_jumlah" name="edit_jumlah" min="1" max="100" step="1" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Pesan Tambahan</label>
                      <textarea class="form-control" id="input_edit_pesan" name="edit_pesan" maxlength="1000" rows="3"></textarea>
                  </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="btn_update" id="btnSimpanPerubahan" class="btn btn-primary rounded-pill px-4 fw-bold" disabled>Simpan Perubahan</button>
              </div>
          </form>
          
        </div>
      </div>
    </div>

    <?php if (
        is_array($update_notification)
        && isset($update_notification['message'], $update_notification['type'])
        && is_string($update_notification['message'])
        && is_string($update_notification['type'])
    ): ?>
      <?php
        $notification_type = in_array($update_notification['type'], ['success', 'warning', 'danger', 'info'], true)
            ? $update_notification['type']
            : 'danger';
        $notification_icon = [
            'success' => 'fa-circle-check',
            'warning' => 'fa-triangle-exclamation',
            'danger' => 'fa-circle-xmark',
            'info' => 'fa-circle-info',
        ][$notification_type];
      ?>
      <div class="modal fade" id="modalUpdateNotification" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
          <div class="modal-content border-0 shadow-lg text-center p-3" style="border-radius: 16px;">
            <div class="modal-body">
              <i class="fas <?php echo $notification_icon; ?> text-<?php echo $notification_type; ?> fs-1 mb-3"></i>
              <p class="mb-0 fs-6"><?php echo htmlspecialchars($update_notification['message'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
              <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Oke</button>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="modal fade" id="modalImportBackup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Import Backup Database</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form action="import_backup.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="alert alert-danger small">
                            Import akan mengganti tabel dan data yang terdapat dalam file backup.
                            Pastikan sudah mengunduh backup terbaru sebelum melanjutkan.
                        </div>
                        <label for="backup_file" class="form-label fw-semibold">File backup (.sql)</label>
                        <input type="file" name="backup_file" id="backup_file" class="form-control" accept=".sql" required>
                        <div class="form-text">Maksimal ukuran file 10 MB.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-dark rounded-pill" onclick="return confirm('Yakin import backup? Data saat ini dapat tergantikan.');">
                            Import Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let sessionDeadline = <?php echo $session_deadline; ?>;
        const countdownEl = document.getElementById('session-countdown');

        function formatCountdown(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function updateCountdown() {
            const remaining = Math.max(0, sessionDeadline - Math.floor(Date.now() / 1000));
            countdownEl.innerHTML = '<i class="fas fa-clock me-1"></i> Sesi aktif: ' + formatCountdown(remaining);

            if (remaining <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'data_pendaftar.php?expired=1';
            }
        }

        let countdownInterval = setInterval(updateCountdown, 1000);
        updateCountdown();

        const updateNotificationModal = document.getElementById('modalUpdateNotification');
        if (updateNotificationModal) {
            bootstrap.Modal.getOrCreateInstance(updateNotificationModal).show();
        }

        // 1. Script untuk mengisi form Modal Edit
        function bukaModalEdit(nama, email, layanan, jumlah, pesan, waktu_lama) {
            const formEdit = document.getElementById('formEditData');
            formEdit.elements.edit_nama.value = nama;
            formEdit.elements.edit_email.value = email;
            formEdit.elements.edit_layanan.value = layanan;
            formEdit.elements.edit_jumlah.value = jumlah;
            formEdit.elements.edit_pesan.value = pesan;
            document.getElementById('input_waktu_lama').value = decodeURIComponent(waktu_lama);

            formEdit.dataset.originalValues = JSON.stringify([
                nama,
                email,
                String(layanan),
                String(jumlah),
                pesan
            ]);
            updateSaveButtonState();

            var editModal = new bootstrap.Modal(document.getElementById('modalEditData'));
            editModal.show();
        }

        const formEditData = document.getElementById('formEditData');
        const saveChangesButton = document.getElementById('btnSimpanPerubahan');

        function updateSaveButtonState() {
            const originalValues = JSON.parse(formEditData.dataset.originalValues || '[]');
            const currentValues = [
                formEditData.elements.edit_nama.value,
                formEditData.elements.edit_email.value,
                formEditData.elements.edit_layanan.value,
                formEditData.elements.edit_jumlah.value,
                formEditData.elements.edit_pesan.value
            ];
            saveChangesButton.disabled = currentValues.every(
                (value, index) => value === originalValues[index]
            );
        }

        formEditData.addEventListener('input', updateSaveButtonState);
        formEditData.addEventListener('change', updateSaveButtonState);

        // 2. Script untuk Checkbox "Pilih Semua" (Check All)
        document.getElementById('checkAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.checkItem');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>