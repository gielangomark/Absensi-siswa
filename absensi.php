<!-- absensi.php -->
<?php
include 'config.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi tanggal filter
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Tambah Absensi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_absensi'])) {
    $nis = $_POST['nis'];
    $status = $_POST['status'];
    $sql = "INSERT INTO absensi (nis, status, tanggal) VALUES ('$nis', '$status', '$tanggal_filter')";
    if ($conn->query($sql) === TRUE) {
        header("Location: absensi.php?tanggal=$tanggal_filter");
        exit();
    } else {
        die("Query gagal: " . $conn->error);
    }
}

// Hapus Absensi
if (isset($_GET['delete_absensi'])) {
    $id = $_GET['delete_absensi'];
    $sql = "DELETE FROM absensi WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        header("Location: absensi.php?tanggal=$tanggal_filter");
        exit();
    } else {
        die("Query gagal: " . $conn->error);
    }
}

// Update Absensi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_absensi'])) {
    $id = $_POST['id'];
    $nis = $_POST['nis'];
    $status = $_POST['status'];
    $sql = "UPDATE absensi SET nis='$nis', status='$status' WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        header("Location: absensi.php?tanggal=$tanggal_filter");
        exit();
    } else {
        die("Query gagal: " . $conn->error);
    }
}

// Ambil Data Siswa & Absensi
$siswa_result = $conn->query("SELECT * FROM siswa");
if (!$siswa_result) {
    die("Query gagal: " . $conn->error);
}

$absensi_result = $conn->query("SELECT absensi.id, siswa.nis AS siswa_nis, siswa.nama, absensi.status, absensi.tanggal 
                                FROM absensi 
                                JOIN siswa ON absensi.nis = siswa.nis
                                WHERE DATE(absensi.tanggal) = '$tanggal_filter'");
if (!$absensi_result) {
    die("Query gagal: " . $conn->error);
}

// Ambil Data Absensi untuk Edit
$edit_absensi_result = null;
if (isset($_GET['edit_absensi'])) {
    $id = $_GET['edit_absensi'];
    $edit_absensi_result = $conn->query("SELECT * FROM absensi WHERE id=$id");
    if (!$edit_absensi_result) {
        die("Query gagal: " . $conn->error);
    }
    $edit_absensi_row = $edit_absensi_result->fetch_assoc();
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
            <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid" width="150px" style="margin-left:45px;">
        </div>
        <a href="index.php"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
        <a href="siswa.php"><i class="fas fa-users mr-2"></i> Data Siswa</a>
        <a href="absensi.php"><i class="fas fa-calendar-check mr-2"></i> Absensi Siswa</a>
        <a href="logout.php" id="logoutLink" class="logout-link"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
    </div>
    <div class="main-content">
        <h1>Absensi Siswa</h1>
        <div class="card">
            <div class="card-header">Absensi Siswa pada Tanggal <?= $tanggal_filter ?></div>
            <div class="card-body">
                <!-- Form Tambah Absensi -->
                <form method="POST" action="absensi.php">
                    <input type="hidden" name="tanggal" value="<?= $tanggal_filter ?>">
                    <div class="form-group">
                        <label for="nis">NIS Siswa</label>
                        <select class="form-control" id="nis" name="nis" required>
                            <option value="">Pilih Siswa</option>
                            <?php while ($row = $siswa_result->fetch_assoc()) { ?>
                                <option value="<?= $row['nis'] ?>"><?= $row['nama'] ?></option>
                            <?php } ?>
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
                <!-- Form Filter Tanggal -->
                <form method="GET" action="absensi.php" class="mb-3">
                    <div class="form-group">
                        <label for="tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= $tanggal_filter ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
                <!-- Tabel Data Absensi -->
                <table class="table table-striped table-bordered">
                    <thead>
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
                        <?php $absensi_index = 1; while ($row = $absensi_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $absensi_index++ ?></td>
                            <td><?= $row['siswa_nis'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td><?= $row['tanggal'] ?></td>
                            <td>
                                <a href="absensi.php?edit_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_filter ?>" class="btn btn-warning btn-sm">Edit</a> |
                                <a href="absensi.php?delete_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Form Edit Absensi -->
        <?php if (isset($_GET['edit_absensi'])) { ?>
        <div class="card">
            <div class="card-header">Edit Absensi</div>
            <div class="card-body">
                <form method="POST" action="absensi.php">
                    <input type="hidden" name="id" value="<?= $edit_absensi_row['id'] ?>">
                    <input type="hidden" name="tanggal" value="<?= $tanggal_filter ?>">
                    <div class="form-group">
                        <label for="nis">NIS Siswa</label>
                        <select class="form-control" id="nis" name="nis" required>
                            <option value="">Pilih Siswa</option>
                            <?php while ($row = $siswa_result->fetch_assoc()) { ?>
                                <option value="<?= $row['nis'] ?>" <?= $edit_absensi_row['nis'] == $row['nis'] ? 'selected' : '' ?>><?= $row['nama'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="Hadir" <?= $edit_absensi_row['status'] == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="Sakit" <?= $edit_absensi_row['status'] == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                            <option value="Terlambat" <?= $edit_absensi_row['status'] == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                            <option value="Alpha" <?= $edit_absensi_row['status'] == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                        </select>
                    </div>
                    <button type="submit" name="update_absensi" class="btn btn-primary">Update Absensi</button>
                </form>
            </div>
        </div>
        <?php } ?>
    </div>
    <script>
        // Konfirmasi logout
        document.getElementById('logoutLink').addEventListener('click', function(event) {
            event.preventDefault();
            if (confirm('Yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>