<?php
session_start();
include "../koneksi.php";

// SET TIMEZONE
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// --- AMBIL PENGATURAN TAMPILAN ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 
// ---------------------------------

// 1. Cek Kelas Terpilih
$id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
if ($id_kelas_pilih == '') {
    // MODIFIKASI: Hanya pilih kelas yang BELUM lulus (tahun_lulus NULL)
    $first = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL LIMIT 1"));
    if($first) { $id_kelas_pilih = $first['id_kelas']; }
}

// 2. Ambil Info Kelas Lengkap
$nama_kelas_aktif = "Belum Ada Kelas";
$jurusan_aktif = "-";
$wali_kelas_aktif = "-";
$nip_wali_aktif = "-";

if($id_kelas_pilih != '') {
    $q_info_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas = '$id_kelas_pilih'");
    $d_info_kelas = mysqli_fetch_array($q_info_kelas);
    $nama_kelas_aktif = $d_info_kelas['nama_kelas'];
    $jurusan_aktif = $d_info_kelas['jurusan'];
    
    if(!empty($d_info_kelas['nama_wali'])){
        $wali_kelas_aktif = $d_info_kelas['nama_wali'];
        $nip_wali_aktif = $d_info_kelas['nip_wali'];
    }
}

// --- LOGIKA CRUD ---

// MODIFIKASI: LOGIKA LULUSKAN KELAS (DENGAN TAHUN LULUS)
if (isset($_POST['luluskan_kelas_aksi'])) {
    $id_kls_lulus = $_POST['id_kelas_lulus'];
    $tahun_sekarang = date('Y');
    
    // Set status siswa jadi alumni
    mysqli_query($koneksi, "UPDATE siswa SET status_siswa='alumni' WHERE id_kelas='$id_kls_lulus'");
    // Set tahun lulus di tabel kelas agar masuk arsip
    mysqli_query($koneksi, "UPDATE kelas SET tahun_lulus='$tahun_sekarang' WHERE id_kelas='$id_kls_lulus'");
    
    echo "<script>alert('Selamat! Seluruh siswa di kelas ini telah berhasil diluluskan menjadi Alumni.'); window.location='data_kelas.php';</script>";
}

// HAPUS MASSAL
if (isset($_POST['hapus_massal'])) {
    if(!empty($_POST['pilih_siswa'])) {
        $jumlah_dihapus = 0;
        $daftar_nisn = $_POST['pilih_siswa']; 

        foreach($daftar_nisn as $nisn_target) {
            $cek_f = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE nisn='$nisn_target'");
            $data_f = mysqli_fetch_array($cek_f);
            if(!empty($data_f['foto_siswa']) && file_exists("foto_siswa/".$data_f['foto_siswa'])){
                unlink("foto_siswa/".$data_f['foto_siswa']);
            }
            mysqli_query($koneksi, "DELETE FROM absensi WHERE nisn='$nisn_target'");
            mysqli_query($koneksi, "DELETE FROM siswa WHERE nisn='$nisn_target'");
            $jumlah_dihapus++;
        }
        $kelas_kembali = $_POST['id_kelas_asal'];
        echo "<script>alert('Berhasil menghapus $jumlah_dihapus siswa!'); window.location='data_kelas.php?kelas=$kelas_kembali';</script>";
    } else {
        echo "<script>alert('Tidak ada siswa yang dipilih!');</script>";
    }
}

// Update Wali Kelas
if (isset($_POST['update_wali_kelas'])) {
    $id_kelas = $_POST['id_kelas'];
    $nama_wali = $_POST['nama_wali'];
    $nip_wali = $_POST['nip_wali'];
    mysqli_query($koneksi, "UPDATE kelas SET nama_wali='$nama_wali', nip_wali='$nip_wali' WHERE id_kelas='$id_kelas'");
    echo "<script>alert('Data Wali Kelas Berhasil Disimpan!'); window.location='data_kelas.php?kelas=$id_kelas';</script>";
}

// Tambah Kelas
if (isset($_POST['tambah_kelas'])) {
    $nama_kelas_baru = $_POST['nama_kelas_baru'];
    $jurusan_baru = $_POST['jurusan_baru'];
    mysqli_query($koneksi, "INSERT INTO kelas (nama_kelas, jurusan) VALUES ('$nama_kelas_baru', '$jurusan_baru')");
    echo "<script>alert('Berhasil Menambah Kelas Baru!'); window.location='data_kelas.php';</script>";
}

