<?php
session_start();
require_once('../config/koneksi.php');

// Cek apakah pengendara sudah login
if (!isset($_SESSION['id_pengendara'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id_pengendara = $_SESSION['id_pengendara'];

// Ambil data pengendara
$stmt = $koneksi->prepare("SELECT nama FROM pengendara WHERE id_pengendara = ?");
$stmt->execute([$id_pengendara]);
$pengendara = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil kendaraan
$stmt = $koneksi->prepare("SELECT merk, model, no_plat FROM kendaraan WHERE id_pengendara = ? LIMIT 1");
$stmt->execute([$id_pengendara]);
$kendaraan = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil transaksi terbaru
$stmt = $koneksi->prepare("SELECT t.jumlah_kwh, t.total_harga, s.nama_stasiun 
    FROM transaksi t 
    JOIN stasiun_pengisian s ON t.id_stasiun = s.id_stasiun 
    WHERE t.id_pengendara = ? 
    ORDER BY t.tanggal_transaksi DESC LIMIT 3");
$stmt->execute([$id_pengendara]);
$transaksi_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil notifikasi
$stmt = $koneksi->prepare("SELECT judul, pesan FROM notifikasi 
    WHERE id_penerima = ? AND tipe_penerima = 'pengendara'
    ORDER BY dikirim_pada DESC LIMIT 5");
$stmt->execute([$id_pengendara]);
$notifikasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pengendara - E-Station</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800;900&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

/* ========== ANIMASI KEREN ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-40px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes glowBlue {
    0%, 100% { 
        box-shadow: 0 0 15px rgba(96, 165, 250, 0.4);
        text-shadow: 0 0 15px rgba(96, 165, 250, 0.6);
    }
    50% { 
        box-shadow: 0 0 30px rgba(96, 165, 250, 0.8);
        text-shadow: 0 0 25px rgba(96, 165, 250, 1);
    }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

/* ========== DARK MODE (Default) ========== */
body {
    background: linear-gradient(135deg, #0a192f 0%, #1e3a8a 35%, #312e81 65%, #1e293b 100%);
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    color: #edf2f7;
    min-height: 100vh;
    transition: all 0.4s ease;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(96, 165, 250, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(168, 85, 247, 0.12) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

.container {
    position: relative;
    z-index: 1;
}

/* ========== NAVBAR FUTURISTIK ========== */
.navbar {
    background: rgba(15, 23, 42, 0.75) !important;
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(96, 165, 250, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    animation: slideInLeft 0.8s ease;
}

.navbar-brand {
    font-weight: 900 !important;
    font-size: 1.8rem !important;
    color: #e2e8f0 !important;
    text-shadow: 0 0 20px rgba(96, 165, 250, 0.6);
    transition: all 0.3s ease;
    letter-spacing: 1px;
}

.navbar-brand:hover {
    transform: scale(1.1);
    animation: glowBlue 1.5s ease-in-out infinite;
}

.nav-link {
    color: #cbd5e1 !important;
    font-weight: 600;
    margin: 0 8px;
    padding: 8px 16px !important;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #60a5fa, #a855f7);
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.nav-link:hover {
    color: #60a5fa !important;
    background: rgba(96, 165, 250, 0.15);
    transform: translateY(-3px);
}

.nav-link:hover::after {
    width: 80%;
}

/* ========== TOGGLE TEMA MELAYANG ========== */
.theme-toggle {
    position: fixed;
    top: 80px;
    right: 25px;
    z-index: 999;
    animation: float 3s ease-in-out infinite;
}

.theme-toggle button {
    font-size: 2rem;
    background: linear-gradient(135deg, rgba(96, 165, 250, 0.25), rgba(168, 85, 247, 0.2));
    backdrop-filter: blur(15px);
    border: 2px solid rgba(96, 165, 250, 0.3);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    cursor: pointer;
    color: inherit;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 10px 30px rgba(96, 165, 250, 0.3);
}

.theme-toggle button:hover {
    transform: rotate(180deg) scale(1.2);
    animation: glowBlue 1s ease-in-out infinite;
}

/* ========== WELCOME BANNER ========== */
h2.fw-bold {
    font-size: 2.5rem !important;
    font-weight: 900 !important;
    background: linear-gradient(135deg, #60a5fa, #a855f7, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: fadeIn 1s ease, shimmer 3s linear infinite;
    background-size: 200% 200%;
    margin-bottom: 10px !important;
}

.container > p {
    color: #94a3b8;
    font-size: 1.1rem;
    animation: fadeIn 1.2s ease;
}

/* ========== CARD GLASSMORPHISM 3D ========== */
.card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    padding: 30px;
    margin-bottom: 25px;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: fadeIn 1s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(96, 165, 250, 0.1), transparent);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 50px rgba(96, 165, 250, 0.4);
    border-color: rgba(96, 165, 250, 0.5);
}

.card:hover::before {
    opacity: 1;
}

/* ========== CARD TITLES ========== */
.card-title {
    font-weight: 800 !important;
    font-size: 1.4rem !important;
    color: #60a5fa !important;
    text-shadow: 0 0 15px rgba(96, 165, 250, 0.7);
    margin-bottom: 20px !important;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ========== CARD CONTENT ========== */
.card p {
    color: #cbd5e1;
    font-size: 1.05rem;
    line-height: 1.8;
    margin-bottom: 12px;
}

.card ul {
    list-style: none;
    padding: 0;
}

.card ul li {
    background: rgba(255, 255, 255, 0.05);
    border-left: 4px solid #60a5fa;
    padding: 15px;
    margin: 10px 0;
    border-radius: 10px;
    transition: all 0.3s ease;
    color: #e2e8f0;
    animation: slideInLeft 0.6s ease;
}

.card ul li:hover {
    background: rgba(96, 165, 250, 0.15);
    transform: translateX(10px);
    border-left-width: 6px;
    box-shadow: 0 4px 15px rgba(96, 165, 250, 0.3);
}

.card ul li strong {
    color: #60a5fa;
    font-weight: 700;
}

/* ========== SPECIAL CARDS BY COLUMN ========== */
.col-md-4:nth-child(1) .card {
    border-top: 3px solid #22c55e;
    animation-delay: 0.1s;
}

.col-md-4:nth-child(1) .card-title {
    color: #22c55e !important;
    text-shadow: 0 0 15px rgba(34, 197, 94, 0.7);
}

.col-md-4:nth-child(1) .card ul li {
    border-left-color: #22c55e;
}

.col-md-4:nth-child(2) .card {
    border-top: 3px solid #3b82f6;
    animation-delay: 0.2s;
}

.col-md-4:nth-child(2) .card-title {
    color: #3b82f6 !important;
}

.col-md-4:nth-child(2) .card ul li {
    border-left-color: #3b82f6;
}

.col-md-4:nth-child(3) .card {
    border-top: 3px solid #a855f7;
    animation-delay: 0.3s;
}

.col-md-4:nth-child(3) .card-title {
    color: #a855f7 !important;
    text-shadow: 0 0 15px rgba(168, 85, 247, 0.7);
}

.col-md-4:nth-child(3) .card ul li {
    border-left-color: #a855f7;
}

/* ========== EMPTY STATE ========== */
.card p:only-child {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
    font-style: italic;
}

/* ========== LIGHT MODE ========== */
body.light {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 35%, #dbeafe 65%, #f8fafc 100%);
    background-size: 400% 400%;
    color: #1e293b;
}

body.light::before {
    background: 
        radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(168, 85, 247, 0.06) 0%, transparent 50%);
}

body.light .navbar {
    background: rgba(255, 255, 255, 0.8) !important;
    border-bottom: 1px solid rgba(59, 130, 246, 0.2);
}

body.light .navbar-brand {
    color: #1e293b !important;
}

body.light .nav-link {
    color: #475569 !important;
}

body.light .nav-link:hover {
    color: #3b82f6 !important;
    background: rgba(59, 130, 246, 0.1);
}

body.light h2.fw-bold {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

body.light .container > p {
    color: #64748b;
}

body.light .card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(59, 130, 246, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}

body.light .card:hover {
    box-shadow: 0 20px 50px rgba(59, 130, 246, 0.25);
}

body.light .card-title {
    color: #3b82f6 !important;
    text-shadow: none;
}

body.light .card p {
    color: #475569;
}

body.light .card ul li {
    background: rgba(248, 250, 252, 0.8);
    color: #1e293b;
}

body.light .card ul li:hover {
    background: rgba(59, 130, 246, 0.1);
}

body.light .theme-toggle button {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(168, 85, 247, 0.15));
    border-color: rgba(59, 130, 246, 0.3);
}

body.light .col-md-4:nth-child(1) .card-title {
    color: #16a34a !important;
}

body.light .col-md-4:nth-child(3) .card-title {
    color: #9333ea !important;
}

/* ========== SCROLLBAR CUSTOM ========== */
::-webkit-scrollbar {
    width: 12px;
}

::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #60a5fa, #a855f7);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #3b82f6, #9333ea);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    h2.fw-bold {
        font-size: 2rem !important;
    }
    
    .theme-toggle {
        top: 70px;
        right: 15px;
    }
    
    .theme-toggle button {
        width: 50px;
        height: 50px;
        font-size: 1.6rem;
    }
    
    .card {
        padding: 20px;
    }
}

/* ========== ENHANCEMENT ========== */
.row {
    animation: fadeIn 1.2s ease;
}

.col-md-4 {
    animation: fadeIn 1.4s ease;
}
</style>
</head>

<body>

<!-- TOMBOL TOGGLE TEMA -->
<div class="theme-toggle">
    <button id="toggleTheme">🌙</button>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand">⚡ E-Station</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="search_location.php"><i class="fas fa-map-marked-alt me-1"></i> Cari Lokasi</a>
            <a class="nav-link" href="transaction_history.php"><i class="fas fa-history me-1"></i> Riwayat</a>
            <a class="nav-link" href="station_detail.php"><i class="fas fa-charging-station me-1"></i> Detail Station</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
        </div>
    </div>
</nav>

<!-- CONTENT -->
<div class="container mt-5 mb-5">

<h2 class="fw-bold mb-3">👋 Selamat Datang, <?= htmlspecialchars($pengendara['nama']); ?>!</h2>
<p class="mb-4">Berikut ringkasan akun Anda</p>

<div class="row">

    <!-- KENDARAAN -->
    <div class="col-md-4">
        <div class="card">
            <h5 class="card-title">🚗 Kendaraan Aktif</h5>
            <p>
                <?= $kendaraan ? 
                htmlspecialchars($kendaraan['merk'] . ' ' . $kendaraan['model'] . ' (' . $kendaraan['no_plat'] . ')')
                : "Tidak ada kendaraan terdaftar"; ?>
            </p>
        </div>
    </div>

    <!-- TRANSAKSI -->
    <div class="col-md-4">
        <div class="card">
            <h5 class="card-title">🧾 Transaksi Terbaru</h5>
            <?php if ($transaksi_terbaru): ?>
                <ul>
                    <?php foreach ($transaksi_terbaru as $t): ?>
                        <li>
                            <strong><?= $t['jumlah_kwh']; ?> kWh</strong> - 
                            Rp <?= number_format($t['total_harga'], 0, ',', '.'); ?>
                            <br><small style="color: #94a3b8;"><?= htmlspecialchars($t['nama_stasiun']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Tidak ada transaksi.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <div class="col-md-4">
        <div class="card">
            <h5 class="card-title">🔔 Notifikasi</h5>
            <?php if ($notifikasi): ?>
                <ul>
                    <?php foreach ($notifikasi as $n): ?>
                        <li>
                            <strong><?= htmlspecialchars($n['judul']); ?></strong><br>
                            <small style="color: #cbd5e1;"><?= htmlspecialchars($n['pesan']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Tidak ada notifikasi.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

</div>

<!-- SCRIPT TOGGLE TEMA -->
<script>
const toggleButton = document.getElementById("toggleTheme");

// Load tema tersimpan
const savedTheme = localStorage.getItem("theme");
if (savedTheme === "light") {
    document.body.classList.add("light");
    toggleButton.textContent = "☀️";
} else {
    toggleButton.textContent = "🌙";
}

// Toggle tema
toggleButton.addEventListener("click", () => {
    document.body.classList.toggle("light");
    
    const isLight = document.body.classList.contains("light");
    toggleButton.textContent = isLight ? "☀️" : "🌙";
    localStorage.setItem("theme", isLight ? "light" : "dark");
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
