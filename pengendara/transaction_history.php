<?php
session_start();
require_once('../config/koneksi.php');  // Path diperbaiki dan menggunakan koneksi.php

if (!isset($_SESSION['id_pengendara'])) {
    header('Location: ../login.php');
    exit;
}

// Jika koneksi gagal, tampilkan error
if (!isset($koneksi)) {
    die("Koneksi database tidak tersedia. Periksa file config/koneksi.php.");
}

$id_pengendara = $_SESSION['id_pengendara'];

try {
    // Ambil riwayat transaksi
    $stmt = $koneksi->prepare("SELECT t.tanggal_transaksi, t.jumlah_kwh, t.total_harga, t.status_transaksi, s.nama_stasiun FROM transaksi t JOIN stasiun_pengisian s ON t.id_stasiun = s.id_stasiun WHERE t.id_pengendara = ? ORDER BY t.tanggal_transaksi DESC");
    $stmt->execute([$id_pengendara]);
    $transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error query database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - E-Station</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">E-Station</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Riwayat Transaksi</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Stasiun</th>
                    <th>Jumlah kWh</th>
                    <th>Total Harga</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transaksi as $t): ?>
                    <tr>
                        <td><?php echo $t['tanggal_transaksi']; ?></td>
                        <td><?php echo htmlspecialchars($t['nama_stasiun']); ?></td>
                        <td><?php echo $t['jumlah_kwh']; ?></td>
                        <td>Rp <?php echo number_format($t['total_harga'], 0, ',', '.'); ?></td>
                        <td><?php echo $t['status_transaksi']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
