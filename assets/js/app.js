console.log("JavaScript aktif");

const dataProduk = {
    tiketReguler: { nama: "Tiket Reguler", harga: "Rp 50.000", deskripsi: "Tiket akan dikirimkan via email H-1 acara. Berlaku untuk 1 orang dengan akses masuk ke seminar, snack box, dan e-sertifikat berpoin SKKM.", gambar: "../assets/contoh foto.jpg" },
    tiketVip: { nama: "Tiket VIP", harga: "Rp 100.000", deskripsi: "Akses jalur cepat (fast track) dan merchandise spesial saat registrasi ulang. Termasuk kursi barisan depan, makan siang eksklusif, e-sertifikat, dan sesi foto.", gambar: "../assets/contoh foto.jpg" },
    kaos: { nama: "Kaos Seminar Resmi", harga: "Rp 75.000", deskripsi: "Tersedia ukuran S, M, L, XL, XXL. Bahan cotton combed 30s premium edisi khusus 'Transformasi Digital'.", gambar: "../assets/contoh foto.jpg" },
    totebag: { nama: "Totebag Kanvas", harga: "Rp 35.000", deskripsi: "Bahan kanvas tebal dengan resleting. Ukuran 30x40 cm ramah lingkungan dengan logo HIMA SI UHW Perbanas.", gambar: "../assets/contoh foto.jpg" },
    bukuModul: { nama: "Buku Modul Big Data", harga: "Rp 45.000", deskripsi: "Berisi 50 halaman materi cetak berwarna dan ringkasan studi kasus sebagai panduan materi dari Prisna Dwi Hanggandau.", gambar: "../assets/contoh foto.jpg" },
    sertifikat: { nama: "Cetak Sertifikat & Pigura", harga: "Rp 30.000", deskripsi: "Dicetak di kertas linen premium dengan pigura kayu minimalis ukuran A4. Layanan cetak sertifikat fisik lengkap.", gambar: "../assets/contoh foto.jpg" }
};

const filterButtons = document.querySelectorAll(".filter-kategori");
const produkCards = document.querySelectorAll(".produk-card");

filterButtons.forEach(function(button) {
    button.addEventListener("click", function() {
        const filter = button.dataset.filter;

        filterButtons.forEach(function(item) {
            const aktif = item === button;
            item.classList.toggle("active", aktif);
            item.classList.toggle("btn-primary", aktif);
            item.classList.toggle("btn-outline-primary", !aktif);
        });

        produkCards.forEach(function(card) {
            card.classList.toggle(
                "is-hidden",
                filter !== "semua" && card.dataset.kategori !== filter
            );
        });
    });
});

function bukaDetail(idItem) {
    const item = dataProduk[idItem];
    
    document.getElementById('modalDetailTitle').textContent = item.nama;
    document.getElementById('modalDetailPrice').textContent = item.harga;
    document.getElementById('modalDetailDesc').textContent = item.deskripsi;
    document.getElementById('modalDetailImg').src = item.gambar;
    
    const modalDetail = new bootstrap.Modal(document.getElementById('modalDetail'));
    modalDetail.show();
}

const btnDetail1 = document.querySelector("#btn-detail-1");
const btnDetail2 = document.querySelector("#btn-detail-2");
const btnDetail3 = document.querySelector("#btn-detail-3");
const btnDetail4 = document.querySelector("#btn-detail-4");
const btnDetail5 = document.querySelector("#btn-detail-5");
const btnDetail6 = document.querySelector("#btn-detail-6");

if (btnDetail1) btnDetail1.addEventListener("click", function() { bukaDetail('tiketReguler'); });
if (btnDetail2) btnDetail2.addEventListener("click", function() { bukaDetail('tiketVip'); });
if (btnDetail3) btnDetail3.addEventListener("click", function() { bukaDetail('kaos'); });
if (btnDetail4) btnDetail4.addEventListener("click", function() { bukaDetail('totebag'); });
if (btnDetail5) btnDetail5.addEventListener("click", function() { bukaDetail('bukuModul'); });
if (btnDetail6) btnDetail6.addEventListener("click", function() { bukaDetail('sertifikat'); });
const inputLayanan = document.querySelector("#layanan");
const infoHarga = document.querySelector("#info-harga");
const inputJumlah = document.querySelector("#jumlah");
const infoJumlah = document.querySelector("#info-jumlah");

inputLayanan.addEventListener("change", function(event) {
    const hargaDipilih = Number(event.target.value);
    infoHarga.textContent = `Harga Satuan: Rp ${hargaDipilih.toLocaleString("id-ID")}`;
});

