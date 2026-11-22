# Seminar Nasional Sistem Informasi

Project PHP OOP untuk tugas Pertemuan 9.

## Struktur OOP

- `public/index.php` berperan sebagai perakit: memuat class, membuat object seminar dan produk, menjalankan demo setter, lalu memanggil view.
- `app/Product.php` menyimpan model produk dengan property private, constructor, getter, dan setter.
- `app/Seminar.php` menyimpan kumpulan object `Product` dan method kalkulasi total.
- `views/ringkasan.php` berisi template HTML dan `foreach` untuk menampilkan data object.
- `assets/` berisi Bootstrap dan JavaScript interaksi halaman.

Data produk seminar tetap dirakit melalui object PHP, sedangkan pendaftaran disimpan ke tabel
`pendaftaran` melalui `proses_daftar.php`.

## Validasi dan database

- Form memvalidasi nama 3-100 karakter, email valid maksimal 254 karakter, jumlah 1-100,
  dan pesan tambahan maksimal 500 karakter.
- `proses_daftar.php` mengulang validasi menggunakan `$_POST`, memeriksa stok secara
  terkontrol, serta menangani kegagalan database dengan `try/catch/finally`.
- Konfigurasi database lokal menggunakan database `db_seminar` dan tabel `pendaftaran`.
