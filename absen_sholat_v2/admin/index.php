<?php
session_start();
include "../koneksi.php";

// 1. SET ZONA WAKTU
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// --- AMBIL PENGATURAN ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

$label_hadir    = isset($d_set['label_hadir']) ? $d_set['label_hadir'] : "Hadir";
$label_sakit    = isset($d_set['label_sakit']) ? $d_set['label_sakit'] : "Sakit";
$label_izin     = isset($d_set['label_izin']) ? $d_set['label_izin'] : "Izin";
$label_halangan = isset($d_set['label_halangan']) ? $d_set['label_halangan'] : "Halangan";
$label_tidak    = isset($d_set['label_tidak_hadir']) ? $d_set['label_tidak_hadir'] : "Alpha";

$tgl_pilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// --- KUNCI DATA KELAS KHUSUS WALI KELAS ---
if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
    $id_kelas_pilih = $_SESSION['id_kelas_wali'];
} else {
    $id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
}

if ($id_kelas_pilih == '') {
    // Ambil kelas pertama yang masih aktif (bukan alumni)
    $first = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL LIMIT 1"));
    if($first){ $id_kelas_pilih = $first['id_kelas']; }
}

function simpan_status($koneksi, $nisn, $tanggal, $status) {
    $cek = mysqli_query($koneksi, "SELECT * FROM absensi WHERE nisn='$nisn' AND tanggal='$tanggal'");
    $waktu = ($status == 'Hadir') ? date('H:i:s') : '-';
    if(mysqli_num_rows($cek) == 0){
        mysqli_query($koneksi, "INSERT INTO absensi (nisn, tanggal, waktu_scan, status) VALUES ('$nisn', '$tanggal', '$waktu', '$status')");
    } else {
        mysqli_query($koneksi, "UPDATE absensi SET status='$status', waktu_scan='$waktu' WHERE nisn='$nisn' AND tanggal='$tanggal'");
    }
}

// --- LOGIKA AKSI MASAL (POST) ---
if (isset($_POST['aksi_masal'])) {
    $pilihan_nisn = $_POST['pilih_siswa'];
    $status_masal = $_POST['status_masal'];
    $target_tgl = $_POST['tgl_masal'];
    $target_kelas = $_POST['kelas_masal'];

    if(!empty($pilihan_nisn)){
        foreach($pilihan_nisn as $nisn_item){
            if($status_masal == 'Reset') {
                mysqli_query($koneksi, "DELETE FROM absensi WHERE nisn='$nisn_item' AND tanggal='$target_tgl'");
            } else if($status_masal == 'Halangan') {
                $q_jk = mysqli_query($koneksi, "SELECT jenis_kelamin FROM siswa WHERE nisn='$nisn_item'");
                $d_jk = mysqli_fetch_assoc($q_jk);
                if($d_jk['jenis_kelamin'] == 'P') {
                    simpan_status($koneksi, $nisn_item, $target_tgl, $status_masal);
                }
            } else {
                simpan_status($koneksi, $nisn_item, $target_tgl, $status_masal);
            }
        }
    }
    header("Location: index.php?kelas=$target_kelas&tanggal=$target_tgl");
    exit();
}

