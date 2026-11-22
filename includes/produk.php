<?php

require_once __DIR__ . '/../app/Product.php';

/**
 * @return Product[]
 */
function getDefaultProducts(): array
{
    return [
        new Product('tiketReguler', 'tiket', '🎫 Tiket', 'Tiket Reguler', 50000, 'Akses masuk ke seminar, snack box, dan e-sertifikat berpoin SKKM.', '../assets/contoh foto.jpg'),
        new Product('tiketVip', 'tiket', '🌟 Tiket VIP', 'Tiket VIP', 100000, 'Kursi barisan depan, makan siang eksklusif, e-sertifikat, dan sesi foto.', '../assets/contoh foto.jpg'),
        new Product('kaos', 'merchandise', '👕 Merchandise', 'Kaos Seminar Resmi', 75000, 'Kaos katun premium edisi khusus "Transformasi Digital".', '../assets/contoh foto.jpg'),
        new Product('totebag', 'merchandise', '🛍️ Merchandise', 'Totebag Kanvas', 35000, 'Totebag ramah lingkungan dengan logo HIMA SI UHW Perbanas.', '../assets/contoh foto.jpg'),
        new Product('bukuModul', 'edukasi', '📚 Edukasi', 'Buku Modul Big Data', 45000, 'Buku panduan materi dari Prisna Dwi Hanggandau untuk referensi perkuliahan.', '../assets/contoh foto.jpg'),
        new Product('sertifikat', 'aksesori', '🖼️ Aksesori', 'Cetak Sertifikat & Pigura', 30000, 'Layanan cetak sertifikat fisik lengkap dengan pigura minimalis.', '../assets/contoh foto.jpg'),
    ];
}

/**
 * Menyiapkan katalog produk untuk demo lokal.
 */
function ensureProductTable(mysqli $conn): bool
{
    $createSql = 'CREATE TABLE IF NOT EXISTS kartu_produk (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(50) NOT NULL,
    badge VARCHAR(100) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    harga INT UNSIGNED NOT NULL,
    deskripsi TEXT NOT NULL,
    gambar VARCHAR(255) NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    if (!$conn->query($createSql)) {
        error_log('Gagal membuat tabel kartu_produk: ' . $conn->error);
        return false;
    }

    $countResult = $conn->query('SELECT COUNT(*) AS total FROM kartu_produk');
    if (!$countResult) {
        error_log('Gagal memeriksa katalog produk: ' . $conn->error);
        return false;
    }

    $total = (int)$countResult->fetch_assoc()['total'];
    $countResult->free();
    if ($total > 0) {
        return true;
    }

    $seed = [];
    foreach (getDefaultProducts() as $product) {
        $seed[] = [$product->getCategory(), $product->getBadge(), $product->getName(), $product->getPrice(), $product->getDescription()];
    }
    $stmt = $conn->prepare(
        'INSERT INTO kartu_produk (kategori, badge, nama, harga, deskripsi, gambar) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('Gagal menyiapkan data awal produk: ' . $conn->error);
        return false;
    }

    $gambar = '../assets/contoh foto.jpg';
    foreach ($seed as [$kategori, $badge, $nama, $harga, $deskripsi]) {
        $stmt->bind_param('sssiss', $kategori, $badge, $nama, $harga, $deskripsi, $gambar);
        if (!$stmt->execute()) {
            error_log('Gagal memasukkan data awal produk: ' . $stmt->error);
            $stmt->close();
            return false;
        }
    }
    $stmt->close();
    return true;
}

/**
 * @return Product[]
 */
function getActiveProducts(mysqli $conn): array
{
    if (!ensureProductTable($conn)) {
        return [];
    }

    $result = $conn->query(
        'SELECT id, kategori, badge, nama, harga, deskripsi, gambar
         FROM kartu_produk WHERE aktif = 1 ORDER BY id ASC'
    );
    if (!$result) {
        error_log('Gagal mengambil produk: ' . $conn->error);
        return [];
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = new Product(
            (string)$row['id'],
            $row['kategori'],
            $row['badge'],
            $row['nama'],
            (int)$row['harga'],
            $row['deskripsi'],
            $row['gambar']
        );
    }
    $result->free();
    return $products;
}
