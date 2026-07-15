<?php
session_start();
include "../koneksi.php";

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// --- LOGIKA MODE TAMPILAN ---
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'kelas'; // default tampil per kelas

// 1. KUNCI DATA KELAS KHUSUS WALI KELAS
if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
    $id_kelas_pilih = $_SESSION['id_kelas_wali'];
} else {
    $id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
}

// 2. AMBIL DATA TANDA TANGAN (Aman dari Warning)
$d_admin = array(); 
if (isset($_SESSION['id_admin'])) {
    $id_admin_login = $_SESSION['id_admin'];
    $q_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE id_admin='$id_admin_login'");
    $d_admin = mysqli_fetch_array($q_admin);
}

$nama_admin_ttd = !empty($d_admin['nama_admin']) ? $d_admin['nama_admin'] : "..........................";
$bagian_admin_ttd = !empty($d_admin['bagian']) ? $d_admin['bagian'] : "Bagian Penanggung Jawab";
$nip_admin_ttd = !empty($d_admin['nip']) ? $d_admin['nip'] : "..........................";

// --- AMBIL INFO SEKOLAH ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// --- FILTER TAHUN ---
$tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// ARRAY BULAN
$nama_bulan = [ 1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun', 7=>'Jul', 8=>'Agu', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des' ];
$nama_bulan_indo = [ 1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember' ];

// FUNGSI HITUNG HARI EFEKTIF
function hitung_hari_efektif($bulan, $tahun) {
    $jum_hari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
    $efektif = 0;
    for($i=1; $i<=$jum_hari; $i++){
        $curr = $tahun.'-'.$bulan.'-'.sprintf("%02d", $i);
        $hari = date('N', strtotime($curr));
        if($hari != 6 && $hari != 7) { 
            $efektif++;
        }
    }
    return $efektif;
}

// --- PERSIAPAN DATA (TAMPILAN KELAS) ---
$data_kelas = [];
$daftar_warna = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6610f2', '#fd7e14', '#20c997', '#0dcaf0', '#d63384', '#ae2012', '#005f73', '#94d2bd'];
$index_warna = 0;

// 3. FILTER DATABASE KELAS: Admin tampil semua, Wali Kelas tampil kelasnya saja
if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
    $id_kw = $_SESSION['id_kelas_wali'];
    $q_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas='$id_kw'");
} else {
    $q_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");
}

// Variabel bantu untuk menampilkan Nama Kelas & Jurusan saat Print
$info_kelas_print = "";

