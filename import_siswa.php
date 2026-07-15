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

// --- PROSES IMPORT ---
$pesan = "";
$tipe = "";

if (isset($_POST['import_data'])) {
    $id_kelas_target = $_POST['id_kelas'];
    
    // Cek File
    $fileName = $_FILES['file_csv']['name'];
    $fileTmp = $_FILES['file_csv']['tmp_name'];
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);

    if ($ext == 'csv') {
        $file = fopen($fileTmp, "r");
        $berhasil = 0;
        $gagal = 0;
        $baris = 0;

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $baris++;
            // Lewati baris pertama (Header Judul)
            if($baris == 1) continue; 

            // Ambil Data dari CSV
            // Urutan: [0] NISN, [1] NAMA, [2] JK
            $nisn = mysqli_real_escape_string($koneksi, $data[0]);
            $nama = mysqli_real_escape_string($koneksi, $data[1]);
            $jk   = strtoupper(mysqli_real_escape_string($koneksi, $data[2])); // Paksa Huruf Besar

            // Validasi Data Kosong
            if(!empty($nisn) && !empty($nama)){
                // Cek apakah NISN sudah ada?
                $cek = mysqli_query($koneksi, "SELECT nisn FROM siswa WHERE nisn='$nisn'");
                if(mysqli_num_rows($cek) == 0){
                    // MODIFIKASI: Tambahkan status_siswa='aktif' agar langsung bisa absen
                    $masuk = mysqli_query($koneksi, "INSERT INTO siswa (nisn, nama_siswa, jenis_kelamin, id_kelas, status_siswa) VALUES ('$nisn', '$nama', '$jk', '$id_kelas_target', 'aktif')");
                    if($masuk) $berhasil++;
                    else $gagal++;
                } else {
                    // Jika sudah ada, lewati
                    $gagal++; 
                }
            }
        }
        fclose($file);
        
        $pesan = "Proses Selesai! <br>Berhasil: <b>$berhasil</b> siswa. <br>Gagal/Duplikat: <b>$gagal</b> siswa.";
        $tipe = "success";
    } else {
        $pesan = "Format salah! Harap upload file .CSV";
        $tipe = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Import Siswa - <?php echo $sekolah_nama; ?></title>
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

        <div class="col-md-10 p-4">
            <h3 class="fw-bold mb-4"><i class="fa-solid fa-file-import"></i> IMPORT DATA SISWA</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white fw-bold">Upload File CSV</div>
                        <div class="card-body">
                            
                            <?php if(!empty($pesan)) { ?>
                                <div class="alert alert-<?php echo $tipe; ?>"><?php echo $pesan; ?></div>
                            <?php } ?>

                            <div class="alert alert-info small shadow-sm">
                                <strong>Panduan:</strong>
                                <ol class="mb-0 ps-3">
                                    <li>Download template CSV di samping.</li>
                                    <li>Isi data siswa (NISN, Nama, L/P) menggunakan Excel.</li>
                                    <li>Simpan file dengan format <b>CSV (Comma Delimited)</b>.</li>
                                    <li>Pilih Kelas Aktif, upload file, dan klik Import.</li>
                                </ol>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="fw-bold">Pilih Kelas Tujuan (Aktif):</label>
                                    <select name="id_kelas" class="form-select" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php 
                                        // MODIFIKASI: Hanya menampilkan kelas yang BELUM lulus (Aktif)
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
                                    <label class="fw-bold">File CSV:</label>
                                    <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="data_kelas.php" class="btn btn-secondary w-50">Kembali</a>
                                    <button type="submit" name="import_data" class="btn btn-success w-50 fw-bold">IMPORT SEKARANG</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-dark text-white fw-bold">Template Data</div>
                        <div class="card-body text-center">
                            <p>Gunakan template ini agar format data sesuai sistem.</p>
                            <a href="template_siswa.csv" download class="btn btn-outline-dark fw-bold shadow-sm">
                                <i class="fa-solid fa-download"></i> DOWNLOAD TEMPLATE CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>