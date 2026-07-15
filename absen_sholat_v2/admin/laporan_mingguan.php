<?php
session_start();
include "../koneksi.php";

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// 1. AMBIL DATA TANDA TANGAN (Aman dari Warning)
$d_admin = array(); 
if (isset($_SESSION['id_admin'])) {
    $id_admin_login = $_SESSION['id_admin'];
    $q_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE id_admin='$id_admin_login'");
    $d_admin = mysqli_fetch_array($q_admin);
}

$nama_admin_ttd = !empty($d_admin['nama_admin']) ? $d_admin['nama_admin'] : "..........................";
$bagian_admin_ttd = !empty($d_admin['bagian']) ? $d_admin['bagian'] : "Bagian Penanggung Jawab";
$nip_admin_ttd = !empty($d_admin['nip']) ? $d_admin['nip'] : "..........................";

// --- INFO SEKOLAH ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// --- FILTER INPUT ---
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d', strtotime('monday this week'));
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// KUNCI DATA KELAS: Jika Wali Kelas, paksa gunakan ID Kelas miliknya sendiri
if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
    $id_kelas_pilih = $_SESSION['id_kelas_wali'];
} else {
    $id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
}

if ($id_kelas_pilih == '') {
    // Ambil kelas aktif pertama sebagai default
    $first = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL LIMIT 1"));
    if($first) { $id_kelas_pilih = $first['id_kelas']; }
}

$nama_kelas_aktif = "Semua Kelas";
$jurusan_aktif = "-"; 
$wali_kelas_aktif = ".........................."; 
$nip_wali_aktif = "..........................";

if($id_kelas_pilih != '') {
    $q_info = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas = '$id_kelas_pilih'");
    $d_info = mysqli_fetch_array($q_info);
    $nama_kelas_aktif = $d_info['nama_kelas'];
    $jurusan_aktif = $d_info['jurusan']; 
    if(!empty($d_info['nama_wali'])){
        $wali_kelas_aktif = $d_info['nama_wali'];
        $nip_wali_aktif = $d_info['nip_wali'];
    }
}

// LOGIKA HITUNG JUMLAH HARI
$begin = new DateTime($tgl_awal);
$end = new DateTime($tgl_akhir);
$end->modify('+1 day'); 
$interval = new DateInterval('P1D');
$daterange = new DatePeriod($begin, $interval, $end);

$arr_tgl_header = [];
foreach($daterange as $date){
    $hari_inggris = $date->format("l");
    if($hari_inggris != 'Sunday'){ 
        $arr_tgl_header[] = $date;
    }
}

