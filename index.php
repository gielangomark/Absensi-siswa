<?php
include 'config.php';
// Check if admin is logged in
$logged_in = isset($_SESSION['admin']);

// Get student data
$siswa_result = $conn->query("SELECT * FROM siswa");
if (!$siswa_result) {
    die("Query failed: " . $conn->error);
}

// Initialize filter date
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Get today's attendance
$tanggal_hari_ini = date('Y-m-d');
$absensi_hari_ini_result = $conn->prepare("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                        FROM absensi 
                                        JOIN siswa ON absensi.nis = siswa.nis 
                                        WHERE DATE(absensi.tanggal) = ?");
$absensi_hari_ini_result->bind_param("s", $tanggal_hari_ini);
$absensi_hari_ini_result->execute();
$absensi_hari_ini_result = $absensi_hari_ini_result->get_result();
if (!$absensi_hari_ini_result) {
    die("Query failed: " . $conn->error);
}

// Calculate today's attendance percentages
$total_siswa = $siswa_result->num_rows;

// Prepared statement for count queries
$count_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM absensi WHERE status = ? AND DATE(tanggal) = ?");

// Get today's counts
$count_stmt->bind_param("ss", $status, $tanggal_hari_ini);

$status = 'Hadir';
$count_stmt->execute();
$result = $count_stmt->get_result();
$hadir_count = $result->fetch_assoc()['count'];

$status = 'Sakit';
$count_stmt->execute();
$result = $count_stmt->get_result();
$sakit_count = $result->fetch_assoc()['count'];

$status = 'Terlambat';
$count_stmt->execute();
$result = $count_stmt->get_result();
$terlambat_count = $result->fetch_assoc()['count'];

$status = 'Alpha';
$count_stmt->execute();
$result = $count_stmt->get_result();
$alpha_count = $result->fetch_assoc()['count'];

// Calculate percentages
$hadir_percentage = ($total_siswa > 0) ? round(($hadir_count / $total_siswa) * 100, 2) : 0;
$sakit_percentage = ($total_siswa > 0) ? round(($sakit_count / $total_siswa) * 100, 2) : 0;
$terlambat_percentage = ($total_siswa > 0) ? round(($terlambat_count / $total_siswa) * 100, 2) : 0;
$alpha_percentage = ($total_siswa > 0) ? round(($alpha_count / $total_siswa) * 100, 2) : 0;

// Get filtered attendance data
$absensi_filter_result = $conn->prepare("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                      FROM absensi 
                                      JOIN siswa ON absensi.nis = siswa.nis 
                                      WHERE DATE(absensi.tanggal) = ?");
$absensi_filter_result->bind_param("s", $tanggal_filter);
$absensi_filter_result->execute();
$absensi_filter_result = $absensi_filter_result->get_result();
if (!$absensi_filter_result) {
    die("Query failed: " . $conn->error);
}

// Get filtered count data
$count_stmt->bind_param("ss", $status, $tanggal_filter);

$status = 'Hadir';
$count_stmt->execute();
$result = $count_stmt->get_result();
$hadir_filter_count = $result->fetch_assoc()['count'];

$status = 'Sakit';
$count_stmt->execute();
$result = $count_stmt->get_result();
$sakit_filter_count = $result->fetch_assoc()['count'];

$status = 'Terlambat';
$count_stmt->execute();
$result = $count_stmt->get_result();
$terlambat_filter_count = $result->fetch_assoc()['count'];

$status = 'Alpha';
$count_stmt->execute();
$result = $count_stmt->get_result();
$alpha_filter_count = $result->fetch_assoc()['count'];

// Calculate filtered percentages
$hadir_filter_percentage = ($total_siswa > 0) ? round(($hadir_filter_count / $total_siswa) * 100, 2) : 0;
$sakit_filter_percentage = ($total_siswa > 0) ? round(($sakit_filter_count / $total_siswa) * 100, 2) : 0;
$terlambat_filter_percentage = ($total_siswa > 0) ? round(($terlambat_filter_count / $total_siswa) * 100, 2) : 0;
$alpha_filter_percentage = ($total_siswa > 0) ? round(($alpha_filter_count / $total_siswa) * 100, 2) : 0;

// Monthly attendance recap
$bulan_tahun = date('Y-m');

// Monthly count query with prepared statement
$monthly_count_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM absensi WHERE status = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?");

// Get monthly attendance data
$absensi_bulanan_result = $conn->prepare("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                        FROM absensi 
                                        JOIN siswa ON absensi.nis = siswa.nis 
                                        WHERE DATE_FORMAT(absensi.tanggal, '%Y-%m') = ?");
$absensi_bulanan_result->bind_param("s", $bulan_tahun);
$absensi_bulanan_result->execute();
$absensi_bulanan_result = $absensi_bulanan_result->get_result();
if (!$absensi_bulanan_result) {
    die("Query failed: " . $conn->error);
}

// Get monthly counts
$monthly_count_stmt->bind_param("ss", $status, $bulan_tahun);

$status = 'Hadir';
$monthly_count_stmt->execute();
$result = $monthly_count_stmt->get_result();
$hadir_bulanan_count = $result->fetch_assoc()['count'];

$status = 'Sakit';
$monthly_count_stmt->execute();
$result = $monthly_count_stmt->get_result();
$sakit_bulanan_count = $result->fetch_assoc()['count'];

$status = 'Terlambat';
$monthly_count_stmt->execute();
$result = $monthly_count_stmt->get_result();
$terlambat_bulanan_count = $result->fetch_assoc()['count'];

$status = 'Alpha';
$monthly_count_stmt->execute();
$result = $monthly_count_stmt->get_result();
$alpha_bulanan_count = $result->fetch_assoc()['count'];

// Calculate monthly percentages
$hadir_bulanan_percentage = ($total_siswa > 0) ? round(($hadir_bulanan_count / $total_siswa) * 100, 2) : 0;
$sakit_bulanan_percentage = ($total_siswa > 0) ? round(($sakit_bulanan_count / $total_siswa) * 100, 2) : 0;
$terlambat_bulanan_percentage = ($total_siswa > 0) ? round(($terlambat_bulanan_count / $total_siswa) * 100, 2) : 0;
$alpha_bulanan_percentage = ($total_siswa > 0) ? round(($alpha_bulanan_count / $total_siswa) * 100, 2) : 0;
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
    <?php if ($logged_in): ?>
        <div class="sidebar">
    <div class="logo">
        <img src="assets/images/40.png" alt="Logo Sekolah" class="img-fluid">
    </div>

    <div class="menu">
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="absensi.php"><i class="fas fa-calendar-check"></i> Absensi Siswa</a>
    </div>

    <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </button>
</div>

    <?php endif; ?>
    <div class="main-content">
        <?php if (isset($_GET['login_success']) && $_GET['login_success'] == 'true'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Selamat datang! Selamat mengedit.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>
        <h1 class="mb-4">Dashboard Absensi Siswa</h1>
        <?php if (!$logged_in): ?>
            <div class="text-center mb-4">
                <a href="login.php" class="btn btn-primary">Login Admin</a>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-3">
                <div class="card bg-success text-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $hadir_percentage ?>%</h3>
                                <p class="card-text">Hadir Hari Ini</p>
                            </div>
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $sakit_percentage ?>%</h3>
                                <p class="card-text">Sakit Hari Ini</p>
                            </div>
                            <i class="fas fa-times-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $terlambat_percentage ?>%</h3>
                                <p class="card-text">Terlambat Hari Ini</p>
                            </div>
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= $alpha_percentage ?>%</h3>
                                <p class="card-text">Alpha Hari Ini</p>
                            </div>
                            <i class="fas fa-user-alt-slash fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-filter mr-2"></i> Filter Absensi
            </div>
            <div class="card-body">
                <form method="GET" action="index.php" class="mb-3">
                    <div class="form-group">
                        <label for="tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-list-alt mr-2"></i> Absensi Siswa pada Tanggal <?= htmlspecialchars($tanggal_filter) ?>
            </div>
            <div class="card-body">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>No.</th>
                <th>Nama Siswa</th>
                <th>Foto</th>
                <th>Status</th>
                <th>Tanggal</th>
                <?php if ($logged_in): ?>
                <th>Aksi</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $absensi_filter_index = 1; while ($row = $absensi_filter_result->fetch_assoc()) { ?>
            <tr>
                <td><?= $absensi_filter_index++ ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td>
                    <?php if (!empty($row['foto']) && file_exists("uploads/" . $row['foto'])): ?>
                        <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" alt="Foto <?= htmlspecialchars($row['nama']) ?>" class="student-photo" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                    <?php else: ?>
                        <div class="photo-placeholder" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 5px;">
                            <i class="fas fa-user text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= htmlspecialchars($row['tanggal']) ?></td>
                <?php if ($logged_in): ?>
                <td>
                    <a href="absensi.php?edit_absensi=<?= (int)$row['id'] ?>&tanggal=<?= htmlspecialchars($tanggal_filter) ?>" class="btn btn-warning btn-sm">Edit</a> |
                    <a href="absensi.php?delete_absensi=<?= (int)$row['id'] ?>&tanggal=<?= htmlspecialchars($tanggal_filter) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                </td>
                <?php endif; ?>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-bar mr-2"></i> Presentase Kehadiran Siswa pada Tanggal <?= htmlspecialchars($tanggal_filter) ?>
            </div>
            <div class="card-body chart-container">
                <canvas id="attendanceChartFilter" width="300" height="150"></canvas>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-bar mr-2"></i> Presentase Kehadiran Siswa Bulan Ini (<?= htmlspecialchars($bulan_tahun) ?>)
            </div>
            <div class="card-body chart-container">
                <canvas id="attendanceChartBulanan" width="300" height="150"></canvas>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-users mr-2"></i> Data Siswa
            </div>
            <div class="card-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset cursor position for siswa_result
                        $siswa_result->data_seek(0);
                        $siswa_index = 1; 
                        while ($row = $siswa_result->fetch_assoc()) { 
                        ?>
                        <tr>
                            <td><?= $siswa_index++ ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['kelas']) ?></td>
                            <td><?= htmlspecialchars($row['jurusan']) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-calendar-check mr-2"></i> Absensi Siswa Hari Ini (<?= htmlspecialchars($tanggal_hari_ini) ?>)
            </div>
            <div class="card-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Siswa</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <?php if ($logged_in): ?>
                            <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $absensi_hari_ini_index = 1; while ($row = $absensi_hari_ini_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $absensi_hari_ini_index++ ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal']) ?></td>
                            <?php if ($logged_in): ?>
                            <td>
                                <a href="absensi.php?edit_absensi=<?= (int)$row['id'] ?>&tanggal=<?= htmlspecialchars($tanggal_hari_ini) ?>" class="btn btn-warning btn-sm">Edit</a> |
                                <a href="absensi.php?delete_absensi=<?= (int)$row['id'] ?>&tanggal=<?= htmlspecialchars($tanggal_hari_ini) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            // Show welcome alert if login successful
            document.addEventListener('DOMContentLoaded', function() {
                // Welcome alert is already handled by PHP, no need for duplicate
                
                // Fixed event listener - removed non-existent element reference
                // and added proper check for logout button
                var logoutBtn = document.querySelector('.logout-btn');
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (confirm('Yakin ingin logout?')) {
                            window.location.href = 'logout.php';
                        }
                    });
                }
            });

            // Bar chart for attendance percentage based on selected date
            var ctxFilter = document.getElementById('attendanceChartFilter').getContext('2d');
            var attendanceChartFilter = new Chart(ctxFilter, {
                type: 'bar',
                data: {
                    labels: ['Hadir', 'Sakit', 'Terlambat', 'Alpha'],
                    datasets: [{
                        label: 'Presentase Kehadiran',
                        data: [<?= $hadir_filter_percentage ?>, <?= $sakit_filter_percentage ?>, <?= $terlambat_filter_percentage ?>, <?= $alpha_filter_percentage ?>],
                        backgroundColor: ['#2ecc71', '#e74c3c', '#f39c12', '#ecf0f1'],
                        hoverBackgroundColor: ['#27ae60', '#c0392b', '#d35400', '#bdc3c7']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Presentase Kehadiran Siswa pada Tanggal <?= htmlspecialchars($tanggal_filter) ?>'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });

            // Bar chart for monthly attendance percentage
            var ctxBulanan = document.getElementById('attendanceChartBulanan').getContext('2d');
            var attendanceChartBulanan = new Chart(ctxBulanan, {
                type: 'bar',
                data: {
                    labels: ['Hadir', 'Sakit', 'Terlambat', 'Alpha'],
                    datasets: [{
                        label: 'Presentase Kehadiran',
                        data: [<?= $hadir_bulanan_percentage ?>, <?= $sakit_bulanan_percentage ?>, <?= $terlambat_bulanan_percentage ?>, <?= $alpha_bulanan_percentage ?>],
                        backgroundColor: ['#2ecc71', '#e74c3c', '#f39c12', '#ecf0f1'],
                        hoverBackgroundColor: ['#27ae60', '#c0392b', '#d35400', '#bdc3c7']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Presentase Kehadiran Siswa Bulan Ini (<?= htmlspecialchars($bulan_tahun) ?>)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        </script>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>