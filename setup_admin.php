
<?php
require_once "koneksi.php";

// Data akun admin khusus pengujian lokal
$nama = "Admin Sarpras";
$email = "admin@siperu.test";
$password = "12345678";

// Cek apakah akun sudah ada
$cek = $koneksi->prepare(
    "SELECT id_user FROM users WHERE email = ?"
);
$cek->execute([$email]);

if ($cek->fetch()) {
    exit("Email admin sudah terdaftar.");
}

// Enkripsi satu arah password (hash)
$hash = password_hash($password, PASSWORD_DEFAULT);

// Simpan akun dengan role admin
$sql = "INSERT INTO users
        (nama, email, password, role)
        VALUES (?, ?, ?, 'admin')";

$stmt = $koneksi->prepare($sql);
$stmt->execute([$nama, $email, $hash]);

echo "Akun Admin Sarpras berhasil dibuat!";
?>
