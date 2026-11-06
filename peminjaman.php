
<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

// Hanya admin yang boleh mengakses
if (!isset($_SESSION["id_user"])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman khusus admin.");
}

// Token keamanan form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$pesan = $_SESSION["pesan_admin"] ?? "";
unset($_SESSION["pesan_admin"]);

// PROSES PERSETUJUAN ATAU PENOLAKAN
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $id = filter_input(
        INPUT_POST,
        "id_peminjaman",
        FILTER_VALIDATE_INT
    );

    $aksi = $_POST["aksi"] ?? "";

    if (!$id || !in_array($aksi, ["setujui", "tolak"], true)) {
        exit("Data pengajuan tidak valid.");
    }

    try {
        $koneksi->beginTransaction();

        // Kunci pengajuan saat diproses
        $stmt = $koneksi->prepare(
            "SELECT * FROM peminjaman
             WHERE id_peminjaman = ?
             FOR UPDATE"
        );

        $stmt->execute([$id]);
        $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pengajuan || $pengajuan["status"] !== "menunggu") {
            throw new Exception(
                "Pengajuan sudah diproses atau tidak ditemukan."
            );
        }

        if ($aksi === "setujui") {

            // Kunci ruangan
            $kunci = $koneksi->prepare(
                "SELECT id_ruangan FROM ruangan
                 WHERE id_ruangan = ?
                 FOR UPDATE"
            );

            $kunci->execute([$pengajuan["id_ruangan"]]);

            if (!$kunci->fetch()) {
                throw new Exception("Ruangan tidak ditemukan.");
            }

            // Cek jadwal bentrok
            $cek = $koneksi->prepare(
                "SELECT COUNT(*)
                 FROM peminjaman
                 WHERE id_ruangan = ?
                   AND tanggal = ?
                   AND status = 'disetujui'
                   AND id_peminjaman <> ?
                   AND jam_mulai < ?
                   AND jam_selesai > ?"
            );

            $cek->execute([
                $pengajuan["id_ruangan"],
                $pengajuan["tanggal"],
                $id,
                $pengajuan["jam_selesai"],
                $pengajuan["jam_mulai"]
            ]);

            if ($cek->fetchColumn() > 0) {
                throw new Exception(
                    "Tidak bisa disetujui karena jadwal bentrok."
                );
            }

            $status = "disetujui";

        } else {
            $status = "ditolak";
        }

        $update = $koneksi->prepare(
            "UPDATE peminjaman
             SET status = ?
             WHERE id_peminjaman = ?
               AND status = 'menunggu'"
        );

        $update->execute([$status, $id]);

        $koneksi->commit();

        $_SESSION["pesan_admin"] =
            "Pengajuan berhasil " . $status . ".";

    } catch (Exception $e) {

        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }

        $_SESSION["pesan_admin"] = $e->getMessage();
    }

    header("Location: peminjaman.php");
    exit;
}

// AMBIL SELURUH PENGAJUAN
$sql = "SELECT p.*, u.nama, r.nama_ruangan
        FROM peminjaman p
        JOIN users u ON p.id_user = u.id_user
        JOIN ruangan r ON p.id_ruangan = r.id_ruangan
        ORDER BY p.tanggal DESC, p.jam_mulai DESC";

$data = $koneksi->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// HITUNG STATISTIK
$total = count($data);
$menunggu = 0;
$disetujui = 0;
$ditolak = 0;

foreach ($data as $item) {
    switch ($item["status"]) {
        case "menunggu":
            $menunggu++;
            break;
        case "disetujui":
            $disetujui++;
            break;
        case "ditolak":
            $ditolak++;
            break;
    }
}

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Kelola Peminjaman | SIPERU</title>

    <link rel="stylesheet"
          href="../assets/css/peminjaman-admin.css">
