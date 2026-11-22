# Seminar Nasional Sistem Informasi

Project PHP OOP untuk tugas Pertemuan 9.

## Struktur OOP

- `public/index.php` berperan sebagai perakit: memuat class, membuat object seminar dan produk, menjalankan demo setter, lalu memanggil view.
- `app/Product.php` menyimpan model produk dengan property private, constructor, getter, dan setter.
- `app/Seminar.php` menyimpan kumpulan object `Product` dan method kalkulasi total.
- `views/ringkasan.php` berisi template HTML dan `foreach` untuk menampilkan data object.
- `assets/` berisi Bootstrap dan JavaScript interaksi halaman.
- `includes/` berisi konfigurasi, koneksi database, autentikasi, inventori,
  maintenance, dan katalog produk yang dipakai bersama oleh halaman PHP.

Data seminar utama tetap dirakit dengan class OOP, sedangkan katalog kartu produk
dapat dikelola melalui database.

## Pengelolaan kartu produk

Kartu produk sekarang dapat dikelola dari halaman **Kelola Kartu** di panel admin.
Data kartu disimpan di tabel `produk` MySQL dan tabel tersebut beserta enam kartu
awal dibuat otomatis saat aplikasi pertama kali terhubung ke database. Kartu yang
ditambahkan akan tampil di halaman utama dan pilihan produk pemesanan.

Harga kartu harus unik karena kolom `layanan` pada tabel `pendaftaran` saat ini
menyimpan harga sebagai kode produk.

Dashboard admin menyediakan tombol **Backup** untuk mengunduh seluruh tabel dan
data database sebagai file SQL. Fitur ini hanya dapat digunakan setelah login
admin dan memerlukan token CSRF yang valid.

File backup diberi tanda tangan HMAC. Import hanya menerima file yang dibuat
oleh tombol Backup dan menolak file yang isinya telah diedit. Setelah fitur
penandatanganan ini dipasang, backup lama harus diunduh ulang.

## CodeIgniter 4

Kerangka CodeIgniter 4 dipasang di folder `ci4/` agar dapat dikembangkan
bertahap tanpa mengganggu halaman PHP lama. Jalankan dari folder tersebut
dengan `php spark serve`, lalu buka `http://127.0.0.1:8081/` jika port 8080
sedang digunakan aplikasi lain.

Instalasi saat ini menggunakan CodeIgniter 4.4.8 karena PHP XAMPP yang
tersedia adalah 7.4.28. Versi ini merupakan instalasi legacy; upgrade PHP ke
8.2 atau yang lebih baru sebelum aplikasi CodeIgniter dipakai di production.
