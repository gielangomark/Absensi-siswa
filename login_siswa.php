<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nis = $_POST['nis'];
    $password = $_POST['password'];

    // Query untuk memeriksa NIS dan password
    $query = "SELECT * FROM siswa WHERE nis='$nis' AND password='$password'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $siswa = $result->fetch_assoc();
        $_SESSION['siswa_nis'] = $nis;
        $_SESSION['siswa_nama'] = $siswa['nama'];
        header("Location: dashboard_siswa.php?login_success=true");
        exit();
    } else {
        echo "<script>alert('Login gagal. Periksa kembali NIS dan password.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .login-card {
            width: 340px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
        }
        
        .login-card-header {
            background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .logo {
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ddd;
        }
        
        .btn-primary {
            background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-image: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }
        
        .login-card-footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            top: 15px;
            left: 15px;
            color: #3498db;
        }
        
        .input-icon input {
            padding-left: 40px;
        }
        
        .switch-login {
            text-align: center;
            margin-top: 15px;
        }
        
        .switch-login a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        
        .switch-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card">
            <div class="login-card-header">
                <div class="logo">
                    <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid" width="100px">
                </div>
                <h3>Login Siswa</h3>
                <p class="mb-0">Masuk dengan NIS dan Password Anda</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group input-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" class="form-control" id="nis" name="nis" placeholder="Nomor Induk Siswa (NIS)" required>
                    </div>
                    <div class="form-group input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                </form>
                <div class="switch-login">
                    <small>Admin? <a href="login.php">Login sebagai Admin</a></small>
                </div>
            </div>
            <div class="login-card-footer">
                <small>&copy; 2025 SMKN 40 Jakarta</small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>