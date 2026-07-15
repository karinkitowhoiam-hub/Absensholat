<?php
session_start();
include "../koneksi.php";

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// AMBIL INFO SEKOLAH
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo'];

// ==========================================
// 1. LOGIKA PULIHKAN KELAS (MASAL)
// ==========================================
if (isset($_POST['pulihkan_kelas'])) {
    $id_kelas_pulih = $_POST['id_kelas_pilihan'];
    
    // Step A: Kembalikan semua siswa di kelas ini menjadi 'aktif'
    mysqli_query($koneksi, "UPDATE siswa SET status_siswa='aktif' WHERE id_kelas='$id_kelas_pulih'");
    
    // Step B: Hapus tahun_lulus di tabel kelas agar muncul lagi di menu Data Kelas
    mysqli_query($koneksi, "UPDATE kelas SET tahun_lulus=NULL WHERE id_kelas='$id_kelas_pulih'");
    
    echo "<script>alert('Berhasil! Kelas dan seluruh siswanya telah diaktifkan kembali.'); window.location='data_kelas.php?kelas=$id_kelas_pulih';</script>";
}

// ==========================================
// 2. LOGIKA AKTIFKAN SATUAN (TOMBOL DI TABEL)
// ==========================================
if (isset($_GET['aktifkan_satuan'])) {
    $nisn_target = mysqli_real_escape_string($koneksi, $_GET['aktifkan_satuan']);
    
    // Step A: Cari tahu siswa ini kelas mana
    $q_cari_k = mysqli_query($koneksi, "SELECT id_kelas FROM siswa WHERE nisn='$nisn_target'");
    $d_cari_k = mysqli_fetch_array($q_cari_k);
    $id_k = $d_cari_k['id_kelas'];

    // Step B: Set status siswa jadi aktif
    mysqli_query($koneksi, "UPDATE siswa SET status_siswa='aktif' WHERE nisn='$nisn_target'");
    
    // Step C: Pastikan kelasnya juga aktif (tahun_lulus jadi NULL)
    mysqli_query($koneksi, "UPDATE kelas SET tahun_lulus=NULL WHERE id_kelas='$id_k'");

    echo "<script>alert('Siswa berhasil diaktifkan kembali!'); window.location='data_kelas.php?kelas=$id_k';</script>";
}

