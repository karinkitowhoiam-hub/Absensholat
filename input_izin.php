<?php
session_start();
include "koneksi.php"; 

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 
$tanggal_hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i:s');

// --- AMBIL PENGATURAN WAKTU DARI DB ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);

$jam_mulai = isset($d_set['jam_mulai_izin']) ? $d_set['jam_mulai_izin'] : '12:30:00';
$jam_selesai = isset($d_set['jam_selesai_izin']) ? $d_set['jam_selesai_izin'] : '13:30:00';

// --- CEK APAKAH DILUAR JAM IZIN? ---
$is_closed = false;
if ($jam_sekarang < $jam_mulai || $jam_sekarang > $jam_selesai) {
    $is_closed = true;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Izin / Halangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #e3f2fd; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header-title { color: #0d47a1; font-weight: 800; }
        .btn-pilihan { width: 100%; text-align: left; margin-bottom: 10px; border-radius: 10px; padding: 15px; border: 1px solid #ddd; background: white; transition: 0.3s; }
        .btn-pilihan:hover { background-color: #f1f8ff; border-color: #0d47a1; }
        .btn-check:checked + .btn-pilihan { background-color: #0d47a1; color: white; border-color: #0d47a1; }
        .foto-profil {
            width: 120px; height: 120px; object-fit: cover;
            border-radius: 50%; border: 4px solid #0d47a1; margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <?php if($is_closed) { ?>
                <div class="card card-custom p-5 text-center">
                    <div class="mb-4">
                        <i class="fa-solid fa-store-slash fa-5x text-secondary"></i>
                    </div>
                    <h2 class="fw-bold text-danger">PORTAL DITUTUP</h2>
                    <p class="text-muted">
                        Mohon maaf, input izin hanya dibuka pada pukul: <br>
                        <span class="badge bg-primary fs-5 mt-2"><?php echo substr($jam_mulai, 0, 5); ?> - <?php echo substr($jam_selesai, 0, 5); ?> WITA</span>
                    </p>
                    <hr>
                    <small>Sekarang Pukul: <b><?php echo date('H:i'); ?> WITA</b></small>
                </div>
            <?php exit(); } ?>

            <?php
            // VARIABEL PESAN
            $pesan = "";
            $tipe_pesan = ""; 
            $data_siswa = null;

            // --- PROSES 1: CEK SISWA ---
            if(isset($_POST['cek_siswa'])) {
                $id_kelas = $_POST['id_kelas'];
                $nisn = $_POST['nisn'];

                // MODIFIKASI: Hanya cari siswa yang berstatus 'aktif'
                $q_cek = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn='$nisn' AND id_kelas='$id_kelas' AND status_siswa='aktif'");
                
                if(mysqli_num_rows($q_cek) > 0) {
                    $temp_data = mysqli_fetch_array($q_cek);

                    if($temp_data['jenis_kelamin'] == 'L') {
                        $teks_tolak = !empty($d_set['pesan_tolak_cowok']) ? $d_set['pesan_tolak_cowok'] : "Mohon maaf, Anda Laki-laki!";
                        $pesan = $teks_tolak . " ⛔";
                        $tipe_pesan = "warning";
                        $data_siswa = null; 
                    } else {
                        $data_siswa = $temp_data;
                    }
                } else {
                    // Cek apakah sebenarnya dia alumni?
                    $q_alumni = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn='$nisn' AND status_siswa='alumni'");
                    if(mysqli_num_rows($q_alumni) > 0) {
                        $pesan = "Akses Ditolak! Anda sudah tercatat sebagai <b>ALUMNI</b>.";
                    } else {
                        $pesan = "Data tidak ditemukan! Pastikan Kelas dan NISN benar.";
                    }
                    $tipe_pesan = "danger";
                }
            }

            // --- PROSES 2: SIMPAN ALASAN ---
            if(isset($_POST['kirim_alasan'])) {
                $nisn_simpan = $_POST['nisn_hidden'];
                $pilihan_user = $_POST['alasan']; 
                
                $status_fix = "";
                if ($pilihan_user == 'Halangan') {
                    $status_fix = 'Halangan';
                } elseif ($pilihan_user == 'Sakit') {
                    $status_fix = 'Sakit';
                } elseif ($pilihan_user == 'Tidak Bawa Mukena') {
                    $status_fix = 'Tidak Sholat';
                }
                
                $cek_absen = mysqli_query($koneksi, "SELECT * FROM absensi WHERE nisn='$nisn_simpan' AND tanggal='$tanggal_hari_ini'");
                
                if(mysqli_num_rows($cek_absen) > 0) {
                    $update = mysqli_query($koneksi, "UPDATE absensi SET status='$status_fix', waktu_scan='$jam_sekarang' WHERE nisn='$nisn_simpan' AND tanggal='$tanggal_hari_ini'");
                    if($update){
                        $pesan = "Laporan terkirim! Status tercatat: <b>$status_fix</b>";
                        $tipe_pesan = "success";
                    }
                } else {
                    $insert = mysqli_query($koneksi, "INSERT INTO absensi (nisn, tanggal, waktu_scan, status) VALUES ('$nisn_simpan', '$tanggal_hari_ini', '$jam_sekarang', '$status_fix')");
                    if($insert){
                        $pesan = "Laporan terkirim! Status tercatat: <b>$status_fix</b>";
                        $tipe_pesan = "success";
                    }
                }
                $data_siswa = null; 
            }
            ?>

            <div class="text-center mb-4">
                <h3 class="header-title"><i class="fa-solid fa-hand-holding-heart"></i> Laporan Ibadah</h3>
                <p class="text-muted">Input Izin / Halangan Sholat / Halangan Ibadah</p>
                <span class="badge bg-info text-dark"><i class="fa fa-clock"></i> Buka: <?php echo substr($jam_mulai, 0, 5); ?> - <?php echo substr($jam_selesai, 0, 5); ?></span>
            </div>

            <?php if(!empty($pesan)) { ?>
                <div class="alert alert-<?php echo $tipe_pesan; ?> alert-dismissible fade show text-center" role="alert">
                    <?php echo $pesan; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <div class="card card-custom p-4">
                
                <?php if($data_siswa == null) { ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="fw-bold mb-2">Pilih Kelas Aktif:</label>
                        <select name="id_kelas" class="form-select form-select-lg" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php 
                            // MODIFIKASI: Hanya menampilkan kelas yang BELUM lulus
                            $q_k = mysqli_query($koneksi, "SELECT * FROM kelas WHERE tahun_lulus IS NULL ORDER BY nama_kelas ASC");
                            while($rk = mysqli_fetch_array($q_k)){
                                $nm = $rk['nama_kelas'];
                                if(!empty($rk['jurusan'])) $nm .= " - " . $rk['jurusan'];
                                echo "<option value='$rk[id_kelas]'>$nm</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold mb-2">Masukkan NISN:</label>
                        <input type="number" name="nisn" class="form-control form-select-lg" placeholder="Contoh: 0054xxx" required>
                    </div>

                    <button type="submit" name="cek_siswa" class="btn btn-primary w-100 fw-bold py-3 rounded-pill mb-2 shadow-sm">
                        CEK DATA SISWA <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <?php } else { ?>
                
                <div class="text-center mb-3">
                    <?php 
                        $foto = $data_siswa['foto_siswa']; 
                        if(empty($foto) || !file_exists("admin/foto_siswa/$foto")){ 
                            $link_foto = "https://cdn-icons-png.flaticon.com/512/4140/4140047.png"; 
                        } else {
                            $link_foto = "admin/foto_siswa/$foto"; 
                        }
                    ?>
                    <img src="<?php echo $link_foto; ?>" class="foto-profil" alt="Foto Siswa">
                    
                    <h5 class="fw-bold mb-0 text-primary"><?php echo $data_siswa['nama_siswa']; ?></h5>
                    <span class="badge bg-secondary rounded-pill mb-2"><?php echo $data_siswa['nisn']; ?></span>
                    <br>
                    <small class="text-success"><i class="fa-solid fa-check-circle"></i> Data Terverifikasi (Aktif)</small>
                </div>
                
                <hr>
                <p class="fw-bold text-center mb-3">Mengapa tidak ikut sholat berjamaah?</p>

                <form method="POST">
                    <input type="hidden" name="nisn_hidden" value="<?php echo $data_siswa['nisn']; ?>">
                    
                    <input type="radio" class="btn-check" name="alasan" id="opt1" value="Halangan" required>
                    <label class="btn btn-pilihan" for="opt1">
                        <i class="fa-solid fa-venus text-danger me-2"></i> <b>Sedang Halangan / Haid</b>
                        <br><small class="text-muted ms-4">Saya sedang dalam masa menstruasi</small>
                    </label>

                    <input type="radio" class="btn-check" name="alasan" id="opt2" value="Tidak Bawa Mukena">
                    <label class="btn btn-pilihan" for="opt2">
                        <i class="fa-solid fa-shirt text-warning me-2"></i> <b>Tidak Bawa Mukena</b>
                        <br><small class="text-muted ms-4">Lupa membawa perlengkapan sholat</small>
                    </label>

                    <input type="radio" class="btn-check" name="alasan" id="opt3" value="Sakit">
                    <label class="btn btn-pilihan" for="opt3">
                        <i class="fa-solid fa-user-nurse text-success me-2"></i> <b>Sedang Sakit</b>
                        <br><small class="text-muted ms-4">Kondisi badan tidak memungkinkan</small>
                    </label>

                    <div class="d-flex gap-2 mt-4">
                        <a href="input_izin.php" class="btn btn-secondary w-50 py-2">Batal</a>
                        <button type="submit" name="kirim_alasan" class="btn btn-danger w-50 py-2 fw-bold shadow-sm">Kirim Laporan</button>
                    </div>
                </form>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

</body>
</html>