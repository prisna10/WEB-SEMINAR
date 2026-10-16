<?php

function lockProductInventory(mysqli $conn, int $productId): ?bool
{
    $lockName = 'webseminar_product_' . $productId;
    $stmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
    if (!$stmt) {
        error_log('Gagal menyiapkan penguncian stok: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('s', $lockName);
    if (!$stmt->execute()) {
        error_log('Gagal mengunci stok produk: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $stmt->bind_result($acquired);
    $stmt->fetch();
    $stmt->close();

    return (int)$acquired === 1;
}

function unlockProductInventory(mysqli $conn, int $productId): void
{
    $lockName = 'webseminar_product_' . $productId;
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if (!$stmt) {
        error_log('Gagal menyiapkan pelepasan kunci stok: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $lockName);
    if (!$stmt->execute()) {
        error_log('Gagal melepas kunci stok: ' . $stmt->error);
    }
    $stmt->close();
}
