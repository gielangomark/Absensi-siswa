<?php
include 'config.php';

// Check if student is logged in
if (!isset($_SESSION['siswa_nis'])) {
    header("Location: login_siswa.php");
    exit();
}

$nis = $_SESSION['siswa_nis'];
$nama = $_SESSION['siswa_nama'];

// Get student data
$siswa_query = $conn->prepare("SELECT * FROM siswa WHERE nis = ?");
$siswa_query->bind_param("s", $nis);
$siswa_query->execute();
$siswa_result = $siswa_query->get_result();
$siswa_data = $siswa_result->fetch_assoc();

// Process form submission
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a profile update
    if (isset($_POST['update_profile'])) {
        $email = $_POST['email'];
        $alamat = $_POST['alamat'];
        $telepon = $_POST['telepon'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        
        // Update student information
        $update_query = $conn->prepare("UPDATE siswa SET email = ?, alamat = ?, telepon = ?, tanggal_lahir = ?, jenis_kelamin = ? WHERE nis = ?");
        $update_query->bind_param("ssssss", $email, $alamat, $telepon, $tanggal_lahir, $jenis_kelamin, $nis);
        
        if ($update_query->execute()) {
            $message = "Profil berhasil diperbarui!";
            $message_type = "success";
            
            // Refresh student data
            $siswa_query->execute();
            $siswa_result = $siswa_query->get_result();
            $siswa_data = $siswa_result->fetch_assoc();
        } else {
            $message = "Gagal memperbarui profil: " . $conn->error;
            $message_type = "danger";
        }
    }
    
    // Check if it's a photo upload
    if (isset($_FILES['foto']) && $_FILES['foto']['size'] > 0) {
        $target_dir = "uploads/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Get file extension
        $file_ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
        
        // Generate a unique filename
        $new_filename = "student_" . $nis . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        // Allow certain file formats
        $allowed_extensions = array("jpg", "jpeg", "png", "gif");
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $message = "Hanya file JPG, JPEG, PNG, dan GIF yang diperbolehkan.";
            $message_type = "danger";
        } else if ($_FILES["foto"]["size"] > 5000000) { // 5MB max
            $message = "Ukuran file terlalu besar. Maksimal 5MB.";
            $message_type = "danger";
        } else if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            // Delete old photo if exists and not the default
            if (!empty($siswa_data['foto']) && file_exists($target_dir . $siswa_data['foto']) && $siswa_data['foto'] != "default.jpg") {
                unlink($target_dir . $siswa_data['foto']);
            }
            
            // Update database with new filename
            $update_photo_query = $conn->prepare("UPDATE siswa SET foto = ? WHERE nis = ?");
            $update_photo_query->bind_param("ss", $new_filename, $nis);
            
            if ($update_photo_query->execute()) {
                $message = "Foto profil berhasil diperbarui!";
                $message_type = "success";
                
                // Refresh student data
                $siswa_query->execute();
                $siswa_result = $siswa_query->get_result();
                $siswa_data = $siswa_result->fetch_assoc();
            } else {
                $message = "Gagal memperbarui data foto: " . $conn->error;
                $message_type = "danger";
            }
        } else {
            $message = "Terjadi kesalahan saat mengunggah foto.";
            $message_type = "danger";
        }
    }
    
    // Check if it's a password change
    if (isset($_POST['change_password'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify if old password is correct
        $check_password_query = $conn->prepare("SELECT password FROM siswa WHERE nis = ?");
        $check_password_query->bind_param("s", $nis);
        $check_password_query->execute();
        $result = $check_password_query->get_result();
        $current_password_data = $result->fetch_assoc();
        
        // Make sure password data exists
        if (!$current_password_data) {
            $message = "Terjadi kesalahan saat memverifikasi password.";
            $message_type = "danger";
        } else {
            $current_password = $current_password_data['password'];
            
            // Check if password is already hashed or not
            if (password_verify($old_password, $current_password)) {
                // Old password is correct
                if ($new_password != $confirm_password) {
                    $message = "Password baru dan konfirmasi password tidak sama.";
                    $message_type = "danger";
                } else if (strlen($new_password) < 6) {
                    $message = "Password baru minimal 6 karakter.";
                    $message_type = "danger";
                } else {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_password_query = $conn->prepare("UPDATE siswa SET password = ? WHERE nis = ?");
                    $update_password_query->bind_param("ss", $hashed_password, $nis);
                    
                    if ($update_password_query->execute()) {
                        $message = "Password berhasil diubah!";
                        $message_type = "success";
                    } else {
                        $message = "Gagal mengubah password: " . $conn->error;
                        $message_type = "danger";
                    }
                }
            } else {
                // Check if we need to handle plain text passwords (legacy)
                // This is for backward compatibility if passwords weren't hashed before
                $check_plain_password_query = $conn->prepare("SELECT password FROM siswa WHERE nis = ? AND password = ?");
                $check_plain_password_query->bind_param("ss", $nis, $old_password);
                $check_plain_password_query->execute();
                $plain_result = $check_plain_password_query->get_result();
                
                if ($plain_result->num_rows > 0) {
                    // Old plain password is correct, proceed with update and migration to hashed
                    if ($new_password != $confirm_password) {
                        $message = "Password baru dan konfirmasi password tidak sama.";
                        $message_type = "danger";
                    } else if (strlen($new_password) < 6) {
                        $message = "Password baru minimal 6 karakter.";
                        $message_type = "danger";
                    } else {
                        // Hash the new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password
                        $update_password_query = $conn->prepare("UPDATE siswa SET password = ? WHERE nis = ?");
                        $update_password_query->bind_param("ss", $hashed_password, $nis);
                        
                        if ($update_password_query->execute()) {
                            $message = "Password berhasil diubah!";
                            $message_type = "success";
                        } else {
                            $message = "Gagal mengubah password: " . $conn->error;
                            $message_type = "danger";
                        }
                    }
                } else {
                    $message = "Password lama tidak sesuai.";
                    $message_type = "danger";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Siswa - <?= htmlspecialchars($nama) ?></title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .main-content {
            padding: 30px;
            margin-top: 20px;
        }
        
        .profile-header {
            margin-bottom: 30px;
        }
        
        .profile-header h1 {
            font-weight: 700;
            color: #2c3e50;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c3e50;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .profile-photo-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
        }
        
        .profile-photo {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #3498db;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .photo-placeholder {
            width: 200px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 50%;
            border: 4px solid #3498db;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            font-size: 80px;
            color: #95a5a6;
        }
        
        .photo-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: #3498db;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .photo-overlay:hover {
            background-color: #2980b9;
        }
        
        .btn-primary {
            background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #e9ecef;
        }
        
        .custom-file-label {
            overflow: hidden;
        }
        
        .tab-content {
            padding-top: 20px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #7f8c8d;
            font-weight: 600;
            padding: 10px 20px;
        }
        
        .nav-tabs .nav-link.active {
            color: #3498db;
            border-bottom: 3px solid #3498db;
            background-color: transparent;
        }
        
        .personal-info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .personal-info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .profile-photo, .photo-placeholder {
                width: 150px;
                height: 150px;
            }
            
            .profile-photo-container {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="assets/images/40.png" alt="Logo Sekolah"> SMKN 40 Jakarta
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard_siswa.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="profil_siswa.php"><i class="fas fa-user"></i> Profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?php if ($message_type == 'success'): ?>
                <i class="fas fa-check-circle mr-2"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle mr-2"></i>
            <?php endif; ?>
            <?= $message ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="profile-header">
            <h1><i class="fas fa-user-circle mr-3"></i>Profil Siswa</h1>
            <p class="text-muted">Kelola informasi pribadi dan akun Anda</p>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-id-card mr-2"></i> Info Utama
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-photo-container">
                            <?php if (!empty($siswa_data['foto']) && file_exists("uploads/" . $siswa_data['foto'])): ?>
                                <img src="uploads/<?= htmlspecialchars($siswa_data['foto']) ?>" alt="Foto <?= htmlspecialchars($nama) ?>" class="profile-photo">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <label for="photo-upload" class="photo-overlay" data-toggle="tooltip" title="Ubah Foto">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <h3 class="mb-1"><?= htmlspecialchars($nama) ?></h3>
                        <p class="badge badge-primary mb-3"><?= htmlspecialchars($siswa_data['nis']) ?></p>
                        
                        <form id="photo-form" method="post" enctype="multipart/form-data" style="display: none;">
                            <input type="file" name="foto" id="photo-upload" class="custom-file-input" accept="image/*" onchange="document.getElementById('photo-form').submit();">
                        </form>
                        
                        <div class="personal-info">
                            <div class="personal-info-item">
                                <div class="info-label">Kelas</div>
                                <div><?= htmlspecialchars($siswa_data['kelas']) ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Jurusan</div>
                                <div><?= htmlspecialchars($siswa_data['jurusan']) ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Email</div>
                                <div><?= htmlspecialchars($siswa_data['email'] ?? '-') ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Nomor Telepon</div>
                                <div><?= htmlspecialchars($siswa_data['telepon'] ?? '-') ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Tanggal Lahir</div>
                                <div><?= !empty($siswa_data['tanggal_lahir']) ? date('d F Y', strtotime($siswa_data['tanggal_lahir'])) : '-' ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Jenis Kelamin</div>
                                <div><?= htmlspecialchars($siswa_data['jenis_kelamin'] ?? '-') ?></div>
                            </div>
                            <div class="personal-info-item">
                                <div class="info-label">Alamat</div>
                                <div><?= htmlspecialchars($siswa_data['alamat'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-cog mr-2"></i> Pengaturan Profil
                        </div>
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal">Data Diri</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="password-tab" data-toggle="tab" href="#password">Password</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="personal">
                                <form method="post" action="profil_siswa.php">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nama">Nama Lengkap</label>
                                                <input type="text" class="form-control" id="nama" value="<?= htmlspecialchars($nama) ?>" readonly>
                                                <small class="form-text text-muted">Nama tidak dapat diubah. Hubungi admin untuk perubahan.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nis">NIS</label>
                                                <input type="text" class="form-control" id="nis" value="<?= htmlspecialchars($nis) ?>" readonly>
                                                <small class="form-text text-muted">NIS tidak dapat diubah.</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                    </div>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($siswa_data['email'] ?? '') ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="telepon">Nomor Telepon</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control" id="telepon" name="telepon" value="<?= htmlspecialchars($siswa_data['telepon'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tanggal_lahir">Tanggal Lahir</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                    </div>
                                                    <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?= htmlspecialchars($siswa_data['tanggal_lahir'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="jenis_kelamin">Jenis Kelamin</label>
                                                <select class="form-control" id="jenis_kelamin" name="jenis_kelamin">
                                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                                    <option value="Laki-laki" <?= (isset($siswa_data['jenis_kelamin']) && $siswa_data['jenis_kelamin'] == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                                    <option value="Perempuan" <?= (isset($siswa_data['jenis_kelamin']) && $siswa_data['jenis_kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="alamat">Alamat</label>
                                        <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($siswa_data['alamat'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="form-group mt-4">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i> Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="password">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> Pastikan untuk menggunakan password yang kuat dan jangan bagikan dengan orang lain.
                                </div>
                                <form method="post" action="profil_siswa.php">
                                    <div class="form-group">
                                        <label for="old_password">Password Lama</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="old_password" name="old_password" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="old_password">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new_password">Password Baru</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="new_password">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Minimal 6 karakter</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Konfirmasi Password Baru</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="confirm_password">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group mt-4">
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="fas fa-key mr-1"></i> Ubah Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shield-alt mr-2"></i> Keamanan Akun
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle mr-2"></i>Tips Keamanan Akun</h5>
                            <ul class="mb-0">
                                <li>Gunakan password yang kuat dengan kombinasi huruf, angka, dan simbol.</li>
                                <li>Jangan menggunakan password yang sama dengan akun lain.</li>
                                <li>Jangan membagikan password atau informasi akun Anda kepada siapapun.</li>
                                <li>Selalu logout setelah menggunakan komputer publik atau perangkat yang bukan milik Anda.</li>
                                <li>Perbarui password secara berkala (minimal 3 bulan sekali).</li>
                            </ul>
                        </div>
                        <p>Terakhir login: <strong><?= isset($siswa_data['last_login']) ? date('d F Y H:i', strtotime($siswa_data['last_login'])) : 'Belum ada data login' ?></strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Enable tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
            
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const targetId = $(this).data('target');
                const input = $('#' + targetId);
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Show success message based on hash
            if (window.location.hash === '#profile-updated') {
                $('.alert-success').removeClass('d-none').addClass('show');
            }
        });
    </script>
</body>
</html>