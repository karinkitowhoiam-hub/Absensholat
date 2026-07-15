<?php
session_start();
include "../koneksi.php";

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// 1. AMBIL DATA PENGATURAN & ADMIN
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);

$id_admin_login = $_SESSION['id_admin'];
$q_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE id_admin='$id_admin_login'");
$d_admin = mysqli_fetch_array($q_admin);

// --- PROSES 1: SIMPAN IDENTITAS ---
if (isset($_POST['simpan_tampilan'])) {
    $nama_baru = $_POST['nama_sekolah'];
    $kegiatan_baru = $_POST['judul_kegiatan'];
    
    $file_logo = $_FILES['logo_baru']['name'];
    $tmp_logo = $_FILES['logo_baru']['tmp_name'];

    if (!empty($file_logo)) {
        if ($d_set['logo'] != 'logo_default.png' && file_exists("../assets/" . $d_set['logo'])) {
            unlink("../assets/" . $d_set['logo']);
        }
        $ekstensi = pathinfo($file_logo, PATHINFO_EXTENSION);
        $nama_logo_unik = "logo_" . time() . "." . $ekstensi;
        move_uploaded_file($tmp_logo, "../assets/" . $nama_logo_unik);
        
        mysqli_query($koneksi, "UPDATE pengaturan SET nama_sekolah='$nama_baru', judul_kegiatan='$kegiatan_baru', logo='$nama_logo_unik' WHERE id=1");
    } else {
        mysqli_query($koneksi, "UPDATE pengaturan SET nama_sekolah='$nama_baru', judul_kegiatan='$kegiatan_baru' WHERE id=1");
    }
    echo "<script>alert('Identitas Aplikasi Berhasil Diperbarui!'); window.location='tampilan.php';</script>";
}

// --- PROSES 2: SIMPAN BACKGROUND ---
if (isset($_POST['simpan_background'])) {
    $file_bg = $_FILES['foto_bg']['name'];
    $tmp_bg = $_FILES['foto_bg']['tmp_name'];

    if (!empty($file_bg)) {
        if (!empty($d_set['background']) && file_exists("../assets/" . $d_set['background'])) {
            unlink("../assets/" . $d_set['background']);
        }
        $ekstensi = pathinfo($file_bg, PATHINFO_EXTENSION);
        $nama_bg_unik = "bg_" . time() . "." . $ekstensi;
        move_uploaded_file($tmp_bg, "../assets/" . $nama_bg_unik);
        mysqli_query($koneksi, "UPDATE pengaturan SET background='$nama_bg_unik' WHERE id=1");
        echo "<script>alert('Background Berhasil Diganti!'); window.location='tampilan.php';</script>";
    } else {
        echo "<script>alert('Pilih file gambar dulu!');</script>";
    }
}

// --- PROSES 3: SIMPAN LABEL & VISIBILITAS ---
if (isset($_POST['simpan_label'])) {
    $lbl_hadir = $_POST['label_hadir'];
    $lbl_sakit = $_POST['label_sakit'];
    $lbl_izin  = $_POST['label_izin'];
    $lbl_halangan = $_POST['label_halangan'];
    $lbl_tidak = $_POST['label_tidak_hadir'];

    $t_hadir = isset($_POST['tampil_hadir']) ? 1 : 0;
    $t_sakit = isset($_POST['tampil_sakit']) ? 1 : 0;
    $t_izin  = isset($_POST['tampil_izin']) ? 1 : 0;
    $t_halangan = isset($_POST['tampil_halangan']) ? 1 : 0;
    $t_alpha = isset($_POST['tampil_alpha']) ? 1 : 0;

    $query_label = "UPDATE pengaturan SET 
                    label_hadir='$lbl_hadir', label_sakit='$lbl_sakit', label_izin='$lbl_izin', label_halangan='$lbl_halangan', label_tidak_hadir='$lbl_tidak',
                    tampil_hadir='$t_hadir', tampil_sakit='$t_sakit', tampil_izin='$t_izin', tampil_halangan='$t_halangan', tampil_alpha='$t_alpha'
                    WHERE id=1";
                    
    if(mysqli_query($koneksi, $query_label)){
        echo "<script>alert('Pengaturan Label & Tampilan Berhasil Disimpan!'); window.location='tampilan.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan!');</script>";
    }
}

