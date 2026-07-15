<?php
// Pastikan file koneksi.php ada di luar folder admin
include '../koneksi.php';

echo "<div style='font-family:sans-serif; padding:20px;'>";
echo "<h3>🛠️ Auto-Fix Database Skripsi (Tabel Kelas)</h3>";

// 1. Cek apakah kolom password_wali sudah ada atau belum
$cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM kelas LIKE 'password_wali'");
if (mysqli_num_rows($cek_kolom) == 0) {
    $tambah = mysqli_query($koneksi, "ALTER TABLE kelas ADD password_wali VARCHAR(50) DEFAULT '123456'");
    if ($tambah) {
        echo "✅ <span style='color:green;'>Kolom 'password_wali' berhasil ditambahkan ke database!</span><br>";
    } else {
        echo "❌ <span style='color:red;'>Gagal menambah kolom: " . mysqli_error($koneksi) . "</span><br>";
    }
} else {
    echo "✅ Kolom 'password_wali' sudah tersedia di database.<br>";
}

// 2. Set password default untuk data guru yang sudah ada
$update = mysqli_query($koneksi, "UPDATE kelas SET password_wali = '123456' WHERE password_wali IS NULL OR password_wali = ''");
if ($update) {
    echo "✅ Semua password Wali Kelas berhasil diatur ulang menjadi: <b>123456</b><br>";
} else {
    echo "❌ <span style='color:red;'>Gagal mengupdate password: " . mysqli_error($koneksi) . "</span><br>";
}

// 3. Tampilkan data yang ada di database sekarang
echo "<hr><h4>Daftar Akses Login Wali Kelas Saat Ini:</h4>";
$query = mysqli_query($koneksi, "SELECT * FROM kelas");
echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%; text-align:left;'>";
echo "<tr style='background-color:#f2f2f2;'><th>Nama Kelas</th><th>Nama Wali</th><th>Username (NIP Wali)</th><th>Password Wali</th></tr>";

while ($row = mysqli_fetch_array($query)) {
    echo "<tr>";
    echo "<td>" . (isset($row['nama_kelas']) ? $row['nama_kelas'] : '<i style="color:red">Kolom tidak ada</i>') . "</td>";
    echo "<td>" . (isset($row['nama_wali']) ? $row['nama_wali'] : '<i style="color:red">Kolom tidak ada</i>') . "</td>";
    
    // NIP Wali adalah Username yang akan dipakai login
    $nip = isset($row['nip_wali']) ? $row['nip_wali'] : '';
    if (empty($nip)) {
        echo "<td><b style='color:red;'>KOSONG (Harus diisi di sistem)</b></td>";
    } else {
        echo "<td><b style='color:blue;'>" . $nip . "</b></td>";
    }
    
    echo "<td>" . (isset($row['password_wali']) ? $row['password_wali'] : '-') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<br><br><a href='login.php' style='padding:10px 15px; background:blue; color:white; text-decoration:none; border-radius:5px;'>Kembali ke Halaman Login</a>";
echo "</div>";
?>