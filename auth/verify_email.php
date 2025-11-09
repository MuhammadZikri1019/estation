<?php
session_start();
require_once "../config/koneksi.php";

// Ambil token dan type dari URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

// Validasi parameter
if (empty($token) || empty($type)) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => 'Link verifikasi tidak valid!'
    ];
    header("Location: login.php");
    exit();
}

// Validasi type
if (!in_array($type, ['pengendara', 'mitra'])) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => 'Tipe akun tidak valid!'
    ];
    header("Location: login.php");
    exit();
}

try {
    // Tentukan tabel berdasarkan type
    $table = $type === 'pengendara' ? 'pengendara' : 'mitra';
    
    // Cari user berdasarkan token
    $stmt = $koneksi->prepare("SELECT * FROM $table WHERE verification_token = ? LIMIT 1");
    $stmt->execute([$token]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Token verifikasi tidak ditemukan atau sudah digunakan!'
        ];
        header("Location: login.php");
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cek apakah sudah terverifikasi
    if ($user['is_verified'] == 1) {
        $_SESSION['alert'] = [
            'type' => 'info',
            'message' => 'Email sudah terverifikasi sebelumnya. Silakan login.'
        ];
        header("Location: login.php");
        exit();
    }
    
    // Cek apakah token sudah expired
    $current_time = date('Y-m-d H:i:s');
    if ($current_time > $user['token_expiry']) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Link verifikasi sudah kadaluarsa! Silakan daftar ulang.'
        ];
        header("Location: auth.php");
        exit();
    }
    
    // Update status verifikasi
    $stmt = $koneksi->prepare("UPDATE $table SET is_verified = 1, verification_token = NULL, token_expiry = NULL, verified_at = NOW() WHERE verification_token = ?");
    
    if ($stmt->execute([$token])) {
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Email berhasil diverifikasi! Silakan login untuk melanjutkan.'
        ];
        header("Location: login.php");
        exit();
    } else {
        throw new Exception("Gagal memverifikasi email.");
    }
    
} catch (Exception $e) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ];
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - E-Station</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-container {
            max-width: 500px;
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h3 class="mt-4">Memverifikasi Email...</h3>
        <p class="text-muted">Mohon tunggu sebentar</p>
    </div>
</body>
</html>