// Update Nama Kelas
if (isset($_POST['update_kelas'])) {
    $id_kelas = $_POST['id_kelas'];
    $nama_baru = $_POST['nama_kelas_edit'];
    $jurusan_edit = $_POST['jurusan_edit'];
    mysqli_query($koneksi, "UPDATE kelas SET nama_kelas='$nama_baru', jurusan='$jurusan_edit' WHERE id_kelas='$id_kelas'");
    echo "<script>alert('Data Kelas Berhasil Diubah!'); window.location='data_kelas.php?kelas=$id_kelas';</script>";
}

// Hapus Kelas
if (isset($_POST['hapus_kelas_ini'])) {
    $id_hapus = $_POST['id_kelas_hapus'];
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");
    $q_siswa_del = mysqli_query($koneksi, "SELECT nisn, foto_siswa FROM siswa WHERE id_kelas='$id_hapus'");
    while($row_s = mysqli_fetch_array($q_siswa_del)){
        if(!empty($row_s['foto_siswa']) && file_exists("foto_siswa/".$row_s['foto_siswa'])){
            unlink("foto_siswa/".$row_s['foto_siswa']);
        }
        $nisn_del = $row_s['nisn'];
        mysqli_query($koneksi, "DELETE FROM absensi WHERE nisn='$nisn_del'");
    }
    mysqli_query($koneksi, "DELETE FROM siswa WHERE id_kelas='$id_hapus'");
    mysqli_query($koneksi, "DELETE FROM kelas WHERE id_kelas='$id_hapus'");
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");
    echo "<script>alert('Kelas berhasil dihapus!'); window.location='data_kelas.php';</script>";
}

// Tambah Siswa
if (isset($_POST['tambah_siswa'])) {
    $nisn_baru = $_POST['nisn_baru'];
    $nama_siswa_baru = $_POST['nama_siswa_baru'];
    $jk_baru = $_POST['jk_baru'];
    $id_kelas_target = $_POST['id_kelas_target'];
    
    $nama_foto = $_FILES['foto_baru']['name'];
    $lokasi_foto = $_FILES['foto_baru']['tmp_name'];
    $nama_foto_fix = ""; 

    if(!empty($lokasi_foto)){
        $ekstensi = pathinfo($nama_foto, PATHINFO_EXTENSION);
        $nama_foto_fix = $nisn_baru . "_" . time() . "." . $ekstensi;
        move_uploaded_file($lokasi_foto, "foto_siswa/" . $nama_foto_fix);
    }
    
    $cek = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn='$nisn_baru'");
    if(mysqli_num_rows($cek) > 0){
         echo "<script>alert('Gagal! NISN sudah terdaftar.');</script>";
    } else {
        mysqli_query($koneksi, "INSERT INTO siswa (nisn, nama_siswa, jenis_kelamin, id_kelas, status_siswa, foto_siswa) VALUES ('$nisn_baru', '$nama_siswa_baru', '$jk_baru', '$id_kelas_target', 'aktif', '$nama_foto_fix')");
        echo "<script>alert('Berhasil Menambah Siswa!');</script>";
    }
}

// Hapus Foto
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus_foto') {
    $nisn_target = $_GET['nisn'];
    $kelas_target = $_GET['kelas'];
    $q_cari = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE nisn='$nisn_target'");
    $d_cari = mysqli_fetch_array($q_cari);
    if(!empty($d_cari['foto_siswa']) && file_exists("foto_siswa/".$d_cari['foto_siswa'])){
        unlink("foto_siswa/".$d_cari['foto_siswa']);
    }
    mysqli_query($koneksi, "UPDATE siswa SET foto_siswa=NULL WHERE nisn='$nisn_target'");
    echo "<script>alert('Foto Siswa Berhasil Dihapus!'); window.location='data_kelas.php?kelas=$kelas_target';</script>";
}

