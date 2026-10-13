<?php
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Menangkap data dari form index.html
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $layanan = $_POST['layanan'];
    $jumlah = $_POST['jumlah'];
    $pesan = $_POST['pesan'];

    // Menyiapkan query untuk menyimpan data
    $sql = "INSERT INTO pendaftaran (nama, email, layanan, jumlah, pesan) 
            VALUES ('$nama', '$email', '$layanan', '$jumlah', '$pesan')";

    if ($conn->query($sql) === TRUE) {
        // Jika berhasil, cukup kembalikan pesan sukses untuk dibaca oleh fetch Javascript
        echo "Sukses mendaftar";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>