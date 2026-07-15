<?php
session_start();
include "../koneksi.php";

if ($_SESSION['status'] != "login") {
    header("Location: login.php");
    exit();
}

// 1. AMBIL DATA ADMIN UNTUK TANDA TANGAN
$id_admin_login = $_SESSION['id_admin'];
$q_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE id_admin='$id_admin_login'");
$d_admin = mysqli_fetch_array($q_admin);

$nama_admin_ttd = !empty($d_admin['nama_admin']) ? $d_admin['nama_admin'] : "..........................";
$bagian_admin_ttd = !empty($d_admin['bagian']) ? $d_admin['bagian'] : "Admin Keagamaan";
$nip_admin_ttd = !empty($d_admin['nip']) ? $d_admin['nip'] : "..........................";

$id_kelas_filter = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';

// 2. AMBIL INFO SEKOLAH & PENGATURAN
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo'];

// INFO FILTER JUDUL
$sub_judul = "SELURUH ANGKATAN";
if(!empty($id_kelas_filter)){
    $q_inf = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id_kelas='$id_kelas_filter'");
    $d_inf = mysqli_fetch_array($q_inf);
    $sub_judul = "JURUSAN " . strtoupper($d_inf['jurusan']) . " (LULUS TAHUN " . $d_inf['tahun_lulus'] . ")";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Cetak Laporan Alumni - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background-color: #f4f6f9; }
        .page-area { background: white; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 15mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: white; }
            .no-print, .btn, form { display: none !important; }
            .page-area { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
            
            body, table { font-size: 9pt !important; color: black; } 
            .table-bordered, .table-bordered td, .table-bordered th {
                border: 1px solid black !important;
                padding: 3px 5px !important; 
                line-height: 1.2 !important;
            }
            .table-dark th { background-color: #f8f9fa !important; color: black !important; border: 1px solid black !important; }

            .header-print { 
                text-align: center; border-bottom: 2px solid black; margin-bottom: 15px !important; padding-bottom: 10px !important; 
                display: flex; align-items: center; justify-content: center; gap: 20px;
            }
            .logo-print { width: 65px; height: 65px; object-fit: contain; }
            h3 { font-size: 16pt !important; margin: 0 !important; font-weight: bold; }
            h4 { font-size: 12pt !important; margin: 0 !important; font-weight: bold; }
            p.info-rekap { margin: 0 !important; font-size: 9pt !important; text-transform: uppercase; }

            .ttd-area { margin-top: 20px !important; }
            .ttd-space { height: 60px !important; }
            tr { page-break-inside: avoid; }
            
            /* Agar foto tetap muncul saat print */
            .foto-alumni-print { width: 35px; height: 45px; object-fit: cover; border: 1px solid #000; }
        }

        /* Tampilan Monitor */
        .header-print { display: flex; align-items: center; justify-content: center; gap: 20px; border-bottom: 2px solid black; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-print { width: 60px; height: 60px; object-fit: contain; }
        .foto-alumni-print { width: 35px; height: 45px; object-fit: cover; border: 1px solid #ddd; }
        .ttd-area { margin-top: 30px; }
        .ttd-space { height: 60px; }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> CETAK LAPORAN</button>
        <button onclick="window.close()" class="btn btn-danger"><i class="fa fa-times"></i> TUTUP</button>
    </div>

    <div class="page-area">
        <div class="header-print">
            <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-print">
            <div>
                <h3><?php echo strtoupper($sekolah_nama); ?></h3>
                <h4>LAPORAN REKAPITULASI PERFORMA IBADAH ALUMNI</h4>
                <p class="info-rekap"><?php echo $sub_judul; ?></p>
            </div>
        </div>

        <table class="table table-bordered align-middle">
            <thead class="table-dark text-center">
                <tr>
                    <th width="30">NO</th>
                    <th width="50">FOTO</th>
                    <th>NAMA LENGKAP SISWA</th>
                    <th width="90">NISN</th>
                    <th width="40">L/P</th>
                    <th width="80">THN LULUS</th>
                    <th width="100">PERFORMA</th>
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
                while($d = mysqli_fetch_array($q_siswa)){
                    $nisn_cek = $d['nisn'];
                    
                    // FOTO LOGIC
                    $foto = (!empty($d['foto_siswa']) && file_exists("foto_siswa/".$d['foto_siswa'])) ? "foto_siswa/".$d['foto_siswa'] : (($d['jenis_kelamin']=='L')?'https://cdn-icons-png.flaticon.com/512/3011/3011270.png':'https://cdn-icons-png.flaticon.com/512/3011/3011276.png');

                    // HITUNG PERFORMA
                    $q_range = mysqli_query($koneksi, "SELECT MIN(tanggal) as awal, MAX(tanggal) as akhir FROM absensi WHERE nisn='$nisn_cek'");
                    $d_range = mysqli_fetch_assoc($q_range);
                    
                    $persen_angka = 0;
                    if(!empty($d_range['awal'])){
                        $tgl_awal = new DateTime($d_range['awal']);
                        $tgl_akhir = new DateTime($d_range['akhir']);
                        $diff = $tgl_awal->diff($tgl_akhir)->days + 1; 

                        $q_hadir = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM absensi WHERE nisn='$nisn_cek' AND status='Hadir'");
                        $d_hadir = mysqli_fetch_assoc($q_hadir);
                        $persen_angka = round(($d_hadir['total'] / $diff) * 100, 1);
                    }
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="text-center p-1">
                        <img src="<?php echo $foto; ?>" class="foto-alumni-print">
                    </td>
                    <td class="ps-2"><?php echo strtoupper($d['nama_siswa']); ?></td>
                    <td class="text-center"><?php echo $d['nisn']; ?></td>
                    <td class="text-center"><?php echo $d['jenis_kelamin']; ?></td>
                    <td class="text-center"><?php echo $d['tahun_lulus']; ?></td>
                    <td class="text-center fw-bold"><?php echo $persen_angka; ?>%</td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="ttd-area">
            <div class="row">
                <div class="col-8">
                    <div class="box-keterangan">
                        <small><i>* Laporan ini dicetak secara otomatis melalui Sistem Absensi QR Code.</i></small><br>
                        <small><i>* Data performa dihitung berdasarkan rata-rata kehadiran selama masa aktif sekolah.</i></small>
                    </div>
                </div>
                <div class="col-4 text-center">
                    <p>Sungai Loban, <?php echo date('d F Y'); ?></p>
                    <p><?php echo $bagian_admin_ttd; ?>,</p>
                    <div class="ttd-space"></div>
                    <p><b><u><?php echo $nama_admin_ttd; ?></u></b></p>
                    <p>NIP. <?php echo $nip_admin_ttd; ?></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>