<?php
// Menghubungkan ke database
include 'koneksi.php';

// Mengambil semua data dari tabel pendaftaran diurutkan dari yang terbaru
$sql = "SELECT * FROM pendaftaran ORDER BY waktu_daftar DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pendaftar Seminar</title>
    <!-- Memanggil Bootstrap dari folder assets -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Daftar Peserta Seminar</h4>
                <a href="index.html" class="btn btn-sm btn-light">Kembali ke Web</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Harga Layanan</th>
                                <th>Jumlah</th>
                                <th>Pesan Tambahan</th>
                                <th>Waktu Daftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Mengecek apakah ada data di database
                            if ($result->num_rows > 0) {
                                $no = 1;
                                // Menampilkan data satu per satu (Looping)
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td>" . $row['nama'] . "</td>";
                                    echo "<td>" . $row['email'] . "</td>";
                                    // Memformat angka menjadi format Rupiah
                                    echo "<td>Rp " . number_format($row['layanan'], 0, ',', '.') . "</td>";
                                    echo "<td>" . $row['jumlah'] . "</td>";
                                    echo "<td>" . $row['pesan'] . "</td>";
                                    echo "<td>" . date('d-m-Y H:i', strtotime($row['waktu_daftar'])) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center'>Belum ada pendaftar.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

<?php
// Menutup koneksi database
$conn->close();
?>