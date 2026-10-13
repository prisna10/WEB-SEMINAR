<?php
session_start();

// -----------------------------------------------------
// KUNCI HALAMAN: Cek apakah admin sudah login
// -----------------------------------------------------
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Jika belum login, tendang kembali ke halaman login
    header("Location: login.php");
    exit();
}

// Menghubungkan ke database
include 'koneksi.php';

// ... (sisa kode di bawahnya biarkan tetap sama) ...
// Menghubungkan ke database
include 'koneksi.php';

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
// 3. FITUR EDIT (UPDATE) DATA
// -----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_update'])) {
    $nama_baru = $_POST['edit_nama'];
    $email_baru = $_POST['edit_email'];
    $pesan_baru = $_POST['edit_pesan'];
    $waktu_acuan = $_POST['waktu_lama']; 

    $stmt = $conn->prepare("UPDATE pendaftaran SET nama=?, email=?, pesan=? WHERE waktu_daftar=?");
    $stmt->bind_param("ssss", $nama_baru, $email_baru, $pesan_baru, $waktu_acuan);

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui!'); window.location.href='data_pendaftar.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data!');</script>";
    }
    $stmt->close();
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
</head>
<body class="bg-light">

    <div class="container mt-5 mb-5">
        <div class="card shadow border-0" style="border-radius: 12px; overflow: hidden;">
            
            <!-- FORM UNTUK HAPUS MASSAL (Membungkus tabel) -->
            <form action="data_pendaftar.php" method="POST" id="formHapusMassal">
                
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h4 class="mb-0 fw-bold"><i class="fas fa-users me-2"></i>Daftar Peserta Seminar</h4>
                    <div>
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
                                    <th class="py-3">Harga Layanan</th>
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
                                        echo "<td><span class='badge bg-success bg-opacity-10 text-success fs-6 border border-success border-opacity-25'>Rp " . number_format($row['layanan'], 0, ',', '.') . "</span></td>";
                                        echo "<td class='text-center fw-bold'>" . $row['jumlah'] . "</td>";
                                        
                                        $pesan = htmlspecialchars($row['pesan']);
                                        $pesan_singkat = strlen($pesan) > 30 ? substr($pesan, 0, 30) . "..." : $pesan;
                                        echo "<td><small class='text-muted'>" . $pesan_singkat . "</small></td>";
                                        
                                        echo "<td><small class='text-secondary'><i class='far fa-clock me-1'></i>" . date('d-M-Y H:i', strtotime($waktu_asli)) . "</small></td>";
                                        
                                        echo "<td class='text-center'>";
                                        
                                        // Tombol Edit
                                        echo "<button type='button' class='btn btn-warning btn-sm rounded-pill px-3 shadow-sm me-1 fw-bold' 
                                              onclick=\"bukaModalEdit('" . addslashes($row['nama']) . "', '" . addslashes($row['email']) . "', '" . addslashes($row['pesan']) . "', '" . urlencode($waktu_asli) . "')\">
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
                                    echo "<tr><td colspan='9' class='text-center py-5 text-muted'><i class='fas fa-box-open fs-1 d-block mb-3 text-black-50'></i>Belum ada pendaftar seminar.</td></tr>";
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

    <!-- MODAL POP-UP UNTUK EDIT DATA -->
    <div class="modal fade" id="modalEditData" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
          <div class="modal-header bg-warning border-0" style="border-radius: 12px 12px 0 0;">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Data Peserta</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <form action="data_pendaftar.php" method="POST">
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
                      <label class="form-label fw-bold">Pesan Tambahan</label>
                      <textarea class="form-control" id="input_edit_pesan" name="edit_pesan" rows="3"></textarea>
                  </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="btn_update" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan Perubahan</button>
              </div>
          </form>
          
        </div>
      </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 1. Script untuk mengisi form Modal Edit
        function bukaModalEdit(nama, email, pesan, waktu_lama) {
            document.getElementById('input_edit_nama').value = nama;
            document.getElementById('input_edit_email').value = email;
            document.getElementById('input_edit_pesan').value = pesan;
            document.getElementById('input_waktu_lama').value = decodeURIComponent(waktu_lama);
            var editModal = new bootstrap.Modal(document.getElementById('modalEditData'));
            editModal.show();
        }

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