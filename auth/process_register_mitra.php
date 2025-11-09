<?php
session_start();
require_once "../../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil dan bersihkan input
    $nama_mitra = trim($_POST['nama_mitra']);
    $email = trim($_POST['email']);
    $no_telepon = trim($_POST['no_telepon']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validasi input kosong
    if (empty($nama_mitra) || empty($email) || empty($no_telepon) || empty($password) || empty($confirm_password)) {
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
        $stmt = $koneksi->prepare("SELECT email FROM mitra WHERE email = ? UNION SELECT email FROM admin WHERE email = ? UNION SELECT email FROM pengendara WHERE email = ?");
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
        
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert ke database
        $stmt = $koneksi->prepare("INSERT INTO mitra (nama_mitra, email, no_telepon, password, verification_token, token_expiry, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        
        if ($stmt->execute([$nama_mitra, $email, $no_telepon, $hashed_password, $verification_token, $token_expiry])) {
            // Kirim email verifikasi
            $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/auth/verify_email.php?token=" . $verification_token . "&type=mitra";
            
            // Setup email
            $to = $email;
            $subject = "Verifikasi Email - E-Station Mitra";
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>E-STATION</h1>
                        <p>Layanan Pengisian Kendaraan Listrik</p>
                    </div>
                    <div class='content'>
                        <h2>Halo, " . htmlspecialchars($nama_mitra) . "!</h2>
                        <p>Terima kasih telah mendaftar sebagai Mitra di E-Station.</p>
                        <p>Untuk mengaktifkan akun mitra Anda, silakan klik tombol verifikasi di bawah ini:</p>
                        <p style='text-align: center;'>
                            <a href='" . $verification_link . "' class='button'>Verifikasi Email</a>
                        </p>
                        <p>Atau salin link berikut ke browser Anda:</p>
                        <p style='word-break: break-all; background: #fff; padding: 10px; border-radius: 5px;'>" . $verification_link . "</p>
                        <p><strong>Link verifikasi berlaku selama 24 jam.</strong></p>
                        <p>Setelah verifikasi, akun Anda akan ditinjau oleh admin sebelum dapat digunakan.</p>
                        <p>Jika Anda tidak merasa mendaftar, abaikan email ini.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2024 E-Station. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: E-Station <noreply@e-station.com>" . "\r\n";
            
            // Kirim email
            @mail($to, $subject, $message, $headers);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Registrasi berhasil! Silakan cek email Anda untuk verifikasi akun.'
            ];
            header("Location: ../auth.php");
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