</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <header class="page-header">

        <div class="header-content">

            <div class="header-icon">
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="29" height="29"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">
                    <rect x="4" y="3"
                          width="16" height="18"
                          rx="2"/>
                    <path d="M8 7h8M8 11h8M8 15h4"/>
                    <path d="m14 16 2 2 4-4"/>
                </svg>
            </div>

            <div>
                <h1>Kelola Peminjaman</h1>
                <p>
                    Kelola dan pantau seluruh pengajuan
                    peminjaman ruangan kampus.
                </p>
            </div>

        </div>

        <a href="dashboard.php" class="back-button">
            &larr; Kembali ke Dashboard
        </a>

    </header>

    <!-- PESAN -->
    <?php if ($pesan): ?>
        <div class="alert">
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="summary-grid">

        <div class="summary-card">
            <div class="summary-icon total-icon">
                &#9776;
            </div>
            <div>
                <p>Total Pengajuan</p>
                <h2><?= $total ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon pending-icon">
                &#9719;
            </div>
            <div>
                <p>Menunggu</p>
                <h2><?= $menunggu ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon approved-icon">
                &#10003;
            </div>
            <div>
                <p>Disetujui</p>
                <h2><?= $disetujui ?></h2>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon rejected-icon">
                &times;
            </div>
            <div>
                <p>Ditolak</p>
                <h2><?= $ditolak ?></h2>
            </div>
        </div>

    </section>

    <!-- DAFTAR PENGAJUAN -->
    <section class="history-card">

        <div class="history-heading">
            <div>
                <h2>Daftar Pengajuan</h2>
                <p>
                    Semua permohonan peminjaman
                    ruangan yang masuk ke sistem.
                </p>
            </div>

            <span class="total-label">
                <?= $total ?> Pengajuan
            </span>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Peminjam</th>
                        <th>Ruangan</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Keperluan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($data)): ?>

                    <tr>
                        <td colspan="8" class="empty-state">
                            <h3>Belum Ada Pengajuan</h3>
                            <p>
                                Belum ada permohonan peminjaman
                                ruangan yang masuk.
                            </p>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($data as $index => $p): ?>

                        <?php
                        $kelasStatus = [
                            "menunggu" => "pending",
                            "disetujui" => "approved",
                            "ditolak" => "rejected"
                        ][$p["status"]] ?? "pending";

                        $labelStatus = [
                            "menunggu" => "Menunggu",
                            "disetujui" => "Disetujui",
                            "ditolak" => "Ditolak"
                        ][$p["status"]] ?? $p["status"];
                        ?>

                        <tr>

                            <td class="number-cell">
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <strong class="borrower-name">
                                    <?= aman($p["nama"]) ?>
                                </strong>
                            </td>

                            <td>
                                <strong class="room-name">
                                    <?= aman($p["nama_ruangan"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= aman(date(
                                    "d/m/Y",
                                    strtotime($p["tanggal"])
                                )) ?>
                            </td>

                            <td class="time-cell">
                                <?= aman(substr(
                                    $p["jam_mulai"], 0, 5
                                )) ?>
                                -
                                <?= aman(substr(
                                    $p["jam_selesai"], 0, 5
                                )) ?>
                            </td>

                            <td class="purpose-cell">
                                <?= aman($p["keperluan"]) ?>
                            </td>

                            <td>
                                <span class="status <?= aman($kelasStatus) ?>">
                                    <?= aman($labelStatus) ?>
                                </span>
                            </td>

                            <td class="action-cell">

                                <?php if ($p["status"] === "menunggu"): ?>

                                    <form method="POST"
                                          class="action-form">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= aman($_SESSION["csrf_token"]) ?>">

                                        <input type="hidden"
                                               name="id_peminjaman"
                                               value="<?= (int) $p["id_peminjaman"] ?>">

                                        <button type="submit"
                                                name="aksi"
                                                value="setujui"
                                                class="approve-button">
                                            Setujui
                                        </button>

                                        <button type="submit"
                                                name="aksi"
                                                value="tolak"
                                                class="reject-button">
                                            Tolak
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="processed-label">
                                        Sudah diproses
                                    </span>

                                <?php endif; ?>

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
        &copy; 2026 SIPERU —
        Sistem Informasi Peminjaman Ruangan
    </footer>

</div>

</body>
</html>