$nama_bulan_indo = [ 1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember' ];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Mingguan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        
        .bg-libur { background-color: #555 !important; color: #555 !important; }
        .bg-kuning { background-color: #fff3cd !important; color: #664d03 !important; font-weight: bold; } 
        .text-alpha { color: red !important; font-weight: bold; }
        
        /* --- SETTING PRINT --- */
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; font-family: sans-serif; }
            .sidebar, .no-print, .btn, form { display: none !important; }
            .col-md-10 { width: 100% !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            .card-body { padding: 0 !important; }
            body, table { font-size: 9.5pt !important; } 
            .table-bordered, .table-bordered td, .table-bordered th {
                border: 1px solid black !important;
                padding: 2px 3px !important; 
                height: auto !important;
                line-height: 1.2 !important;
            }
            .table-dark { background-color: #f8f9fa !important; color: black !important; }
            .table-dark th { background-color: #f8f9fa !important; color: black !important; border: 1px solid black !important; }
            .bg-libur { background-color: white !important; color: white !important; }
            .bg-kuning { background-color: #f0f0f0 !important; color: black !important; }
            .header-print { 
                text-align: center; border-bottom: 2px solid black; margin-bottom: 10px !important; padding-bottom: 10px !important; 
                display: flex; align-items: center; justify-content: center; gap: 15px;
            }
            .logo-print { width: 50px; height: 50px; object-fit: contain; }
            h3 { font-size: 16pt !important; margin: 0 !important; }
            h4 { font-size: 14pt !important; margin: 0 !important; }
            p { margin: 0 !important; font-size: 11pt !important;}
            .row.mb-3 { margin-bottom: 10px !important; }
            .ttd-area { margin-top: 15px !important; }
            .ttd-space { height: 50px !important; }
            .box-keterangan { padding: 0; font-size: 9pt; display: inline-block; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0 no-print">
            <div class="text-center p-4">
                <img src="../assets/<?php echo $sekolah_logo; ?>" width="80" style="border-radius:50%; object-fit:contain;">
                <h6 class="mt-2 fw-bold"><?php echo $sekolah_nama; ?></h6>
            </div>
            <a href="index.php" class="menu-item">ABSENSI HARIAN</a>
            <a href="laporan_mingguan.php" class="menu-item active">REKAP MINGGUAN</a>
            <a href="laporan.php" class="menu-item">LAPORAN BULANAN</a>
            <a href="laporan_tahunan.php" class="menu-item">LAPORAN TAHUNAN</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin') { ?>
            <a href="data_kelas.php" class="menu-item">DATA KELAS</a>
            <a href="data_alumni.php" class="menu-item">DATA ALUMNI</a>
            <a href="backup.php" class="menu-item">BACKUP & RESTORE</a>
            <a href="tampilan.php" class="menu-item">TAMPILAN</a>
            <?php } ?>
            <a href="logout.php" class="menu-item text-danger">KELUAR</a>
        </div>

        <div class="col-md-10 p-4">
            
            <div class="card mb-4 no-print shadow-sm">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Filter Laporan Mingguan</h4>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="fw-bold small">Pilih Kelas:</label>
                            <select name="kelas" class="form-select" <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') echo 'style="pointer-events: none; background-color: #e9ecef;"'; ?>>
                                <?php 
                                if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
                                    $id_kw = $_SESSION['id_kelas_wali'];
                                    $q_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas='$id_kw'");
                                } else {
                                    $q_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");
                                }
                                
                                while($rk = mysqli_fetch_array($q_kelas)){
                                    $sel = ($rk['id_kelas'] == $id_kelas_pilih) ? 'selected' : '';
                                    $tampil = $rk['nama_kelas'];
                                    if(!empty($rk['jurusan'])) { $tampil .= " - " . $rk['jurusan']; }
                                    echo "<option value='$rk[id_kelas]' $sel>$tampil</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold small">Dari Tanggal:</label>
                            <input type="date" name="tgl_awal" class="form-control" value="<?php echo $tgl_awal; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold small">Sampai Tanggal:</label>
                            <input type="date" name="tgl_akhir" class="form-control" value="<?php echo $tgl_akhir; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary fw-bold w-100"><i class="fa-solid fa-filter"></i> TAMPILKAN</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="header-print d-none d-print-flex">
                        <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-print">
                        <div class="text-center">
                            <h3 class="fw-bold mb-0">LAPORAN ABSENSI (MINGGUAN)</h3>
                            <h4 class="mb-0 text-uppercase"><?php echo $sekolah_nama; ?></h4>
                            <p class="mb-0">Periode: <?php echo date('d/m/Y', strtotime($tgl_awal)); ?> s/d <?php echo date('d/m/Y', strtotime($tgl_akhir)); ?></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-9">
                            <table class="table table-borderless table-sm m-0">
                                <tr>
                                    <td width="100" class="fw-bold p-0">Kelas</td>
                                    <td width="10" class="p-0">:</td>
                                    <td class="text-nowrap p-0"><?php echo $nama_kelas_aktif; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold p-0">Jurusan</td>
                                    <td class="p-0">:</td>
                                    <td class="text-nowrap p-0"><?php echo $jurusan_aktif; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold p-0">Wali Kelas</td>
                                    <td class="p-0">:</td>
                                    <td class="text-nowrap p-0"><?php echo $wali_kelas_aktif; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-3 text-end">
                            <button onclick="window.print()" class="btn btn-success no-print"><i class="fa fa-print"></i> Cetak Laporan</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center align-middle" style="font-size: 12px; border-color: black;">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" style="vertical-align: middle; width: 30px;">No</th>
                                    <th rowspan="2" style="vertical-align: middle; text-align:left; min-width:180px;">Nama Siswa</th>
                                    <th rowspan="2" style="vertical-align: middle; width: 30px;">L/P</th>
                                    <th colspan="<?php echo count($arr_tgl_header); ?>" style="vertical-align: middle;">Tanggal</th>
                                    <th colspan="3" style="vertical-align: middle;">Total Absen Sholat</th>
                                </tr>
                                <tr>
                                    <?php
                                    $nama_hari_full = [
                                        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                                        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                                    ];

                                    foreach($arr_tgl_header as $dt){
                                        $label_tgl = $dt->format('d/m'); 
                                        $hari_inggris = $dt->format('l');
                                        $hari_indo = $nama_hari_full[$hari_inggris];
                                        
                                        $bg_head = "";
                                        if($hari_inggris == 'Sunday') $bg_head = "style='background-color:#dc3545; color:white;'"; 
                                        
                                        echo "<th $bg_head class='py-1'>
                                                <span style='font-size:11px; font-weight:bold; display:block;'>$hari_indo</span>
                                                <span style='font-size:9px;'>($label_tgl)</span>
                                              </th>";
                                    }
                                    ?>
                                    <th class="bg-success text-white" style="width:25px; vertical-align: middle !important;">Hadir</th>
                                    <th class="bg-warning text-dark" style="width:25px; vertical-align: middle !important;">Izin</th>
                                    <th class="bg-danger text-white" style="width:25px; vertical-align: middle !important;">Alpa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if($id_kelas_pilih != '') {
                                    $q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_kelas='$id_kelas_pilih' AND status_siswa='aktif' ORDER BY nama_siswa ASC");
                                    $no = 1;
                                    while($d = mysqli_fetch_array($q_siswa)){
                                        $nisn = $d['nisn']; 
                                        $hadir_count = 0; 
                                        $izin_count = 0; 
                                        $alpha_count = 0;
                                        
                                        echo "<tr>";
                                        echo "<td>".$no++."</td>";
                                        echo "<td class='text-start fw-bold'>$d[nama_siswa]</td>";
                                        echo "<td>$d[jenis_kelamin]</td>";
                                        
                                        foreach($arr_tgl_header as $dt){
                                            $tgl_cek = $dt->format('Y-m-d');
                                            $q_absen = mysqli_query($koneksi, "SELECT status FROM absensi WHERE nisn='$nisn' AND tanggal='$tgl_cek'");
                                            $d_absen = mysqli_fetch_array($q_absen);

                                            if(isset($d_absen['status'])) {
                                                if($d_absen['status'] == 'Halangan') { echo "<td class='bg-kuning'>H</td>"; $izin_count++; }
                                                elseif($d_absen['status'] == 'Sakit') { echo "<td class='bg-kuning'>S</td>"; $izin_count++; }
                                                elseif($d_absen['status'] == 'Izin') { echo "<td class='bg-kuning'>I</td>"; $izin_count++; }
                                                elseif($d_absen['status'] == 'Tidak Sholat') { echo "<td class='text-alpha'>-</td>"; $alpha_count++; }
                                                else { echo "<td class='fw-bold'>✓</td>"; $hadir_count++; }
                                            } else {
                                                echo "<td>-</td>";
                                                $alpha_count++;
                                            }
                                        }
                                        
                                        echo "<td class='fw-bold'>$hadir_count</td>";
                                        echo "<td class='fw-bold'>$izin_count</td>";
                                        echo "<td class='fw-bold text-danger'>$alpha_count</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='100%' class='text-center p-4'>Silakan pilih kelas dan periode tanggal terlebih dahulu.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row d-none d-print-flex ttd-area">
                        <div class="col-8 text-start">
                            <div class="box-keterangan">
                                <b style="text-decoration: underline;">Keterangan:</b><br>
                                <span style="display:inline-block; width: 80px;">✓ : Hadir</span>
                                <span style="display:inline-block; width: 80px;">S : Sakit</span>
                                <span style="display:inline-block; width: 80px;">I : Izin</span><br>
                                <span style="display:inline-block; width: 80px;">H : Halangan</span>
                                <span style="display:inline-block; width: 80px;">- : Alpa</span>
                            </div>
                        </div>

                        <div class="col-4 text-start">
                            <p class="mb-1">Sungai Loban, <?php echo date('d') . ' ' . $nama_bulan_indo[date('n')] . ' ' . date('Y'); ?></p>
                            <p class="fw-bold"><?php echo $bagian_admin_ttd; ?></p>
                            <div class="ttd-space"></div>
                            <p class="fw-bold text-decoration-underline mb-0 text-uppercase"><?php echo $nama_admin_ttd; ?></p>
                            <p>NIP. <?php echo $nip_admin_ttd; ?></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>