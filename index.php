<!-- index.php -->
<?php
include 'config.php';
// Cek apakah admin sudah login
$logged_in = isset($_SESSION['admin']);

// Ambil data siswa
$siswa_result = $conn->query("SELECT * FROM siswa");
if (!$siswa_result) {
    die("Query gagal: " . $conn->error);
}

// Inisialisasi tanggal filter
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Ambil absensi per hari ini
$tanggal_hari_ini = date('Y-m-d');
$absensi_hari_ini_result = $conn->query("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                        FROM absensi 
                                        JOIN siswa ON absensi.nis = siswa.nis 
                                        WHERE DATE(absensi.tanggal) = '$tanggal_hari_ini'");
if (!$absensi_hari_ini_result) {
    die("Query gagal: " . $conn->error);
}

// Hitung presentase kehadiran hari ini
$total_siswa = $siswa_result->num_rows;
$hadir_count = $conn->query("SELECT COUNT(*) AS hadir FROM absensi WHERE status = 'Hadir' AND DATE(tanggal) = '$tanggal_hari_ini'")->fetch_assoc()['hadir'];
$sakit_count = $conn->query("SELECT COUNT(*) AS sakit FROM absensi WHERE status = 'Sakit' AND DATE(tanggal) = '$tanggal_hari_ini'")->fetch_assoc()['sakit'];
$terlambat_count = $conn->query("SELECT COUNT(*) AS terlambat FROM absensi WHERE status = 'Terlambat' AND DATE(tanggal) = '$tanggal_hari_ini'")->fetch_assoc()['terlambat'];
$alpha_count = $conn->query("SELECT COUNT(*) AS alpha FROM absensi WHERE status = 'Alpha' AND DATE(tanggal) = '$tanggal_hari_ini'")->fetch_assoc()['alpha'];
$hadir_percentage = ($total_siswa > 0) ? round(($hadir_count / $total_siswa) * 100, 2) : 0;
$sakit_percentage = ($total_siswa > 0) ? round(($sakit_count / $total_siswa) * 100, 2) : 0;
$terlambat_percentage = ($total_siswa > 0) ? round(($terlambat_count / $total_siswa) * 100, 2) : 0;
$alpha_percentage = ($total_siswa > 0) ? round(($alpha_count / $total_siswa) * 100, 2) : 0;

// Rekapan absensi berdasarkan tanggal yang dipilih
$absensi_filter_result = $conn->query("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                      FROM absensi 
                                      JOIN siswa ON absensi.nis = siswa.nis 
                                      WHERE DATE(absensi.tanggal) = '$tanggal_filter'");
if (!$absensi_filter_result) {
    die("Query gagal: " . $conn->error);
}

// Hitung presentase kehadiran berdasarkan tanggal yang dipilih
$hadir_filter_count = $conn->query("SELECT COUNT(*) AS hadir FROM absensi WHERE status = 'Hadir' AND DATE(tanggal) = '$tanggal_filter'")->fetch_assoc()['hadir'];
$sakit_filter_count = $conn->query("SELECT COUNT(*) AS sakit FROM absensi WHERE status = 'Sakit' AND DATE(tanggal) = '$tanggal_filter'")->fetch_assoc()['sakit'];
$terlambat_filter_count = $conn->query("SELECT COUNT(*) AS terlambat FROM absensi WHERE status = 'Terlambat' AND DATE(tanggal) = '$tanggal_filter'")->fetch_assoc()['terlambat'];
$alpha_filter_count = $conn->query("SELECT COUNT(*) AS alpha FROM absensi WHERE status = 'Alpha' AND DATE(tanggal) = '$tanggal_filter'")->fetch_assoc()['alpha'];
$hadir_filter_percentage = ($total_siswa > 0) ? round(($hadir_filter_count / $total_siswa) * 100, 2) : 0;
$sakit_filter_percentage = ($total_siswa > 0) ? round(($sakit_filter_count / $total_siswa) * 100, 2) : 0;
$terlambat_filter_percentage = ($total_siswa > 0) ? round(($terlambat_filter_count / $total_siswa) * 100, 2) : 0;
$alpha_filter_percentage = ($total_siswa > 0) ? round(($alpha_filter_count / $total_siswa) * 100, 2) : 0;

// Rekapan absensi bulanan
$bulan_tahun = date('Y-m');
$absensi_bulanan_result = $conn->query("SELECT absensi.id, siswa.id AS siswa_id, siswa.nama, absensi.status, absensi.tanggal 
                                        FROM absensi 
                                        JOIN siswa ON absensi.nis = siswa.nis 
                                        WHERE DATE_FORMAT(absensi.tanggal, '%Y-%m') = '$bulan_tahun'");
if (!$absensi_bulanan_result) {
    die("Query gagal: " . $conn->error);
}

// Hitung presentase kehadiran bulanan
$hadir_bulanan_count = $conn->query("SELECT COUNT(*) AS hadir FROM absensi WHERE status = 'Hadir' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_tahun'")->fetch_assoc()['hadir'];
$sakit_bulanan_count = $conn->query("SELECT COUNT(*) AS sakit FROM absensi WHERE status = 'Sakit' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_tahun'")->fetch_assoc()['sakit'];
$terlambat_bulanan_count = $conn->query("SELECT COUNT(*) AS terlambat FROM absensi WHERE status = 'Terlambat' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_tahun'")->fetch_assoc()['terlambat'];
$alpha_bulanan_count = $conn->query("SELECT COUNT(*) AS alpha FROM absensi WHERE status = 'Alpha' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_tahun'")->fetch_assoc()['alpha'];
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
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= $tanggal_filter ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-list-alt mr-2"></i> Absensi Siswa pada Tanggal <?= $tanggal_filter ?>
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
                        <?php $absensi_filter_index = 1; while ($row = $absensi_filter_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $absensi_filter_index++ ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td><?= $row['tanggal'] ?></td>
                            <?php if ($logged_in): ?>
                            <td>
                                <a href="absensi.php?edit_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_filter ?>" class="btn btn-warning btn-sm">Edit</a> |
                                <a href="absensi.php?delete_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
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
                <i class="fas fa-chart-bar mr-2"></i> Presentase Kehadiran Siswa pada Tanggal <?= $tanggal_filter ?>
            </div>
            <div class="card-body chart-container">
                <canvas id="attendanceChartFilter" width="300" height="150"></canvas>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-bar mr-2"></i> Presentase Kehadiran Siswa Bulan Ini (<?= $bulan_tahun ?>)
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
                        <?php $siswa_index = 1; while ($row = $siswa_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $siswa_index++ ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['kelas'] ?></td>
                            <td><?= $row['jurusan'] ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-calendar-check mr-2"></i> Absensi Siswa Hari Ini (<?= $tanggal_hari_ini ?>)
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
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td><?= $row['tanggal'] ?></td>
                            <?php if ($logged_in): ?>
                            <td>
                                <a href="absensi.php?edit_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_hari_ini ?>" class="btn btn-warning btn-sm">Edit</a> |
                                <a href="absensi.php?delete_absensi=<?= $row['id'] ?>&tanggal=<?= $tanggal_hari_ini ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            // Tampilkan alert selamat datang jika login berhasil
            document.addEventListener('DOMContentLoaded', function() {
                var loginSuccess = new URLSearchParams(window.location.search).get('login_success');
                if (loginSuccess === 'true') {
                    var alertContainer = document.createElement('div');
                    alertContainer.className = 'alert alert-success alert-dismissible fade show';
                    alertContainer.role = 'alert';
                    alertContainer.innerHTML = 'Selamat datang! Selamat mengedit. <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                    document.querySelector('.main-content').prepend(alertContainer);
                }
            });

            // Konfirmasi logout
            document.getElementById('logoutLink').addEventListener('click', function(event) {
                event.preventDefault();
                if (confirm('Yakin ingin logout?')) {
                    window.location.href = 'logout.php';
                }
            });

            // Diagram batang untuk presentase kehadiran berdasarkan tanggal yang dipilih
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
                            text: 'Presentase Kehadiran Siswa pada Tanggal <?= $tanggal_filter ?>'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, values) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });

            // Diagram batang untuk presentase kehadiran bulanan
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
                            text: 'Presentase Kehadiran Siswa Bulan Ini (<?= $bulan_tahun ?>)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, values) {
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