// --- LOGIKA TOMBOL AKSI SATUAN (GET) ---
if (isset($_GET['aksi'])) {
    $nisn = $_GET['nisn'];
    $target_tgl = $_GET['tgl_aksi']; 
    $target_kelas = $_GET['kelas_aksi']; 
    if ($_GET['aksi'] == 'hadir_manual') { simpan_status($koneksi, $nisn, $target_tgl, 'Hadir'); }
    if ($_GET['aksi'] == 'set_halangan') { 
        $q_jk = mysqli_query($koneksi, "SELECT jenis_kelamin FROM siswa WHERE nisn='$nisn'");
        $d_jk = mysqli_fetch_assoc($q_jk);
        if($d_jk['jenis_kelamin'] == 'P') simpan_status($koneksi, $nisn, $target_tgl, 'Halangan'); 
    }
    if ($_GET['aksi'] == 'set_sakit') { simpan_status($koneksi, $nisn, $target_tgl, 'Sakit'); }
    if ($_GET['aksi'] == 'set_izin') { simpan_status($koneksi, $nisn, $target_tgl, 'Izin'); }
    if ($_GET['aksi'] == 'batal_hadir') { mysqli_query($koneksi, "DELETE FROM absensi WHERE nisn='$nisn' AND tanggal='$target_tgl'"); }
    header("Location: index.php?kelas=$target_kelas&tanggal=$target_tgl");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Dashboard Admin - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        .profil-admin { font-size: 14px; color: #555; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .badge-sakit { background-color: #0dcaf0; color: black; }
        .badge-izin { background-color: #6c757d; color: white; }
        .badge-halangan { background-color: #ffc107; color: black; }
        #bulkActionToolbar {
            display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            z-index: 1000; background: white; padding: 15px 25px; border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: 2px solid #0d47a1;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <div class="text-center p-4">
                <img src="../assets/<?php echo $sekolah_logo; ?>" width="80" style="border-radius:50%; object-fit:contain;">
                <h6 class="mt-2 fw-bold text-uppercase"><?php echo $sekolah_nama; ?></h6>
            </div>
            <a href="index.php" class="menu-item active">ABSENSI HARIAN</a>
            <a href="laporan_mingguan.php" class="menu-item">REKAP MINGGUAN</a>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">KELOLA ABSENSI</h3>
                <div class="profil-admin">Halo, <b><?php echo $_SESSION['nama_admin']; ?></b> 👋</div>
            </div>

            <?php
            $total_siswa = 0; $total_hadir = 0; $total_sakit = 0; $total_izin = 0; $total_halangan = 0; $total_absen = 0;
            if($id_kelas_pilih != '') {
                $q_tot = mysqli_query($koneksi, "SELECT count(*) as total FROM siswa WHERE id_kelas='$id_kelas_pilih' AND status_siswa='aktif'");
                $d_tot = mysqli_fetch_assoc($q_tot);
                $total_siswa = $d_tot['total'];

                $q_rekap = mysqli_query($koneksi, "SELECT status, count(*) as jumlah FROM absensi a JOIN siswa s ON a.nisn=s.nisn 
                                                 WHERE s.id_kelas='$id_kelas_pilih' AND s.status_siswa='aktif' AND a.tanggal='$tgl_pilih' GROUP BY status");
                while($row = mysqli_fetch_assoc($q_rekap)){
                    if($row['status'] == 'Hadir') $total_hadir += $row['jumlah'];
                    if($row['status'] == 'Sakit') $total_sakit += $row['jumlah'];
                    if($row['status'] == 'Izin') $total_izin += $row['jumlah'];
                    if($row['status'] == 'Halangan') $total_halangan += $row['jumlah'];
                }
                $total_absen = $total_siswa - ($total_hadir + $total_sakit + $total_izin + $total_halangan);
            }
            ?>

            <div class="mb-4 bg-white p-3 rounded shadow-sm">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <form method="GET">
                            <label class="fw-bold small">Pilih Kelas:</label>
                            
                            <select name="kelas" class="form-select" onchange="this.form.submit()" <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') echo 'style="pointer-events: none; background-color: #e9ecef;"'; ?>>
                                <?php 
                                if (isset($_SESSION['role']) && $_SESSION['role'] == 'wali_kelas') {
                                    // Hanya ambil kelas miliknya sendiri
                                    $id_kw = $_SESSION['id_kelas_wali'];
                                    $q_k = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas='$id_kw'");
                                } else {
                                    // Jika Admin, tampilkan semua kelas yang belum lulus
                                    $q_k = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");
                                }

                                while($rk = mysqli_fetch_array($q_k)){
                                    $sel = ($rk['id_kelas'] == $id_kelas_pilih) ? 'selected' : '';
                                    $tampil_jurusan = isset($rk['jurusan']) ? " " . $rk['jurusan'] : "";
                                    echo "<option value='$rk[id_kelas]' $sel>$rk[nama_kelas]$tampil_jurusan</option>";
                                }
                                ?>
                            </select>
                            <input type="hidden" name="tanggal" value="<?php echo $tgl_pilih; ?>">
                        </form>
                    </div>
                    <div class="col-md-3">
                        <form method="GET">
                            <label class="fw-bold small">Pilih Tanggal:</label>
                            <input type="date" name="tanggal" class="form-control" value="<?php echo $tgl_pilih; ?>" onchange="this.form.submit()">
                            <input type="hidden" name="kelas" value="<?php echo $id_kelas_pilih; ?>">
                        </form>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold small text-primary"><i class="fa fa-search"></i> Cari Nama:</label>
                        <input type="text" id="inputCari" class="form-control border-primary" placeholder="Cari..." onkeyup="cariSiswa()">
                    </div>
                </div>
            </div>

            <form method="POST" id="formMasal">
                <input type="hidden" name="tgl_masal" value="<?php echo $tgl_pilih; ?>">
                <input type="hidden" name="kelas_masal" value="<?php echo $id_kelas_pilih; ?>">
                <input type="hidden" name="status_masal" id="status_masal_input">

                <div id="bulkActionToolbar" class="align-items-center gap-3">
                    <span class="fw-bold text-primary"><span id="countSelected">0</span> Terpilih</span>
                    <button type="button" onclick="submitMasal('Hadir')" class="btn btn-success btn-sm">Hadir</button>
                    <button type="button" onclick="submitMasal('Sakit')" class="btn btn-info btn-sm text-white">Sakit</button>
                    <button type="button" onclick="submitMasal('Izin')" class="btn btn-secondary btn-sm">Izin</button>
                    <button type="button" id="btnHalanganMasal" onclick="submitMasal('Halangan')" class="btn btn-warning btn-sm">Halangan</button>
                    
                    <button type="button" onclick="submitMasal('Reset')" class="btn btn-outline-danger btn-sm"><i class="fa fa-rotate-left"></i> Reset</button>
                    
                    <button type="submit" name="aksi_masal" id="btnSubmitMasal" style="display:none;"></button>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Daftar Siswa Aktif</span>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label small fw-bold" for="checkAll">Pilih Semua</label>
                                </div>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-striped table-hover mb-0 align-middle">
                                    <thead class="table-primary">
                                        <tr>
                                            <th width="40">#</th>
                                            <th>Nama Siswa</th>
                                            <th>L/P</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="daftarSiswa">
                                        <?php
                                        if($id_kelas_pilih != '') {
                                            $q_list = mysqli_query($koneksi, "SELECT s.*, a.waktu_scan, a.status FROM siswa s 
                                                                            LEFT JOIN absensi a ON s.nisn = a.nisn AND a.tanggal = '$tgl_pilih'
                                                                            WHERE s.id_kelas = '$id_kelas_pilih' AND s.status_siswa = 'aktif' ORDER BY s.nama_siswa ASC");
                                            while($d = mysqli_fetch_array($q_list)){
                                                $badge_jk = ($d['jenis_kelamin'] == 'L') ? 'primary' : 'danger';
                                                
                                                $st_db = $d['status'];
                                                if(empty($st_db)) { $st_tampil = "<span class='badge bg-danger'>$label_tidak</span>"; }
                                                elseif($st_db == 'Hadir') { $st_tampil = "<span class='badge bg-success'>$label_hadir</span>"; }
                                                elseif($st_db == 'Sakit') { $st_tampil = "<span class='badge badge-sakit'>$label_sakit</span>"; }
                                                elseif($st_db == 'Izin') { $st_tampil = "<span class='badge badge-izin'>$label_izin</span>"; }
                                                elseif($st_db == 'Halangan') { $st_tampil = "<span class='badge badge-halangan'>$label_halangan</span>"; }
                                                else { $st_tampil = "<span class='badge bg-secondary'>$st_db</span>"; }

                                                echo "<tr>
                                                        <td><input type='checkbox' name='pilih_siswa[]' value='$d[nisn]' data-gender='$d[jenis_kelamin]' class='checkItem'></td>
                                                        <td class='nama-siswa'>$d[nama_siswa]</td>
                                                        <td><span class='badge bg-$badge_jk'>$d[jenis_kelamin]</span></td>
                                                        <td>$st_tampil</td>
                                                        <td>";
                                                        if(!empty($st_db)){
                                                            echo "<a href='index.php?aksi=batal_hadir&nisn=$d[nisn]&tgl_aksi=$tgl_pilih&kelas_aksi=$id_kelas_pilih' class='btn btn-light btn-sm border'><i class='fa fa-rotate-left'></i> Reset</a>";
                                                        } else {
                                                            echo "<div class='btn-group'>
                                                                    <a href='index.php?aksi=hadir_manual&nisn=$d[nisn]&tgl_aksi=$tgl_pilih&kelas_aksi=$id_kelas_pilih' class='btn btn-success btn-sm'><i class='fa fa-check'></i></a>
                                                                    <a href='index.php?aksi=set_sakit&nisn=$d[nisn]&tgl_aksi=$tgl_pilih&kelas_aksi=$id_kelas_pilih' class='btn btn-info btn-sm text-white'>S</a>
                                                                    <a href='index.php?aksi=set_izin&nisn=$d[nisn]&tgl_aksi=$tgl_pilih&kelas_aksi=$id_kelas_pilih' class='btn btn-secondary btn-sm'>I</a>";
                                                            if($d['jenis_kelamin'] == 'P'){
                                                                echo "<a href='index.php?aksi=set_halangan&nisn=$d[nisn]&tgl_aksi=$tgl_pilih&kelas_aksi=$id_kelas_pilih' class='btn btn-warning btn-sm'>H</a>";
                                                            }
                                                            echo "</div>";
                                                        }
                                                echo "</td></tr>";
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm"><div class="card-body text-center"><canvas id="grafikAbsen"></canvas></div></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const checkAll = document.getElementById('checkAll');
    const checkItems = document.getElementsByClassName('checkItem');
    const bulkToolbar = document.getElementById('bulkActionToolbar');
    const btnHalanganMasal = document.getElementById('btnHalanganMasal');

    checkAll.addEventListener('change', function() {
        for (let item of checkItems) {
            if(item.parentElement.parentElement.style.display !== 'none') {
                item.checked = this.checked;
            }
        }
        updateToolbar();
    });

    for (let item of checkItems) { item.addEventListener('change', updateToolbar); }

    function updateToolbar() {
        let selected = document.querySelectorAll('.checkItem:checked');
        let checkedCount = selected.length;
        document.getElementById('countSelected').innerText = checkedCount;
        bulkToolbar.style.display = checkedCount > 0 ? 'flex' : 'none';

        let adaLakiLaki = false;
        selected.forEach(el => {
            if(el.getAttribute('data-gender') === 'L') {
                adaLakiLaki = true;
            }
        });

        if(adaLakiLaki) {
            btnHalanganMasal.style.display = 'none'; 
        } else {
            btnHalanganMasal.style.display = 'block'; 
        }
    }

    function submitMasal(status) {
        let msg = 'Ubah status ke ' + status + '?';
        if(status === 'Reset') {
            msg = 'Hapus status absensi untuk semua siswa yang dipilih?';
        }
        
        if(!confirm(msg)) return;
        
        document.getElementById('status_masal_input').value = status;
        document.getElementById('btnSubmitMasal').click();
    }

    function cariSiswa() {
        let filter = document.getElementById("inputCari").value.toUpperCase();
        let tr = document.getElementById("daftarSiswa").getElementsByTagName("tr");
        for (let i = 0; i < tr.length; i++) {
            let td = tr[i].getElementsByClassName("nama-siswa")[0];
            if (td) {
                let match = td.textContent.toUpperCase().indexOf(filter) > -1;
                tr[i].style.display = match ? "" : "none";
                if(!match) tr[i].querySelector('.checkItem').checked = false;
            }
        }
        updateToolbar();
    }

    const ctx = document.getElementById('grafikAbsen');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Halangan', 'Alpha'],
            datasets: [{
                data: [<?php echo "$total_hadir, $total_sakit, $total_izin, $total_halangan, $total_absen"; ?>],
                backgroundColor: ['#198754', '#0dcaf0', '#6c757d', '#ffc107', '#dc3545']
            }]
        }
    });
</script>
</body>
</html>