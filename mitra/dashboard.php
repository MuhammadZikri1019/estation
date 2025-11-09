<?php
//memulai sesi
session_start();

// menghubungkan ke database
require_once "../koneksi.php";

// Cek apakah sudah login sebagai admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pengendara') {
    header("Location: ../login.php");
    exit();
}