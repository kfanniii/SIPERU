
<?php
session_start();
require_once "koneksi.php";

// Hanya peminjam yang dapat mengakses
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "peminjam") {
    header("Location: admin/dashboard.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];
$nama = $_SESSION["nama"] ?? "Peminjam";

function aman($teks) {
    return htmlspecialchars(
        (string) $teks,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Mengambil seluruh riwayat peminjaman pengguna
$sql = "SELECT
            p.id_peminjaman,
            r.nama_ruangan,
            r.lokasi,
            p.tanggal,
            p.jam_mulai,
            p.jam_selesai,
            p.keperluan,
            p.status
        FROM peminjaman p
        INNER JOIN ruangan r
            ON p.id_ruangan = r.id_ruangan
        WHERE p.id_user = ?
        ORDER BY p.id_peminjaman DESC";

$stmt = $koneksi->prepare($sql);
$stmt->execute([$idUser]);
$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik dari data pengguna
$total = count($riwayat);
$menunggu = 0;
$disetujui = 0;
$ditolak = 0;

foreach ($riwayat as $data) {
    if ($data["status"] === "menunggu") {
        $menunggu++;
    } elseif ($data["status"] === "disetujui") {
        $disetujui++;
    } elseif ($data["status"] === "ditolak") {
        $ditolak++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Riwayat Peminjaman | SIPERU</title>

    <link rel="stylesheet"
          href="assets/css/riwayat.css">
</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <header class="page-header">

        <div class="header-content">
            <div class="header-icon">▤</div>

            <div>
                <h1>Riwayat Peminjaman</h1>
                <p>
                    Pantau status dan seluruh
                    pengajuan peminjaman ruangan Anda.
                </p>
            </div>
        </div>

        <a href="peminjam/dashboard.php"
           class="back-button">
            ← Kembali ke Dashboard
        </a>

    </header>

    <!-- RINGKASAN -->
    <section class="summary-grid">

        <div class="summary-card">
            <div class="summary-icon total-icon">▤</div>
            <div>
                <p>Total Pengajuan</p>
                <h2><?= $total ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon pending-icon">◷</div>
            <div>
                <p>Menunggu</p>
                <h2><?= $menunggu ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon approved-icon">✓</div>
            <div>
                <p>Disetujui</p>
                <h2><?= $disetujui ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon rejected-icon">×</div>
            <div>
                <p>Ditolak</p>
                <h2><?= $ditolak ?></h2>
            </div>
        </div>

    </section>

    <!-- TABEL RIWAYAT -->
    <section class="history-card">

        <div class="history-heading">
            <div>
                <h2>Daftar Pengajuan</h2>
                <p>
                    Riwayat peminjaman atas nama
                    <?= aman($nama) ?>
                </p>
            </div>

            <a href="ajukan_peminjaman.php"
               class="new-button">
                + Ajukan Peminjaman
            </a>
        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Ruangan</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Keperluan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($riwayat)): ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            <div class="empty-icon">▤</div>
                            <h3>Belum Ada Riwayat</h3>
                            <p>
                                Anda belum pernah mengajukan
                                peminjaman ruangan.
                            </p>
                            <a href="ruangan.php">
                                Lihat Daftar Ruangan
                            </a>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($riwayat as $index => $data): ?>

                        <?php
                        $status = $data["status"];

                        $kelasStatus = [
                            "menunggu" => "pending",
                            "disetujui" => "approved",
                            "ditolak" => "rejected"
                        ][$status] ?? "pending";

                        $labelStatus = [
                            "menunggu" => "Menunggu",
                            "disetujui" => "Disetujui",
                            "ditolak" => "Ditolak"
                        ][$status] ?? $status;
                        ?>

                        <tr>

                            <td class="number-cell">
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <strong class="room-name">
                                    <?= aman($data["nama_ruangan"]) ?>
                                </strong>

                                <span class="room-location">
                                    <?= aman($data["lokasi"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= aman(date(
                                    "d/m/Y",
                                    strtotime($data["tanggal"])
                                )) ?>
                            </td>

                            <td class="time-cell">
                                <?= aman(substr(
                                    $data["jam_mulai"], 0, 5
                                )) ?>
                                -
                                <?= aman(substr(
                                    $data["jam_selesai"], 0, 5
                                )) ?>
                            </td>

                            <td class="purpose-cell">
                                <?= aman($data["keperluan"]) ?>
                            </td>

                            <td>
                                <span class="status <?= aman($kelasStatus) ?>">
                                    <?= aman($labelStatus) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div class="table-footer">
            Menampilkan <?= $total ?> pengajuan peminjaman.
        </div>

    </section>

    <footer class="footer">
        © 2026 SIPERU — Sistem Informasi Peminjaman Ruangan
    </footer>

</div>

</body>
</html>
