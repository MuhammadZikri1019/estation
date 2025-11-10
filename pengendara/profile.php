<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/koneksi.php');

// Cek apakah pengendara sudah login
if (!isset($_SESSION['id_pengendara'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id_pengendara = $_SESSION['id_pengendara'];
$message = '';
$error = '';

// Ambil data pengendara
$stmt = $koneksi->prepare("SELECT * FROM pengendara WHERE id_pengendara = ?");
$stmt->execute([$id_pengendara]);
$pengendara = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data kendaraan
$stmt = $koneksi->prepare("SELECT * FROM kendaraan WHERE id_pengendara = ?");
$stmt->execute([$id_pengendara]);
$kendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil foto profil (cek tabel ada atau tidak)
$foto = null;
try {
    $stmt = $koneksi->prepare("SELECT * FROM foto_profil WHERE id_pengendara = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute([$id_pengendara]);
    $foto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabel belum dibuat, skip
    $foto = null;
}

// Handle upload foto profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload_dir = '../uploads/profile/';
    
    // Buat folder jika belum ada
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['foto_profil'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    
    if (in_array($file['type'], $allowed_types) && $file['size'] <= 2000000) { // Max 2MB
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $id_pengendara . '_' . time() . '.' . $extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            try {
                // Hapus foto lama jika ada
                if ($foto && file_exists($foto['path_file'])) {
                    unlink($foto['path_file']);
                }
                
                // Simpan ke database
                $stmt = $koneksi->prepare("INSERT INTO foto_profil (id_pengendara, nama_file, path_file) VALUES (?, ?, ?)");
                $stmt->execute([$id_pengendara, $new_filename, $upload_path]);
                
                $message = "Foto profil berhasil diupload!";
                
                // Refresh data foto
                $stmt = $koneksi->prepare("SELECT * FROM foto_profil WHERE id_pengendara = ? ORDER BY uploaded_at DESC LIMIT 1");
                $stmt->execute([$id_pengendara]);
                $foto = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = "Tabel foto_profil belum dibuat! Silakan buat tabel terlebih dahulu.";
            }
        } else {
            $error = "Gagal mengupload foto!";
        }
    } else {
        $error = "File tidak valid! Hanya JPG, PNG, GIF (Max 2MB)";
    }
}

// Handle update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama']);
    $no_telepon = trim($_POST['no_telepon']);
    $alamat = trim($_POST['alamat']);
    
    $stmt = $koneksi->prepare("UPDATE pengendara SET nama = ?, no_telepon = ?, alamat = ? WHERE id_pengendara = ?");
    if ($stmt->execute([$nama, $no_telepon, $alamat, $id_pengendara])) {
        $message = "Profil berhasil diperbarui!";
        // Refresh data
        $stmt = $koneksi->prepare("SELECT * FROM pengendara WHERE id_pengendara = ?");
        $stmt->execute([$id_pengendara]);
        $pengendara = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Gagal memperbarui profil!";
    }
}

// Handle tambah kendaraan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_kendaraan'])) {
    $merk = trim($_POST['merk']);
    $model = trim($_POST['model']);
    $no_plat = strtoupper(trim($_POST['no_plat']));
    $tahun = !empty($_POST['tahun']) ? intval($_POST['tahun']) : null;
    
    // Validasi input
    if (empty($merk) || empty($model) || empty($no_plat)) {
        $error = "Merk, Model, dan No. Plat harus diisi!";
    } else {
        try {
            // Cek apakah tabel kendaraan ada
            $check_table = $koneksi->query("SHOW TABLES LIKE 'kendaraan'");
            if ($check_table->rowCount() == 0) {
                $error = "Tabel kendaraan belum dibuat! Silakan buat tabel terlebih dahulu dengan menjalankan SQL yang disediakan.";
            } else {
                // Cek apakah no plat sudah terdaftar
                $stmt = $koneksi->prepare("SELECT COUNT(*) FROM kendaraan WHERE no_plat = ?");
                $stmt->execute([$no_plat]);
                $exists = $stmt->fetchColumn();
                
                if ($exists > 0) {
                    $error = "No. Plat sudah terdaftar oleh pengguna lain!";
                } else {
                    // Insert kendaraan baru
                    $stmt = $koneksi->prepare("INSERT INTO kendaraan (id_pengendara, merk, model, no_plat, tahun) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$id_pengendara, $merk, $model, $no_plat, $tahun])) {
                        $message = "Kendaraan berhasil ditambahkan!";
                        // Refresh data kendaraan
                        $stmt = $koneksi->prepare("SELECT * FROM kendaraan WHERE id_pengendara = ?");
                        $stmt->execute([$id_pengendara]);
                        $kendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Gagal menambahkan kendaraan!";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error Database: " . $e->getMessage();
        }
    }
}

// Handle hapus kendaraan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_kendaraan'])) {
    $id_kendaraan = $_POST['id_kendaraan'];
    
    $stmt = $koneksi->prepare("DELETE FROM kendaraan WHERE id_kendaraan = ? AND id_pengendara = ?");
    if ($stmt->execute([$id_kendaraan, $id_pengendara])) {
        $message = "Kendaraan berhasil dihapus!";
        // Refresh data kendaraan
        $stmt = $koneksi->prepare("SELECT * FROM kendaraan WHERE id_pengendara = ?");
        $stmt->execute([$id_pengendara]);
        $kendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Gagal menghapus kendaraan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya - E-Station</title>
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

/* ========== ANIMASI ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-40px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes slideInRight {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
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

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

/* ========== DARK MODE ========== */
body {
    background: linear-gradient(135deg, #0a192f 0%, #1e3a8a 35%, #312e81 65%, #1e293b 100%);
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    color: #edf2f7;
    min-height: 100vh;
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

/* ========== NAVBAR ========== */
.navbar {
    background: rgba(15, 23, 42, 0.75) !important;
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(96, 165, 250, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    animation: slideInRight 0.8s ease;
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
}

.nav-link:hover {
    color: #60a5fa !important;
    background: rgba(96, 165, 250, 0.15);
    transform: translateY(-3px);
}

/* ========== THEME TOGGLE ========== */
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

/* ========== TITLE ========== */
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

/* ========== PROFILE CARD ========== */
.profile-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    padding: 40px;
    margin-bottom: 30px;
    transition: all 0.5s ease;
    animation: fadeIn 1s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.profile-card::before {
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

.profile-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(96, 165, 250, 0.3);
    border-color: rgba(96, 165, 250, 0.5);
}

.profile-card:hover::before {
    opacity: 1;
}

/* ========== PROFILE IMAGE ========== */
.profile-image-container {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 30px;
}

.profile-image {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid rgba(96, 165, 250, 0.5);
    box-shadow: 0 10px 40px rgba(96, 165, 250, 0.4);
    transition: all 0.3s ease;
}

.profile-image:hover {
    transform: scale(1.05);
    border-color: rgba(96, 165, 250, 0.8);
}

.upload-overlay {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(96, 165, 250, 0.5);
}

.upload-overlay:hover {
    transform: scale(1.1);
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.upload-overlay i {
    color: white;
    font-size: 1.2rem;
}

#file-input {
    display: none;
}

/* ========== INFO SECTION ========== */
.info-group {
    background: rgba(255, 255, 255, 0.05);
    border-left: 4px solid #60a5fa;
    padding: 20px;
    margin: 15px 0;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.info-group:hover {
    background: rgba(96, 165, 250, 0.1);
    transform: translateX(10px);
}

.info-label {
    color: #94a3b8;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-value {
    color: #e2e8f0;
    font-size: 1.1rem;
    font-weight: 500;
}

/* ========== KENDARAAN CARD ========== */
.vehicle-card {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.vehicle-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
}

.vehicle-card h6 {
    color: #22c55e;
    font-weight: 700;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vehicle-card p {
    color: #cbd5e1;
    margin-bottom: 5px;
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 6px 12px !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    color: white !important;
}

.btn-delete:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4) !important;
}

.btn-add-vehicle {
    background: linear-gradient(135deg, #22c55e, #16a34a) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 30px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 5px 20px rgba(34, 197, 94, 0.4) !important;
    margin-bottom: 20px !important;
    color: white !important;
    cursor: pointer !important;
    display: inline-block !important;
}

.btn-add-vehicle:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(34, 197, 94, 0.6) !important;
    background: linear-gradient(135deg, #16a34a, #15803d) !important;
}

/* ========== FORM ========== */
.form-control {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(96, 165, 250, 0.3) !important;
    border-radius: 12px !important;
    color: #e2e8f0 !important;
    padding: 12px 20px !important;
    transition: all 0.3s ease !important;
}

.form-control:focus {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(96, 165, 250, 0.6) !important;
    box-shadow: 0 0 20px rgba(96, 165, 250, 0.3) !important;
    color: #fff !important;
}

.form-control::placeholder {
    color: #64748b !important;
}

.form-label {
    color: #94a3b8;
    font-weight: 600;
    margin-bottom: 8px;
}

/* ========== BUTTONS ========== */
.btn-primary {
    background: linear-gradient(135deg, #60a5fa, #3b82f6) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 30px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 5px 20px rgba(96, 165, 250, 0.4) !important;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(96, 165, 250, 0.6) !important;
}

.btn-secondary {
    background: linear-gradient(135deg, #64748b, #475569) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 30px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

.btn-secondary:hover {
    transform: translateY(-3px);
}

/* ========== ALERTS ========== */
.alert {
    border-radius: 12px !important;
    border: none !important;
    backdrop-filter: blur(10px);
    animation: slideInLeft 0.5s ease;
}

.alert-success {
    background: rgba(34, 197, 94, 0.2) !important;
    border-left: 4px solid #22c55e !important;
    color: #86efac !important;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.2) !important;
    border-left: 4px solid #ef4444 !important;
    color: #fca5a5 !important;
}

/* ========== SECTION TITLE ========== */
.section-title {
    color: #60a5fa;
    font-weight: 800;
    font-size: 1.8rem;
    margin-bottom: 20px;
    text-shadow: 0 0 15px rgba(96, 165, 250, 0.6);
}

/* ========== LIGHT MODE ========== */
body.light {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 35%, #dbeafe 65%, #f8fafc 100%);
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

body.light .profile-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

body.light .info-group {
    background: rgba(248, 250, 252, 0.8);
}

body.light .info-value {
    color: #1e293b;
}

body.light .form-control {
    background: rgba(248, 250, 252, 0.8) !important;
    color: #1e293b !important;
}

body.light .form-control::placeholder {
    color: #94a3b8 !important;
}

body.light .vehicle-card {
    background: rgba(240, 253, 244, 0.8);
    border-color: rgba(34, 197, 94, 0.3);
}

body.light .vehicle-card p {
    color: #475569;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    h2.fw-bold {
        font-size: 2rem !important;
    }
    
    .profile-card {
        padding: 25px;
    }
    
    .profile-image-container,
    .profile-image {
        width: 150px;
        height: 150px;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
    
    .btn-add-vehicle {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 576px) {
    h2.fw-bold {
        font-size: 1.6rem !important;
    }
    
    .profile-card {
        padding: 20px;
    }
    
    .profile-image-container,
    .profile-image {
        width: 120px;
        height: 120px;
    }
    
    .btn-add-vehicle {
        width: 100%;
        font-size: 0.9rem;
        padding: 10px 20px !important;
    }
}

/* ========== SCROLLBAR ========== */
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
.profile-card,
.profile-card * {
    pointer-events: auto !important;
}
.profile-card::before {
    pointer-events: none !important;
}
body::before {
    pointer-events: none !important;
}

</style>
</head>

<body>

<!-- THEME TOGGLE -->
<div class="theme-toggle">
    <button id="toggleTheme">🌙</button>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">⚡ E-Station</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                <a class="nav-link" href="search_location.php"><i class="fas fa-map-marked-alt me-1"></i> Cari Lokasi</a>
                <a class="nav-link" href="transaction_history.php"><i class="fas fa-history me-1"></i> Riwayat</a>
                <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- CONTENT -->
<div class="container mt-5 mb-5">

<h2 class="fw-bold mb-4">👤 Profil Saya</h2>

<!-- ALERTS -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    
    <!-- FOTO & INFO PROFIL -->
    <div class="col-lg-4 mb-4">
        <div class="profile-card">
            
            <!-- FOTO PROFIL -->
            <div class="profile-image-container">
                <?php if ($foto && file_exists($foto['path_file'])): ?>
                    <img src="<?= htmlspecialchars($foto['path_file']); ?>" alt="Profile" class="profile-image">
                <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($pengendara['nama']); ?>&size=200&background=60a5fa&color=fff&bold=true" alt="Profile" class="profile-image">
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <label for="file-input" class="upload-overlay">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="file-input" name="foto_profil" accept="image/*" onchange="this.form.submit();">
                </form>
            </div>
            
            <!-- INFO DASAR -->
            <div class="text-center mb-4">
                <h4 class="fw-bold" style="color: #60a5fa;"><?= htmlspecialchars($pengendara['nama']); ?></h4>
                <p style="color: #94a3b8;"><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($pengendara['email']); ?></p>
                <span class="badge bg-success" style="font-size: 0.9rem; padding: 8px 16px; border-radius: 20px;">
                    <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($pengendara['status_akun']); ?>
                </span>
            </div>
            
            <!-- INFO DETAIL -->
            <div class="info-group">
                <div class="info-label"><i class="fas fa-phone me-2"></i>No. Telepon</div>
                <div class="info-value"><?= htmlspecialchars($pengendara['no_telepon'] ?? '-'); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label"><i class="fas fa-map-marker-alt me-2"></i>Alamat</div>
                <div class="info-value"><?= htmlspecialchars($pengendara['alamat'] ?? '-'); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label"><i class="fas fa-calendar me-2"></i>Bergabung Sejak</div>
                <div class="info-value"><?= date('d M Y', strtotime($pengendara['created_at'])); ?></div>
            </div>
            
        </div>
    </div>
    
    <!-- EDIT PROFIL & KENDARAAN -->
    <div class="col-lg-8">
        
        <!-- EDIT PROFIL -->
        <div class="profile-card mb-4">
            <h5 class="section-title"><i class="fas fa-edit me-2"></i>Edit Profil</h5>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($pengendara['nama']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($pengendara['email']); ?>" disabled>
                    <small style="color: #94a3b8;">Email tidak dapat diubah</small>
                </div>
                
                <div class="mb-3">
                    <label for="no_telepon" class="form-label">No. Telepon</label>
                    <input type="text" class="form-control" id="no_telepon" name="no_telepon" value="<?= htmlspecialchars($pengendara['no_telepon'] ?? ''); ?>" placeholder="Contoh: 081234567890">
                </div>
                
                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap Anda"><?= htmlspecialchars($pengendara['alamat'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" name="update_profil" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                </button>
            </form>
        </div>
        
        <!-- KENDARAAN -->
        <div class="profile-card">
            <h5 class="section-title"><i class="fas fa-car me-2"></i>Kendaraan Terdaftar</h5>
            
            <div class="alert" style="background: rgba(96, 165, 250, 0.15); border-left: 4px solid #60a5fa; color: #bfdbfe; margin-bottom: 20px;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Informasi:</strong> Daftarkan kendaraan listrik Anda untuk memudahkan proses charging di stasiun E-Station.
            </div>
            
            <!-- FORM TAMBAH KENDARAAN (COLLAPSIBLE) -->
            <div class="mb-4">
                <button class="btn btn-add-vehicle" type="button" data-bs-toggle="collapse" data-bs-target="#formTambahKendaraan" aria-expanded="false">
                    <i class="fas fa-plus me-2"></i>Tambah Kendaraan Baru
                </button>
                
                <div class="collapse mt-3" id="formTambahKendaraan">
                    <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 16px; padding: 25px;">
                        <h6 style="color: #22c55e; font-weight: 700; margin-bottom: 20px;">
                            <i class="fas fa-car-side me-2"></i>Form Tambah Kendaraan
                        </h6>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="merk" class="form-label">Merk Kendaraan *</label>
                                    <input type="text" class="form-control" id="merk" name="merk" placeholder="Contoh: Tesla, Hyundai, BYD" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" placeholder="Contoh: Model 3, Ioniq 5" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="no_plat" class="form-label">Nomor Plat *</label>
                                    <input type="text" class="form-control" id="no_plat" name="no_plat" placeholder="Contoh: B 1234 XYZ" required style="text-transform: uppercase;">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tahun" class="form-label">Tahun</label>
                                    <input type="number" class="form-control" id="tahun" name="tahun" placeholder="Contoh: 2024" min="1900" max="2100">
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="tambah_kendaraan" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Simpan Kendaraan
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#formTambahKendaraan">
                                    <i class="fas fa-times me-1"></i>Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- DAFTAR KENDARAAN -->
            <?php if ($kendaraan && count($kendaraan) > 0): ?>
                <div class="mb-3">
                    <h6 style="color: #94a3b8; font-weight: 600;">
                        <i class="fas fa-list me-2"></i>Total Kendaraan: <?= count($kendaraan); ?>
                    </h6>
                </div>
                <?php foreach ($kendaraan as $v): ?>
                <div class="vehicle-card">
                    <h6>
                        <span><i class="fas fa-charging-station me-2"></i><?= htmlspecialchars($v['merk'] . ' ' . $v['model']); ?></span>
                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus kendaraan ini?');">
                            <input type="hidden" name="id_kendaraan" value="<?= $v['id_kendaraan']; ?>">
                            <button type="submit" name="hapus_kendaraan" class="btn btn-delete">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                    </h6>
                    <p><strong><i class="fas fa-id-card me-2"></i>No. Plat:</strong> <?= htmlspecialchars($v['no_plat']); ?></p>
                    <p><strong><i class="fas fa-calendar-alt me-2"></i>Tahun:</strong> <?= htmlspecialchars($v['tahun'] ?? '-'); ?></p>
                    <p style="font-size: 0.85rem; color: #64748b;"><i class="fas fa-clock me-2"></i>Ditambahkan: <?= date('d M Y H:i', strtotime($v['created_at'])); ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5" style="color: #94a3b8;">
                    <i class="fas fa-car" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h5 style="margin-bottom: 10px;">Belum ada kendaraan terdaftar</h5>
                    <p style="font-size: 0.95rem;">Klik tombol <strong>"Tambah Kendaraan Baru"</strong> di atas untuk mendaftarkan kendaraan listrik Anda.</p>
                    <p style="font-size: 0.85rem; margin-top: 15px;">
                        <i class="fas fa-lightbulb me-2"></i>
                        Tip: Anda dapat mendaftarkan lebih dari satu kendaraan
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
</div>

</div>

<!-- SCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle Tema
const toggleButton = document.getElementById("toggleTheme");

const savedTheme = localStorage.getItem("theme");
if (savedTheme === "light") {
    document.body.classList.add("light");
    toggleButton.textContent = "☀️";
} else {
    toggleButton.textContent = "🌙";
}

toggleButton.addEventListener("click", () => {
    document.body.classList.toggle("light");
    
    const isLight = document.body.classList.contains("light");
    toggleButton.textContent = isLight ? "☀️" : "🌙";
    localStorage.setItem("theme", isLight ? "light" : "dark");
});

// Auto close form after submit success
<?php if ($message && strpos($message, 'Kendaraan berhasil ditambahkan') !== false): ?>
    const collapseForm = document.getElementById('formTambahKendaraan');
    if (collapseForm && collapseForm.classList.contains('show')) {
        const bsCollapse = bootstrap.Collapse.getInstance(collapseForm) || new bootstrap.Collapse(collapseForm, {toggle: false});
        bsCollapse.hide();
    }
    
    // Scroll to vehicle list
    setTimeout(() => {
        const vehicleSection = document.querySelector('.vehicle-card');
        if (vehicleSection) {
            vehicleSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 500);
<?php endif; ?>

// Auto open form if there's error on vehicle form
<?php if ($error && isset($_POST['tambah_kendaraan'])): ?>
    const collapseFormError = document.getElementById('formTambahKendaraan');
    if (collapseFormError && !collapseFormError.classList.contains('show')) {
        const bsCollapseError = new bootstrap.Collapse(collapseFormError, {toggle: true});
    }
<?php endif; ?>

// Auto uppercase no plat
document.getElementById('no_plat')?.addEventListener('input', function(e) {
    e.target.value = e.target.value.toUpperCase();
});
</script>

</body>
</html>
