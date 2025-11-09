<?php
session_start();
require_once('../config/koneksi.php');

// Cek apakah pengendara sudah login
if (!isset($_SESSION['id_pengendara'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Jika koneksi gagal, tampilkan error
if (!isset($koneksi)) {
    die("Koneksi database tidak tersedia. Periksa file config/koneksi.php.");
}

$id_pengendara = $_SESSION['id_pengendara'];

try {
    // Ambil data pengendara (dengan saldo & poin jika ada di tabel pengendara)
    $stmt = $koneksi->prepare("SELECT nama, email FROM pengendara WHERE id_pengendara = ?");
    $stmt->execute([$id_pengendara]);
    $pengendara = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set saldo dan poin default (karena tidak ada tabel saldo_pengendara)
    $saldo = ['saldo' => 0, 'poin' => 0];
    
    // OPSIONAL: Jika kolom saldo/poin ada di tabel pengendara, uncomment baris ini:
    // $stmt = $koneksi->prepare("SELECT saldo, poin FROM pengendara WHERE id_pengendara = ?");
    // $stmt->execute([$id_pengendara]);
    // $saldo = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['saldo' => 0, 'poin' => 0];

    // Ambil kendaraan aktif
    $stmt = $koneksi->prepare("SELECT merk, model, no_plat FROM kendaraan WHERE id_pengendara = ? LIMIT 1");
    $stmt->execute([$id_pengendara]);
    $kendaraan = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ambil transaksi terbaru (3 terakhir)
    $stmt = $koneksi->prepare("SELECT t.jumlah_kwh, t.total_harga, t.status_transaksi, s.nama_stasiun FROM transaksi t JOIN stasiun_pengisian s ON t.id_stasiun = s.id_stasiun WHERE t.id_pengendara = ? ORDER BY t.tanggal_transaksi DESC LIMIT 3");
    $stmt->execute([$id_pengendara]);
    $transaksi_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil notifikasi terbaru
    $stmt = $koneksi->prepare("SELECT judul, pesan FROM notifikasi WHERE id_penerima = ? AND tipe_penerima = 'pengendara' ORDER BY dikirim_pada DESC LIMIT 5");
    $stmt->execute([$id_pengendara]);
    $notifikasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error query database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengendara - E-Station</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">E-Station</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_location.php">Cari Lokasi</a>
                <a class="nav-link" href="transaction_history.php">Riwayat Transaksi</a>
                <a class="nav-link" href="battery_stock.php">Stok Baterai</a>
                <a class="nav-link" href="station_detail.php">Detail Station</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Selamat Datang, <?php echo htmlspecialchars($pengendara['nama'] ?? 'Pengendara'); ?>!</h1>
        
        <!-- Ringkasan -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Saldo & Poin</h5>
                        <p>Saldo: Rp <?php echo number_format($saldo['saldo'], 0, ',', '.'); ?></p>
                        <p>Poin: <?php echo $saldo['poin']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Kendaraan Aktif</h5>
                        <?php if ($kendaraan): ?>
                            <p><?php echo htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['model']); ?> (<?php echo htmlspecialchars($kendaraan['no_plat']); ?>)</p>
                        <?php else: ?>
                            <p>Tidak ada kendaraan terdaftar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Transaksi Terbaru</h5>
                        <?php if ($transaksi_terbaru): ?>
                            <ul>
                                <?php foreach ($transaksi_terbaru as $t): ?>
                                    <li><?php echo $t['jumlah_kwh']; ?> kWh - Rp <?php echo number_format($t['total_harga'], 0, ',', '.'); ?> (<?php echo htmlspecialchars($t['nama_stasiun']); ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Tidak ada transaksi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifikasi -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Notifikasi Terbaru</h5>
                <?php if ($notifikasi): ?>
                    <ul>
                        <?php foreach ($notifikasi as $n): ?>
                            <li><strong><?php echo htmlspecialchars($n['judul']); ?>:</strong> <?php echo htmlspecialchars($n['pesan']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Tidak ada notifikasi.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>