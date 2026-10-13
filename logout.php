<?php
session_start();
// Menghapus semua sesi login
session_destroy();
// Mengarahkan kembali ke halaman utama
header("Location: index.html");
exit();
?>