
<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

// Proteksi halaman admin
if (!isset($_SESSION["id_user"])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Khusus admin.");
}

// Token keamanan form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

$pesan = $_SESSION["pesan_ruangan"] ?? "";
unset($_SESSION["pesan_ruangan"]);

$error = "";

// PROSES TAMBAH, EDIT, DAN HAPUS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $aksi = $_POST["aksi"] ?? "";
    $id = filter_input(
        INPUT_POST,
        "id_ruangan",
        FILTER_VALIDATE_INT
    );

    try {

        if ($aksi === "tambah" || $aksi === "edit") {

            $nama = trim($_POST["nama_ruangan"] ?? "");
            $lokasi = trim($_POST["lokasi"] ?? "");

            $kapasitas = filter_input(
                INPUT_POST,
                "kapasitas",
                FILTER_VALIDATE_INT
            );

            $fasilitas = trim($_POST["fasilitas"] ?? "");

            if (
                $nama === "" ||
                $lokasi === "" ||
                $kapasitas === false ||
                $kapasitas === null ||
                $kapasitas < 1
            ) {
                throw new Exception(
                    "Nama, lokasi, dan kapasitas harus valid."
                );
            }

            if (
                mb_strlen($nama) > 100 ||
                mb_strlen($lokasi) > 150
            ) {
                throw new Exception(
                    "Nama atau lokasi terlalu panjang."
                );
            }

            if ($aksi === "tambah") {

                $stmt = $koneksi->prepare(
                    "INSERT INTO ruangan
                     (nama_ruangan, lokasi, kapasitas, fasilitas)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $nama,
                    $lokasi,
                    $kapasitas,
                    $fasilitas
                ]);

                $pesanBaru = "Ruangan berhasil ditambahkan.";

            } else {

                if (!$id) {
                    throw new Exception("ID ruangan tidak valid.");
                }

                $stmt = $koneksi->prepare(
                    "UPDATE ruangan
                     SET nama_ruangan = ?,
                         lokasi = ?,
                         kapasitas = ?,
                         fasilitas = ?
                     WHERE id_ruangan = ?"
                );

                $stmt->execute([
                    $nama,
                    $lokasi,
                    $kapasitas,
                    $fasilitas,
                    $id
                ]);

                $pesanBaru = "Data ruangan berhasil diperbarui.";
            }

        } elseif ($aksi === "hapus") {

            if (!$id) {
                throw new Exception("ID ruangan tidak valid.");
            }

            // Cegah hapus jika ada riwayat peminjaman
            $cek = $koneksi->prepare(
                "SELECT COUNT(*)
                 FROM peminjaman
                 WHERE id_ruangan = ?"
            );

            $cek->execute([$id]);

            if ($cek->fetchColumn() > 0) {
                throw new Exception(
                    "Ruangan tidak bisa dihapus karena memiliki riwayat peminjaman."
                );
            }

            $stmt = $koneksi->prepare(
                "DELETE FROM ruangan WHERE id_ruangan = ?"
            );

            $stmt->execute([$id]);

            $pesanBaru = "Ruangan berhasil dihapus.";

        } else {
            throw new Exception("Aksi tidak dikenal.");
        }

        $_SESSION["pesan_ruangan"] = $pesanBaru;

        header("Location: ruangan.php");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// DATA YANG AKAN DIEDIT
$edit = null;

