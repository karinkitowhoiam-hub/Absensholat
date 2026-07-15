<?php
include "koneksi.php";

// PENTING: SAMAKAN ZONA WAKTU
date_default_timezone_set('Asia/Makassar'); 

// --- ARRAY NAMA BULAN INDONESIA ---
$nama_bulan_indo = [
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', 
    '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
];

// --- AMBIL PENGATURAN ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// --- AMBIL LABEL & PENGATURAN TAMPILAN ---
$label_hadir    = $d_set['label_hadir'];
$label_sakit    = $d_set['label_sakit'];
$label_izin     = $d_set['label_izin'];
$label_halangan = $d_set['label_halangan'];
$label_tidak    = $d_set['label_tidak_hadir'];

// Check Status Tampil (1=Ya, 0=Tidak)
$show_hadir    = $d_set['tampil_hadir'];
$show_sakit    = $d_set['tampil_sakit'];
$show_izin     = $d_set['tampil_izin'];
$show_halangan = $d_set['tampil_halangan'];
$show_alpha    = $d_set['tampil_alpha'];

// 1. ATUR TANGGAL & KELAS DEFAULT
$tanggal_pilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// MODIFIKASI: Hanya ambil daftar kelas yang BELUM lulus (Aktif)
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");

$id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
if ($id_kelas_pilih == '') {
    // MODIFIKASI: Cari kelas aktif pertama sebagai default
    $data_kelas_pertama = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL LIMIT 1"));
    if($data_kelas_pertama) {
        $id_kelas_pilih = $data_kelas_pertama['id_kelas'];
    }
}

// 2. INFO KELAS
$nama_kelas_aktif = "Semua Kelas";
$jurusan_aktif = "-";
$wali_kelas_aktif = "-";

if($id_kelas_pilih != '') {
    $q_info = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas = '$id_kelas_pilih'");
    $d_info = mysqli_fetch_array($q_info);
    
    $nama_kelas_aktif = $d_info['nama_kelas'];
    $jurusan_aktif = $d_info['jurusan'];
    
    if(!empty($d_info['nama_wali'])){
        $wali_kelas_aktif = $d_info['nama_wali'];
    }
}

// --- LOGIKA HITUNG STATISTIK ---
$total_siswa = 0; 
$jum_hadir = 0; 
$jum_halangan = 0; 
$jum_sakit = 0; 
$jum_izin = 0; 
$jum_alpha = 0; 