// FILTER KELAS ALUMNI
$id_kelas_filter = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Database Alumni - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; position: fixed; width: 16.666667%; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        .content-area { margin-left: 16.666667%; }
        .foto-alumni { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid #ddd; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <div class="text-center p-4">
                <img src="../assets/<?php echo $sekolah_logo; ?>" width="80" style="border-radius:50%; object-fit:contain;">
                <h6 class="mt-2 fw-bold"><?php echo $sekolah_nama; ?></h6>
            </div>
            <a href="index.php" class="menu-item active">ABSENSI HARIAN</a>
            <a href="laporan_mingguan.php" class="menu-item">REKAP MINGGUAN</a>
            <a href="laporan.php" class="menu-item">LAPORAN BULANAN</a>
            <a href="laporan_tahunan.php" class="menu-item">LAPORAN TAHUNAN</a>
            <?php if($_SESSION['role'] == 'admin') { ?>
            <a href="data_kelas.php" class="menu-item">DATA KELAS</a>
            <a href="data_alumni.php" class="menu-item">DATA ALUMNI</a>
            <a href="backup.php" class="menu-item">BACKUP & RESTORE</a>
            <a href="tampilan.php" class="menu-item">TAMPILAN</a>
            <?php } ?>
            <a href="logout.php" class="menu-item text-danger">KELUAR</a>
        </div>

        <div class="col-md-10 content-area p-4">
            <h3 class="fw-bold mb-4"><i class="fa-solid fa-graduation-cap text-secondary"></i> ARSIP DATA ALUMNI</h3>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-end g-3">
                        <div class="col-md-5">
                            <form method="GET">
                                <label class="fw-bold small text-muted">Pilih Berdasarkan Tahun Lulus:</label>
                                <select name="filter_kelas" class="form-select border-primary shadow-sm" onchange="this.form.submit()">
                                    <option value="">-- Lihat Semua Alumni --</option>
                                    <?php 
                                    $q_k_alumni = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NOT NULL ORDER BY tahun_lulus DESC");
                                    while($rk = mysqli_fetch_array($q_k_alumni)){
                                        $sel = ($rk['id_kelas'] == $id_kelas_filter) ? 'selected' : '';
                                        echo "<option value='$rk[id_kelas]' $sel>$rk[jurusan] ($rk[tahun_lulus])</option>";
                                    }
                                    ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-md-7 text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="cetak_alumni.php<?php echo (!empty($id_kelas_filter)) ? '?filter_kelas='.$id_kelas_filter : ''; ?>" 
                                   target="_blank" class="btn btn-primary fw-bold shadow-sm">
                                    <i class="fa fa-print"></i> CETAK DATA ALUMNI
                                </a>

                                <?php if(!empty($id_kelas_filter)) { ?>
                                <form method="POST">
                                    <input type="hidden" name="id_kelas_pilihan" value="<?php echo $id_kelas_filter; ?>">
                                    <button type="submit" name="pulihkan_kelas" class="btn btn-success fw-bold shadow-sm" onclick="return confirm('Seluruh siswa di kelas ini akan kembali aktif. Lanjutkan?')">
                                        <i class="fa fa-sync-alt"></i> PULIHKAN KELAS INI JADI AKTIF
                                    </button>
                                </form>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th width="50">No</th>
                                    <th>Foto</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>L/P</th>
                                    <th>Tahun Lulus</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $where = (!empty($id_kelas_filter)) ? "AND s.id_kelas='$id_kelas_filter'" : "";
                                $q_siswa = mysqli_query($koneksi, "SELECT s.*, k.tahun_lulus FROM siswa s 
                                            JOIN kelas k ON s.id_kelas = k.id_kelas 
                                            WHERE s.status_siswa='alumni' $where 
                                            ORDER BY s.nama_siswa ASC");
                                
                                $no = 1;
                                if (mysqli_num_rows($q_siswa) > 0) {
                                    while ($d = mysqli_fetch_array($q_siswa)) {
                                        $foto = (!empty($d['foto_siswa']) && file_exists("foto_siswa/".$d['foto_siswa'])) ? "foto_siswa/".$d['foto_siswa'] : (($d['jenis_kelamin']=='L')?'https://cdn-icons-png.flaticon.com/512/3011/3011270.png':'https://cdn-icons-png.flaticon.com/512/3011/3011276.png');
                                ?>
                                <tr class="text-center">
                                    <td><?php echo $no++; ?></td>
                                    <td><img src="<?php echo $foto; ?>" class="foto-alumni shadow-sm"></td>
                                    <td class="text-start fw-bold"><?php echo strtoupper($d['nama_siswa']); ?></td>
                                    <td><?php echo $d['nisn']; ?></td>
                                    <td><span class="badge <?php echo ($d['jenis_kelamin']=='L')?'bg-primary':'bg-danger'; ?>"><?php echo $d['jenis_kelamin']; ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo $d['tahun_lulus']; ?></span></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-info btn-sm text-white fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDetail<?php echo $d['nisn']; ?>">
                                                <i class="fa fa-eye"></i> Detail
                                            </button>
                                            <a href="?aktifkan_satuan=<?php echo $d['nisn']; ?>" class="btn btn-outline-success btn-sm fw-bold shadow-sm" onclick="return confirm('Aktifkan kembali siswa ini?')">
                                                <i class="fa fa-user-check"></i> Aktifkan
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    } 
                                } else {
                                    echo "<tr><td colspan='7' class='text-center py-5 text-muted italic'>Belum ada data alumni yang ditemukan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (mysqli_num_rows($q_siswa) > 0) {
    mysqli_data_seek($q_siswa, 0);
    while($d = mysqli_fetch_array($q_siswa)){
        $nisn_cek = $d['nisn'];
        
        // HITUNG PERSENTASE
        $q_range = mysqli_query($koneksi, "SELECT MIN(tanggal) as awal, MAX(tanggal) as akhir FROM absensi WHERE nisn='$nisn_cek'");
        $d_range = mysqli_fetch_assoc($q_range);
        
        $persen_tampil = "0%";
        if(!empty($d_range['awal'])){
            $tgl_awal = new DateTime($d_range['awal']);
            $tgl_akhir = new DateTime($d_range['akhir']);
            $diff = $tgl_awal->diff($tgl_akhir)->days + 1; 

            $q_hadir = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM absensi WHERE nisn='$nisn_cek' AND status='Hadir'");
            $d_hadir = mysqli_fetch_assoc($q_hadir);
            
            $persen_tampil = round(($d_hadir['total'] / $diff) * 100, 1) . "%";
        }
?>
<div class="modal fade" id="modalDetail<?php echo $d['nisn']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white"><h5 class="modal-title fw-bold">Profil Alumni</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <img src="<?php echo (!empty($d['foto_siswa'])) ? "foto_siswa/".$d['foto_siswa'] : (($d['jenis_kelamin']=='L')?'https://cdn-icons-png.flaticon.com/512/3011/3011270.png':'https://cdn-icons-png.flaticon.com/512/3011/3011276.png'); ?>" width="120" height="120" class="rounded-circle border mb-3 shadow" style="object-fit:cover;">
                <h4 class="fw-bold"><?php echo $d['nama_siswa']; ?></h4>
                <p class="text-muted small">NISN: <?php echo $d['nisn']; ?> | <?php echo ($d['jenis_kelamin']=='L'?'Laki-laki':'Perempuan'); ?></p>
                <hr>
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-uppercase fw-bold text-secondary">Tahun Kelulusan</small>
                        <h5 class="text-dark fw-bold"><?php echo $d['tahun_lulus']; ?></h5>
                    </div>
                    <div class="col-6">
                        <small class="text-uppercase fw-bold text-secondary">Performa Ibadah</small>
                        <h5 class="text-primary fw-bold"><?php echo $persen_tampil; ?></h5>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Tutup Jendela</button></div>
        </div>
    </div>
</div>
<?php } } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>