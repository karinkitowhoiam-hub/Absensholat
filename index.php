<?php
include "koneksi.php";
date_default_timezone_set('Asia/Makassar'); 

// --- PROTEKSI PHP (Pintu Belakang) ---
if (isset($_POST['idanggota']) && isset($_COOKIE['mode_sholat']) && $_COOKIE['mode_sholat'] == 'aktif') {
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}

// AMBIL PENGATURAN
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);

$sekolah_nama = $d_set['nama_sekolah'];
$judul_kegiatan = $d_set['judul_kegiatan']; 
$sekolah_logo = $d_set['logo']; 
$template_pesan_sukses = $d_set['pesan_sukses']; 
$bg_db = $d_set['background'];
$background_image = (!empty($bg_db) && file_exists("assets/$bg_db")) ? "assets/$bg_db" : "luar.jpg"; 

// AMBIL DURASI (Format database: menit.detik, misal 4.30)
$durasi_raw = isset($d_set['durasi_sholat']) ? $d_set['durasi_sholat'] : "15.00"; 

$pesan_notifikasi = "Menunggu Absensi Siswa..."; 
$kelas_notifikasi = ""; 

if (isset($_POST['idanggota'])) {
    $nisn = mysqli_real_escape_string($koneksi, $_POST['idanggota']);
    $tanggal_hari_ini = date('Y-m-d');
    $waktu_sekarang = date('H:i:s'); 

    // MODIFIKASI: Tambahkan syarat status_siswa = 'aktif' agar Alumni tertolak
    $cek_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn = '$nisn' AND status_siswa = 'aktif'");
    $data_siswa = mysqli_fetch_array($cek_siswa);

    if ($data_siswa) {
        $nama_siswa = $data_siswa['nama_siswa'];
        $cek_absen = mysqli_query($koneksi, "SELECT * FROM absensi WHERE nisn = '$nisn' AND tanggal = '$tanggal_hari_ini'");
        
        if (mysqli_num_rows($cek_absen) > 0) {
            $foto_db = $data_siswa['foto_siswa'];
            $jk = $data_siswa['jenis_kelamin'];
            $link_foto = ($jk == 'L') ? 'https://cdn-icons-png.flaticon.com/512/3011/3011270.png' : 'https://cdn-icons-png.flaticon.com/512/3011/3011276.png';
            if (!empty($foto_db) && file_exists("admin/foto_siswa/$foto_db")) { $link_foto = "admin/foto_siswa/$foto_db"; }
            $html_foto = "<img src='$link_foto' class='foto-popup shadow-sm'>";
            $pesan_notifikasi = $html_foto . "<br>Mohon maaf <br><b>$nama_siswa</b>,<br>Anda sudah absen sebelumnya.";
            $kelas_notifikasi = "error"; 
            echo '<audio autoplay><source src="assets/beep_warning.mp3" type="audio/mpeg"></audio>';
        } else {
            $simpan = mysqli_query($koneksi, "INSERT INTO absensi (nisn, tanggal, waktu_scan, status) VALUES ('$nisn', '$tanggal_hari_ini', '$waktu_sekarang', 'Hadir')");
            if ($simpan) {
                $foto_db = $data_siswa['foto_siswa'];
                $jk = $data_siswa['jenis_kelamin'];
                $link_foto = ($jk == 'L') ? 'https://cdn-icons-png.flaticon.com/512/3011/3011270.png' : 'https://cdn-icons-png.flaticon.com/512/3011/3011276.png';
                if (!empty($foto_db) && file_exists("admin/foto_siswa/$foto_db")) { $link_foto = "admin/foto_siswa/$foto_db"; }
                $html_foto = "<img src='$link_foto' class='foto-popup shadow'>";
                $pesan_jadi = str_replace(['{nama}', '{waktu}'], [$nama_siswa, $waktu_sekarang], $template_pesan_sukses);
                $pesan_notifikasi = $html_foto . "<br>" . $pesan_jadi;
                $kelas_notifikasi = "success"; 
                echo '<audio autoplay><source src="assets/beep_success.mp3" type="audio/mpeg"></audio>';
            } else {
                $pesan_notifikasi = "Error Database: Gagal menyimpan data.";
                $kelas_notifikasi = "error";
            }
        }
    } else {
        // Tampilkan pesan khusus jika NISN terdaftar tapi statusnya ALUMNI
        $cek_alumni = mysqli_query($koneksi, "SELECT * FROM siswa WHERE nisn = '$nisn' AND status_siswa = 'alumni'");
        if(mysqli_num_rows($cek_alumni) > 0) {
            $pesan_notifikasi = "<img src='https://cdn-icons-png.flaticon.com/512/4424/4424212.png' width='80' class='mb-3'><br>Akses Ditolak!<br>Siswa ini sudah berstatus <b>ALUMNI</b>.";
        } else {
            $pesan_notifikasi = "<img src='https://cdn-icons-png.flaticon.com/512/675/675564.png' width='80' class='mb-3'><br>Maaf, QR Code / NISN Tidak Dikenali.";
        }
        $kelas_notifikasi = "error";
        echo '<audio autoplay><source src="assets/beep_error.mp3" type="audio/mpeg"></audio>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi <?php echo $judul_kegiatan; ?> - <?php echo $sekolah_nama; ?></title>
    <style>
        :root {
            --bg-image: url('<?php echo $background_image; ?>'); 
            --color-text: #333333;
            --color-primary: #0d6efd; 
            --color-primary-dark: #0a58ca;
            --color-success: #198754; 
            --color-error: #dc3545;   
            --color-bg-input: #f8f9fa;
        }

        body { 
            margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), var(--bg-image);
            background-size: cover; background-position: center; background-attachment: fixed;
            overflow: hidden;
        }

        /* OVERLAY SHOLAT DULU */
        #overlaySholat {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #1a472a 0%, #2d5f3f 25%, #1a472a 50%, #0f3d2c 75%, #1a472a 100%);
            z-index: 9999; display: none; justify-content: center; align-items: center;
            flex-direction: column; overflow: hidden;
        }

        #overlaySholat .main-text {
            font-size: 11vw; font-weight: bold; color: #ffffff; text-align: center;
            text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.5), 0 0 20px rgba(255, 255, 255, 0.3);
            margin-bottom: 0;
            animation: pulse 2s ease-in-out infinite; z-index: 10; letter-spacing: 5px;
        }

        .sub-text {
            font-size: 1.8rem; color: #ffffff; font-weight: 500;
            margin-top: 10px; z-index: 10; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        #timerText {
            font-size: 5rem; color: #ffd700; font-weight: bold; margin-top: 10px;
            z-index: 10; text-shadow: 0 0 15px rgba(0,0,0,0.5);
            font-family: 'Courier New', Courier, monospace;
        }

        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        @keyframes twinkle { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }

        /* CONTAINER UTAMA */
        .container { 
            display: flex; width: 90%; max-width: 1100px; height: 600px; 
            background: #ffffff; box-shadow: 0 20px 50px rgba(0,0,0,0.5); 
            border-radius: 20px; overflow: hidden; 
        }

        .form-area { 
            flex: 1; padding: 40px; display: flex; flex-direction: column; justify-content: center;
            border-right: 1px solid #eee; background: white; z-index: 2;
        }

        .logo-container { text-align: center; margin-bottom: 10px; }
        #smkLogo { width: 80px; height: 80px; object-fit: contain; }
        
        h2 { color: var(--color-text); margin-bottom: 30px; font-size: 1.4rem; text-align: center; text-transform: uppercase; line-height: 1.5; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        
        input[type="text"] { 
            width: 100%; padding: 15px; background-color: var(--color-bg-input); 
            border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; 
        }
        input[type="text"]:focus { border-color: var(--color-primary); background-color: #fff; outline: none; }

        button[type="submit"] { 
            background-color: var(--color-primary); color: white; padding: 15px; 
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; 
            font-weight: bold; width: 100%; margin-top: 10px; transition: 0.3s; 
        }
        button[type="submit"]:hover { background-color: var(--color-primary-dark); }

        .link-group { display: flex; gap: 10px; margin-top: 15px; }
        .btn-link { 
            flex: 1; padding: 10px; text-align: center; text-decoration: none; 
            border-radius: 8px; font-size: 0.9rem; font-weight: bold; color: white; 
            display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; 
        }
        .btn-rekap { background-color: #6c757d; }
        .btn-admin { background-color: #212529; }
        .btn-sholat { background-color: #198754; }

        .display-area { 
            flex: 1.5; background-image: var(--bg-image); background-size: cover; 
            background-position: center; position: relative; 
            display: flex; justify-content: center; align-items: center; padding: 40px; 
        }
        .display-area::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); }

        .notification { 
            position: relative; padding: 40px; border-radius: 15px; 
            font-size: 1.5rem; font-weight: bold; color: white; text-align: center; 
            width: 100%; min-height: 200px; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px); border: 2px solid rgba(255,255,255,0.3); 
        }
        .foto-popup { width: 130px; height: 130px; object-fit: cover; border-radius: 50%; border: 5px solid white; margin-bottom: 20px; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        
        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .success { background-color: var(--color-success); border: 2px solid #146c43; }
        .error { background-color: var(--color-error); border: 2px solid #b02a37; }

        @media (max-width: 768px) { .container { flex-direction: column; height: auto; } .display-area { min-height: 300px; } }
    </style>
</head>
<body>
    <div id="overlaySholat">
        <div id="stars"></div>
        <svg style="position: absolute; opacity: 0.08; width: 600px;" viewBox="0 0 200 200">
            <path d="M100 20 L120 60 L160 60 L130 85 L145 125 L100 100 L55 125 L70 85 L40 60 L80 60 Z" fill="rgba(255, 215, 0, 0.3)" stroke="rgba(255, 215, 0, 0.5)" stroke-width="2"/>
        </svg>
        <h1 class="main-text">SHOLAT DULU</h1>
        <div class="sub-text">Absensi akan dibuka dalam waktu:</div>
        <div id="timerText">00:00</div>
    </div>

    <div class="container">
        <div class="form-area">
            <div class="logo-container">
                <img id="smkLogo" src="assets/<?php echo $sekolah_logo; ?>" alt="Logo Sekolah">
            </div>
            <h2>SELAMAT DATANG DI ABSENSI <?php echo $judul_kegiatan; ?><br><?php echo $sekolah_nama; ?></h2>
            
            <form id="absensiForm" method="POST" action="">
                <div class="form-group">
                    <label for="idanggota">Masukkan NISN Siswa:</label>
                    <input type="text" id="idanggota" name="idanggota" placeholder="Masukkan NISN atau Scan Kartu..." required autofocus autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>Sekolah:</label>
                    <input type="text" value="<?php echo $sekolah_nama; ?>" disabled style="background-color: #e9ecef;">
                </div>

                <button type="submit">Absen</button>
                
                <div class="link-group">
                    <a href="rekap.php" class="btn-link btn-rekap">Lihat Rekap</a>
                    <button type="button" onclick="toggleSholat()" class="btn-link btn-sholat">Waktunya Sholat</button>
                    <a href="admin/login.php" class="btn-link btn-admin">Admin</a>
                </div>
            </form>
        </div>
        
        <div class="display-area">
            <div id="notificationArea" class="notification <?php echo $kelas_notifikasi; ?>">
                <?php echo $pesan_notifikasi; ?>
            </div>
        </div>
    </div>

<script>
    let countdown;
    // MODIFIKASI: Baca data durasi format menit.detik
    const durasiRaw = "<?php echo $durasi_raw; ?>"; 

    function bicaraAbsenDibuka() {
        window.speechSynthesis.cancel(); 
        const pesan = new SpeechSynthesisUtterance("ya Absensi sholat telah dibuka Kembali");
        pesan.lang = "id-ID"; 
        window.speechSynthesis.speak(pesan);
    }

    function isSholatAktif() {
        return document.cookie.split('; ').find(row => row.startsWith('mode_sholat='))?.split('=')[1] === 'aktif';
    }

    window.onload = function() {
        const input = document.getElementById("idanggota");
        const form = document.getElementById("absensiForm");
        
        if (isSholatAktif()) {
            toggleSholat(true);
        } else {
            input.focus();
        }

        form.onsubmit = function(e) {
            if (isSholatAktif()) {
                e.preventDefault(); 
                return false;
            }
        };

        document.addEventListener("click", () => {
            if(!isSholatAktif()) { input.focus(); }
        });

        document.addEventListener("keydown", function(e) {
            if (e.ctrlKey && e.key === 'z') {
                if (isSholatAktif()) { toggleSholat(); }
            }
            if (e.key === "Enter" && isSholatAktif()) {
                e.preventDefault();
            }
        });

        const notif = document.getElementById("notificationArea");
        if (notif.classList.contains("success") || notif.classList.contains("error")) {
            setTimeout(function() { 
                if(!isSholatAktif()) window.location.href = 'index.php'; 
            }, 5000); 
        }
    };

    function toggleSholat(forceActive = false) {
        const overlay = document.getElementById('overlaySholat');
        const inputNISN = document.getElementById("idanggota");

        if (!forceActive && overlay.style.display === 'flex') {
            overlay.style.display = 'none';
            clearInterval(countdown);
            document.cookie = "mode_sholat=mati; path=/"; 
            inputNISN.disabled = false;
            inputNISN.focus();
            bicaraAbsenDibuka(); 
        } else {
            overlay.style.display = 'flex';
            generateStars();
            document.cookie = "mode_sholat=aktif; path=/"; 
            inputNISN.disabled = true;
            
            // MODIFIKASI: Logika pecah Menit dan Detik dari database
            let parts = durasiRaw.includes('-') ? durasiRaw.split('-') : durasiRaw.split('.');
            let mnt = parseInt(parts[0]) || 0;
            let dtk = parseInt(parts[1]) || 0;
            let waktuDetikTotal = parseInt("<?php echo $d_set['durasi_sholat']; ?>") || 900;

            updateDisplay(waktuDetikTotal);

            if(countdown) clearInterval(countdown);
            countdown = setInterval(function() {
                waktuDetikTotal--;
                updateDisplay(waktuDetikTotal);
                if (waktuDetikTotal <= 0) {
                    clearInterval(countdown);
                    toggleSholat(); 
                }
            }, 1000);
        }
    }

    function updateDisplay(detikTotal) {
        const m = Math.floor(detikTotal / 60);
        const s = detikTotal % 60;
        document.getElementById('timerText').innerText = (m < 10 ? "0"+m : m) + ":" + (s < 10 ? "0"+s : s);
    }

    function generateStars() {
        const starsContainer = document.getElementById('stars');
        if (starsContainer.innerHTML !== "") return; 
        for (let i = 0; i < 50; i++) {
            const star = document.createElement('div');
            star.style.position = 'absolute';
            star.style.width = '3px'; star.style.height = '3px';
            star.style.background = 'gold'; star.style.borderRadius = '50%';
            star.style.left = Math.random() * 100 + '%';
            star.style.top = Math.random() * 100 + '%';
            star.style.animation = `twinkle ${Math.random() * 3 + 2}s infinite`;
            starsContainer.appendChild(star);
        }
    }
</script>
</body>
</html>