if (isset($_GET["edit"])) {

    $idEdit = filter_input(
        INPUT_GET,
        "edit",
        FILTER_VALIDATE_INT
    );

    if ($idEdit) {

        $stmt = $koneksi->prepare(
            "SELECT * FROM ruangan
             WHERE id_ruangan = ?"
        );

        $stmt->execute([$idEdit]);
        $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// AMBIL SELURUH RUANGAN
$ruangan = $koneksi->query(
    "SELECT * FROM ruangan ORDER BY id_ruangan ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// STATISTIK
$totalRuangan = count($ruangan);
$totalKapasitas = 0;

foreach ($ruangan as $r) {
    $totalKapasitas += (int) $r["kapasitas"];
}

// Mode konfirmasi hapus tanpa JavaScript
$idHapus = filter_input(
    INPUT_GET,
    "hapus",
    FILTER_VALIDATE_INT
);

$ruanganHapus = null;

if ($idHapus) {
    foreach ($ruangan as $r) {
        if ((int) $r["id_ruangan"] === $idHapus) {
            $ruanganHapus = $r;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Kelola Ruangan | SIPERU</title>

    <link rel="stylesheet"
          href="../assets/css/ruangan-admin.css">
</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <header class="page-header">

        <div class="header-content">

            <div class="header-icon">
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="30" height="30"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">
                    <rect x="4" y="2"
                          width="16" height="20"
                          rx="2"/>
                    <path d="M9 6h1M14 6h1
                             M9 10h1M14 10h1
                             M9 14h1M14 14h1"/>
                    <path d="M10 22v-4h4v4"/>
                </svg>
            </div>

            <div>
                <h1>Kelola Ruangan</h1>
                <p>
                    Tambah, ubah, dan kelola
                    data ruangan kampus.
                </p>
            </div>

        </div>

        <a href="dashboard.php"
           class="back-button">
            &larr; Kembali ke Dashboard
        </a>

    </header>

    <!-- PESAN BERHASIL -->
    <?php if ($pesan): ?>
        <div class="alert success-alert">
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- PESAN ERROR -->
    <?php if ($error): ?>
        <div class="alert error-alert">
            <?= aman($error) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon room-icon">
                &#9638;
            </div>

            <div>
                <p>Total Ruangan</p>
                <h2><?= $totalRuangan ?></h2>
            </div>

        </div>

        <div class="summary-card">

            <div class="summary-icon capacity-icon">
                &#9786;
            </div>

            <div>
                <p>Total Kapasitas</p>
                <h2><?= $totalKapasitas ?> <small>orang</small></h2>
            </div>

        </div>

    </section>

    <!-- FORM TAMBAH / EDIT -->
    <section class="form-card">

        <div class="section-heading">
            <h2>
                <?= $edit ? "Edit Ruangan" : "Tambah Ruangan" ?>
            </h2>

            <p>
                <?= $edit
                    ? "Perbarui informasi ruangan yang dipilih."
                    : "Masukkan informasi ruangan baru ke sistem."
                ?>
            </p>
        </div>

        <form method="POST" class="room-form">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= aman($_SESSION["csrf_token"]) ?>">

            <input type="hidden"
                   name="aksi"
                   value="<?= $edit ? "edit" : "tambah" ?>">

            <?php if ($edit): ?>
                <input type="hidden"
                       name="id_ruangan"
                       value="<?= (int) $edit["id_ruangan"] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label for="nama_ruangan">
                        Nama Ruangan
                    </label>

                    <input type="text"
                           id="nama_ruangan"
                           name="nama_ruangan"
                           placeholder="Contoh: Lab Komputer"
                           maxlength="100"
                           required
                           value="<?= aman($edit["nama_ruangan"] ?? "") ?>">
                </div>

                <div class="form-group">
                    <label for="lokasi">
                        Lokasi
                    </label>

                    <input type="text"
                           id="lokasi"
                           name="lokasi"
                           placeholder="Contoh: Gedung A Lantai 2"
                           maxlength="150"
                           required
                           value="<?= aman($edit["lokasi"] ?? "") ?>">
                </div>

                <div class="form-group">
                    <label for="kapasitas">
                        Kapasitas (Orang)
                    </label>

                    <input type="number"
                           id="kapasitas"
                           name="kapasitas"
                           min="1"
                           placeholder="Contoh: 40"
                           required
                           value="<?= aman($edit["kapasitas"] ?? "") ?>">
                </div>

                <div class="form-group full-width">
                    <label for="fasilitas">
                        Fasilitas
                    </label>

                    <textarea id="fasilitas"
                              name="fasilitas"
                              rows="3"
                              placeholder="Contoh: AC, Proyektor, WiFi"><?= aman($edit["fasilitas"] ?? "") ?></textarea>
                </div>

            </div>

            <div class="form-actions">

                <button type="submit"
                        class="primary-button">
                    <?= $edit
                        ? "Simpan Perubahan"
                        : "+ Tambah Ruangan"
                    ?>
                </button>

                <?php if ($edit): ?>
                    <a href="ruangan.php"
                       class="cancel-button">
                        Batal Edit
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- KONFIRMASI HAPUS -->
    <?php if ($ruanganHapus): ?>

        <section class="delete-card">

            <h2>Konfirmasi Hapus Ruangan</h2>

            <p>
                Yakin ingin menghapus
                <strong>
                    <?= aman($ruanganHapus["nama_ruangan"]) ?>
                </strong>?
                Tindakan ini tidak dapat dibatalkan.
            </p>

            <div class="delete-actions">

                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= aman($_SESSION["csrf_token"]) ?>">

                    <input type="hidden"
                           name="aksi"
                           value="hapus">

                    <input type="hidden"
                           name="id_ruangan"
                           value="<?= (int) $ruanganHapus["id_ruangan"] ?>">

                    <button type="submit"
                            class="danger-button">
                        Ya, Hapus Ruangan
                    </button>

                </form>

                <a href="ruangan.php"
                   class="cancel-button">
                    Batal
                </a>

            </div>

        </section>

    <?php endif; ?>

    <!-- TABEL DAFTAR RUANGAN -->
    <section class="history-card">

        <div class="history-heading">

            <div>
                <h2>Daftar Ruangan</h2>
                <p>
                    Seluruh data ruangan
                    yang tersedia dalam sistem.
                </p>
            </div>

            <span class="total-label">
                <?= $totalRuangan ?> Ruangan
            </span>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Ruangan</th>
                        <th>Lokasi</th>
                        <th>Kapasitas</th>
                        <th>Fasilitas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($ruangan)): ?>

                    <tr>
                        <td colspan="6"
                            class="empty-state">
                            <h3>Belum Ada Ruangan</h3>
                            <p>
                                Tambahkan ruangan pertama
                                menggunakan formulir di atas.
                            </p>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($ruangan as $r): ?>

                        <tr>

                            <td class="number-cell">
                                #<?= (int) $r["id_ruangan"] ?>
                            </td>

                            <td>
                                <strong class="room-name">
                                    <?= aman($r["nama_ruangan"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= aman($r["lokasi"]) ?>
                            </td>

                            <td>
                                <?= (int) $r["kapasitas"] ?>
                                orang
                            </td>

                            <td class="facility-cell">
                                <?= aman($r["fasilitas"]) ?>
                            </td>

                            <td>

                                <div class="table-actions">

                                    <a href="ruangan.php?edit=<?= (int) $r["id_ruangan"] ?>"
                                       class="edit-button">
                                        Edit
                                    </a>

                                    <a href="ruangan.php?hapus=<?= (int) $r["id_ruangan"] ?>"
                                       class="delete-button">
                                        Hapus
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="table-footer">
            Menampilkan <?= $totalRuangan ?> ruangan.
        </div>

    </section>

    <footer class="footer">
        &copy; 2026 SIPERU —
        Sistem Informasi Peminjaman Ruangan
    </footer>

</div>

</body>
</html>
