<?php
session_start();
include "../koneksi.php";

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

$id_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$nisn_cetak = isset($_GET['nisn']) ? $_GET['nisn'] : ''; 
// Tambahan variabel untuk menangkap data dari tombol "Print Terpilih"
$nisn_pilih = isset($_GET['nisn_pilih']) ? $_GET['nisn_pilih'] : '';

// AMBIL INFO SEKOLAH
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo'];

// AMBIL JURUSAN
$jurusan_aktif = "-";
if($id_kelas != '') {
    $q_k = mysqli_query($koneksi, "SELECT jurusan FROM kelas WHERE id_kelas='$id_kelas'");
    $d_k = mysqli_fetch_array($q_k);
    $jurusan_aktif = $d_k['jurusan'] ?? "-";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Cetak Kartu Siswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
        /* RESET DASAR */
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #e0e0e0;
            margin: 0; 
            padding: 20px;
        }

        /* AREA KERTAS MONITOR */
        .page-area {
            width: 210mm; 
            /* PERBAIKAN: Gunakan min-height auto agar tidak memaksa tinggi kertas berlebih */
            min-height: auto; 
            background: white; 
            margin: auto;
            padding: 10mm; 
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }

        /* BARIS KARTU */
        .card-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 5mm; 
            page-break-inside: avoid; 
            break-inside: avoid;
        }

        /* --- UKURAN KARTU (7.8cm x 4.8cm) --- */
        .card-box {
            width: 78mm;  
            height: 48mm; 
            position: relative; background: white;
            border: 1px solid #ddd; border-radius: 8px; overflow: hidden;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        /* --- HIASAN CSS --- */
        .decoration-top {
            position: absolute; top: 0; left: 0; width: 100%; height: 9mm; 
            background: linear-gradient(90deg, #0d6efd 20%, #4facfe 100%); z-index: 1;
        }
        .decoration-top::after {
            content: ""; position: absolute; top: 0; right: 20mm; width: 5mm; height: 100%;
            background: rgba(255,255,255,0.3); transform: skewX(-20deg);
        }
        
        .decoration-bottom {
            position: absolute; bottom: -15mm; right: -15mm; 
            width: 35mm; height: 35mm; 
            background: #0d6efd; border-radius: 50%; opacity: 0.9; z-index: 0;
        }
        .decoration-bottom-small {
            position: absolute; bottom: -8mm; left: -8mm; 
            width: 20mm; height: 20mm; 
            background: #4facfe; border-radius: 50%; opacity: 0.8; z-index: 0;
        }

        /* --- HEADER --- */
        .card-header {
            position: absolute; top: 0; left: 0; width: 100%;
            display: flex; align-items: center; padding: 1.5mm 3mm; 
            z-index: 2; color: white;
        }
        .logo-sm { width: 6mm; height: 6mm; object-fit: contain; background: white; border-radius: 50%; padding: 1px; margin-right: 2mm; }
        .school-name { font-size: 7pt; font-weight: bold; line-height: 1; text-transform: uppercase; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); }

        /* LABEL KANAN DEPAN */
        .card-title-box {
            position: absolute; top: 8mm; right: 0;
            background: linear-gradient(90deg, #005bea, #00c6fb);
            color: white; font-size: 5.5pt; font-weight: bold;
            padding: 1mm 2.5mm; border-radius: 10mm 0 0 10mm;
            box-shadow: -2px 2px 5px rgba(0,0,0,0.1); z-index: 3;
        }

        /* LABEL KANAN BELAKANG */
        .card-title-box-back {
            position: absolute; top: 8mm; right: 0; max-width: 25mm; 
            background: linear-gradient(90deg, #005bea, #00c6fb);
            color: white; font-size: 5.5pt; font-weight: bold;
            padding: 1mm 2.5mm; border-radius: 10mm 0 0 10mm;
            box-shadow: -2px 2px 5px rgba(0,0,0,0.1); z-index: 3;
            text-align: right;
        }

        /* --- ISI DEPAN --- */
        .front-content {
            position: absolute; top: 15mm; 
            left: 3mm; right: 2mm;
            display: flex; align-items: flex-start; z-index: 2;
        }
        .foto-box {
            width: 20mm; height: 26mm; 
            flex-shrink: 0; 
            border: 2px solid #0d6efd; border-radius: 4px; 
            object-fit: cover; background: #eee; margin-right: 3mm;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        }
        .data-siswa { font-size: 6.5pt; color: #333; line-height: 1.1; flex-grow: 1; padding-top: 0px; }
        
        .data-row { display: flex; align-items: flex-start; margin-bottom: 2.5px; }
        .label-field { display: inline-block; min-width: 11mm; font-weight: 600; color: #555; }
        .separator { margin-right: 2px; }
        .value-field { flex: 1; word-wrap: break-word; }

        /* --- ISI BELAKANG (QR JUMBO) --- */
        .back-content {
            position: absolute; top: 10mm; 
            left: 0; width: 100%; bottom: 0;
            display: flex; justify-content: center; align-items: center; z-index: 2;
        }
        .qr-box {
            width: 35mm; height: 35mm; 
            border: 2px solid #333; padding: 1mm; background: white; border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* --- PENGATURAN PRINT --- */
        .no-print { text-align: center; margin-bottom: 20px; }
        
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: white; padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .page-area { width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
            /* PERBAIKAN: Hilangkan margin bottom pada baris terakhir untuk mencegah halaman kosong */
            .card-row:last-child { margin-bottom: 0 !important; }
            .card-box { border: 1px solid #999; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-weight:bold; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ CETAK KARTU</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-weight:bold; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">TUTUP</button>
        <br><br>
        <span style="color:#555; font-size:14px;"><i>*Pastikan pilih Layout: <b>Portrait</b> dan Paper Size: <b>A4</b> di pengaturan printer.</i></span>
    </div>

    <div class="page-area">
        <?php
        if($id_kelas != '' || !empty($nisn_pilih)) {
            
            // LOGIKA FILTER QUERY
            if(!empty($nisn_pilih)){
                // Jika mencentang beberapa siswa
                $arr_nisn = explode(',', $nisn_pilih);
                $filter_nisn = "'" . implode("','", $arr_nisn) . "'";
                $query_siswa = "SELECT * FROM siswa WHERE nisn IN ($filter_nisn) ORDER BY nama_siswa ASC";
            } elseif(!empty($nisn_cetak)) {
                // Jika print satuan
                $query_siswa = "SELECT * FROM siswa WHERE nisn='$nisn_cetak'";
            } else {
                // Jika print satu kelas
                $query_siswa = "SELECT * FROM siswa WHERE id_kelas='$id_kelas' AND status_siswa='aktif' ORDER BY nama_siswa ASC";
            }

            $q_siswa = mysqli_query($koneksi, $query_siswa);

            while($d = mysqli_fetch_array($q_siswa)){
                $nisn = $d['nisn'];
                $nama = strtoupper($d['nama_siswa']);
                $jk = ($d['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan';
                
                $foto_src = "https://cdn-icons-png.flaticon.com/512/3011/3011270.png"; 
                if($d['jenis_kelamin'] == 'P') $foto_src = "https://cdn-icons-png.flaticon.com/512/3011/3011276.png";
                if(!empty($d['foto_siswa']) && file_exists("foto_siswa/".$d['foto_siswa'])){
                    $foto_src = "foto_siswa/".$d['foto_siswa'];
                }
        ?>

        <div class="card-row">
            <div class="card-box">
                <div class="decoration-top"></div>
                <div class="decoration-bottom"></div>
                <div class="decoration-bottom-small"></div>
                <div class="card-header">
                    <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-sm">
                    <div class="school-name"><?php echo $sekolah_nama; ?></div>
                </div>
                <div class="card-title-box">KARTU ABSEN IBADAH SISWA</div>

                <div class="front-content">
                    <img src="<?php echo $foto_src; ?>" class="foto-box">
                    <div class="data-siswa">
                        <div class="data-row">
                            <span class="label-field">Nama</span><span class="separator">:</span>
                            <span class="value-field"><b><?php echo $nama; ?></b></span>
                        </div>
                        <div class="data-row">
                            <span class="label-field">NISN</span><span class="separator">:</span>
                            <span class="value-field"><?php echo $nisn; ?></span>
                        </div>
                        <div class="data-row">
                            <span class="label-field">Gender</span><span class="separator">:</span>
                            <span class="value-field"><?php echo $jk; ?></span>
                        </div>
                        <div class="data-row">
                            <span class="label-field">Jurusan</span><span class="separator">:</span>
                            <span class="value-field"><?php echo $jurusan_aktif; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="decoration-top"></div>
                <div class="decoration-bottom"></div>
                <div class="decoration-bottom-small" style="left:auto; right: 50mm;"></div>
                <div class="card-header">
                    <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-sm">
                    <div class="school-name"><?php echo $sekolah_nama; ?></div>
                </div>
                
                <div class="card-title-box-back">QR CODE SISWA</div>

                <div class="back-content">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=<?php echo $nisn; ?>" class="qr-box">
                </div>
            </div>
        </div>

        <?php }} else { echo "<center><h3>Data Tidak Ditemukan</h3></center>"; } ?>
    </div>
</body>
</html>