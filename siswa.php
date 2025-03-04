<!-- siswa.php -->
<?php
include 'config.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Tambah Siswa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_siswa'])) {
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $jurusan = $_POST['jurusan'];
    $foto = $_FILES['foto']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($foto);

    // Validasi NIS
    $check_nis_query = "SELECT * FROM siswa WHERE nis='$nis'";
    $check_nis_result = $conn->query($check_nis_query);
    if ($check_nis_result->num_rows > 0) {
        echo "<script>alert('NIS sudah ada. Silakan gunakan NIS yang berbeda.');</script>";
    } else {
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO siswa (nis, nama, kelas, jurusan, foto) VALUES ('$nis', '$nama', '$kelas', '$jurusan', '$foto')";
        } else {
            $sql = "INSERT INTO siswa (nis, nama, kelas, jurusan) VALUES ('$nis', '$nama', '$kelas', '$jurusan')";
        }
        $conn->query($sql);
    }
}

// Hapus Siswa
if (isset($_GET['delete_siswa'])) {
    $id = $_GET['delete_siswa'];
    $sql = "DELETE FROM siswa WHERE id=$id";
    $conn->query($sql);
    header("Location: siswa.php");
    exit();
}

// Edit Siswa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_siswa'])) {
    $id = $_POST['id'];
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $jurusan = $_POST['jurusan'];
    $foto = $_FILES['foto']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($foto);

    // Validasi NIS saat edit
    $check_nis_query = "SELECT * FROM siswa WHERE nis='$nis' AND id != $id";
    $check_nis_result = $conn->query($check_nis_query);
    if ($check_nis_result->num_rows > 0) {
        echo "<script>alert('NIS sudah ada. Silakan gunakan NIS yang berbeda.');</script>";
    } else {
        if ($foto) {
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                $sql = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', jurusan='$jurusan', foto='$foto' WHERE id=$id";
            } else {
                echo "<script>alert('Gagal mengupload foto.');</script>";
                $sql = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', jurusan='$jurusan' WHERE id=$id";
            }
        } else {
            $sql = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', jurusan='$jurusan' WHERE id=$id";
        }
        $conn->query($sql);
        header("Location: siswa.php");
        exit();
    }
}

// Ambil Data Siswa
$siswa_result = $conn->query("SELECT * FROM siswa");
if (!$siswa_result) {
    die("Query gagal: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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
        <h1>Data Siswa</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nis">NIS</label>
                <input type="text" class="form-control" id="nis" name="nis" placeholder="NIS" required>
            </div>
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap" required>
            </div>
            <div class="form-group">
                <label for="kelas">Kelas</label>
                <input type="text" class="form-control" id="kelas" name="kelas" placeholder="Kelas" required>
            </div>
            <div class="form-group">
                <label for="jurusan">Jurusan</label>
                <input type="text" class="form-control" id="jurusan" name="jurusan" placeholder="Jurusan" required>
            </div>
            <div class="form-group">
                <label for="foto">Upload Foto</label>
                <input type="file" class="form-control-file" id="foto" name="foto" accept="image/*">
            </div>
            <button type="submit" name="add_siswa" class="btn btn-primary">Tambah Siswa</button>
        </form>
        <div class="card">
            <div class="card-header">Daftar Siswa</div>
            <div class="card-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>Foto</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $siswa_index = 1; while ($row = $siswa_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $siswa_index++ ?></td>
                            <td><?= $row['nis'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['kelas'] ?></td>
                            <td><?= $row['jurusan'] ?></td>
                            <td><img src="uploads/<?= $row['foto'] ?>" alt="Foto Siswa" width="50"></td>
                            <td>
                                <a href="siswa.php?edit_siswa=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a> |
                                <a href="siswa.php?delete_siswa=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Siswa -->
        <?php if (isset($_GET['edit_siswa'])) {
            $id = $_GET['edit_siswa'];
            $edit_result = $conn->query("SELECT * FROM siswa WHERE id=$id");
            if (!$edit_result) {
                die("Query gagal: " . $conn->error);
            }
            $edit_row = $edit_result->fetch_assoc();
        ?>
        <div class="card">
            <div class="card-header">Edit Data Siswa</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <div class="form-group">
                        <label for="nis">NIS</label>
                        <input type="text" class="form-control" id="nis" name="nis" placeholder="NIS" value="<?= $edit_row['nis'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap" value="<?= $edit_row['nama'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="kelas">Kelas</label>
                        <input type="text" class="form-control" id="kelas" name="kelas" placeholder="Kelas" value="<?= $edit_row['kelas'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="jurusan">Jurusan</label>
                        <input type="text" class="form-control" id="jurusan" name="jurusan" placeholder="Jurusan" value="<?= $edit_row['jurusan'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="foto">Upload Foto</label>
                        <input type="file" class="form-control-file" id="foto" name="foto" accept="image/*">
                        <?php if ($edit_row['foto']): ?>
                            <img src="uploads/<?= $edit_row['foto'] ?>" alt="Foto Siswa" width="100" class="mt-2">
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="update_siswa" class="btn btn-primary">Update Siswa</button>
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