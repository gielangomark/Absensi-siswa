<?php
include 'config.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Initialize filter date
$tanggal_filter = isset($_GET['tanggal']) ? htmlspecialchars($_GET['tanggal']) : date('Y-m-d');

// Define CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Add attendance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_absensi'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    $nis = $conn->real_escape_string($_POST['nis']);
    $status = $conn->real_escape_string($_POST['status']);
    // Validate inputs
    if (empty($nis) || empty($status)) {
        $error = "All fields are required.";
    } else {
        // Create datetime with current time
        $current_time = date('H:i:s');
        $datetime = $tanggal_filter . ' ' . $current_time;
        $sql = "INSERT INTO absensi (nis, status, tanggal) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nis, $status, $datetime);
        if ($stmt->execute()) {
            header("Location: absensi.php?tanggal=" . urlencode($tanggal_filter) . "&success=1");
            exit();
        } else {
            $error = "Failed to add attendance: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Delete attendance
if (isset($_GET['delete_absensi']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    $id = intval($_GET['delete_absensi']);
    $sql = "DELETE FROM absensi WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: absensi.php?tanggal=" . urlencode($tanggal_filter) . "&deleted=1");
        exit();
    } else {
        $error = "Failed to delete attendance: " . $stmt->error;
    }
    $stmt->close();
}

// Update attendance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_absensi'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    $id = intval($_POST['id']);
    $nis = $conn->real_escape_string($_POST['nis']);
    $status = $conn->real_escape_string($_POST['status']);
    // Validate inputs
    if (empty($nis) || empty($status)) {
        $error = "All fields are required.";
    } else {
        // Create datetime with current time
        $current_time = date('H:i:s');
        $datetime = $tanggal_filter . ' ' . $current_time;
        $sql = "UPDATE absensi SET nis = ?, status = ?, tanggal = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nis, $status, $datetime, $id);
        if ($stmt->execute()) {
            header("Location: absensi.php?tanggal=" . urlencode($tanggal_filter) . "&updated=1");
            exit();
        } else {
            $error = "Failed to update attendance: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Process notification actions (accept/reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $notification_id = intval($_POST['notification_id']);
    $student_file_id = intval($_POST['student_file_id']);
    $action = $_POST['action'];
    
    // Update student file status
    $status = ($action == 'accept') ? 'accepted' : 'rejected';
    $update_file_sql = "UPDATE student_files SET status = ? WHERE id = ?";
    $update_file_stmt = $conn->prepare($update_file_sql);
    $update_file_stmt->bind_param("si", $status, $student_file_id);
    
    if ($update_file_stmt->execute()) {
        // Mark notification as read
        $mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = $conn->prepare($mark_read_sql);
        $mark_read_stmt->bind_param("i", $notification_id);
        $mark_read_stmt->execute();
        
        // Create notification for student
        $message = ($action == 'accept') ? "File Anda telah diterima oleh admin." : "File Anda ditolak oleh admin.";
        $get_nis_sql = "SELECT nis FROM student_files WHERE id = ?";
        $get_nis_stmt = $conn->prepare($get_nis_sql);
        $get_nis_stmt->bind_param("i", $student_file_id);
        $get_nis_stmt->execute();
        $nis_result = $get_nis_stmt->get_result();
        
        if ($nis_row = $nis_result->fetch_assoc()) {
            $nis = $nis_row['nis'];
            $create_notif_sql = "INSERT INTO notifications (target_role, target_id, message, created_at) VALUES ('student', ?, ?, NOW())";
            $create_notif_stmt = $conn->prepare($create_notif_sql);
            $create_notif_stmt->bind_param("ss", $nis, $message);
            $create_notif_stmt->execute();
        }
        
        header("Location: absensi.php?notification_processed=1");
        exit();
    } else {
        $error = "Failed to process file: " . $update_file_stmt->error;
    }
}

// Process notification actions (accept/reject for absence requests)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absence_request_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $notification_id = intval($_POST['notification_id']);
    $student_nis = $_POST['student_nis'];
    $status = $_POST['status'];
    $request_date = $_POST['request_date'];
    $action = $_POST['action'];
    
    if ($action == 'accept') {
        // Add the attendance record to the database
        $current_time = date('H:i:s');
        $datetime = $request_date . ' ' . $current_time;
        
        $sql = "INSERT INTO absensi (nis, status, tanggal) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $student_nis, $status, $datetime);
        
        if ($stmt->execute()) {
            // Mark notification as read
            $mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $mark_read_stmt = $conn->prepare($mark_read_sql);
            $mark_read_stmt->bind_param("i", $notification_id);
            $mark_read_stmt->execute();
            
            // Send confirmation notification to student
            $message = "Permohonan absen anda dengan status '$status' telah disetujui oleh admin.";
            $create_notif_sql = "INSERT INTO notifications (target_role, target_id, message, created_at) VALUES ('student', ?, ?, NOW())";
            $create_notif_stmt = $conn->prepare($create_notif_sql);
            $create_notif_stmt->bind_param("ss", $student_nis, $message);
            $create_notif_stmt->execute();
            
            header("Location: absensi.php?absence_processed=1");
            exit();
        } else {
            $error = "Failed to add attendance record: " . $stmt->error;
        }
    } else {
        // Reject - just mark notification as read and inform student
        $mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = $conn->prepare($mark_read_sql);
        $mark_read_stmt->bind_param("i", $notification_id);
        $mark_read_stmt->execute();
        
        // Send rejection notification to student
        $message = "Permohonan absen anda dengan status '$status' telah ditolak oleh admin.";
        $create_notif_sql = "INSERT INTO notifications (target_role, target_id, message, created_at) VALUES ('student', ?, ?, NOW())";
        $create_notif_stmt = $conn->prepare($create_notif_sql);
        $create_notif_stmt->bind_param("ss", $student_nis, $message);
        $create_notif_stmt->execute();
        
        header("Location: absensi.php?absence_processed=2");
        exit();
    }
}

// Get student data
$sql_siswa = "SELECT * FROM siswa";
$siswa_result = $conn->query($sql_siswa);
if (!$siswa_result) {
    $error = "Failed to retrieve student data: " . $conn->error;
}

// Store student data in array for reuse
$siswa_data = [];
if ($siswa_result) {
    while ($row = $siswa_result->fetch_assoc()) {
        $siswa_data[$row['nis']] = $row['nama'];
    }
}

// Get attendance data
$sql_absensi = "SELECT absensi.id, siswa.nis AS siswa_nis, siswa.nama, absensi.status, absensi.tanggal 
               FROM absensi 
               JOIN siswa ON absensi.nis = siswa.nis
               WHERE DATE(absensi.tanggal) = ?
               ORDER BY siswa.nama ASC";
$stmt = $conn->prepare($sql_absensi);
$stmt->bind_param("s", $tanggal_filter);
$stmt->execute();
$absensi_result = $stmt->get_result();
if (!$absensi_result) {
    $error = "Failed to retrieve attendance data: " . $stmt->error;
}

// Get attendance data for editing
$edit_absensi_row = null;
if (isset($_GET['edit_absensi'])) {
    $id = intval($_GET['edit_absensi']);
    $sql = "SELECT * FROM absensi WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_absensi_result = $stmt->get_result();
    if ($edit_absensi_result && $edit_absensi_result->num_rows > 0) {
        $edit_absensi_row = $edit_absensi_result->fetch_assoc();
    } else {
        $error = "Failed to retrieve attendance data for editing: " . $stmt->error;
    }
}


// Get notifications with student file information
$notifications_query = $conn->prepare("
    SELECT n.*, sf.id as file_id, sf.file_path, sf.file_name, sf.uploaded_at, sf.description, s.nama
    FROM notifications n
    LEFT JOIN student_files sf ON n.reference_id = sf.id AND n.reference_type = 'student_file'
    LEFT JOIN siswa s ON sf.nis = s.nis
    WHERE n.target_role = 'admin' AND n.is_read = 0
    ORDER BY n.created_at DESC
");
$notifications_query->execute();
$notifications_result = $notifications_query->get_result();
$notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);

// Get file details for preview if requested
$file_preview = null;
if (isset($_GET['preview_file'])) {
    $file_id = intval($_GET['preview_file']);
    $file_query = $conn->prepare("
        SELECT sf.*, s.nama
        FROM student_files sf
        JOIN siswa s ON sf.nis = s.nis
        WHERE sf.id = ?
    ");
    $file_query->bind_param("i", $file_id);
    $file_query->execute();
    $file_result = $file_query->get_result();
    if ($file_result && $file_result->num_rows > 0) {
        $file_preview = $file_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Siswa</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
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
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
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
        .table-responsive {
            overflow-x: auto;
        }
        .table thead th {
            background-color: #3498db;
            color: white;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-primary {
            background-image: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-secondary {
            background-color: #34495e;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-warning {
            background-color: #f39c12;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-danger {
            background-color: #e74c3c;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .badge-success {
            background-color: #2ecc71;
            color: white;
        }
        .badge-warning {
            background-color: #f39c12;
            color: white;
        }
        .badge-info {
            background-color: #3498db;
            color: white;
        }
        .badge-danger {
            background-color: #e74c3c;
            color: white;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background-color: #34495e;
            color: white;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .sidebar .logo img {
            height: 130px;
            width: 130px;
            margin-bottom: 20px;
        }
        .sidebar .menu a {
            display: block;
            color: white;
            padding: 10px 0;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        .sidebar .menu a:hover {
            color: #3498db;
        }
        .sidebar .menu a.active {
            color: #3498db;
            font-weight: 600;
        }
        .main-content {
            margin-left: 280px;
        }
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background-color: red;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            color: #3498db;
        }
        .notification-card {
            background-color: #f8f9fa;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-5px);
        }
        .notification-card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c3e50;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0 !important;
        }
        .notification-list {
            padding: 20px;
        }
        .notification-item {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }
        .notification-item:hover {
            transform: translateY(-2px);
        }
        .notification-message {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .notification-file-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-view-file {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-view-file:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .btn-accept {
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-accept:hover {
            background-color: #27ae60;
            transform: translateY(-1px);
        }
        .btn-reject {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-reject:hover {
            background-color: #c0392b;
            transform: translateY(-1px);
        }
        .file-preview-modal .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        .file-preview-modal .modal-header {
            background-color: #3498db;
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .file-preview-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 15px 15px;
        }
        .file-preview-container {
            padding: 20px;
            text-align: center;
        }
        .file-preview-container img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .file-preview-container .pdf-preview {
            width: 100%;
            height: 500px;
        }
        .file-info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        .file-description {
            font-style: italic;
            color: #7f8c8d;
            margin-top: 10px;
        }
        .mark-read-btn {
            background-color: #95a5a6;
            border: none;
            border-radius: 10px;
            padding: 8px 15px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .mark-read-btn:hover {
            transform: translateY(-1px);
            background-color: #7f8c8d;
        }
    </style>
</head>
<body>
<div class="sidebar">
<div class="logo">
        <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid">
    </div>
    <div class="menu">
        <a href="index.php"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
        <a href="siswa.php" ><i class="fas fa-users mr-2"></i> Data Siswa</a>
        <a href="absensi.php"><i class="fas fa-calendar-check mr-2"></i> Absensi Siswa</a>
    </div>
    <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </button>
</div>
    <div class="main-content">
        <h1>Absensi Siswa</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Data absensi berhasil ditambahkan.</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Data absensi berhasil diperbarui.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Data absensi berhasil dihapus.</div>
        <?php endif; ?>
        <?php if (isset($_GET['notification_processed'])): ?>
            <div class="alert alert-success">Berhasil memproses pengajuan file siswa.</div>
        <?php endif; ?>
        
       <!-- Notification Card -->
<?php if (!empty($notifications)): ?>
<div class="card notification-card mb-4">
    <div class="card-header notification-card-header">
        <h5><i class="fas fa-bell mr-2"></i> Notifikasi</h5>
    </div>
    <div class="notification-list">
        <?php foreach ($notifications as $notification): ?>
            <!-- Notification Item --> 
            <?php if (strpos($notification['message'], 'ingin menambahkan absen') !== false): ?>
                <!-- This is an absence request notification -->
                <div class="notification-item">
                    <div class="notification-message">
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                    
                    <?php
                    // Extract the information from the message using regex
                    $pattern = '/(.+) ingin menambahkan absen (.+) pada tanggal (.+)/';
                    preg_match($pattern, $notification['message'], $matches);
                    $student_name = isset($matches[1]) ? $matches[1] : '';
                    $status = isset($matches[2]) ? $matches[2] : '';
                    $request_date = isset($matches[3]) ? $matches[3] : date('Y-m-d');
                    
                    // Get NIS from student name
                    $student_nis = '';
                    foreach ($siswa_data as $nis => $nama) {
                        if ($nama == $student_name) {
                            $student_nis = $nis;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="notification-actions">
                        <form method="POST" action="absensi.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <input type="hidden" name="student_nis" value="<?= $student_nis ?>">
                            <input type="hidden" name="status" value="<?= $status ?>">
                            <input type="hidden" name="request_date" value="<?= $request_date ?>">
                            <input type="hidden" name="absence_request_action" value="process">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn-accept">
                                <i class="fas fa-check"></i> Terima
                            </button>
                        </form>
                        
                        <form method="POST" action="absensi.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <input type="hidden" name="student_nis" value="<?= $student_nis ?>">
                            <input type="hidden" name="status" value="<?= $status ?>">
                            <input type="hidden" name="request_date" value="<?= $request_date ?>">
                            <input type="hidden" name="absence_request_action" value="process">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn-reject">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (isset($notification['file_id']) && !empty($notification['file_id'])): ?>
                <!-- Existing file notification code -->
                <div class="notification-item">
                    <div class="notification-message">
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                    <div class="notification-file-info">
                        <p><strong>Nama File:</strong> <?= htmlspecialchars($notification['file_name']) ?></p>
                        <p><strong>Deskripsi:</strong> <?= htmlspecialchars($notification['description']) ?></p>
                        <p><strong>Diunggah:</strong> <?= date('d-m-Y H:i', strtotime($notification['uploaded_at'])) ?></p>
                    </div>
                    <div class="notification-actions">
                        <a href="absensi.php?preview_file=<?= $notification['file_id'] ?>&notification_id=<?= $notification['id'] ?>" class="btn-view-file">
                            <i class="fas fa-eye"></i> Lihat File
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Other notifications -->
                <div class="notification-item">
                    <div class="notification-message">
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                    <button class="mark-read-btn" data-notification-id="<?= htmlspecialchars($notification['id']) ?>">Tandai Sebagai Dibaca</button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Filter Absensi</span>
            </div>
            <div class="card-body">
                <!-- Form Filter Tanggal -->
                <form method="GET" action="absensi.php" class="mb-3">
                    <div class="form-group">
                        <label for="tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Tambah Absensi Siswa pada Tanggal <?= htmlspecialchars($tanggal_filter) ?></div>
            <div class="card-body">
                <!-- Form Tambah Absensi -->
                <form method="POST" action="absensi.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>">
                    <div class="form-group">
                        <label for="nis">Siswa</label>
                        <select class="form-control" id="nis" name="nis" required>
                            <option value="">Pilih Siswa</option>
                            <?php foreach ($siswa_data as $nis => $nama): ?>
                                <option value="<?= htmlspecialchars($nis) ?>"><?= htmlspecialchars($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="Hadir">Hadir</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Terlambat">Terlambat</option>
                            <option value="Alpha">Alpha</option>
                        </select>
                    </div>
                    <button type="submit" name="add_absensi" class="btn btn-primary">Tambah Absensi</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Data Absensi Siswa pada Tanggal <?= htmlspecialchars($tanggal_filter) ?></div>
            <div class="card-body">
                <!-- Tabel Data Absensi -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>No.</th>
                                <th>NIS Siswa</th>
                                <th>Nama Siswa</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($absensi_result && $absensi_result->num_rows > 0): ?>
                                <?php $absensi_index = 1; while ($row = $absensi_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $absensi_index++ ?></td>
                                    <td><?= htmlspecialchars($row['siswa_nis']) ?></td>
                                    <td><?= htmlspecialchars($row['nama']) ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch($row['status']) {
                                            case 'Hadir': $statusClass = 'badge-success'; break;
                                            case 'Sakit': $statusClass = 'badge-warning'; break;
                                            case 'Terlambat': $statusClass = 'badge-info'; break;
                                            case 'Alpha': $statusClass = 'badge-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                                    </td>
                                    <td><?= date('d-m-Y H:i', strtotime($row['tanggal'])) ?></td>
                                    <td>
                                        <a href="absensi.php?edit_absensi=<?= $row['id'] ?>&tanggal=<?= urlencode($tanggal_filter) ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="absensi.php?delete_absensi=<?= $row['id'] ?>&csrf_token=<?= $csrf_token ?>&tanggal=<?= urlencode($tanggal_filter) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data absensi untuk tanggal ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Edit Absensi Modal -->
        <?php if ($edit_absensi_row): ?>
        <div class="modal fade show" id="editAbsensiModal" tabindex="-1" role="dialog" aria-labelledby="editAbsensiModalLabel" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAbsensiModalLabel">Edit Absensi</h5>
                        <button type="button" class="close" aria-label="Close" onclick="window.location.href='absensi.php?tanggal=<?= urlencode($tanggal_filter) ?>'">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="absensi.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="id" value="<?= $edit_absensi_row['id'] ?>">
                            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>">
                            <div class="form-group">
                                <label for="edit_nis">Siswa</label>
                                <select class="form-control" id="edit_nis" name="nis" required>
                                    <?php foreach ($siswa_data as $nis => $nama): ?>
                                        <option value="<?= htmlspecialchars($nis) ?>" <?= $nis == $edit_absensi_row['nis'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nama) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_status">Status</label>
                                <select class="form-control" id="edit_status" name="status" required>
                                    <option value="Hadir" <?= $edit_absensi_row['status'] == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                    <option value="Sakit" <?= $edit_absensi_row['status'] == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                    <option value="Terlambat" <?= $edit_absensi_row['status'] == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                    <option value="Alpha" <?= $edit_absensi_row['status'] == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                                </select>
                            </div>
                            <button type="submit" name="update_absensi" class="btn btn-primary">Simpan Perubahan</button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='absensi.php?tanggal=<?= urlencode($tanggal_filter) ?>'">Batal</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- File Preview Modal -->
        <?php if ($file_preview): ?>
        <div class="modal fade show file-preview-modal" id="filePreviewModal" tabindex="-1" role="dialog" aria-labelledby="filePreviewModalLabel" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="filePreviewModalLabel">
                            Preview File: <?= htmlspecialchars($file_preview['file_name']) ?>
                        </h5>
                        <button type="button" class="close" aria-label="Close" onclick="window.location.href='absensi.php'">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="file-info-section">
                            <p><strong>Nama Siswa:</strong> <?= htmlspecialchars($file_preview['nama']) ?></p>
                            <p><strong>Nama File:</strong> <?= htmlspecialchars($file_preview['file_name']) ?></p>
                            <p><strong>Diunggah Pada:</strong> <?= date('d-m-Y H:i', strtotime($file_preview['uploaded_at'])) ?></p>
                            <p><strong>Status:</strong> 
                                <?php 
                                    $statusBadge = '';
                                    switch($file_preview['status']) {
                                        case 'pending': $statusBadge = 'badge-warning'; $statusText = 'Menunggu Persetujuan'; break;
                                        case 'accepted': $statusBadge = 'badge-success'; $statusText = 'Diterima'; break;
                                        case 'rejected': $statusBadge = 'badge-danger'; $statusText = 'Ditolak'; break;
                                        default: $statusBadge = 'badge-secondary'; $statusText = 'Unknown'; break;
                                    }
                                ?>
                                <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                            </p>
                            <div class="file-description">
                                <strong>Deskripsi:</strong> <?= htmlspecialchars($file_preview['description']) ?>
                            </div>
                        </div>
                        
                        <div class="file-preview-container">
                            <?php
                            $file_path = $file_preview['file_path'];
                            $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
                            
                            if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                                // Image preview
                                echo '<a href="' . htmlspecialchars($file_path) . '" data-lightbox="file-preview" data-title="' . htmlspecialchars($file_preview['file_name']) . '">';
                                echo '<img src="' . htmlspecialchars($file_path) . '" alt="File Preview" class="img-fluid">';
                                echo '</a>';
                            } elseif (strtolower($file_ext) === 'pdf') {
                                // PDF preview
                                echo '<iframe src="' . htmlspecialchars($file_path) . '" class="pdf-preview"></iframe>';
                            } else {
                                // Other file types
                                echo '<div class="alert alert-info">Preview tidak tersedia untuk tipe file ini. <a href="' . htmlspecialchars($file_path) . '" class="btn btn-primary" target="_blank">Download File</a></div>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" action="absensi.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="notification_id" value="<?= isset($_GET['notification_id']) ? intval($_GET['notification_id']) : 0 ?>">
                            <input type="hidden" name="student_file_id" value="<?= $file_preview['id'] ?>">
                            <input type="hidden" name="notification_action" value="process">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Terima File
                            </button>
                        </form>
                        
                        <form method="POST" action="absensi.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="notification_id" value="<?= isset($_GET['notification_id']) ? intval($_GET['notification_id']) : 0 ?>">
                            <input type="hidden" name="student_file_id" value="<?= $file_preview['id'] ?>">
                            <input type="hidden" name="notification_action" value="process">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Tolak File
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='absensi.php'">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        // Mark notification as read
        $(document).ready(function() {
            $('.mark-read-btn').click(function() {
                var notificationId = $(this).data('notification-id');
                $.ajax({
                    url: 'mark_notification_read.php',
                    type: 'POST',
                    data: {
                        notification_id: notificationId,
                        csrf_token: '<?= $csrf_token ?>'
                    },
                    success: function(response) {
                        location.reload();
                    }
                });
            });
        });
    </script>
</body>
</html>