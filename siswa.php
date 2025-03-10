<?php
include 'config.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Initialize messages array
$messages = [];

// Define CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Function to validate file upload
function validateImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'filename' => null];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'message' => 'File terlalu besar (maksimum 2MB)'];
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['valid' => false, 'message' => 'Hanya file JPG, PNG, dan GIF yang diperbolehkan'];
    }
    
    // Generate unique filename to prevent overwriting
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid('student_') . '.' . $extension;
    
    return ['valid' => true, 'filename' => $new_filename];
}

// Tambah Siswa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_siswa'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $messages[] = ['type' => 'danger', 'text' => 'CSRF token validation failed'];
    } else {
        $nis = $conn->real_escape_string(trim($_POST['nis']));
        $nama = $conn->real_escape_string(trim($_POST['nama']));
        $kelas = $conn->real_escape_string(trim($_POST['kelas']));
        $jurusan = $conn->real_escape_string(trim($_POST['jurusan']));
        
        // Validate NIS
        $check_nis_stmt = $conn->prepare("SELECT * FROM siswa WHERE nis = ?");
        $check_nis_stmt->bind_param("s", $nis);
        $check_nis_stmt->execute();
        $check_nis_result = $check_nis_stmt->get_result();
        
        if ($check_nis_result->num_rows > 0) {
            $messages[] = ['type' => 'danger', 'text' => 'NIS sudah ada. Silakan gunakan NIS yang berbeda.'];
        } else {
            // Validate and process image
            $upload_result = validateImageUpload($_FILES['foto']);
            
            if (!$upload_result['valid']) {
                $messages[] = ['type' => 'danger', 'text' => $upload_result['message']];
            } else {
                $foto = $upload_result['filename'];
                
                // Prepare statement
                if ($foto) {
                    $target_dir = "uploads/";
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    $target_file = $target_dir . $foto;
                    
                    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                        $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, jurusan, foto) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $nis, $nama, $kelas, $jurusan, $foto);
                    } else {
                        $messages[] = ['type' => 'warning', 'text' => 'Gagal mengupload foto. Data siswa tetap ditambahkan.'];
                        $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, jurusan) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $nis, $nama, $kelas, $jurusan);
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, jurusan) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nis, $nama, $kelas, $jurusan);
                }
                
                if ($stmt->execute()) {
                    $messages[] = ['type' => 'success', 'text' => 'Data siswa berhasil ditambahkan.'];
                } else {
                    $messages[] = ['type' => 'danger', 'text' => 'Gagal menambahkan data: ' . $stmt->error];
                }
                
                $stmt->close();
            }
        }
        $check_nis_stmt->close();
    }
}

// Hapus Siswa
if (isset($_GET['delete_siswa'])) {
    // Verify CSRF token
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $messages[] = ['type' => 'danger', 'text' => 'CSRF token validation failed'];
    } else {
        $id = intval($_GET['delete_siswa']);
        
        // Get photo filename before deleting
        $photo_stmt = $conn->prepare("SELECT foto FROM siswa WHERE id = ?");
        $photo_stmt->bind_param("i", $id);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        
        if ($photo_result->num_rows > 0) {
            $photo_row = $photo_result->fetch_assoc();
            $foto = $photo_row['foto'];
            
            // Delete the student record
            $stmt = $conn->prepare("DELETE FROM siswa WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Delete photo file if it exists
                if ($foto && file_exists("uploads/" . $foto)) {
                    unlink("uploads/" . $foto);
                }
                
                $messages[] = ['type' => 'success', 'text' => 'Data siswa berhasil dihapus.'];
            } else {
                $messages[] = ['type' => 'danger', 'text' => 'Gagal menghapus data: ' . $stmt->error];
            }
            
            $stmt->close();
        }
        $photo_stmt->close();
    }
}

