
<?php
session_start();
require_once __DIR__ . "/koneksi.php";

// Pemeriksaan login
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}

// Hanya peminjam yang boleh mengajukan
if (($_SESSION["role"] ?? "") !== "peminjam") {
    http_response_code(403);
    exit("Akses ditolak.");
}

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Token keamanan form
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil semua ruangan
$ruangan = $koneksi->query(
    "SELECT id_ruangan, nama_ruangan
     FROM ruangan
     ORDER BY id_ruangan ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Ruangan otomatis terpilih dari halaman sebelumnya
$idDipilih = filter_input(
    INPUT_GET,
    "id_ruangan",
    FILTER_VALIDATE_INT
) ?: 0;

$tanggal = "";
$jamMulai = "";
$jamSelesai = "";
$keperluan = "";

$pesan = "";
$berhasil = false;

// Proses pengajuan
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $idDipilih = filter_input(
        INPUT_POST,
        "id_ruangan",
        FILTER_VALIDATE_INT
    ) ?: 0;

    $tanggal = trim($_POST["tanggal"] ?? "");
    $jamMulai = trim($_POST["jam_mulai"] ?? "");
    $jamSelesai = trim($_POST["jam_selesai"] ?? "");
    $keperluan = trim($_POST["keperluan"] ?? "");

    try {

        if (
            !$idDipilih ||
            $tanggal === "" ||
            $jamMulai === "" ||
            $jamSelesai === "" ||
            $keperluan === ""
        ) {
            throw new Exception("Semua kolom wajib diisi.");
        }

        // Validasi tanggal
        $tanggalObj = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $tanggal
        );

        if (
            !$tanggalObj ||
            $tanggalObj->format("Y-m-d") !== $tanggal
        ) {
            throw new Exception("Format tanggal tidak valid.");
        }

        // Tidak boleh meminjam tanggal yang sudah lewat
        $hariIni = new DateTimeImmutable(
            "today",
            new DateTimeZone("Asia/Jakarta")
        );

        if ($tanggalObj < $hariIni) {
            throw new Exception(
                "Tanggal peminjaman tidak boleh di masa lalu."
            );
        }

        // Validasi jam HH:MM
        if (
            !preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $jamMulai) ||
            !preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $jamSelesai)
        ) {
            throw new Exception("Format jam tidak valid.");
        }

        if ($jamMulai >= $jamSelesai) {
            throw new Exception(
                "Jam selesai harus lebih besar dari jam mulai."
            );
        }

        // Pastikan ruangan benar-benar ada
        $cekRuangan = $koneksi->prepare(
            "SELECT id_ruangan
             FROM ruangan
             WHERE id_ruangan = ?"
        );

        $cekRuangan->execute([$idDipilih]);

        if (!$cekRuangan->fetch()) {
            throw new Exception("Ruangan tidak ditemukan.");
        }

        // Cek bentrok dengan jadwal disetujui
        $cekJadwal = $koneksi->prepare(
            "SELECT COUNT(*)
             FROM peminjaman
             WHERE id_ruangan = ?
               AND tanggal = ?
               AND status = 'disetujui'
               AND jam_mulai < ?
               AND jam_selesai > ?"
        );

        $cekJadwal->execute([
            $idDipilih,
            $tanggal,
            $jamSelesai,
            $jamMulai
        ]);

        if ($cekJadwal->fetchColumn() > 0) {
            throw new Exception(
                "Ruangan sudah dipinjam pada jadwal tersebut."
            );
        }

        // Simpan pengajuan ke database
        $simpan = $koneksi->prepare(
            "INSERT INTO peminjaman
             (id_user, id_ruangan, tanggal,
              jam_mulai, jam_selesai, keperluan, status)
             VALUES (?, ?, ?, ?, ?, ?, 'menunggu')"
        );

        $simpan->execute([
            $_SESSION["id_user"],
            $idDipilih,
            $tanggal,
            $jamMulai,
            $jamSelesai,
            $keperluan
        ]);

        $berhasil = true;
        $pesan = "Pengajuan berhasil dikirim. Menunggu persetujuan admin.";

        // Kosongkan form setelah berhasil
        $tanggal = "";
        $jamMulai = "";
        $jamSelesai = "";
        $keperluan = "";

    } catch (Exception $e) {
        $pesan = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Peminjaman - SIPERU</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            margin: 0;
            color: #222;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
        }

        h1 {
            color: #183153;
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
            box-sizing: border-box;
            font: inherit;
        }

        button {
            margin-top: 22px;
            background: #183153;
            color: white;
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            cursor: pointer;
        }

        .pesan {
            margin: 15px 0;
            padding: 12px;
            border-radius: 7px;
            background: #fff2e5;
        }

        .sukses {
            background: #e7f6eb;
        }

        .kembali {
            display: inline-block;
            margin-top: 18px;
            color: #183153;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>Ajukan Peminjaman Ruangan</h1>

    <?php if ($pesan): ?>
        <div class="pesan <?= $berhasil ? "sukses" : "" ?>">
            <?= aman($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($berhasil): ?>
        <p>
            <a href="riwayat.php">
                Lihat Riwayat Peminjaman
            </a>
        </p>
    <?php endif; ?>

    <form method="POST" action="ajukan_peminjaman.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= aman($_SESSION["csrf_token"]) ?>"
        >

        <label for="id_ruangan">Pilih Ruangan</label>

        <select id="id_ruangan" name="id_ruangan" required>
            <option value="">-- Pilih Ruangan --</option>

            <?php foreach ($ruangan as $r): ?>
                <option
                    value="<?= (int) $r["id_ruangan"] ?>"
                    <?= (int) $r["id_ruangan"] === (int) $idDipilih
                        ? "selected"
                        : "" ?>
                >
                    <?= aman($r["nama_ruangan"]) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label for="tanggal">Tanggal Peminjaman</label>
        <input
            type="date"
            id="tanggal"
            name="tanggal"
            min="<?= date("Y-m-d") ?>"
            value="<?= aman($tanggal) ?>"
            required
        >

        <label for="jam_mulai">Jam Mulai</label>
        <input
            type="time"
            id="jam_mulai"
            name="jam_mulai"
            value="<?= aman($jamMulai) ?>"
            required
        >

        <label for="jam_selesai">Jam Selesai</label>
        <input
            type="time"
            id="jam_selesai"
            name="jam_selesai"
            value="<?= aman($jamSelesai) ?>"
            required
        >

        <label for="keperluan">Keperluan</label>
        <textarea
            id="keperluan"
            name="keperluan"
            rows="4"
            required
        ><?= aman($keperluan) ?></textarea>

        <button type="submit">
            Kirim Pengajuan
        </button>

    </form>

    <a class="kembali" href="ruangan.php">
        ← Kembali ke Daftar Ruangan
    </a>

</div>

</body>
</html>
