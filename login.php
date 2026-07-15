<?php
session_start();
include "../koneksi.php";

$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id=1");
$d_set = mysqli_fetch_array($q_set);
$sekolah_nama = $d_set['nama_sekolah'];
$sekolah_logo = $d_set['logo']; 

// Ambil Background
$bg_db = $d_set['background'];
$background_image = (!empty($bg_db) && file_exists("../assets/$bg_db")) ? "../assets/$bg_db" : "../luar.jpg"; 

// --- LOGIKA MULTI-LOGIN (AUTO-DETECT ADMIN & WALI KELAS) ---
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // 1. Cek tabel admin terlebih dahulu
    $cek_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE username='$username' AND password='$password'");
    
    if (mysqli_num_rows($cek_admin) > 0) {
        $data = mysqli_fetch_array($cek_admin);
        $_SESSION['status'] = "login";
        $_SESSION['role'] = "admin"; 
        $_SESSION['nama_admin'] = $data['nama_admin'];
        $_SESSION['id_admin'] = $data['id_admin'];
        header("Location: index.php");
        exit();
    } else {
        // 2. Jika bukan admin, cek tabel kelas (NIP Wali)
        $cek_wali = mysqli_query($koneksi, "SELECT * FROM kelas WHERE nip_wali='$username' AND password_wali='$password'");
        
        if (mysqli_num_rows($cek_wali) > 0) {
            $data_wali = mysqli_fetch_array($cek_wali);
            $_SESSION['status'] = "login";
            $_SESSION['role'] = "wali_kelas"; 
            $_SESSION['nama_admin'] = $data_wali['nama_wali']; 
            $_SESSION['id_kelas_wali'] = $data_wali['id_kelas']; 
            
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?pesan=gagal");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System - <?php echo $sekolah_nama; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background-image: url('<?php echo $background_image; ?>');
            background-size: cover;
            background-position: center;
            background-color: rgba(0,0,0,0.5);
            background-blend-mode: overlay;
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Segoe UI', sans-serif;
        }
        .card-login {
            width: 100%; max-width: 400px; border: none; border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3); overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
        }
        .card-header-login { background: transparent; padding: 30px 20px 10px 20px; text-align: center; border-bottom: none; }
        .logo-login { width: 80px; height: 80px; object-fit: contain; margin-bottom: 15px; }
        .card-body { padding: 30px; background: transparent; }
        .form-control { border-radius: 8px; padding: 12px; background-color: #f8f9fa; }
        .form-control:focus { background-color: white; border-color: #0d6efd; box-shadow: none; }
        .btn-login { width: 100%; padding: 12px; border-radius: 8px; font-weight: bold; font-size: 1rem; }

        /* --- TRIK CSS: SEMBUNYIKAN TOMBOL DI HP --- */
        @media (max-width: 768px) {
            .tombol-kembali-pc {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="card card-login mx-3">
        <div class="card-header-login">
            <img src="../assets/<?php echo $sekolah_logo; ?>" class="logo-login" alt="Logo Sekolah">
            <h5 class="fw-bold text-dark mb-0">PORTAL LOGIN</h5>
            <small class="text-muted"><?php echo $sekolah_nama; ?></small>
        </div>
        
        <div class="card-body">
            <?php if(isset($_GET['pesan']) && $_GET['pesan'] == "gagal"){ ?>
                <div class="alert alert-danger text-center p-2 mb-3 small" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> NIP/Username atau Password Salah!
                </div>
            <?php } ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Username / NIP Wali</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan Username / NIP" required autofocus autocomplete="off" value="">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold small">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan Password" required autocomplete="new-password" value="">
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-login">MASUK SEKARANG</button>
            </form>
            
            <div class="text-center mt-3 tombol-kembali-pc">
                <a href="../index.php" class="text-decoration-none small text-secondary">← Kembali ke Halaman Utama Absensi</a>
            </div>
        </div>
    </div>

</body>
</html>