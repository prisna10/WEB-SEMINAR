console.log("JavaScript aktif");

// ==========================================
// TUGAS 2: Fitur Lihat Detail pada Kartu
// ==========================================
// Menyimpan informasi menggunakan objek
// ==========================================
// TUGAS 2: Fitur Lihat Detail (Pop-up Modal)
// ==========================================
// Menyimpan informasi lengkap setiap produk
const dataProduk = {
    tiketReguler: { nama: "Tiket Reguler", harga: "Rp 50.000", deskripsi: "Tiket akan dikirimkan via email H-1 acara. Berlaku untuk 1 orang dengan akses masuk ke seminar, snack box, dan e-sertifikat berpoin SKKM.", gambar: "contoh foto.jpg" },
    tiketVip: { nama: "Tiket VIP", harga: "Rp 100.000", deskripsi: "Akses jalur cepat (fast track) dan merchandise spesial saat registrasi ulang. Termasuk kursi barisan depan, makan siang eksklusif, e-sertifikat, dan sesi foto.", gambar: "contoh foto.jpg" },
    kaos: { nama: "Kaos Seminar Resmi", harga: "Rp 75.000", deskripsi: "Tersedia ukuran S, M, L, XL, XXL. Bahan cotton combed 30s premium edisi khusus 'Transformasi Digital'.", gambar: "contoh foto.jpg" },
    totebag: { nama: "Totebag Kanvas", harga: "Rp 35.000", deskripsi: "Bahan kanvas tebal dengan resleting. Ukuran 30x40 cm ramah lingkungan dengan logo HIMA SI UHW Perbanas.", gambar: "contoh foto.jpg" },
    bukuModul: { nama: "Buku Modul Big Data", harga: "Rp 45.000", deskripsi: "Berisi 50 halaman materi cetak berwarna dan ringkasan studi kasus sebagai panduan materi dari Prisna Dwi Hanggandau.", gambar: "contoh foto.jpg" },
    sertifikat: { nama: "Cetak Sertifikat & Pigura", harga: "Rp 30.000", deskripsi: "Dicetak di kertas linen premium dengan pigura kayu minimalis ukuran A4. Layanan cetak sertifikat fisik lengkap.", gambar: "contoh foto.jpg" }
};

// Fungsi untuk mengisi dan memunculkan Modal
function bukaDetail(idItem) {
    const item = dataProduk[idItem];
    
    // Mengganti isi teks dan gambar di dalam Modal
    document.getElementById('modalDetailTitle').textContent = item.nama;
    document.getElementById('modalDetailPrice').textContent = item.harga;
    document.getElementById('modalDetailDesc').textContent = item.deskripsi;
    document.getElementById('modalDetailImg').src = item.gambar;
    
    // Memunculkan Modal Bootstrap
    const modalDetail = new bootstrap.Modal(document.getElementById('modalDetail'));
    modalDetail.show();
}

// Memilih elemen tombol
const btnDetail1 = document.querySelector("#btn-detail-1");
const btnDetail2 = document.querySelector("#btn-detail-2");
const btnDetail3 = document.querySelector("#btn-detail-3");
const btnDetail4 = document.querySelector("#btn-detail-4");
const btnDetail5 = document.querySelector("#btn-detail-5");
const btnDetail6 = document.querySelector("#btn-detail-6");

// Menambahkan event listener click yang memanggil Pop-up, BUKAN pindah halaman
if (btnDetail1) btnDetail1.addEventListener("click", function() { bukaDetail('tiketReguler'); });
if (btnDetail2) btnDetail2.addEventListener("click", function() { bukaDetail('tiketVip'); });
if (btnDetail3) btnDetail3.addEventListener("click", function() { bukaDetail('kaos'); });
if (btnDetail4) btnDetail4.addEventListener("click", function() { bukaDetail('totebag'); });
if (btnDetail5) btnDetail5.addEventListener("click", function() { bukaDetail('bukuModul'); });
if (btnDetail6) btnDetail6.addEventListener("click", function() { bukaDetail('sertifikat'); });
// ==========================================
// TUGAS 4: Event Input dan Change
// ==========================================
const inputLayanan = document.querySelector("#layanan");
const infoHarga = document.querySelector("#info-harga");
const inputJumlah = document.querySelector("#jumlah");
const infoJumlah = document.querySelector("#info-jumlah");

// Event change: Menampilkan harga satuan saat opsi dipilih
inputLayanan.addEventListener("change", function(event) {
    const hargaDipilih = Number(event.target.value);
    infoHarga.textContent = `Harga Satuan: Rp ${hargaDipilih.toLocaleString("id-ID")}`;
});

// Event input: Menampilkan keterangan jumlah secara real-time
inputJumlah.addEventListener("input", function(event) {
    const jumlahKetik = event.target.value;
    infoJumlah.textContent = `Anda akan memesan sebanyak ${jumlahKetik} item.`;
});

// ==========================================
// TUGAS 3: Fitur Estimasi Biaya
// ==========================================
const btnHitung = document.querySelector("#btn-hitung");
const hasilEstimasi = document.querySelector("#hasil-estimasi");

// Fungsi menghitung total dengan parameter
function hitungTotal(harga, jumlah) {
    return harga * jumlah;
}

// Handler untuk tombol hitung
btnHitung.addEventListener("click", function() {
    const harga = Number(inputLayanan.value);
    const jumlah = Number(inputJumlah.value);
    
    // Validasi input
    if (harga > 0 && jumlah > 0) {
        const totalBiaya = hitungTotal(harga, jumlah);
        // Format hasil sebagai mata uang rupiah
        hasilEstimasi.textContent = `Total Estimasi: ` + totalBiaya.toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR"
        });
    } else {
        hasilEstimasi.textContent = "Silakan pilih produk dan isi jumlah dengan benar.";
    }
});

// ==========================================
// TUGAS 5: Pengiriman Formulir Tanpa Refresh (Fetch API + Pop up)
// ==========================================
const formPemesanan = document.querySelector("#form-pemesanan");
const pesanSimulasi = document.querySelector("#pesan-simulasi");

formPemesanan.addEventListener("submit", function(event) {
    // Mencegah web pindah ke halaman lain
    event.preventDefault(); 
    
    // Menampilkan status loading
    pesanSimulasi.style.display = "block";
    pesanSimulasi.className = "alert alert-warning mt-3";
    pesanSimulasi.textContent = "Sedang mengirim data ke database...";

    // Mengambil seluruh isian form
    const formData = new FormData(formPemesanan);

    // Mengirim ke proses_daftar.php tanpa reload halaman
    fetch(formPemesanan.action, {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Hilangkan pesan loading
        pesanSimulasi.style.display = "none";
        
        // Memunculkan Pop-up Sukses dari Bootstrap
        const modalSukses = new bootstrap.Modal(document.getElementById('modalSukses'));
        modalSukses.show();
        
        // Mereset formulir dan teks estimasi menjadi kosong kembali
        formPemesanan.reset();
        hasilEstimasi.textContent = "";
        infoHarga.textContent = "";
        infoJumlah.textContent = "";
    })
    .catch(error => {
        // Jika terjadi error saat memanggil PHP
        pesanSimulasi.className = "alert alert-danger mt-3";
        pesanSimulasi.textContent = "Terjadi kesalahan koneksi. Silakan coba lagi.";
    });
});