// Hapus Siswa (SATUAN)
if (isset($_GET['hapus_nisn'])) {
    $nisn_hapus = $_GET['hapus_nisn'];
    $kelas_kembali = $_GET['kelas']; 
    $cek_f = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE nisn='$nisn_hapus'");
    $data_f = mysqli_fetch_array($cek_f);
    if(!empty($data_f['foto_siswa']) && file_exists("foto_siswa/".$data_f['foto_siswa'])){
        unlink("foto_siswa/".$data_f['foto_siswa']);
    }
    mysqli_query($koneksi, "DELETE FROM absensi WHERE nisn='$nisn_hapus'");
    mysqli_query($koneksi, "DELETE FROM siswa WHERE nisn='$nisn_hapus'");
    echo "<script>alert('Data Siswa Berhasil Dihapus!'); window.location='data_kelas.php?kelas=$kelas_kembali';</script>";
}

// Update Siswa
if (isset($_POST['update_siswa'])) {
    $nisn_lama = $_POST['nisn_lama'];
    $nisn_baru = $_POST['nisn_baru'];
    $nama_baru = $_POST['nama_baru'];
    $jk_baru = $_POST['jk_baru'];
    $kelas_kembali = $_POST['id_kelas_target'];
    $nama_foto = $_FILES['foto_upload']['name'];
    $lokasi_foto = $_FILES['foto_upload']['tmp_name'];

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");

    if(!empty($lokasi_foto)){
        $ekstensi = pathinfo($nama_foto, PATHINFO_EXTENSION);
        $nama_foto_baru = $nisn_baru . "_" . time() . "." . $ekstensi;
        $q_lama = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE nisn='$nisn_lama'");
        $d_lama = mysqli_fetch_array($q_lama);
        if(!empty($d_lama['foto_siswa']) && file_exists("foto_siswa/".$d_lama['foto_siswa'])){
            unlink("foto_siswa/".$d_lama['foto_siswa']);
        }
        move_uploaded_file($lokasi_foto, "foto_siswa/" . $nama_foto_baru);
        mysqli_query($koneksi, "UPDATE siswa SET nisn='$nisn_baru', nama_siswa='$nama_baru', jenis_kelamin='$jk_baru', foto_siswa='$nama_foto_baru' WHERE nisn='$nisn_lama'");
    } else {
        mysqli_query($koneksi, "UPDATE siswa SET nisn='$nisn_baru', nama_siswa='$nama_baru', jenis_kelamin='$jk_baru' WHERE nisn='$nisn_lama'");
    }
    mysqli_query($koneksi, "UPDATE absensi SET nisn='$nisn_baru' WHERE nisn='$nisn_lama'");
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");
    echo "<script>alert('Data Berhasil Diubah!'); window.location='data_kelas.php?kelas=$kelas_kembali';</script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Data Kelas - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
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
            <?php if($_SESSION['role'] == 'admin') { ?>
            <a href="data_kelas.php" class="menu-item">DATA KELAS</a>
            <a href="data_alumni.php" class="menu-item">DATA ALUMNI</a>
            <a href="backup.php" class="menu-item">BACKUP & RESTORE</a>
            <a href="tampilan.php" class="menu-item">TAMPILAN</a>
            <?php } ?>
            <a href="logout.php" class="menu-item text-danger">KELUAR</a>
        </div>

        <div class="col-md-10 p-4">
            <h2 class="fw-bold mb-4">MANAJEMEN KELAS & SISWA</h2>
            
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <form method="GET">
                                <label class="fw-bold small">Pilih Kelas:</label>
                                <select name="kelas" class="form-select" onchange="this.form.submit()">
                                    <?php 
                                    // MODIFIKASI: Hanya tampilkan kelas aktif (tahun_lulus IS NULL)
                                    $q_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");
                                    while($row = mysqli_fetch_array($q_kelas)){
                                        $selected = ($row['id_kelas'] == $id_kelas_pilih) ? 'selected' : '';
                                        $label_kelas = $row['nama_kelas'];
                                        if(!empty($row['jurusan'])) { $label_kelas .= " - " . $row['jurusan']; }
                                        echo "<option value='$row[id_kelas]' $selected>$label_kelas</option>";
                                    }
                                    ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-md-8 text-end">
                            <?php if($id_kelas_pilih != '') { ?>
                            <div class="d-inline-flex flex-wrap gap-1 shadow-sm p-1 bg-light rounded border">
                                <a href="import_siswa.php" class="btn btn-success btn-sm fw-bold">
                                    <i class="fa-solid fa-file-excel"></i> Import
                                </a>
                                <a href="cetak_kartu.php?kelas=<?php echo $id_kelas_pilih; ?>" target="_blank" class="btn btn-primary btn-sm fw-bold">
                                    <i class="fa-solid fa-id-card"></i> Cetak Semua
                                </a>
                                <div class="vr mx-1 d-none d-lg-block"></div>
                                <button type="button" class="btn btn-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalWaliKelas">
                                    <i class="fa-solid fa-user-tie"></i> Wali
                                </button>
                                <button type="button" class="btn btn-info text-white btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalEditKelas">
                                    <i class="fa-solid fa-pen"></i> Ubah
                                </button>
                                <button type="button" class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalLuluskanKelas">
                                    <i class="fa-solid fa-graduation-cap"></i> Luluskan
                                </button>
                                <button type="button" class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalHapusKelas">
                                    <i class="fa-solid fa-trash"></i> Hapus
                                </button>
                                <div class="vr mx-1 d-none d-lg-block"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahKelas">
                                    <i class="fa-solid fa-plus-circle"></i> Kelas
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
                                    <i class="fa-solid fa-user-plus"></i> Peserta
                                </button>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 shadow-sm border-0">
                <div class="card-body bg-white border-start border-primary border-5 rounded shadow-sm">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-1">Kelas: <?php echo $nama_kelas_aktif; ?></h5>
                            <span class="badge bg-secondary"><?php echo $jurusan_aktif; ?></span>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="d-inline-block text-start">
                                <small class="text-muted fw-bold d-block">Wali Kelas:</small>
                                <span class="fw-bold text-primary fs-5"><?php echo $wali_kelas_aktif; ?></span><br>
                                <small class="text-muted small">NIP: <?php echo $nip_wali_aktif; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" id="formSiswa">
                <input type="hidden" name="id_kelas_asal" value="<?php echo $id_kelas_pilih; ?>">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span>Daftar Siswa Aktif</span>
                        <div class="d-flex gap-2">
                            <button type="button" onclick="printTerpilih()" class="btn btn-outline-primary btn-sm fw-bold">
                                <i class="fa-solid fa-print"></i> Print Terpilih
                            </button>
                            <button type="submit" name="hapus_massal" class="btn btn-outline-danger btn-sm fw-bold" onclick="return confirm('Hapus permanen siswa terpilih?')">
                                <i class="fa-solid fa-trash-can"></i> Hapus Terpilih
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-dark text-center">
                                    <tr>
                                        <th width="40"><input type="checkbox" id="checkAll"></th>
                                        <th width="50">No</th>
                                        <th class="text-start">Nama Siswa</th>
                                        <th width="80">L/P</th>
                                        <th>NISN</th>
                                        <th width="150">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if($id_kelas_pilih != '') {
                                        $q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_kelas = '$id_kelas_pilih' AND status_siswa = 'aktif' ORDER BY nama_siswa ASC");
                                        $no = 1;
                                        while($d = mysqli_fetch_array($q_siswa)){
                                            $foto = (!empty($d['foto_siswa']) && file_exists("foto_siswa/".$d['foto_siswa'])) ? "foto_siswa/".$d['foto_siswa'] : (($d['jenis_kelamin']=='L')?'https://cdn-icons-png.flaticon.com/512/3011/3011270.png':'https://cdn-icons-png.flaticon.com/512/3011/3011276.png');
                                    ?>
                                    <tr class="text-center">
                                        <td><input type="checkbox" name="pilih_siswa[]" value="<?php echo $d['nisn']; ?>" class="checkItem"></td>
                                        <td><?php echo $no++; ?>.</td>
                                        <td class="text-start ps-3">
                                            <img src="<?php echo $foto; ?>" width="30" height="30" class="rounded-circle me-2" style="object-fit:cover;">
                                            <?php echo strtoupper($d['nama_siswa']); ?>
                                        </td>
                                        <td><span class="badge <?php echo ($d['jenis_kelamin']=='L')?'bg-primary':'bg-danger'; ?>"><?php echo $d['jenis_kelamin']; ?></span></td>
                                        <td class="fw-bold"><?php echo $d['nisn']; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="cetak_kartu.php?kelas=<?php echo $id_kelas_pilih; ?>&nisn=<?php echo $d['nisn']; ?>" target="_blank" class="btn btn-warning btn-sm" title="Cetak Kartu"><i class="fa-solid fa-id-card"></i></a>
                                                <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#modalDetail<?php echo $d['nisn']; ?>" title="Lihat"><i class="fa-solid fa-eye"></i></button>
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEdit<?php echo $d['nisn']; ?>" title="Ubah"><i class="fa-solid fa-pen"></i></button>
                                                <a href="data_kelas.php?hapus_nisn=<?php echo $d['nisn']; ?>&kelas=<?php echo $id_kelas_pilih; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus siswa ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
if($id_kelas_pilih != '') {
    mysqli_data_seek($q_siswa, 0);
    while($d = mysqli_fetch_array($q_siswa)){ 
?>
<div class="modal fade" id="modalDetail<?php echo $d['nisn']; ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">Detail Peserta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row align-items-center"><div class="col-md-5 text-center border-end"><img src="<?php echo (!empty($d['foto_siswa']) && file_exists("foto_siswa/".$d['foto_siswa'])) ? "foto_siswa/".$d['foto_siswa'] : (($d['jenis_kelamin']=='L')?'https://cdn-icons-png.flaticon.com/512/3011/3011270.png':'https://cdn-icons-png.flaticon.com/512/3011/3011276.png'); ?>" width="150" height="150" class="rounded-circle border mb-3 shadow" style="object-fit:cover;"><br><img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $d['nisn']; ?>" class="mt-2 border p-2 rounded bg-white shadow-sm"></div><div class="col-md-7 px-4"><h3 class="fw-bold text-primary mb-3"><?php echo $d['nama_siswa']; ?></h3><table class="table table-borderless"><tr><td width="30%">NISN</td><td>: <?php echo $d['nisn']; ?></td></tr><tr><td>JK</td><td>: <?php echo $d['jenis_kelamin']; ?></td></tr><tr><td>Kelas</td><td>: <?php echo $nama_kelas_aktif; ?></td></tr><tr><td>Jurusan</td><td>: <?php echo $jurusan_aktif; ?></td></tr><tr><td>Wali</td><td>: <?php echo $wali_kelas_aktif; ?></td></tr></table></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div></div></div></div>
<div class="modal fade" id="modalEdit<?php echo $d['nisn']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Ubah Data Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body"><input type="hidden" name="id_kelas_target" value="<?php echo $id_kelas_pilih; ?>"><input type="hidden" name="nisn_lama" value="<?php echo $d['nisn']; ?>"><div class="mb-3"><label class="fw-bold">NISN Baru</label><input type="text" name="nisn_baru" class="form-control" value="<?php echo $d['nisn']; ?>" required></div><div class="mb-3"><label class="fw-bold">Nama Lengkap</label><input type="text" name="nama_baru" class="form-control" value="<?php echo $d['nama_siswa']; ?>" required></div><div class="mb-3"><label class="fw-bold">Jenis Kelamin</label><select name="jk_baru" class="form-select"><option value="L" <?php if($d['jenis_kelamin']=='L') echo 'selected'; ?>>Laki-laki</option><option value="P" <?php if($d['jenis_kelamin']=='P') echo 'selected'; ?>>Perempuan</option></select></div><div class="mb-3"><label class="fw-bold">Update Foto (JPG/PNG)</label><input type="file" name="foto_upload" class="form-control" accept="image/*"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_siswa" class="btn btn-primary">Simpan</button></div></form></div></div></div>
<?php } } ?>

<div class="modal fade" id="modalWaliKelas" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Atur Wali Kelas</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="id_kelas" value="<?php echo $id_kelas_pilih; ?>"><div class="mb-3"><label class="fw-bold">Nama Wali Kelas:</label><input type="text" name="nama_wali" class="form-control" value="<?php echo ($wali_kelas_aktif != '-') ? $wali_kelas_aktif : ''; ?>" required></div><div class="mb-3"><label class="fw-bold">NIP Wali:</label><input type="text" name="nip_wali" class="form-control" value="<?php echo ($nip_wali_aktif != '-') ? $nip_wali_aktif : ''; ?>" required></div></div><div class="modal-footer"><button type="submit" name="update_wali_kelas" class="btn btn-primary">Simpan</button></div></form></div></div></div>
<div class="modal fade" id="modalLuluskanKelas" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title fw-bold">Luluskan Kelas?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body text-center"><input type="hidden" name="id_kelas_lulus" value="<?php echo $id_kelas_pilih; ?>"><p>Seluruh siswa di kelas <b><?php echo $nama_kelas_aktif; ?></b> akan dipindahkan ke **Arsip Alumni**.</p><p class="text-danger fw-bold">Lanjutkan?</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="luluskan_kelas_aksi" class="btn btn-warning fw-bold">YA, LULUSKAN</button></div></form></div></div></div>
<div class="modal fade" id="modalTambahKelas" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title fw-bold">Tambah Kelas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><div class="mb-3"><label>Nama Kelas</label><input type="text" name="nama_kelas_baru" class="form-control" placeholder="Contoh: X" required></div><div class="mb-3"><label>Jurusan</label><input type="text" name="jurusan_baru" class="form-control" placeholder="Contoh: Multimedia" required></div></div><div class="modal-footer"><button type="submit" name="tambah_kelas" class="btn btn-primary">Simpan</button></div></form></div></div></div>
<div class="modal fade" id="modalEditKelas" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Ubah Kelas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="id_kelas" value="<?php echo $id_kelas_pilih; ?>"><div class="mb-3"><label>Nama Kelas</label><input type="text" name="nama_kelas_edit" class="form-control" value="<?php echo $nama_kelas_aktif; ?>"></div><div class="mb-3"><label>Jurusan</label><input type="text" name="jurusan_edit" class="form-control" value="<?php echo $jurusan_aktif; ?>"></div></div><div class="modal-footer"><button type="submit" name="update_kelas" class="btn btn-info text-white">Simpan</button></div></form></div></div></div>
<div class="modal fade" id="modalHapusKelas" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title">Hapus Kelas?</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body text-center"><input type="hidden" name="id_kelas_hapus" value="<?php echo $id_kelas_pilih; ?>"><p>Hapus permanen kelas <b><?php echo $nama_kelas_aktif; ?></b> beserta isinya?</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="hapus_kelas_ini" class="btn btn-danger">Hapus</button></div></form></div></div></div>
<div class="modal fade" id="modalTambahSiswa" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title">Tambah Peserta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body"><input type="hidden" name="id_kelas_target" value="<?php echo $id_kelas_pilih; ?>"><div class="mb-3"><label class="fw-bold">NISN</label><input type="number" name="nisn_baru" class="form-control" required placeholder="10 digit"></div><div class="mb-3"><label class="fw-bold">Nama Lengkap</label><input type="text" name="nama_siswa_baru" class="form-control" required></div><div class="mb-3"><label class="fw-bold">Jenis Kelamin</label><select name="jk_baru" class="form-select"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div><div class="mb-3"><label class="fw-bold">Foto (Opsional)</label><input type="file" name="foto_baru" class="form-control" accept="image/*"></div></div><div class="modal-footer"><button type="submit" name="tambah_siswa" class="btn btn-success">Simpan Data</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('checkAll').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('.checkItem');
        for (var i = 0; i < checkboxes.length; i++) { checkboxes[i].checked = this.checked; }
    });

    // FUNGSI JAVASCRIPT BARU UNTUK PRINT TERPILIH
    function printTerpilih() {
        var checkboxes = document.querySelectorAll('.checkItem:checked');
        if (checkboxes.length === 0) {
            alert('Silakan pilih siswa yang ingin dicetak kartunya!');
            return;
        }

        var nisn_list = [];
        checkboxes.forEach(function(checkbox) {
            nisn_list.push(checkbox.value);
        });

        // Kirim NISN terpilih ke cetak_kartu.php via URL
        var url = "cetak_kartu.php?kelas=<?php echo $id_kelas_pilih; ?>&nisn_pilih=" + nisn_list.join(',');
        window.open(url, '_blank');
    }
</script>
</body>
</html>