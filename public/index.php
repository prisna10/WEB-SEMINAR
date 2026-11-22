<?php

require_once __DIR__ . '/../app/Product.php';
require_once __DIR__ . '/../app/Seminar.php';

$seminar = new Seminar(
    'Seminar Nasional Sistem Informasi',
    'Transformasi Digital dan Bisnis di Era Modern'
);

$seminar->addProduct(new Product(
    'tiketReguler',
    'tiket',
    '🎫 Tiket',
    'Tiket Reguler',
    50000,
    'Akses masuk ke seminar, snack box, dan e-sertifikat berpoin SKKM.',
    '../assets/contoh foto.jpg'
));
$seminar->addProduct(new Product(
    'tiketVip',
    'tiket',
    '🌟 Tiket VIP',
    'Tiket VIP',
    100000,
    'Kursi barisan depan, makan siang eksklusif, e-sertifikat, dan sesi foto.',
    '../assets/contoh foto.jpg'
));
$seminar->addProduct(new Product(
    'kaos',
    'merchandise',
    '👕 Merchandise',
    'Kaos Seminar Resmi',
    75000,
    'Kaos katun premium edisi khusus "Transformasi Digital".',
    '../assets/contoh foto.jpg'
));
$seminar->addProduct(new Product(
    'totebag',
    'merchandise',
    '🛍️ Merchandise',
    'Totebag Kanvas',
    35000,
    'Totebag ramah lingkungan dengan logo HIMA SI UHW Perbanas.',
    '../assets/contoh foto.jpg'
));
$seminar->addProduct(new Product(
    'bukuModul',
    'edukasi',
    '📚 Edukasi',
    'Buku Modul Big Data',
    45000,
    'Buku panduan materi dari Prisna Dwi Hanggandau untuk referensi perkuliahan.',
    '../assets/contoh foto.jpg'
));
$seminar->addProduct(new Product(
    'sertifikat',
    'aksesori',
    '🖼️ Aksesori',
    'Cetak Sertifikat & Pigura',
    30000,
    'Layanan cetak sertifikat fisik lengkap dengan pigura minimalis.',
    '../assets/contoh foto.jpg'
));

$demoProduct = new Product(
    'demo',
    'demo',
    'OOP',
    'Contoh Produk Encapsulation',
    25000,
    'Data ini digunakan untuk memperlihatkan getter dan setter.',
    '../assets/contoh foto.jpg'
);
$hargaSebelum = $demoProduct->getPrice();
$demoProduct->setPrice(30000);
$hargaSesudah = $demoProduct->getPrice();
$demoTotal = $seminar->calculateTotal($demoProduct, 2);

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

require __DIR__ . '/../views/ringkasan.php';
