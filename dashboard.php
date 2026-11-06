
<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

// Memastikan pengguna sudah login
if (!isset($_SESSION["id_user"])) {
    header("Location: ../login.php");
    exit;
}

// Hanya admin yang boleh mengakses
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman khusus admin.");
}

// Mengambil statistik dari database
$totalRuangan = $koneksi->query(
    "SELECT COUNT(*) FROM ruangan"
)->fetchColumn();

$totalMenunggu = $koneksi->query(
    "SELECT COUNT(*) FROM peminjaman
     WHERE status = 'menunggu'"
)->fetchColumn();

$totalDisetujui = $koneksi->query(
    "SELECT COUNT(*) FROM peminjaman
     WHERE status = 'disetujui'"
)->fetchColumn();

$totalDitolak = $koneksi->query(
    "SELECT COUNT(*) FROM peminjaman
     WHERE status = 'ditolak'"
)->fetchColumn();

$nama = htmlspecialchars(
    $_SESSION["nama"] ?? "Admin",
    ENT_QUOTES,
    "UTF-8"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIPERU</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6fb;
            color: #222;
        }

        header {
            background: #183153;
            color: white;
            padding: 24px 35px;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .statistik {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 25px 0;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .card h3 {
            margin-top: 0;
            color: #555;
            font-size: 16px;
        }

        .angka {
            font-size: 34px;
            font-weight: bold;
            color: #183153;
        }

        .menu {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 18px;
        }

        .menu a {
            background: #183153;
            color: white;
            padding: 14px 20px;
            text-decoration: none;
            border-radius: 8px;
        }

        .menu a:hover {
            background: #284e7b;
        }

        .logout {
            background: #b42318 !important;
        }
    </style>
</head>
<body>

<header>
    <h1>Dashboard Admin SIPERU</h1>
    <p>Selamat datang, <?= $nama ?>!</p>
</header>

<main>
    <h2>Ringkasan Sistem</h2>

    <section class="statistik">
        <div class="card">
            <h3>Total Ruangan</h3>
            <div class="angka"><?= (int) $totalRuangan ?></div>
        </div>

        <div class="card">
            <h3>Menunggu Persetujuan</h3>
            <div class="angka"><?= (int) $totalMenunggu ?></div>
        </div>

        <div class="card">
            <h3>Disetujui</h3>
            <div class="angka"><?= (int) $totalDisetujui ?></div>
        </div>

        <div class="card">
            <h3>Ditolak</h3>
            <div class="angka"><?= (int) $totalDitolak ?></div>
        </div>
    </section>

    <h2>Menu Admin</h2>

    <nav class="menu">
        <a href="peminjaman.php">Kelola Peminjaman</a>
        <a href="ruangan.php">Kelola Ruangan</a>
        <a href="../logout.php" class="logout">Logout</a>
    </nav>
</main>

</body>
</html>
