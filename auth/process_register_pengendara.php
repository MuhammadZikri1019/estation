<?php
session_start();
require_once "../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil dan bersihkan input
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_telepon = trim($_POST['no_telepon']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validasi input kosong
    if (empty($nama) || empty($email) || empty($no_telepon) || empty($password) || empty($confirm_password)) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Semua field harus diisi!'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    // Validasi email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Format email tidak valid!'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    // Validasi nomor telepon
    if (!preg_match('/^[0-9]{10,15}$/', $no_telepon)) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Format nomor telepon tidak valid! (10-15 digit)'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    // Validasi password minimal 8 karakter
    if (strlen($password) < 8) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Password minimal 8 karakter!'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    // Validasi password strength
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Password harus mengandung huruf besar, kecil, angka, dan simbol!'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    // Validasi password match
    if ($password !== $confirm_password) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Password dan konfirmasi password tidak cocok!'
        ];
        header("Location: ../auth.php");
        exit();
    }
    
    try {
        // Cek apakah email sudah terdaftar
        $stmt = $koneksi->prepare("SELECT email FROM pengendara WHERE email = ? UNION SELECT email FROM admin WHERE email = ? UNION SELECT email FROM mitra WHERE email = ?");
        $stmt->execute([$email, $email, $email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => 'Email sudah terdaftar! Silakan gunakan email lain.'
            ];
            header("Location: ../auth.php");
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert ke database (langsung aktif, tanpa verifikasi email)
        $stmt = $koneksi->prepare("INSERT INTO pengendara (nama, email, no_telepon, password, is_verified, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        
        if ($stmt->execute([$nama, $email, $no_telepon, $hashed_password])) {
            // Dapatkan ID user yang baru dibuat
            $pengendara_id = $koneksi->lastInsertId();
            
            // Dapatkan data lengkap user
            $stmt = $koneksi->prepare("SELECT * FROM pengendara WHERE id = ?");
            $stmt->execute([$pengendara_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set session untuk auto-login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['no_telepon'] = $user['no_telepon'];
            $_SESSION['role'] = 'pengendara';
            $_SESSION['login_time'] = time();
            
            // Set alert sukses
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Selamat datang, ' . $nama . '! Registrasi berhasil.'
            ];
            
            // Redirect ke dashboard pengendara
            header("Location: ../pengendara/dashboard.php");
            exit();
        } else {
            throw new Exception("Gagal menyimpan data.");
        }
        
    } catch (Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Terjadi kesalahan: ' . $e->getMessage()
        ];
        header("Location: ../auth.php");
        exit();
    }
} else {
    header("Location: ../auth.php");
    exit();
}
?>