inputJumlah.addEventListener("input", function(event) {
    const jumlahKetik = event.target.value;
    infoJumlah.textContent = `Anda akan memesan sebanyak ${jumlahKetik} item.`;
});

const btnHitung = document.querySelector("#btn-hitung");
const hasilEstimasi = document.querySelector("#hasil-estimasi");

function hitungTotal(harga, jumlah) {
    return harga * jumlah;
}

btnHitung.addEventListener("click", function() {
    const harga = Number(inputLayanan.value);
    const jumlah = Number(inputJumlah.value);
    
    if (harga > 0 && jumlah > 0) {
        const totalBiaya = hitungTotal(harga, jumlah);
        hasilEstimasi.textContent = `Total Estimasi: ` + totalBiaya.toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR"
        });
    } else {
        hasilEstimasi.textContent = "Silakan pilih produk dan isi jumlah dengan benar.";
    }
});

const formPemesanan = document.querySelector("#form-pemesanan");
const pesanSimulasi = document.querySelector("#pesan-simulasi");
const ringkasanValidasi = document.querySelector("#ringkasan-validasi");

function validasiForm() {
    const nama = document.querySelector("#nama");
    const email = document.querySelector("#email");
    const layanan = document.querySelector("#layanan");
    const jumlah = document.querySelector("#jumlah");
    const pesan = document.querySelector("#pesan");
    const persetujuan = document.querySelector("#persetujuan");
    const kesalahan = [];

    const aturan = [
        [nama, nama.value.trim().length >= 3 && nama.value.trim().length <= 100, "Nama wajib diisi 3 sampai 100 karakter."],
        [email, email.validity.valid && email.value.trim().length <= 254 && email.value.trim() !== "", "Masukkan alamat email yang valid."],
        [layanan, layanan.value !== "", "Pilih sesi atau produk yang ingin dipesan."],
        [jumlah, Number.isInteger(Number(jumlah.value)) && Number(jumlah.value) >= 1 && Number(jumlah.value) <= 100, "Jumlah harus berupa angka antara 1 sampai 100."],
        [pesan, pesan.value.length <= 500, "Pesan tambahan maksimal 500 karakter."],
        [persetujuan, persetujuan.checked, "Persetujuan wajib dicentang sebelum mengirim formulir."]
    ];

    aturan.forEach(function([field, valid, pesan]) {
        field.classList.toggle("is-invalid", !valid);
        field.classList.toggle("is-valid", valid);
        if (!valid) {
            kesalahan.push(pesan);
        }
    });

    ringkasanValidasi.textContent = kesalahan.length
        ? "Periksa kembali formulir: " + kesalahan.join(" ")
        : "";
    ringkasanValidasi.classList.toggle("d-none", kesalahan.length === 0);
    return kesalahan.length === 0;
}

["#nama", "#email", "#layanan", "#jumlah", "#pesan", "#persetujuan"].forEach(function(selector) {
    document.querySelector(selector).addEventListener("input", validasiForm);
    document.querySelector(selector).addEventListener("change", validasiForm);
});

formPemesanan.addEventListener("submit", function(event) {
    event.preventDefault(); 

    if (!validasiForm()) {
        pesanSimulasi.style.display = "none";
        return;
    }
    
    pesanSimulasi.style.display = "block";
    pesanSimulasi.className = "alert alert-warning mt-3";
    pesanSimulasi.textContent = "Sedang mengirim data ke database...";

    const formData = new FormData(formPemesanan);

    fetch(formPemesanan.action, {
        method: "POST",
        body: formData
    })
    .then(async response => {
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || !data.success) {
            throw new Error((data && data.message) || "Pendaftaran gagal. Silakan coba lagi.");
        }
        return data;
    })
    .then(() => {
        pesanSimulasi.style.display = "none";
        
        const modalSukses = new bootstrap.Modal(document.getElementById('modalSukses'));
        modalSukses.show();
        
        formPemesanan.reset();
        formPemesanan.querySelectorAll(".is-valid").forEach(function(field) {
            field.classList.remove("is-valid");
        });
        ringkasanValidasi.classList.add("d-none");
        hasilEstimasi.textContent = "";
        infoHarga.textContent = "";
        infoJumlah.textContent = "";
    })
    .catch(error => {
        pesanSimulasi.style.display = "block";
        pesanSimulasi.className = "alert alert-danger mt-3";
        pesanSimulasi.textContent = error.message || "Terjadi kesalahan koneksi. Silakan coba lagi.";
    });
});