if($id_kelas_pilih != '') {
    // MODIFIKASI: Hanya hitung siswa yang statusnya AKTIF
    $q_total = mysqli_query($koneksi, "SELECT count(*) as jum FROM siswa WHERE id_kelas='$id_kelas_pilih' AND status_siswa='aktif'");
    $d_total = mysqli_fetch_assoc($q_total);
    $total_siswa = (int)$d_total['jum'];

    // MODIFIKASI: Hanya ambil rekap dari siswa yang statusnya AKTIF
    $q_rekap = mysqli_query($koneksi, "SELECT status, count(*) as jumlah FROM siswa s 
                                       JOIN absensi a ON s.nisn = a.nisn 
                                       WHERE s.id_kelas='$id_kelas_pilih' AND s.status_siswa='aktif' AND a.tanggal='$tanggal_pilih'
                                       GROUP BY status");

    while($row = mysqli_fetch_assoc($q_rekap)){
        if($row['status'] == 'Hadir' || $row['status'] == 'Manual Admin') $jum_hadir += $row['jumlah'];
        if($row['status'] == 'Halangan') $jum_halangan += $row['jumlah'];
        if($row['status'] == 'Sakit') $jum_sakit += $row['jumlah'];
        if($row['status'] == 'Izin') $jum_izin += $row['jumlah'];
        if($row['status'] == 'Tidak Sholat') $jum_alpha += $row['jumlah']; 
    }

    $sisa_belum_absen = $total_siswa - ($jum_hadir + $jum_halangan + $jum_sakit + $jum_izin + $jum_alpha);
    $jum_alpha += $sisa_belum_absen;
}

// --- SIAPKAN DATA CHART DINAMIS ---
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

if($show_hadir){ $chart_labels[] = $label_hadir; $chart_data[] = $jum_hadir; $chart_colors[] = '#198754'; }
if($show_sakit){ $chart_labels[] = $label_sakit; $chart_data[] = $jum_sakit; $chart_colors[] = '#0dcaf0'; }
if($show_izin){  $chart_labels[] = $label_izin;  $chart_data[] = $jum_izin;  $chart_colors[] = '#6c757d'; }
if($show_halangan){ $chart_labels[] = $label_halangan; $chart_data[] = $jum_halangan; $chart_colors[] = '#ffc107'; }
if($show_alpha){ $chart_labels[] = $label_tidak; $chart_data[] = $jum_alpha; $chart_colors[] = '#dc3545'; }

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .header-sekolah { background: white; padding: 20px; border-bottom: 4px solid #0d6efd; margin-bottom: 20px; }
        .logo-atas { width: 60px; height: 60px; object-fit: contain; float: left; margin-right: 15px; }
        .judul-atas { font-weight: bold; font-size: 1.5rem; color: #333; margin-top: 10px; display: inline-block;}
        
        .card-tabel { border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; }
        .card-info-kelas { border-left: 5px solid #0d6efd; } 
        
        .badge-hadir { background-color: #d1e7dd; color: #0f5132; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .badge-absen { background-color: #f8d7da; color: #842029; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .badge-halangan { background-color: #fff3cd; color: #664d03; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .badge-sakit { background-color: #cff4fc; color: #055160; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .badge-izin { background-color: #e2e3e5; color: #41464b; padding: 5px 10px; border-radius: 5px; font-weight: bold; }

        .info-box {
            text-align: center; padding: 10px; border-radius: 8px; color: white;
            height: 100%; display: flex; flex-direction: column; justify-content: center;
        }
        .bg-total { background: #0d6efd; }
        .bg-hadir { background: #198754; }
        .bg-halangan { background: #ffc107; color: #000; }
        .bg-sakit { background: #0dcaf0; color: #000; }
        .bg-izin { background: #6c757d; }
        .bg-alpha { background: #dc3545; }
        
        .angka-besar { font-size: 1.5rem; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header-sekolah container-fluid">
        <div class="container">
            <img src="assets/<?php echo $sekolah_logo; ?>" class="logo-atas" alt="Logo">
            <div class="judul-atas">MONITORING <?php echo strtoupper($d_set['judul_kegiatan']); ?></div>
        </div>
    </div>

    <div class="container mb-5">
        
        <div class="card card-tabel mb-3">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Pilih Kelas Aktif:</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <?php 
                                mysqli_data_seek($query_kelas, 0);
                                while($row_k = mysqli_fetch_array($query_kelas)) { 
                                    $selected = ($row_k['id_kelas'] == $id_kelas_pilih) ? 'selected' : '';
                                    $tampil = $row_k['nama_kelas'];
                                    if(!empty($row_k['jurusan'])) { $tampil .= " - " . $row_k['jurusan']; }
                                    echo "<option value='$row_k[id_kelas]' $selected>$tampil</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal:</label>
                            <input type="date" name="tanggal" class="form-control" value="<?php echo $tanggal_pilih; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if($id_kelas_pilih != '') { ?>
        <div class="card card-tabel card-info-kelas mb-4">
            <div class="card-body bg-white">
                <div class="row text-center text-md-start">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <small class="text-secondary fw-bold text-uppercase">Kelas</small>
                        <h4 class="fw-bold text-primary mb-0"><?php echo $nama_kelas_aktif; ?></h4>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0 border-start-md">
                        <small class="text-secondary fw-bold text-uppercase">Jurusan / Kompetensi</small>
                        <h5 class="fw-bold text-dark mb-0"><?php echo $jurusan_aktif; ?></h5>
                    </div>
                    <div class="col-md-4 border-start-md">
                        <small class="text-secondary fw-bold text-uppercase">Wali Kelas</small>
                        <h5 class="fw-bold text-success mb-0">
                            <i class="fa-solid fa-user-tie me-1"></i> <?php echo $wali_kelas_aktif; ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row h-100 g-2">
                    
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-total shadow-sm">
                            <span class="small">Total Siswa Aktif</span>
                            <div class="angka-besar"><?php echo $total_siswa; ?></div>
                        </div>
                    </div>

                    <?php if($show_hadir) { ?>
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-hadir shadow-sm">
                            <span class="small"><?php echo $label_hadir; ?></span>
                            <div class="angka-besar"><?php echo $jum_hadir; ?></div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if($show_halangan) { ?>
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-halangan shadow-sm">
                            <span class="small fw-bold"><?php echo $label_halangan; ?></span>
                            <div class="angka-besar"><?php echo $jum_halangan; ?></div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if($show_sakit) { ?>
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-sakit shadow-sm">
                            <span class="small fw-bold"><?php echo $label_sakit; ?></span>
                            <div class="angka-besar"><?php echo $jum_sakit; ?></div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if($show_izin) { ?>
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-izin shadow-sm">
                            <span class="small fw-bold"><?php echo $label_izin; ?></span>
                            <div class="angka-besar"><?php echo $jum_izin; ?></div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if($show_alpha) { ?>
                    <div class="col-4 col-md-3">
                        <div class="info-box bg-alpha shadow-sm">
                            <span class="small fw-bold"><?php echo $label_tidak; ?></span>
                            <div class="angka-besar"><?php echo $jum_alpha; ?></div>
                        </div>
                    </div>
                    <?php } ?>

                </div>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="card card-tabel h-100">
                    <div class="card-body d-flex justify-content-center align-items-center" style="position: relative; height:200px;">
                        <canvas id="grafikRekap"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-tabel">
            <div class="card-header bg-primary text-white fw-bold">
                Daftar Absen Siswa Aktif (<?php 
                    $tgl_obj = strtotime($tanggal_pilih);
                    echo date('d', $tgl_obj) . ' ' . $nama_bulan_indo[date('m', $tgl_obj)] . ' ' . date('Y', $tgl_obj);
                ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>L/P</th> 
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($id_kelas_pilih != '') {
                                // MODIFIKASI: Hanya menampilkan daftar siswa yang berstatus 'aktif'
                                $query_rekap = "SELECT siswa.*, absensi.waktu_scan, absensi.status 
                                                FROM siswa 
                                                LEFT JOIN absensi ON siswa.nisn = absensi.nisn AND absensi.tanggal = '$tanggal_pilih'
                                                WHERE siswa.id_kelas = '$id_kelas_pilih' AND siswa.status_siswa = 'aktif'
                                                ORDER BY siswa.nama_siswa ASC";
                                
                                $result = mysqli_query($koneksi, $query_rekap);
                                $no = 1;

                                if (mysqli_num_rows($result) == 0) {
                                    echo "<tr><td colspan='4' class='text-center p-4'>Belum ada data siswa aktif di kelas ini.</td></tr>";
                                }

                                while ($data = mysqli_fetch_array($result)) {
                                    $st = $data['status'];
                                    $ket_html = "";

                                    if ($st == 'Halangan') {
                                        $ket_html = "<span class='badge-halangan'>$label_halangan 🛑</span>";
                                    } 
                                    elseif ($st == 'Sakit') {
                                        $ket_html = "<span class='badge-sakit'>$label_sakit 🤒</span>";
                                    }
                                    elseif ($st == 'Izin') {
                                        $ket_html = "<span class='badge-izin'>$label_izin ✉️</span>";
                                    }
                                    elseif ($st == 'Hadir' || $st == 'Manual Admin') {
                                        $jam = ($data['waktu_scan'] != '-') ? $data['waktu_scan'] : 'Manual';
                                        $ket_html = "<span class='badge-hadir'>$label_hadir ($jam)</span>";
                                    }
                                    elseif ($st == 'Tidak Sholat') {
                                        $ket_html = "<span class='badge-absen'>$label_tidak ❌</span>";
                                    }
                                    else {
                                        $ket_html = "<span class='badge-absen'>$label_tidak ❌</span>";
                                    }
                                    
                                    $badge_jk = ($data['jenis_kelamin'] == 'L') ? 'primary' : 'danger';
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?>.</td>
                                        <td class="fw-bold"><?php echo $data['nama_siswa']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $badge_jk; ?>">
                                                <?php echo $data['jenis_kelamin']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $ket_html; ?></td>
                                    </tr>
                                <?php } 
                            } else {
                                echo "<tr><td colspan='4' class='text-center p-4'>Silakan pilih kelas aktif terlebih dahulu.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        // CHART DINAMIS (HANYA YG DICENTANG)
        const ctx = document.getElementById('grafikRekap');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                }
            }
        });
    </script>

</body>
</html>