// --- PROSES 4: SIMPAN NOTIFIKASI ---
if (isset($_POST['simpan_notif'])) {
    $pesan_sukses_baru = mysqli_real_escape_string($koneksi, $_POST['pesan_sukses']);
    $pesan_tolak_baru = mysqli_real_escape_string($koneksi, $_POST['pesan_tolak_cowok']);
    
    mysqli_query($koneksi, "UPDATE pengaturan SET pesan_sukses='$pesan_sukses_baru', pesan_tolak_cowok='$pesan_tolak_baru' WHERE id=1");
    echo "<script>alert('Kata-kata Notifikasi Berhasil Diubah!'); window.location='tampilan.php';</script>";
}

// --- PROSES 5: SIMPAN PROFIL ADMIN ---
if (isset($_POST['simpan_admin'])) {
    $nama_admin_baru = $_POST['nama_admin_baru'];
    $bagian_baru = $_POST['bagian_baru'];
    $nip_baru = $_POST['nip_baru']; 

    mysqli_query($koneksi, "UPDATE admin SET nama_admin='$nama_admin_baru', bagian='$bagian_baru', nip='$nip_baru' WHERE id_admin='$id_admin_login'");
    $_SESSION['nama_admin'] = $nama_admin_baru; 
    echo "<script>alert('Profil Admin Berhasil Diperbarui!'); window.location='tampilan.php';</script>";
}

// --- PERBAIKAN TOTAL PROSES 6: SIMPAN DURASI SEBAGAI TOTAL DETIK ---
if (isset($_POST['simpan_waktu'])) {
    $mulai = $_POST['jam_mulai'];
    $selesai = $_POST['jam_selesai'];
    
    $menit = (int)$_POST['durasi_menit'];
    $detik = (int)$_POST['durasi_detik'];
    
    // Simpan hasil konversi ke total detik (Contoh: 4 menit 30 detik = 270 detik)
    $total_detik_final = ($menit * 60) + $detik;
    
    mysqli_query($koneksi, "UPDATE pengaturan SET jam_mulai_izin='$mulai', jam_selesai_izin='$selesai', durasi_sholat='$total_detik_final' WHERE id=1");
    echo "<script>alert('Pengaturan Waktu & Durasi Berhasil Diatur!'); window.location='tampilan.php';</script>";
}

