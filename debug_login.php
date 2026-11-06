
<?php
session_start();
require_once __DIR__ . "/koneksi.php";

if (!isset($_SESSION["id_user"])) {
    exit("Belum login.");
}

$stmt = $koneksi->prepare(
    "SELECT id_user, nama, email, role
     FROM users
     WHERE id_user = ?"
);

$stmt->execute([$_SESSION["id_user"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>DEBUG LOGIN SIPERU</h2>";

echo "ID Session: " . htmlspecialchars((string) $_SESSION["id_user"]) . "<br>";
echo "Role Session: " . htmlspecialchars($_SESSION["role"] ?? "KOSONG") . "<br>";

if ($user) {
    echo "Email Database: " . htmlspecialchars($user["email"]) . "<br>";
    echo "Role Database: " . htmlspecialchars($user["role"]) . "<br>";
} else {
    echo "Akun tidak ditemukan di database.";
}
?>