// Edit Siswa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_siswa'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $messages[] = ['type' => 'danger', 'text' => 'CSRF token validation failed'];
    } else {
        $id = intval($_POST['id']);
        $nis = $conn->real_escape_string(trim($_POST['nis']));
        $nama = $conn->real_escape_string(trim($_POST['nama']));
        $kelas = $conn->real_escape_string(trim($_POST['kelas']));
        $jurusan = $conn->real_escape_string(trim($_POST['jurusan']));
        
        // Validate NIS saat edit
        $check_nis_stmt = $conn->prepare("SELECT * FROM siswa WHERE nis = ? AND id != ?");
        $check_nis_stmt->bind_param("si", $nis, $id);
        $check_nis_stmt->execute();
        $check_nis_result = $check_nis_stmt->get_result();
        
        if ($check_nis_result->num_rows > 0) {
            $messages[] = ['type' => 'danger', 'text' => 'NIS sudah ada. Silakan gunakan NIS yang berbeda.'];
        } else {
            // Get current photo
            $current_photo_stmt = $conn->prepare("SELECT foto FROM siswa WHERE id = ?");
            $current_photo_stmt->bind_param("i", $id);
            $current_photo_stmt->execute();
            $current_photo_result = $current_photo_stmt->get_result();
            $current_photo_row = $current_photo_result->fetch_assoc();
            $current_photo = $current_photo_row['foto'];
            
            // Check if new photo uploaded
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = validateImageUpload($_FILES['foto']);
                
                if (!$upload_result['valid']) {
                    $messages[] = ['type' => 'danger', 'text' => $upload_result['message']];
                    $foto = $current_photo; // Keep current photo
                } else {
                    $foto = $upload_result['filename'];
                    $target_dir = "uploads/";
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    $target_file = $target_dir . $foto;
                    
                    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                        // Delete old photo if it exists
                        if ($current_photo && file_exists("uploads/" . $current_photo)) {
                            unlink("uploads/" . $current_photo);
                        }
                    } else {
                        $messages[] = ['type' => 'warning', 'text' => 'Gagal mengupload foto baru. Tetap menggunakan foto lama.'];
                        $foto = $current_photo; // Keep current photo
                    }
                }
            } else {
                $foto = $current_photo; // Keep current photo
            }
            
            // Update the database
            if ($foto != $current_photo) {
                $stmt = $conn->prepare("UPDATE siswa SET nis = ?, nama = ?, kelas = ?, jurusan = ?, foto = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $nis, $nama, $kelas, $jurusan, $foto, $id);
            } else {
                $stmt = $conn->prepare("UPDATE siswa SET nis = ?, nama = ?, kelas = ?, jurusan = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $nis, $nama, $kelas, $jurusan, $id);
            }
            
            if ($stmt->execute()) {
                $messages[] = ['type' => 'success', 'text' => 'Data siswa berhasil diperbarui.'];
                // Redirect to clear the edit form
                header("Location: siswa.php?success=updated");
                exit();
            } else {
                $messages[] = ['type' => 'danger', 'text' => 'Gagal memperbarui data: ' . $stmt->error];
            }
            
            $stmt->close();
            $current_photo_stmt->close();
        }
        $check_nis_stmt->close();
    }
}

// Get all available jurusan options
$jurusan_options = ['RPL', 'MP', 'DKV 1', 'DKV 2', 'BR', 'AK'];

// Get the selected filter
$filter_jurusan = isset($_GET['filter_jurusan']) ? $_GET['filter_jurusan'] : '';

// Ambil Data Siswa dengan filter jurusan
if (!empty($filter_jurusan)) {
    $siswa_sql = "SELECT * FROM siswa WHERE jurusan = ? ORDER BY nama ASC";
    $stmt = $conn->prepare($siswa_sql);
    $stmt->bind_param("s", $filter_jurusan);
    $stmt->execute();
    $siswa_result = $stmt->get_result();
    $stmt->close();
} else {
    $siswa_sql = "SELECT * FROM siswa ORDER BY nama ASC";
    $siswa_result = $conn->query($siswa_sql);
}

if (!$siswa_result) {
    $messages[] = ['type' => 'danger', 'text' => 'Gagal mengambil data siswa: ' . $conn->error];
}

// Ambil Data untuk Edit
$edit_row = null;
if (isset($_GET['edit_siswa'])) {
    $id = intval($_GET['edit_siswa']);
    $edit_stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
    $edit_stmt->bind_param("i", $id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_row = $edit_result->fetch_assoc();
    } else {
        $messages[] = ['type' => 'danger', 'text' => 'Siswa tidak ditemukan.'];
    }
    $edit_stmt->close();
}

