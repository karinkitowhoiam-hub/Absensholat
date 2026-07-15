<?php
session_start();
include "../koneksi.php";

// Set Timezone
date_default_timezone_set('Asia/Makassar'); 

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// --- AMBIL INFO SEKOLAH ---
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// ==========================================
// 1. LOGIKA BACKUP DATABASE
// ==========================================
if(isset($_POST['backup_database'])){
    
    $tables = array();
    $result = mysqli_query($koneksi, "SHOW TABLES");
    while($row = mysqli_fetch_row($result)){
        $tables[] = $row[0];
    }

    $return = "-- Backup Database Absen Sholat v2\n";
    $return .= "-- Waktu Backup: " . date("Y-m-d H:i:s") . "\n\n";
    
    foreach($tables as $table){
        $result = mysqli_query($koneksi, "SELECT * FROM ".$table);
        $num_fields = mysqli_num_fields($result);
        
        // Hapus tabel jika ada (biar bersih saat restore)
        $return .= "DROP TABLE IF EXISTS ".$table.";";
        
        $row2 = mysqli_fetch_row(mysqli_query($koneksi, "SHOW CREATE TABLE ".$table));
        $return .= "\n\n".$row2[1].";\n\n";
        
        while($row = mysqli_fetch_row($result)){
            $return .= "INSERT INTO ".$table." VALUES(";
            for($j=0; $j<$num_fields; $j++){
                $row[$j] = addslashes($row[$j]);
                // Perbaikan handling NULL values
                if(isset($row[$j])) { $return .= '"'.$row[$j].'"'; } else { $return .= 'NULL'; }
                if($j<($num_fields-1)) { $return .= ','; }
            }
            $return .= ");\n";
        }
        $return .= "\n\n\n";
    }

    // Download File
    $nama_file = 'backup_absen_sholat_'.date("Y-m-d_H-i-s").'.sql';
    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=".$nama_file);
    echo $return;
    exit; 
}

// ==========================================
// 2. LOGIKA RESTORE DATABASE
// ==========================================
$pesan_restore = "";
if(isset($_POST['restore_database'])){
    $file_upload = $_FILES['file_sql']['tmp_name'];
    
    if(!empty($file_upload)){
        $sql_content = file_get_contents($file_upload);
        
        // Perbaikan pembacaan query agar lebih akurat
        $sql_array = preg_split("/;+(?=(?:[^'\"]*['\"][^'\"]*['\"])*[^'\"]*$)/", $sql_content);
        
        mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");

        foreach($sql_array as $query){
            $query = trim($query);
            if(!empty($query)){
                mysqli_query($koneksi, $query);
            }
        }
        
        mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");
        $pesan_restore = "<div class='alert alert-success fw-bold'><i class='fa fa-check-circle'></i> Database Berhasil Dipulihkan!</div>";
    } else {
        $pesan_restore = "<div class='alert alert-danger fw-bold'>Gagal! Pilih file .sql terlebih dahulu.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Backup & Restore - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #ddd; position: fixed; width: 16.666667%; }
        .menu-item { display: block; padding: 15px 20px; color: #333; text-decoration: none; font-weight: bold; border-bottom: 1px solid #eee; }
        .menu-item:hover, .menu-item.active { background-color: #e9ecef; color: #0d47a1; border-left: 5px solid #0d47a1; }
        .content-area { margin-left: 16.666667%; }
        
        .card-backup { border-left: 5px solid #0d6efd; transition:0.3s; }
        .card-restore { border-left: 5px solid #dc3545; transition:0.3s; }
        .card-backup:hover, .card-restore:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
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
            <h2 class="fw-bold mb-4">UTILITAS SISTEM</h2>
            
            <?php echo $pesan_restore; ?>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm card-backup h-100">
                        <div class="card-body p-4 text-center">
                            <i class="fa-solid fa-cloud-arrow-down fa-4x text-primary mb-3"></i>
                            <h3 class="fw-bold">Backup Database</h3>
                            <p class="text-muted">
                                Unduh seluruh data sistem (Siswa, Absensi, Laporan) ke dalam satu file aman <b>(.sql)</b>. <br>
                                Simpan file ini di Flashdisk atau Google Drive.
                            </p>
                            <form method="POST">
                                <button type="submit" name="backup_database" class="btn btn-primary fw-bold px-4 py-2 w-100 shadow-sm">
                                    <i class="fa-solid fa-download"></i> DOWNLOAD DATABASE
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm card-restore h-100">
                        <div class="card-body p-4 text-center">
                            <i class="fa-solid fa-cloud-arrow-up fa-4x text-danger mb-3"></i>
                            <h3 class="fw-bold">Restore Database</h3>
                            <p class="text-muted">
                                Pulihkan data yang hilang atau error dengan mengupload file <b>.sql</b> cadangan Anda. <br>
                                <span class="text-danger fw-bold">Peringatan:</span> Data saat ini akan tertimpa!
                            </p>
                            
                            <button class="btn btn-danger fw-bold px-4 py-2 w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRestore">
                                <i class="fa-solid fa-upload"></i> UPLOAD & RESTORE
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info d-flex align-items-center shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info fa-2x me-3"></i>
                <div>
                    <strong>Saran Keamanan:</strong> Lakukan Backup Data secara rutin (misal: seminggu sekali atau setelah ada perubahan data besar) untuk menghindari kehilangan data jika komputer rusak.
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalRestore" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Restore</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body text-center">
                    <p class="text-danger fw-bold mb-3 fs-5">APAKAH ANDA YAKIN?</p>
                    <p>Proses ini akan <b>MENGHAPUS</b> data yang ada sekarang dan menggantinya dengan data dari file backup yang Anda pilih.</p>
                    
                    <div class="mb-3 text-start">
                        <label class="fw-bold small text-muted">Pilih File Backup (.sql):</label>
                        <input type="file" name="file_sql" class="form-control" accept=".sql" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="restore_database" class="btn btn-danger btn-sm fw-bold shadow-sm">YA, PULIHKAN DATA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
