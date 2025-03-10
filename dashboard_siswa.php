<?php
include 'config.php';

// Check if student is logged in
if (!isset($_SESSION['siswa_nis'])) {
    header("Location: login_siswa.php");
    exit();
}

$nis = $_SESSION['siswa_nis'];
$nama = $_SESSION['siswa_nama'];

// Create the absensi_request table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS absensi_request (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nis VARCHAR(20) NOT NULL,
        tanggal_request DATETIME NOT NULL,
        status_request ENUM('Hadir', 'Sakit', 'Terlambat') NOT NULL,
        keterangan TEXT,
        bukti_file VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        tanggal_respons DATETIME DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Create notifications table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT(11) NOT NULL AUTO_INCREMENT,
        target_role ENUM('admin', 'guru', 'siswa') NOT NULL,
        target_id VARCHAR(50) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Get student data
$siswa_query = $conn->prepare("SELECT * FROM siswa WHERE nis = ?");
$siswa_query->bind_param("s", $nis);
$siswa_query->execute();
$siswa_result = $siswa_query->get_result();
$siswa_data = $siswa_result->fetch_assoc();

// Initialize dates
$tanggal_hari_ini = date('Y-m-d');
$bulan_tahun = date('Y-m');

// Get today's attendance for this student
$absensi_hari_ini_query = $conn->prepare("
    SELECT * FROM absensi 
    JOIN siswa ON absensi.nis = siswa.nis
    WHERE siswa.nis = ? AND DATE(tanggal) = ?
");
$absensi_hari_ini_query->bind_param("ss", $nis, $tanggal_hari_ini);
$absensi_hari_ini_query->execute();
$absensi_hari_ini_result = $absensi_hari_ini_query->get_result();
$total_absen = $absensi_hari_ini_result->num_rows;
$absensi_hari_ini = $total_absen > 0 ? $absensi_hari_ini_result->fetch_assoc() : null;
$status_hari_ini = $absensi_hari_ini ? $absensi_hari_ini['status'] : 'Belum Absen';

// Get monthly attendance count for this student
$monthly_status_query = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM absensi 
    JOIN siswa ON absensi.nis = siswa.nis
    WHERE siswa.nis = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? 
    GROUP BY status
");
$monthly_status_query->bind_param("ss", $nis, $bulan_tahun);
$monthly_status_query->execute();
$monthly_status_result = $monthly_status_query->get_result();

// Initialize counts
$hadir_count = 0;
$sakit_count = 0;
$terlambat_count = 0;
$alpha_count = 0;

// Process monthly status counts
while ($row = $monthly_status_result->fetch_assoc()) {
    switch ($row['status']) {
        case 'Hadir':
            $hadir_count = $row['count'];
            break;
        case 'Sakit':
            $sakit_count = $row['count'];
            break;
        case 'Terlambat':
            $terlambat_count = $row['count'];
            break;
        case 'Alpha':
            $alpha_count = $row['count'];
            break;
    }
}

// Get total days in current month
$total_days = date('t');
$workdays = 0;

// Calculate working days (Monday to Friday) in current month
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');
$current_date = strtotime($start_date);
$end_timestamp = strtotime($end_date);
while ($current_date <= $end_timestamp) {
    $day_of_week = date('N', $current_date);
    if ($day_of_week <= 5) { // Monday to Friday (1-5)
        $workdays++;
    }
    $current_date = strtotime('+1 day', $current_date);
}

// Get attendance history (last 30 days)
$absensi_history_query = $conn->prepare("
    SELECT absensi.* 
    FROM absensi 
    JOIN siswa ON absensi.nis = siswa.nis
    WHERE siswa.nis = ? 
    ORDER BY tanggal DESC LIMIT 30
");
$absensi_history_query->bind_param("s", $nis);
$absensi_history_query->execute();
$absensi_history_result = $absensi_history_query->get_result();

// Calculate attendance percentage
$attendance_percentage = $workdays > 0 ? round((($hadir_count + $terlambat_count) / $workdays) * 100, 2) : 0;

// Get class attendance today
$kelas = $siswa_data['kelas'];
$jurusan = $siswa_data['jurusan'];
$class_attendance_query = $conn->prepare("
    SELECT absensi.status, COUNT(*) as count 
    FROM absensi 
    JOIN siswa ON absensi.nis = siswa.nis 
    WHERE siswa.kelas = ? AND siswa.jurusan = ? AND DATE(absensi.tanggal) = ?
    GROUP BY absensi.status
");
$class_attendance_query->bind_param("sss", $kelas, $jurusan, $tanggal_hari_ini);
$class_attendance_query->execute();
$class_attendance_result = $class_attendance_query->get_result();

// Initialize class attendance counts
$class_hadir = 0;
$class_sakit = 0;
$class_terlambat = 0;
$class_alpha = 0;

// Process class attendance counts
while ($row = $class_attendance_result->fetch_assoc()) {
    switch ($row['status']) {
        case 'Hadir':
            $class_hadir = $row['count'];
            break;
        case 'Sakit':
            $class_sakit = $row['count'];
            break;
        case 'Terlambat':
            $class_terlambat = $row['count'];
            break;
        case 'Alpha':
            $class_alpha = $row['count'];
            break;
    }
}

// Get total students in class
$class_total_query = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE kelas = ? AND jurusan = ?");
$class_total_query->bind_param("ss", $kelas, $jurusan);
$class_total_query->execute();
$class_total_result = $class_total_query->get_result();
$class_total = $class_total_result->fetch_assoc()['total'];

// Calculate class attendance percentages
$class_hadir_percentage = $class_total > 0 ? round(($class_hadir / $class_total) * 100, 2) : 0;
$class_sakit_percentage = $class_total > 0 ? round(($class_sakit / $class_total) * 100, 2) : 0;
$class_terlambat_percentage = $class_total > 0 ? round(($class_terlambat / $class_total) * 100, 2) : 0;
$class_alpha_percentage = $class_total > 0 ? round(($class_alpha / $class_total) * 100, 2) : 0;

// Handle attendance request submission
$attendance_message = '';
$attendance_alert_type = '';

// Check if there's a pending attendance request
$has_pending_request = false;
try {
    $pending_request_query = $conn->prepare("
        SELECT * FROM absensi_request 
        WHERE nis = ? AND DATE(tanggal_request) = ? AND status = 'pending'
    ");
    $pending_request_query->bind_param("ss", $nis, $tanggal_hari_ini);
    $pending_request_query->execute();
    $pending_request_result = $pending_request_query->get_result();
    $has_pending_request = $pending_request_result->num_rows > 0;
} catch (Exception $e) {
    // Table doesn't exist or other error, default to no pending request
    $has_pending_request = false;
}

if (isset($_POST['submit_attendance'])) {
    // Check if an image is uploaded
    if (isset($_FILES['bukti_kehadiran']) && $_FILES['bukti_kehadiran']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        if (in_array($_FILES['bukti_kehadiran']['type'], $allowed_types)) {
            $file_name = time() . '_' . $nis . '_' . $_FILES['bukti_kehadiran']['name'];
            $upload_path = 'uploads/attendance_proof/' . $file_name;
            // Create directory if it doesn't exist
            if (!file_exists('uploads/attendance_proof/')) {
                mkdir('uploads/attendance_proof/', 0777, true);
            }
            if (move_uploaded_file($_FILES['bukti_kehadiran']['tmp_name'], $upload_path)) {
                // File uploaded successfully, now insert request into database
                $status_request = $_POST['status_request'];
                $keterangan = $_POST['keterangan'];
                $now = date('Y-m-d H:i:s');
                // Insert request into database
                $insert_query = $conn->prepare("
                    INSERT INTO absensi_request (nis, tanggal_request, status_request, keterangan, bukti_file, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $insert_query->bind_param("sssss", $nis, $now, $status_request, $keterangan, $file_name);
                if ($insert_query->execute()) {
                    $attendance_message = "Permintaan absensi berhasil dikirim ke admin dan sedang menunggu persetujuan.";
                    $attendance_alert_type = "success";
                    // Send notification to admin
                    $notification_message = "Siswa $nama ($nis) dari kelas $kelas $jurusan telah mengajukan permintaan absensi dengan status $status_request.";
                    $notification_time = date('Y-m-d H:i:s');
                    $notification_query = $conn->prepare("
                        INSERT INTO notifications (target_role, message, created_at)
                        VALUES ('admin', ?, ?)
                    ");
                    $notification_query->bind_param("ss", $notification_message, $notification_time);
                    $notification_query->execute();
                    // Redirect to prevent form resubmission
                    header("Location: dashboard_siswa.php?attendance_success=true");
                    exit();
                } else {
                    $attendance_message = "Gagal mengirim permintaan absensi. Silakan coba lagi.";
                    $attendance_alert_type = "danger";
                }
            } else {
                $attendance_message = "Gagal mengunggah file bukti kehadiran. Silakan coba lagi.";
                $attendance_alert_type = "danger";
            }
        } else {
            $attendance_message = "Format file tidak didukung. Gunakan file JPG, JPEG, atau PNG.";
            $attendance_alert_type = "danger";
        }
    } else {
        $attendance_message = "Anda harus mengunggah bukti kehadiran.";
        $attendance_alert_type = "danger";
    }
}

// Handle GET parameter for success message
if (isset($_GET['attendance_success']) && $_GET['attendance_success'] == 'true') {
    $attendance_message = "Permintaan absensi berhasil dikirim ke admin dan sedang menunggu persetujuan.";
    $attendance_alert_type = "success";
}
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard Siswa - <?= htmlspecialchars($nama) ?></title>
        <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
        <link href="assets/css/style.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            .dashboard-header {
                margin-bottom: 30px;
            }
            
            .dashboard-header h1 {
                font-weight: 700;
                color: #2c3e50;
            }
            
            .student-info {
                background-color: white;
                border-radius: 15px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                padding: 20px;
                margin-bottom: 30px;
                transition: transform 0.3s ease;
            }
            
            .student-info:hover {
                transform: translateY(-5px);
            }
            
            .student-photo {
                width: 100px;
                height: 100px;
                object-fit: cover;
                border-radius: 50%;
                border: 4px solid #3498db;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }
            
            .photo-placeholder {
                width: 100px;
                height: 100px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0f0f0;
                border-radius: 50%;
                border: 4px solid #3498db;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                font-size: 40px;
                color: #95a5a6;
            }
            
            .student-info h2 {
                margin-top: 15px;
                color: #3498db;
                font-weight: 600;
            }
            
            .student-details {
                margin-top: 15px;
            }
            
            .detail-label {
                font-weight: 600;
                color: #7f8c8d;
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
            
            .status-card {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 20px;
                border-radius: 15px;
                color: white;
                text-align: center;
            }
            
            .status-card i {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            
            .status-card h3 {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 5px;
            }
            
            .status-card p {
                font-size: 1rem;
                margin-bottom: 0;
            }
            
            .status-hadir {
                background-image: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            }
            
            .status-sakit {
                background-image: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            }
            
            .status-terlambat {
                background-image: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
            }
            
            .status-alpha {
                background-image: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            }
            
            .status-unknown {
                background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            }
            
            .chart-container {
                position: relative;
                height: 300px;
                width: 100%;
            }
            
            .history-table th, .history-table td {
                vertical-align: middle;
            }
            
            .status-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.8rem;
            }
            
            .badge-hadir {
                background-color: #2ecc71;
                color: white;
            }
            
            .badge-sakit {
                background-color: #e74c3c;
                color: white;
            }
            
            .badge-terlambat {
                background-color: #f39c12;
                color: white;
            }
            
            .badge-alpha {
                background-color: #95a5a6;
                color: white;
            }
            
            .attendance-progress {
                height: 10px;
                border-radius: 5px;
                margin-top: 5px;
            }
            
            .attendance-progress-bar {
                background-image: linear-gradient(to right, #3498db, #2980b9);
                border-radius: 5px;
            }
            
            /* NEW STYLES FOR ATTENDANCE FORM */
            .attendance-form {
                background-color: white;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            }
            
            .attendance-form label {
                font-weight: 600;
                color: #2c3e50;
            }
            
            .attendance-form .btn-submit {
                background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                border: none;
                border-radius: 10px;
                padding: 10px 20px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .attendance-form .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }
            
            .file-preview {
                max-width: 100%;
                max-height: 200px;
                border-radius: 10px;
                margin-top: 10px;
                display: none;
            }
            
            .attendance-btn-container {
                text-align: center;
                margin: 20px 0;
            }
            
            .btn-attendance {
                background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                color: white;
                border: none;
                border-radius: 10px;
                padding: 12px 25px;
                font-weight: 600;
                font-size: 16px;
                transition: all 0.3s ease;
            }
            
            .btn-attendance:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .btn-attendance i {
                margin-right: 10px;
            }
            
            .pending-badge {
                background-color: #f39c12;
                color: white;
                font-size: 0.8rem;
                padding: 5px 10px;
                border-radius: 20px;
                margin-left: 10px;
            }
            
            @media (max-width: 768px) {
                .main-content {
                    padding: 15px;
                }
                
                .student-photo, .photo-placeholder {
                    width: 80px;
                    height: 80px;
                }
                
                .status-card i {
                    font-size: 2rem;
                }
                
                .status-card h3 {
                    font-size: 1.5rem;
                }
                
                .chart-container {
                    height: 250px;
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
                        <li class="nav-item active">
                            <a class="nav-link" href="dashboard_siswa.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
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
            <?php if (isset($_GET['login_success']) && $_GET['login_success'] == 'true'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i> Selamat datang kembali, <?= htmlspecialchars($nama) ?>!
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- NEW CODE: Display attendance submission message -->
            <?php if (!empty($attendance_message)): ?>
            <div class="alert alert-<?= $attendance_alert_type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $attendance_alert_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i> <?= $attendance_message ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <div class="dashboard-header">
                <h1><i class="fas fa-tachometer-alt mr-3"></i>Dashboard Siswa</h1>
                <p class="text-muted">Pantau kehadiran dan informasi kelas Anda</p>
            </div>

            <!-- NEW CODE: Attendance Button and Modal -->
            <?php if ($status_hari_ini == 'Belum Absen' && !$has_pending_request): ?>
            <div class="attendance-btn-container">
                <button type="button" class="btn btn-attendance" data-toggle="modal" data-target="#attendanceModal">
                    <i class="fas fa-clipboard-check"></i> Absen Sekarang
                </button>
            </div>
            <?php elseif ($has_pending_request): ?>
            <div class="attendance-btn-container">
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="fas fa-clock"></i> Permintaan Absen Menunggu Persetujuan
                </button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="student-info text-center">
                        <?php if (!empty($siswa_data['foto']) && file_exists("uploads/" . $siswa_data['foto'])): ?>
                            <img src="uploads/<?= htmlspecialchars($siswa_data['foto']) ?>" alt="Foto <?= htmlspecialchars($nama) ?>" class="student-photo">
                        <?php else: ?>
                            <div class="photo-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <h2><?= htmlspecialchars($nama) ?></h2>
                        <p class="badge badge-primary"><?= htmlspecialchars($siswa_data['nis']) ?></p>
                        <div class="student-details text-left">
                            <p><span class="detail-label">Kelas:</span> <?= htmlspecialchars($siswa_data['kelas']) ?></p>
                            <p><span class="detail-label">Jurusan:</span> <?= htmlspecialchars($siswa_data['jurusan']) ?></p>
                            <p><span class="detail-label">Email:</span> <?= htmlspecialchars($siswa_data['email'] ?? '-') ?></p>
                            <p><span class="detail-label">Alamat:</span> <?= htmlspecialchars($siswa_data['alamat'] ?? '-') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-calendar-check mr-2"></i> Status Kehadiran Hari Ini (<?= date('d F Y') ?>)
                            <?php if ($has_pending_request): ?>
                            <span class="pending-badge"><i class="fas fa-clock mr-1"></i>Menunggu Persetujuan</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <?php if ($absensi_hari_ini): ?>
                                        <?php 
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch ($absensi_hari_ini['status']) {
                                            case 'Hadir':
                                                $statusClass = 'status-hadir';
                                                $statusIcon = 'fas fa-check-circle';
                                                break;
                                            case 'Sakit':
                                                $statusClass = 'status-sakit';
                                                $statusIcon = 'fas fa-procedures';
                                                break;
                                            case 'Terlambat':
                                                $statusClass = 'status-terlambat';
                                                $statusIcon = 'fas fa-clock';
                                                break;
                                            case 'Alpha':
                                                $statusClass = 'status-alpha';
                                                $statusIcon = 'fas fa-user-alt-slash';
                                                break;
                                        }
                                        ?>
                                        <div class="status-card <?= $statusClass ?>">
                                            <i class="<?= $statusIcon ?>"></i>
                                            <h3><?= htmlspecialchars($absensi_hari_ini['status']) ?></h3>
                                            <p>Tercatat pukul <?= date('H:i', strtotime($absensi_hari_ini['tanggal'])) ?> WIB</p>
                                        </div>
                                    <?php elseif ($has_pending_request): ?>
                                        <div class="status-card status-terlambat">
                                            <i class="fas fa-clock"></i>
                                            <h3>Menunggu Persetujuan</h3>
                                            <p>Permintaan absensi Anda sedang ditinjau oleh admin</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-card status-unknown">
                                            <i class="fas fa-question-circle"></i>
                                            <h3>Belum Tercatat</h3>
                                            <p>Status kehadiran Anda belum tercatat hari ini</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line mr-2"></i> Ringkasan Kehadiran Bulan Ini
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                            <div class="col-md-3 col-6 mb-3">
                                    <div class="text-center">
                                        <h4 class="text-success"><?= $hadir_count ?></h4>
                                        <p class="text-muted">Hadir</p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="text-center">
                                        <h4 class="text-warning"><?= $terlambat_count ?></h4>
                                        <p class="text-muted">Terlambat</p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="text-center">
                                        <h4 class="text-danger"><?= $sakit_count ?></h4>
                                        <p class="text-muted">Sakit</p>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="text-center">
                                        <h4 class="text-secondary"><?= $alpha_count ?></h4>
                                        <p class="text-muted">Alpha</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h5>Persentase Kehadiran: <?= $attendance_percentage ?>%</h5>
                                <div class="progress attendance-progress">
                                    <div class="progress-bar attendance-progress-bar" role="progressbar" style="width: <?= $attendance_percentage ?>%" aria-valuenow="<?= $attendance_percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users mr-2"></i> Kehadiran Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> Hari Ini
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="classAttendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history mr-2"></i> Riwayat Kehadiran
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover history-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if ($absensi_history_result->num_rows > 0) {
                                            while ($history = $absensi_history_result->fetch_assoc()) {
                                                $badge_class = '';
                                                switch ($history['status']) {
                                                    case 'Hadir':
                                                        $badge_class = 'badge-hadir';
                                                        break;
                                                    case 'Sakit':
                                                        $badge_class = 'badge-sakit';
                                                        break;
                                                    case 'Terlambat':
                                                        $badge_class = 'badge-terlambat';
                                                        break;
                                                    case 'Alpha':
                                                        $badge_class = 'badge-alpha';
                                                        break;
                                                }
                                        ?>
                                        <tr>
                                            <td><?= date('d F Y', strtotime($history['tanggal'])) ?></td>
                                            <td><span class="status-badge <?= $badge_class ?>"><?= $history['status'] ?></span></td>
                                        </tr>
                                        <?php 
                                            }
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="2" class="text-center">Tidak ada riwayat kehadiran</td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Request Modal -->
        <div class="modal fade" id="attendanceModal" tabindex="-1" role="dialog" aria-labelledby="attendanceModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="attendanceModalLabel">Ajukan Permintaan Absensi</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="" method="POST" enctype="multipart/form-data" class="attendance-form">
                            <div class="form-group">
                                <label for="status_request">Status Kehadiran</label>
                                <select class="form-control" id="status_request" name="status_request" required>
                                    <option value="">-- Pilih Status --</option>
                                    <option value="Hadir">Hadir</option>
                                    <option value="Sakit">Sakit</option>
                                    <option value="Terlambat">Terlambat</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="keterangan">Keterangan</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Berikan penjelasan jika diperlukan"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="bukti_kehadiran">Bukti Kehadiran</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="bukti_kehadiran" name="bukti_kehadiran" accept="image/jpeg,image/jpg,image/png" required>
                                    <label class="custom-file-label" for="bukti_kehadiran">Pilih file...</label>
                                </div>
                                <small class="form-text text-muted">Upload foto atau screenshot untuk membuktikan kehadiran Anda. Format: JPG, JPEG, PNG</small>
                                <img id="image_preview" class="file-preview mt-3" alt="Preview" />
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" name="submit_attendance" class="btn btn-primary btn-submit">
                                    <i class="fas fa-paper-plane mr-2"></i> Kirim Permintaan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        
        <script>
            $(document).ready(function() {
                // File input preview
                $('#bukti_kehadiran').change(function() {
                    const file = this.files[0];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        $('#image_preview').attr('src', e.target.result);
                        $('#image_preview').css('display', 'block');
                    }
                    
                    reader.readAsDataURL(file);
                    
                    // Update file input label
                    $(this).next('.custom-file-label').html(file.name);
                });
                
                // Monthly attendance chart
                const monthlyCtx = document.getElementById('attendanceChart').getContext('2d');
                const monthlyChart = new Chart(monthlyCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Hadir', 'Terlambat', 'Sakit', 'Alpha'],
                        datasets: [{
                            data: [<?= $hadir_count ?>, <?= $terlambat_count ?>, <?= $sakit_count ?>, <?= $alpha_count ?>],
                            backgroundColor: [
                                '#2ecc71',
                                '#f39c12',
                                '#e74c3c',
                                '#95a5a6'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: {
                            position: 'bottom'
                        }
                    }
                });
                
                // Class attendance chart
                const classCtx = document.getElementById('classAttendanceChart').getContext('2d');
                const classChart = new Chart(classCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Hadir', 'Terlambat', 'Sakit', 'Alpha', 'Belum Absen'],
                        datasets: [{
                            label: 'Jumlah Siswa',
                            data: [
                                <?= $class_hadir ?>, 
                                <?= $class_terlambat ?>, 
                                <?= $class_sakit ?>, 
                                <?= $class_alpha ?>, 
                                <?= $class_total - ($class_hadir + $class_terlambat + $class_sakit + $class_alpha) ?>
                            ],
                            backgroundColor: [
                                '#2ecc71',
                                '#f39c12',
                                '#e74c3c',
                                '#95a5a6',
                                '#3498db'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    stepSize: 1
                                }
                            }]
                        }
                    }
                });
                
                // Auto dismiss alerts after 5 seconds
                setTimeout(function() {
                    $('.alert').alert('close');
                }, 5000);
            });
        </script>
    </body>
    </html>
                            