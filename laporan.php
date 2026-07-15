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

// --- AMBIL PENGATURAN TAMPILAN ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// --- LOGIKA FILTER INPUT ---
$bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// KUNCI DATA KELAS KHUSUS WALI KELAS
if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
    $id_kelas_pilih = $_SESSION['id_kelas_wali'];
} else {
    $id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
}

// LOGIKA CENTANG HARI (CHECKBOX)
if(isset($_GET['filter_laporan'])) {
    $cek_jumat = isset($_GET['cek_jumat']) ? true : false;
    $cek_sabtu = isset($_GET['cek_sabtu']) ? true : false;
    $cek_minggu = isset($_GET['cek_minggu']) ? true : false;
} else {
    $cek_jumat = true; 
    $cek_sabtu = false; 
    $cek_minggu = false;
}

if ($id_kelas_pilih == '') {
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

$nama_bulan = [ '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember' ];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Absensi</title>
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
        
        @media print {
            @page { size: landscape; margin: 10mm; }
            .sidebar, .no-print, .btn, form { display: none !important; }
            .col-md-10 { width: 100% !important; padding: 0 !important; }
            body, table { font-size: 10pt !important; }
            .table th, .table td { padding: 2px 4px !important; font-size: 9pt !important; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .card { border: none !important; box-shadow: none !important; }
            .table-bordered, .table-bordered td, .table-bordered th { border: 1px solid black !important; }
            .table-dark { background-color: #f8f9fa !important; color: black !important; }
            .table-dark th { background-color: #f8f9fa !important; color: black !important; }
            .bg-libur { background-color: white !important; color: white !important; }
            .bg-kuning { background-color: #f0f0f0 !important; color: black !important; }
            .header-print { 
                text-align: center; border-bottom: 2px solid black; margin-bottom: 15px; padding-bottom: 10px; 
                display: flex; align-items: center; justify-content: center; gap: 20px;
            }
            .logo-print { width: 60px; height: 60px; object-fit: contain; }
            .ttd-area { break-inside: avoid; page-break-inside: avoid; }
            .box-keterangan { padding: 0; font-size: 9pt; display: inline-block; }
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
            <a href="laporan_mingguan.php" class="menu-item">REKAP MINGGUAN</a> 
            <a href="laporan.php" class="menu-item active">LAPORAN BULANAN</a>
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
                    <h4 class="fw-bold mb-3">Filter Laporan Bulanan</h4>
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
                            <label class="fw-bold small">Bulan:</label>
                            <select name="bulan" class="form-select">
                                <?php foreach($nama_bulan as $k => $v){ $sel = ($k == $bulan_pilih) ? 'selected' : ''; echo "<option value='$k' $sel>$v</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="fw-bold small">Tahun:</label>
                            <select name="tahun" class="form-select">
                                <?php for($t=2024; $t<=2030; $t++){ $sel = ($t == $tahun_pilih) ? 'selected' : ''; echo "<option value='$t' $sel>$t</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="fw-bold small d-block mb-2">Tampilkan Hari (Centang jika Ada Kegiatan):</label>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="cek_jumat" value="1" id="c_jumat" <?php if($cek_jumat) echo 'checked'; ?>><label class="form-check-label fw-bold text-success" for="c_jumat">Jumat</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="cek_sabtu" value="1" id="c_sabtu" <?php if($cek_sabtu) echo 'checked'; ?>><label class="form-check-label fw-bold text-primary" for="c_sabtu">Sabtu</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="cek_minggu" value="1" id="c_minggu" <?php if($cek_minggu) echo 'checked'; ?>><label class="form-check-label fw-bold text-danger" for="c_minggu">Minggu</label></div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="filter_laporan" class="btn btn-primary fw-bold w-100"><i class="fa-solid fa-filter"></i> TAMPILKAN LAPORAN</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="header-print d-none d-print-flex">
                        <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-print">
                        <div class="text-center">
                            <h3 class="fw-bold mb-0">LAPORAN ABSENSI</h3>
                            <h4 class="mb-0 text-uppercase"><?php echo $sekolah_nama; ?></h4>
                            <p class="mb-0">Bulan: <?php echo $nama_bulan[$bulan_pilih]; ?> <?php echo $tahun_pilih; ?></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="100" class="fw-bold">Kelas</td><td width="10">:</td><td><?php echo $nama_kelas_aktif; ?></td></tr>
                                <tr><td class="fw-bold">Jurusan</td><td>:</td><td><?php echo $jurusan_aktif; ?></td></tr>
                                <tr><td class="fw-bold">Wali Kelas</td><td>:</td><td><?php echo $wali_kelas_aktif; ?></td></tr>
                            </table>
                        </div>
                        <div class="col-6 text-end">
                            <button onclick="window.print()" class="btn btn-success no-print"><i class="fa fa-print"></i> Cetak Laporan</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center align-middle" style="font-size: 12px; border-color: black;">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" style="vertical-align: middle;">No</th>
                                    <th rowspan="2" style="vertical-align: middle; text-align:left; min-width:180px;">Nama Siswa</th>
                                    <th rowspan="2" style="vertical-align: middle;">L/P</th>
                                    <th colspan="31">Tanggal (<?php echo $nama_bulan[$bulan_pilih]; ?>)</th>
                                    <th colspan="3">Total</th>
                                </tr>
                                <tr>
                                    <?php
                                    $jum_hari = cal_days_in_month(CAL_GREGORIAN, $bulan_pilih, $tahun_pilih);
                                    for($h=1; $h<=$jum_hari; $h++){
                                        $tgl_cek = $tahun_pilih.'-'.$bulan_pilih.'-'.sprintf("%02d", $h);
                                        $hari_pekan = date('N', strtotime($tgl_cek)); 
                                        $style_bg = "";
                                        if ($hari_pekan == 5 && $cek_jumat == false) { $style_bg = "class='bg-libur'"; }
                                        elseif ($hari_pekan == 6 && $cek_sabtu == false) { $style_bg = "class='bg-libur'"; }
                                        elseif ($hari_pekan == 7 && $cek_minggu == false) { $style_bg = "class='bg-libur'"; }
                                        echo "<th $style_bg style='width:20px; font-size:10px;'>$h</th>";
                                    }
                                    for($s=$jum_hari+1; $s<=31; $s++){ echo "<th></th>"; }
                                    ?>
                                    <th class="bg-success text-white" style="width:30px;">H</th>
                                    <th class="bg-warning text-dark" style="width:30px;">I</th> <th class="bg-danger text-white" style="width:30px;">A</th>
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
                                        
                                        for($h=1; $h<=$jum_hari; $h++){
                                            $tgl_cek = $tahun_pilih.'-'.$bulan_pilih.'-'.sprintf("%02d", $h);
                                            $hari_pekan = date('N', strtotime($tgl_cek));
                                            
                                            $is_libur = false;
                                            if ($hari_pekan == 5 && $cek_jumat == false) { $is_libur = true; }
                                            if ($hari_pekan == 6 && $cek_sabtu == false) { $is_libur = true; }
                                            if ($hari_pekan == 7 && $cek_minggu == false) { $is_libur = true; }

                                            if($is_libur) { 
                                                echo "<td class='bg-libur'></td>"; 
                                            } else {
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
                                        }
                                        for($s=$jum_hari+1; $s<=31; $s++){ echo "<td style='background:#eee;'></td>"; }
                                        
                                        echo "<td class='fw-bold'>$hadir_count</td>";
                                        echo "<td class='fw-bold'>$izin_count</td>";
                                        echo "<td class='fw-bold text-danger'>$alpha_count</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-5 d-none d-print-flex ttd-area">
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
                            <p class="mb-1">Sungai Loban, <?php echo date('d') . ' ' . $nama_bulan[date('m')] . ' ' . date('Y'); ?></p>
                            <p class="fw-bold mb-5"><?php echo $bagian_admin_ttd; ?></p>
                            <br>
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