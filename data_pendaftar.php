<?php
require 'config.php';

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
// KUNCI HALAMAN: Cek apakah admin sudah login
// -----------------------------------------------------
if (
    !isset($_SESSION['admin_logged_in'], $_SESSION['session_expires_at'])
    || $_SESSION['admin_logged_in'] !== true
) {
    // Jika belum login, tendang kembali ke halaman login
    header("Location: login.php");
    exit();
}

function redirectWithUpdateNotification(string $message, string $type): void
{
    $_SESSION['update_notification'] = [
        'message' => $message,
        'type' => $type,
    ];
    header('Location: data_pendaftar.php');
    exit();
}

// Menghubungkan ke database
include 'koneksi.php';

// ... (sisa kode di bawahnya biarkan tetap sama) ...
// Menghubungkan ke database
include 'koneksi.php';
$update_notification = $_SESSION['update_notification'] ?? null;
unset($_SESSION['update_notification']);

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
    $produk_valid = [50000, 100000, 75000, 35000, 45000, 30000];

    if (
        $nama_baru === ''
        || !filter_var($email_baru, FILTER_VALIDATE_EMAIL)
        || $layanan_baru === false
        || $layanan_baru === null
        || !in_array($layanan_baru, $produk_valid, true)
        || $jumlah_baru === false
        || $jumlah_baru === null
        || $jumlah_baru < 1
        || $jumlah_baru > MAX_ITEMS_PER_PRODUCT
    ) {
        redirectWithUpdateNotification('Data pendaftar tidak valid. Periksa kembali formulir.', 'warning');
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

    $nama_baru = $_POST['edit_nama'];
    $email_baru = $_POST['edit_email'];
    $layanan_baru = filter_input(INPUT_POST, 'edit_layanan', FILTER_VALIDATE_INT);
    $jumlah_baru = filter_input(INPUT_POST, 'edit_jumlah', FILTER_VALIDATE_INT);
    $pesan_baru = $_POST['edit_pesan'];
    $waktu_acuan = $_POST['waktu_lama']; 

    $produk_valid = [50000, 100000, 75000, 35000, 45000, 30000];
    if (
        $layanan_baru === false
        || $layanan_baru === null
        || !in_array($layanan_baru, $produk_valid, true)
        || $jumlah_baru === false
        || $jumlah_baru === null
        || $jumlah_baru < 1
        || $jumlah_baru > MAX_ITEMS_PER_PRODUCT
    ) {
        redirectWithUpdateNotification('Produk atau jumlah tidak valid. Maksimal 100 item per produk.', 'warning');
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
$jenis_tiket_map = [
    "50000" => "Tiket Reguler",
    "100000" => "Tiket VIP",
    "75000" => "Kaos Seminar Resmi",
    "35000" => "Totebag Kanvas",
    "45000" => "Buku Modul Big Data",
    "30000" => "Cetak Sertifikat & Pigura",
];

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
</head>
<body class="bg-light">

    <?php $session_deadline = $_SESSION['session_expires_at']; ?>
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-end mb-3">
            <span id="session-countdown" class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-clock me-1"></i> Sesi aktif: 05:00
            </span>
        </div>
        <div class="card shadow border-0" style="border-radius: 12px; overflow: hidden;">
            
            <!-- FORM UNTUK HAPUS MASSAL (Membungkus tabel) -->
            <form action="data_pendaftar.php" method="POST" id="formHapusMassal">
                
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h4 class="mb-0 fw-bold"><i class="fas fa-users me-2"></i>Daftar Peserta Seminar</h4>
                    <div>
                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 me-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPendaftar">
                            <i class="fas fa-user-plus me-1"></i> Tambah Pendaftar
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
                        <table class="table table-hover table-striped mb-0 align-middle">
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
                <input type="text" class="form-control" id="tambah_nama" name="tambah_nama" required>
              </div>
              <div class="mb-3">
                <label for="tambah_email" class="form-label fw-bold">Email</label>
                <input type="email" class="form-control" id="tambah_email" name="tambah_email" required>
              </div>
              <div class="mb-3">
                <label for="tambah_layanan" class="form-label fw-bold">Jenis Tiket / Produk</label>
                <select class="form-select" id="tambah_layanan" name="tambah_layanan" required>
                  <option value="">-- Pilih Produk/Layanan --</option>
                  <option value="50000">Tiket Reguler</option>
                  <option value="100000">Tiket VIP</option>
                  <option value="75000">Kaos Seminar Resmi</option>
                  <option value="35000">Totebag Kanvas</option>
                  <option value="45000">Buku Modul Big Data</option>
                  <option value="30000">Cetak Sertifikat &amp; Pigura</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="tambah_jumlah" class="form-label fw-bold">Jumlah</label>
                <input type="number" class="form-control" id="tambah_jumlah" name="tambah_jumlah" min="1" max="100" step="1" required>
              </div>
              <div class="mb-3">
                <label for="tambah_pesan" class="form-label fw-bold">Pesan Tambahan</label>
                <textarea class="form-control" id="tambah_pesan" name="tambah_pesan" rows="3"></textarea>
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
                      <input type="text" class="form-control" id="input_edit_nama" name="edit_nama" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Email</label>
                      <input type="email" class="form-control" id="input_edit_email" name="edit_email" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Jenis Tiket / Produk</label>
                      <select class="form-select" id="input_edit_layanan" name="edit_layanan" required>
                          <option value="">-- Pilih Produk/Layanan --</option>
                          <option value="50000">Tiket Reguler</option>
                          <option value="100000">Tiket VIP</option>
                          <option value="75000">Kaos Seminar Resmi</option>
                          <option value="35000">Totebag Kanvas</option>
                          <option value="45000">Buku Modul Big Data</option>
                          <option value="30000">Cetak Sertifikat & Pigura</option>
                      </select>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Jumlah</label>
                      <input type="number" class="form-control" id="input_edit_jumlah" name="edit_jumlah" min="1" max="100" step="1" required>
                  </div>
                  <div class="mb-3">
                      <label class="form-label fw-bold">Pesan Tambahan</label>
                      <textarea class="form-control" id="input_edit_pesan" name="edit_pesan" rows="3"></textarea>
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
                window.location.href = 'login.php?expired=1';
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