while($k = mysqli_fetch_array($q_kelas)){
    $id_k = $k['id_kelas'];
    $nama_k = $k['nama_kelas'] . (!empty($k['jurusan']) ? " - " . $k['jurusan'] : "");
    
    // Jika tidak ada kelas yang dipilih di awal, otomatis pilih kelas pertama yang muncul
    if($id_kelas_pilih == '') {
        $id_kelas_pilih = $id_k;
    }

    if($view_mode == 'siswa' && $id_k == $id_kelas_pilih) {
        $info_kelas_print = $nama_k;
    }

    $q_sis = mysqli_query($koneksi, "SELECT count(*) as total FROM siswa WHERE id_kelas='$id_k' AND status_siswa='aktif'");
    $d_sis = mysqli_fetch_array($q_sis);
    $total_siswa = $d_sis['total'];

    $persen_per_bulan = [];
    for($b=1; $b<=12; $b++){
        if($total_siswa > 0){
            $hari_efektif = hitung_hari_efektif($b, $tahun_pilih);
            $target_ibadah = $total_siswa * $hari_efektif; 
            $q_hadir = mysqli_query($koneksi, "SELECT count(*) as jum FROM absensi a JOIN siswa s ON a.nisn = s.nisn WHERE s.id_kelas='$id_k' AND s.status_siswa='aktif' AND MONTH(a.tanggal)='$b' AND YEAR(a.tanggal)='$tahun_pilih' AND (a.status IS NULL OR a.status != 'Halangan')"); 
            $d_hadir = mysqli_fetch_array($q_hadir);
            $realisasi = $d_hadir['jum'];
            $persen = ($target_ibadah > 0) ? round(($realisasi / $target_ibadah) * 100, 1) : 0;
        } else { $persen = 0; }
        $persen_per_bulan[] = $persen;
    }

    $warna_pilihan = ($index_warna < count($daftar_warna)) ? $daftar_warna[$index_warna] : 'rgba('.rand(100,255).', '.rand(100,255).', '.rand(100,255).', 1)';
    $data_kelas[] = ['nama' => $nama_k, 'data' => $persen_per_bulan, 'color' => $warna_pilihan];
    $index_warna++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Laporan Tahunan - <?php echo $view_mode == 'siswa' ? 'Per Siswa' : 'Per Kelas'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        .table-responsive { font-size: 11px; }
        .badge-persen { min-width: 40px; padding: 4px 2px; font-size: 10px; } 

        .ttd-area { display: flex; justify-content: flex-end; margin-top: 30px; }
        .ttd-box-container { width: 300px; text-align: center; }
        .nama-ttd-format { font-weight: bold; text-decoration: underline; margin-top: 80px; display: block; text-transform: uppercase; }
        
        @media print {
            @page { size: portrait; margin: 10mm; }
            .sidebar, .no-print, .btn-group-nav, form { display: none !important; }
            .col-md-10 { width: 100% !important; padding: 0 !important; max-width: 100% !important; }
            .header-print { display: flex !important; align-items: center; justify-content: center; border-bottom: 2px solid black; margin-bottom: 20px; padding-bottom: 10px; gap: 20px; }
            .logo-print { width: 60px; height: 60px; object-fit: contain; }
            
            .table { font-size: 10px !important; width: 100% !important; border-collapse: collapse !important; }
            .table-bordered, .table-bordered td, .table-bordered th { border: 1.5px solid black !important; color: black !important; }
            .table thead th { vertical-align: middle !important; text-align: center !important; font-weight: bold !important; background-color: #f2f2f2 !important; }
            .table td { padding: 4px !important; }
            .card { border: none !important; }
            
            .ttd-area { display: block !important; width: 100% !important; border: none !important; }
            .ttd-box-container { float: right !important; width: 250px !important; text-align: center !important; }
            .ttd-box-container p { margin: 0; font-size: 11px; }
            .nama-ttd-format { margin-top: 80px !important; }
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
            <a href="laporan.php" class="menu-item">LAPORAN BULANAN</a>
            <a href="laporan_tahunan.php" class="menu-item active">LAPORAN TAHUNAN</a>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin') { ?>
            <a href="data_kelas.php" class="menu-item">DATA KELAS</a>
            <a href="data_alumni.php" class="menu-item">DATA ALUMNI</a>
            <a href="backup.php" class="menu-item">BACKUP & RESTORE</a>
            <a href="tampilan.php" class="menu-item">TAMPILAN</a>
            <?php } ?>
            
            <a href="logout.php" class="menu-item text-danger">KELUAR</a>
        </div>

        <div class="col-md-10 p-4">
            
            <div class="header-print d-none">
                <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-print">
                <div class="text-center">
                    <h3 class="fw-bold mb-0 text-uppercase">LAPORAN PERSENTASE IBADAH TAHUNAN</h3>
                    <h4 class="mb-0 text-uppercase"><?php echo $sekolah_nama; ?></h4>
                    <p class="mb-0">Tahun Periode: <?php echo $tahun_pilih; ?></p>
                    <?php if($view_mode == 'siswa'): ?>
                        <p class="mb-0 fw-bold">Kelas: <?php echo $info_kelas_print; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 no-print">
                <div>
                    <h3 class="fw-bold mb-0 text-primary">LAPORAN PERSENTASE TAHUNAN</h3>
                    <div class="btn-group btn-group-nav mt-2 shadow-sm">
                        <a href="laporan_tahunan.php?view=kelas&tahun=<?php echo $tahun_pilih; ?>" class="btn <?php echo $view_mode == 'kelas' ? 'btn-primary active' : 'btn-outline-primary'; ?> fw-bold px-4">Per Kelas</a>
                        <a href="laporan_tahunan.php?view=siswa&tahun=<?php echo $tahun_pilih; ?>" class="btn <?php echo $view_mode == 'siswa' ? 'btn-primary active' : 'btn-outline-primary'; ?> fw-bold px-4">Per Siswa</a>
                    </div>
                </div>

                <form method="GET" class="d-flex gap-2 align-items-center bg-white p-2 rounded shadow-sm border">
                    <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                    <?php if($view_mode == 'siswa'): ?>
                    <select name="kelas" class="form-select fw-bold border-primary" onchange="this.form.submit()" <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') echo 'style="pointer-events: none; background-color: #e9ecef;"'; ?>>
                        <?php 
                        // Reset pointer data kelas untuk melooping opsi dropdown
                        mysqli_data_seek($q_kelas, 0);
                        while($rk = mysqli_fetch_array($q_kelas)){
                            $sel = ($rk['id_kelas'] == $id_kelas_pilih) ? 'selected' : '';
                            echo "<option value='$rk[id_kelas]' $sel>$rk[nama_kelas] - $rk[jurusan]</option>";
                        }
                        ?>
                    </select>
                    <?php endif; ?>

                    <select name="tahun" class="form-select fw-bold" onchange="this.form.submit()">
                        <?php for($t=2024; $t<=2030; $t++){ $sel = ($t == $tahun_pilih) ? 'selected' : ''; echo "<option value='$t' $sel>Tahun $t</option>"; } ?>
                    </select>
                    
                    <button type="button" onclick="window.print()" class="btn btn-success fw-bold"><i class="fa fa-print"></i></button>
                </form>
            </div>

            <?php if($view_mode == 'kelas'): ?>
            <div id="area-grafik" class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white fw-bold text-center border-bottom">Grafik Tren Ibadah Per Kelas Aktif (<?php echo $tahun_pilih; ?>)</div>
                <div class="card-body"><div style="height: 350px;"><canvas id="chartTahunan"></canvas></div></div>
            </div>

            <div id="area-tabel" class="card shadow-sm mb-3 border-0">
                <div class="card-header bg-white fw-bold text-center border-bottom">Rincian Persentase Kehadiran Ibadah Sholat (%)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="40" rowspan="2" class="fw-bold">No</th>
                                    <th rowspan="2" style="min-width: 250px;" class="fw-bold text-start ps-3">Kelas / Jurusan</th>
                                    <th colspan="12" class="fw-bold">Bulan</th>
                                    <th rowspan="2" class="bg-primary text-white fw-bold">Rata2</th>
                                </tr>
                                <tr><?php foreach($nama_bulan as $bln) { echo "<th style='width:45px; padding:2px;' class='fw-bold'>$bln</th>"; } ?></tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach($data_kelas as $dk) { 
                                    $total_persen = 0; $count_bulan = 0;
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td class="fw-bold text-start ps-3"><?php echo $dk['nama']; ?></td>
                                    <?php foreach($dk['data'] as $p) { 
                                        $cls = ($p >= 80) ? 'bg-success' : (($p >= 50) ? 'bg-warning text-dark' : 'bg-danger'); 
                                        echo "<td style='padding:2px;'><span class='badge $cls badge-persen'>$p%</span></td>";
                                        $total_persen += $p; $count_bulan++;
                                    } ?>
                                    <td class="fw-bold small"><?php echo ($count_bulan > 0) ? round($total_persen / $count_bulan, 1) : 0; ?>%</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($view_mode == 'siswa'): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 text-center border-bottom">
                    TABEL PERSENTASE IBADAH PER SISWA TAHUN <?php echo $tahun_pilih; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="35" rowspan="2" class="fw-bold">No</th>
                                    <th rowspan="2" class="text-start fw-bold" style="min-width:180px;">Nama Siswa</th>
                                    <th width="35" rowspan="2" class="fw-bold">L/P</th>
                                    <th colspan="12" class="fw-bold">Bulan (%)</th>
                                    <th rowspan="2" class="bg-primary fw-bold">Rata2</th>
                                </tr>
                                <tr>
                                    <?php foreach($nama_bulan as $bln) { echo "<th style='width:35px; font-size:8px;' class='fw-bold'>$bln</th>"; } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if($id_kelas_pilih != '') {
                                    $q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_kelas='$id_kelas_pilih' AND status_siswa='aktif' ORDER BY nama_siswa ASC");
                                    $no = 1;
                                    while($s = mysqli_fetch_array($q_siswa)){
                                        $nisn = $s['nisn'];
                                        $total_persen_siswa = 0;
                                        
                                        echo "<tr>";
                                        echo "<td>".$no++."</td>";
                                        echo "<td class='text-start fw-bold'>".strtoupper($s['nama_siswa'])."</td>";
                                        echo "<td>".$s['jenis_kelamin']."</td>";

                                        for($b=1; $b<=12; $b++){
                                            $efektif = hitung_hari_efektif($b, $tahun_pilih);
                                            $q_h = mysqli_query($koneksi, "SELECT count(*) as jum FROM absensi WHERE nisn='$nisn' AND status='Hadir' AND MONTH(tanggal)='$b' AND YEAR(tanggal)='$tahun_pilih'");
                                            $d_h = mysqli_fetch_array($q_h);
                                            $persen = ($efektif > 0) ? round(($d_h['jum'] / $efektif) * 100) : 0;
                                            $total_persen_siswa += $persen;
                                            echo "<td class='".($persen < 50 ? 'text-danger' : '')."'>".$persen."%</td>";
                                        }
                                        echo "<td class='fw-bold bg-light'>".round($total_persen_siswa / 12, 1)."%</td>";
                                        echo "</tr>";
                                    }
                                } else { echo "<tr><td colspan='16' class='p-5 text-muted text-center'>Silakan pilih kelas terlebih dahulu.</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="ttd-area">
                <div class="no-print" style="flex: 1;">
                    <?php if($view_mode == 'kelas'): ?>
                    <div id="area-legenda" class="col-md-7 mb-4">
                        <div class="card shadow-sm h-100 border-0 bg-transparent ps-0"> 
                            <div class="card-body ps-0">
                                <h6 class="fw-bold mb-3 small text-uppercase text-secondary">Keterangan Indikator:</h6>
                                <div class="d-flex align-items-center mb-2"><span class="badge bg-success me-3" style="min-width:65px;">&gt; 80%</span><span><b>Mantap!</b></span></div>
                                <div class="d-flex align-items-center mb-2"><span class="badge bg-warning text-dark me-3" style="min-width:65px;">50-79%</span><span><b>Hati-hati</b></span></div>
                                <div class="d-flex align-items-center"><span class="badge bg-danger me-3" style="min-width:65px;">&lt; 50%</span><span><b>Perlu Dibina</b></span></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="ttd-box-container">
                    <p>Sungai Loban, <?php echo date('d') . ' ' . $nama_bulan_indo[date('n')] . ' ' . date('Y'); ?></p>
                    <p class="fw-bold text-uppercase"><?php echo $bagian_admin_ttd; ?></p>
                    <p class="nama-ttd-format"><?php echo $nama_admin_ttd; ?></p>
                    <p>NIP. <?php echo $nip_admin_ttd; ?></p>
                </div>
                <div style="clear: both;"></div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if($view_mode == 'kelas'): ?>
    const ctx = document.getElementById('chartTahunan');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php foreach($nama_bulan as $n) echo "'$n',"; ?>],
            datasets: [
                <?php foreach($data_kelas as $dk) { ?>
                {
                    label: '<?php echo $dk['nama']; ?>',
                    data: [<?php echo implode(',', $dk['data']); ?>],
                    borderColor: '<?php echo $dk['color']; ?>',
                    backgroundColor: '<?php echo $dk['color']; ?>',
                    borderWidth: 2, tension: 0.3, pointRadius: 3
                },
                <?php } ?>
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, max: 100 } } }
    });
    <?php endif; ?>
</script>

</body>
</html>