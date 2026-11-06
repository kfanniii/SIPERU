
<?php
require_once "koneksi.php";

$hasil = "";
$ruangan = $koneksi->query(
    "SELECT id_ruangan, nama_ruangan FROM ruangan"
)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_ruangan = (int) ($_POST["id_ruangan"] ?? 0);
    $tanggal = $_POST["tanggal"] ?? "";
    $mulai = $_POST["jam_mulai"] ?? "";
    $selesai = $_POST["jam_selesai"] ?? "";

    if (
        $id_ruangan <= 0 ||
        !$tanggal ||
        !$mulai ||
        !$selesai ||
        $mulai >= $selesai
    ) {
        $hasil = "Input tidak valid. Periksa jadwal.";
    } else {
        $sql = "SELECT COUNT(*) FROM peminjaman
                WHERE id_ruangan = ?
                AND tanggal = ?
                AND status = 'disetujui'
                AND jam_mulai < ?
                AND jam_selesai > ?";

        $stmt = $koneksi->prepare($sql);
        $stmt->execute([
            $id_ruangan,
            $tanggal,
            $selesai,
            $mulai
        ]);

        $bentrok = (int) $stmt->fetchColumn();

        $hasil = $bentrok > 0
            ? "Jadwal bentrok! Ruangan sudah dipesan."
            : "Jadwal tersedia!";
    }
}
?>

<h2>Cek Jadwal SIPERU</h2>

<form method="POST">
    <label>Ruangan</label>
    <select name="id_ruangan" required>
        <?php foreach ($ruangan as $r): ?>
            <option value="<?= (int) $r['id_ruangan'] ?>">
                <?= htmlspecialchars($r['nama_ruangan']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <p>
        <label>Tanggal</label>
        <input type="date" name="tanggal" required>
    </p>

    <p>
        <label>Jam mulai</label>
        <input type="time" name="jam_mulai" required>
    </p>

    <p>
        <label>Jam selesai</label>
        <input type="time" name="jam_selesai" required>
    </p>

    <button type="submit">Cek Jadwal</button>
</form>

<p><strong><?= htmlspecialchars($hasil) ?></strong></p>
