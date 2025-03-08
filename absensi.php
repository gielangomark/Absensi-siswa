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
        $sql = "INSERT INTO absensi (nis, status, tanggal) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nis, $status, $tanggal_filter);
        
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
        $sql = "UPDATE absensi SET nis = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nis, $status, $id);
        
        if ($stmt->execute()) {
            header("Location: absensi.php?tanggal=" . urlencode($tanggal_filter) . "&updated=1");
            exit();
        } else {
            $error = "Failed to update attendance: " . $stmt->error;
        }
        $stmt->close();
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
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid">
    </div>

    <div class="menu">
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="absensi.php" class="active"><i class="fas fa-calendar-check"></i> Absensi Siswa</a>
    </div>

    <a href="logout.php" class="logout-btn" id="logoutLink">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
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
                                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                    <td>
                                        <a href="absensi.php?edit_absensi=<?= $row['id'] ?>&tanggal=<?= urlencode($tanggal_filter) ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="absensi.php?delete_absensi=<?= $row['id'] ?>&tanggal=<?= urlencode($tanggal_filter) ?>&csrf_token=<?= $csrf_token ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Yakin ingin menghapus data ini?')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data absensi untuk tanggal ini</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Form Edit Absensi -->
        <?php if (isset($_GET['edit_absensi']) && $edit_absensi_row): ?>
        <div class="card mt-4">
            <div class="card-header">Edit Absensi</div>
            <div class="card-body">
                <form method="POST" action="absensi.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_absensi_row['id']) ?>">
                    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>">
                    <div class="form-group">
                        <label for="edit_nis">Siswa</label>
                        <select class="form-control" id="edit_nis" name="nis" required>
                            <option value="">Pilih Siswa</option>
                            <?php foreach ($siswa_data as $nis => $nama): ?>
                                <option value="<?= htmlspecialchars($nis) ?>" <?= $edit_absensi_row['nis'] == $nis ? 'selected' : '' ?>>
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
                    <button type="submit" name="update_absensi" class="btn btn-primary">Update Absensi</button>
                    <a href="absensi.php?tanggal=<?= urlencode($tanggal_filter) ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Logout confirmation
        document.getElementById('logoutLink').addEventListener('click', function(event) {
            event.preventDefault();
            if (confirm('Yakin ingin logout?')) {
                window.location.href = this.getAttribute('href');
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>