// Logika konversi balik dari database (Total Detik) ke Menit & Detik untuk form
$db_val_detik = isset($d_set['durasi_sholat']) ? (int)$d_set['durasi_sholat'] : 900; // Default 15 menit
$val_menit = floor($db_val_detik / 60);
$val_detik = $db_val_detik % 60;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Pengaturan Tampilan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        .preview-logo { width: 60px; height: 60px; object-fit: contain; border: 1px dashed #ccc; padding: 5px; border-radius: 5px; background: white; }
        .preview-bg { width: 100%; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd; margin-bottom: 10px; }
        .alert-keterangan { font-size: 0.75rem; line-height: 1.2; border-left: 3px solid; padding: 8px; margin-bottom: 15px; }
        .card-header { font-size: 0.9rem; padding: 10px 15px; }
        .b-hijau { border-left: 5px solid #198754; }
        .b-biru { border-left: 5px solid #0dcaf0; }
        .b-abu { border-left: 5px solid #6c757d; }
        .b-kuning { border-left: 5px solid #ffc107; }
        .b-merah { border-left: 5px solid #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-0">
            <div class="text-center p-4">
                <img src="../assets/<?php echo $d_set['logo']; ?>" width="80" style="border-radius:50%; object-fit:contain;">
                <h6 class="mt-2 fw-bold"><?php echo $d_set['nama_sekolah']; ?></h6>
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
            <h3 class="fw-bold mb-4">PENGATURAN TAMPILAN & SISTEM</h3>

            <div class="row g-3">
                
                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-school"></i> Identitas Aplikasi</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-2"><label class="fw-bold small">Nama Sekolah:</label><input type="text" name="nama_sekolah" class="form-control form-control-sm" value="<?php echo $d_set['nama_sekolah']; ?>" required></div>
                                <div class="mb-2"><label class="fw-bold small">Judul Kegiatan:</label><input type="text" name="judul_kegiatan" class="form-control form-control-sm" value="<?php echo $d_set['judul_kegiatan']; ?>" required></div>
                                <div class="mb-2"><label class="fw-bold small">Logo:</label><div class="d-flex align-items-center"><div class="me-2"><img src="../assets/<?php echo $d_set['logo']; ?>" class="preview-logo"></div><input type="file" name="logo_baru" class="form-control form-control-sm" accept="image/*"></div></div>
                                <button type="submit" name="simpan_tampilan" class="btn btn-primary w-100 fw-bold btn-sm mt-2">SIMPAN IDENTITAS</button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white fw-bold"><i class="fa-solid fa-clock"></i> Jam Akses & Timer Sholat</div>
                        <div class="card-body">
                            <div class="alert alert-danger alert-keterangan text-dark bg-light border-danger"><i class="fa-solid fa-circle-info"></i> Atur jadwal izin siswa dan durasi otomatis layar sholat.</div>
                            <form method="POST">
                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <label class="fw-bold small">Mulai Izin:</label>
                                        <input type="time" name="jam_mulai" class="form-control form-control-sm" value="<?php echo isset($d_set['jam_mulai_izin']) ? $d_set['jam_mulai_izin'] : '12:30'; ?>" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="fw-bold small">Selesai Izin:</label>
                                        <input type="time" name="jam_selesai" class="form-control form-control-sm" value="<?php echo isset($d_set['jam_selesai_izin']) ? $d_set['jam_selesai_izin'] : '13:30'; ?>" required>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-2">
                                    <label class="fw-bold small">Durasi Layar "Sholat Dulu":</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="durasi_menit" class="form-control" value="<?php echo $val_menit; ?>" required min="0" placeholder="Menit">
                                                <span class="input-group-text small">Mnt</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="durasi_detik" class="form-control" value="<?php echo $val_detik; ?>" required min="0" max="59" placeholder="Detik">
                                                <span class="input-group-text small">Dtk</span>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">*Layar sholat akan tertutup otomatis setelah waktu ini habis.</small>
                                </div>
                                <button type="submit" name="simpan_waktu" class="btn btn-danger w-100 fw-bold btn-sm mt-2">SIMPAN PENGATURAN</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-dark text-white fw-bold"><i class="fa-solid fa-tags"></i> Status & Tampilan</div>
                        <div class="card-body">
                            <div class="alert alert-info alert-keterangan"><i class="fa-solid fa-eye"></i> Centang kotak di kanan untuk menampilkan status tersebut di rekap HP.</div>
                            
                            <form method="POST">
                                <div class="mb-2 d-flex align-items-center">
                                    <input type="text" name="label_hadir" class="form-control form-control-sm b-hijau me-2" value="<?php echo isset($d_set['label_hadir']) ? $d_set['label_hadir'] : 'Sudah Sholat'; ?>" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="tampil_hadir" value="1" <?php if($d_set['tampil_hadir']) echo 'checked'; ?>></div>
                                </div>
                                <div class="mb-2 d-flex align-items-center">
                                    <input type="text" name="label_sakit" class="form-control form-control-sm b-biru me-2" value="<?php echo isset($d_set['label_sakit']) ? $d_set['label_sakit'] : 'Sakit'; ?>" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="tampil_sakit" value="1" <?php if($d_set['tampil_sakit']) echo 'checked'; ?>></div>
                                </div>
                                <div class="mb-2 d-flex align-items-center">
                                    <input type="text" name="label_izin" class="form-control form-control-sm b-abu me-2" value="<?php echo isset($d_set['label_izin']) ? $d_set['label_izin'] : 'Izin'; ?>" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="tampil_izin" value="1" <?php if($d_set['tampil_izin']) echo 'checked'; ?>></div>
                                </div>
                                <div class="mb-2 d-flex align-items-center">
                                    <input type="text" name="label_halangan" class="form-control form-control-sm b-kuning me-2" value="<?php echo isset($d_set['label_halangan']) ? $d_set['label_halangan'] : 'Halangan'; ?>" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="tampil_halangan" value="1" <?php if($d_set['tampil_halangan']) echo 'checked'; ?>></div>
                                </div>
                                <div class="mb-2 d-flex align-items-center">
                                    <input type="text" name="label_tidak_hadir" class="form-control form-control-sm b-merah me-2" value="<?php echo isset($d_set['label_tidak_hadir']) ? $d_set['label_tidak_hadir'] : 'Belum Sholat'; ?>" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="tampil_alpha" value="1" <?php if($d_set['tampil_alpha']) echo 'checked'; ?>></div>
                                </div>
                                <button type="submit" name="simpan_label" class="btn btn-dark w-100 fw-bold btn-sm mt-2">SIMPAN PENGATURAN</button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white fw-bold"><i class="fa-solid fa-image"></i> Background</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-2">
                                    <?php $bg_now = (!empty($d_set['background']) && file_exists("../assets/".$d_set['background'])) ? "../assets/".$d_set['background'] : "https://via.placeholder.com/300x100?text=No+BG"; ?>
                                    <img src="<?php echo $bg_now; ?>" class="preview-bg">
                                </div>
                                <div class="mb-2"><input type="file" name="foto_bg" class="form-control form-control-sm" accept="image/*" required></div>
                                <button type="submit" name="simpan_background" class="btn btn-info text-white w-100 fw-bold btn-sm mt-2">GANTI BACKGROUND</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-warning text-dark fw-bold"><i class="fa-solid fa-comment-dots"></i> Notifikasi Sistem</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-2">
                                    <label class="fw-bold small"><i class="fa-solid fa-check text-success"></i> Sukses Scan:</label>
                                    <textarea name="pesan_sukses" class="form-control form-control-sm" rows="2" required><?php echo $d_set['pesan_sukses']; ?></textarea>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold small"><i class="fa-solid fa-ban text-danger"></i> Tolak Laki-laki:</label>
                                    <textarea name="pesan_tolak_cowok" class="form-control form-control-sm" rows="2" required><?php echo !empty($d_set['pesan_tolak_cowok']) ? $d_set['pesan_tolak_cowok'] : 'Mohon maaf, Anda Laki-laki!'; ?></textarea>
                                </div>
                                <button type="submit" name="simpan_notif" class="btn btn-warning w-100 fw-bold btn-sm mt-2">SIMPAN PESAN</button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-bold"><i class="fa-solid fa-user-gear"></i> Profil Admin</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-2">
                                    <label class="fw-bold small">Admin:</label>
                                    <input type="text" name="nama_admin_baru" class="form-control form-control-sm" value="<?php echo $d_admin['nama_admin']; ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold small">Bagian:</label>
                                    <input type="text" name="bagian_baru" class="form-control form-control-sm" value="<?php echo isset($d_admin['bagian']) ? $d_admin['bagian'] : ''; ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold small">NIP:</label>
                                    <input type="number" name="nip_baru" class="form-control form-control-sm" value="<?php echo isset($d_admin['nip']) ? $d_admin['nip'] : ''; ?>">
                                </div>
                                <button type="submit" name="simpan_admin" class="btn btn-success w-100 fw-bold btn-sm mt-2">SIMPAN PROFIL</button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>