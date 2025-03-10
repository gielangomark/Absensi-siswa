<?php
session_start();
$host = 'localhost';
$user = 'root'; 
$pass = ''; 
$db   = 'absensi_siswa';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Set timezone if needed
date_default_timezone_set('Asia/Jakarta');
?>