// Add success message for redirect
if (isset($_GET['success']) && $_GET['success'] == 'updated') {
    $messages[] = ['type' => 'success', 'text' => 'Data siswa berhasil diperbarui.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .student-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .photo-placeholder {
            width: 60px;
            height: 60px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        .card {
            margin-bottom: 20px;
        }
        .filter-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid" width="150px" style="margin-left:45px;">
    </div>
    <div class="menu">
        <a href="index.php"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
        <a href="siswa.php" class="active"><i class="fas fa-users mr-2"></i> Data Siswa</a>
        <a href="absensi.php"><i class="fas fa-calendar-check mr-2"></i> Absensi Siswa</a>
    </div>
    <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </button>
</div>
<div class="main-content">
    <h1><i class="fas fa-users"></i> Data Siswa</h1>
    
    <!-- Display Messages -->
    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
            <?= $msg['text'] ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endforeach; ?>
    
    <!-- Form Tambah/Edit Siswa -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?= isset($_GET['edit_siswa']) ? 'Edit Data Siswa' : 'Tambah Siswa Baru' ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="siswaForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <?php if (isset($_GET['edit_siswa']) && $edit_row): ?>
                    <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nis"><i class="fas fa-id-card"></i> NIS</label>
                        <input type="text" class="form-control" id="nis" name="nis" placeholder="Nomor Induk Siswa" value="<?= isset($edit_row) ? htmlspecialchars($edit_row['nis']) : '' ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="nama"><i class="fas fa-user"></i> Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap Siswa" value="<?= isset($edit_row) ? htmlspecialchars($edit_row['nama']) : '' ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="kelas"><i class="fas fa-school"></i> Kelas</label>
                        <select class="form-control" id="kelas" name="kelas" required>
                            <option value="">Pilih Kelas</option>
                            <?php 
                            $kelas_options = ['X', 'XI', 'XII'];
                            foreach ($kelas_options as $option): 
                                $selected = (isset($edit_row) && $edit_row['kelas'] == $option) ? 'selected' : '';
                            ?>
                                <option value="<?= $option ?>" <?= $selected ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="jurusan"><i class="fas fa-graduation-cap"></i> Jurusan</label>
                        <select class="form-control" id="jurusan" name="jurusan" required>
                            <option value="">Pilih Jurusan</option>
                            <?php 
                            foreach ($jurusan_options as $option): 
                                $selected = (isset($edit_row) && $edit_row['jurusan'] == $option) ? 'selected' : '';
                            ?>
                                <option value="<?= $option ?>" <?= $selected ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="foto"><i class="fas fa-image"></i> Foto Siswa</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*" onchange="previewImage(this)">
                        <label class="custom-file-label" for="foto">Pilih file...</label>
                    </div>
                    <small class="form-text text-muted">Format: JPG, PNG, GIF. Ukuran maks: 2MB</small>
                </div>
                
                <div class="mt-2 mb-3" id="imagePreviewContainer" <?= (isset($edit_row) && $edit_row['foto']) ? '' : 'style="display:none"' ?>>
                    <label>Preview:</label><br>
                    <img id="imagePreview" src="<?= (isset($edit_row) && $edit_row['foto']) ? 'uploads/' . htmlspecialchars($edit_row['foto']) : '' ?>" class="preview-image">
                </div>
                
                <div class="form-group">
                    <?php if (isset($_GET['edit_siswa']) && $edit_row): ?>
                        <button type="submit" name="update_siswa" class="btn btn-success"><i class="fas fa-save"></i> Update Data</button>
                        <a href="siswa.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    <?php else: ?>
                        <button type="submit" name="add_siswa" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Tambah Siswa</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filter Jurusan -->
    <div class="filter-container">
        <form method="GET" class="form-inline">
            <div class="form-group mr-3">
                <label for="filter_jurusan" class="mr-2"><i class="fas fa-filter"></i> Filter Jurusan:</label>
                <select name="filter_jurusan" id="filter_jurusan" class="form-control mr-2" onchange="this.form.submit()">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($jurusan_options as $option): ?>
                        <option value="<?= $option ?>" <?= ($filter_jurusan == $option) ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($filter_jurusan)): ?>
                <a href="siswa.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Tabel Data Siswa -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <i class="fas fa-table"></i> Daftar Siswa <?= !empty($filter_jurusan) ? '- Jurusan ' . htmlspecialchars($filter_jurusan) : '' ?>
        </div>
        <div class="card-body">
            <?php if ($siswa_result && $siswa_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="thead-dark">
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
                            <?php $siswa_index = 1; while ($row = $siswa_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $siswa_index++ ?></td>
                                <td><?= htmlspecialchars($row['nis']) ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['kelas']) ?></td>
                                <td><?= htmlspecialchars($row['jurusan']) ?></td>
                                <td>
                                    <?php if ($row['foto'] && file_exists("uploads/" . $row['foto'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" alt="Foto <?= htmlspecialchars($row['nama']) ?>" class="student-photo">
                                    <?php else: ?>
                                        <div class="photo-placeholder">
                                            <i class="fas fa-user text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="siswa.php?edit_siswa=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="siswa.php?delete_siswa=<?= $row['id'] ?>&csrf_token=<?= $csrf_token ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Yakin ingin menghapus siswa <?= htmlspecialchars($row['nama']) ?>?')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <?= !empty($filter_jurusan) ? 'Tidak ada data siswa untuk jurusan ' . htmlspecialchars($filter_jurusan) . '.' : 'Belum ada data siswa. Silakan tambahkan data baru.' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Image preview
function previewImage(input) {
    var preview = document.getElementById('imagePreview');
    var previewContainer = document.getElementById('imagePreviewContainer');
    var fileLabel = input.nextElementSibling;
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
            fileLabel.textContent = input.files[0].name;
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '';
        previewContainer.style.display = 'none';
        fileLabel.textContent = 'Pilih file...';
    }
}

// Logout confirmation
document.getElementById('logoutLink').addEventListener('click', function(event) {
    event.preventDefault();
    if (confirm('Yakin ingin logout?')) {
        window.location.href = this.getAttribute('href');
    }
});
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>