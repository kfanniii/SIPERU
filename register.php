
<?php
session_start();
require_once "koneksi.php";

// Jika sudah login, arahkan ke dashboard
if (isset($_SESSION["id_user"])) {
    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: peminjam/dashboard.php");
    }
    exit;
}

$error = "";
$sukses = "";
$nama = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi_password"] ?? "";

    // Validasi input
    if (
        $nama === "" ||
        $email === "" ||
        $password === "" ||
        $konfirmasi === ""
    ) {
        $error = "Semua kolom wajib diisi.";

    } elseif (strlen($nama) > 100) {
        $error = "Nama maksimal 100 karakter.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $error = "Alamat email tidak valid.";

    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";

    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak sesuai.";

    } else {

        // Periksa email yang sudah terdaftar
        $cek = $koneksi->prepare(
            "SELECT id_user FROM users WHERE email = ? LIMIT 1"
        );
        $cek->execute([$email]);

        if ($cek->fetch()) {
            $error = "Email sudah terdaftar. Silakan gunakan email lain.";

        } else {

            // Enkripsi password
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {
                // Semua akun baru menjadi peminjam
                $sql = "INSERT INTO users
                        (nama, email, password, role)
                        VALUES (?, ?, ?, 'peminjam')";

                $stmt = $koneksi->prepare($sql);
                $stmt->execute([
                    $nama,
                    $email,
                    $passwordHash
                ]);

                $sukses = "Pendaftaran berhasil! Silakan login.";

                // Kosongkan input setelah berhasil
                $nama = "";
                $email = "";

            } catch (PDOException $e) {

                // Tangani email duplikat
                if ($e->getCode() === "23000") {
                    $error = "Email sudah terdaftar.";
                } else {
                    error_log($e->getMessage());
                    $error = "Pendaftaran gagal. Silakan coba lagi.";
                }
            }
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

    <title>Daftar Akun | SIPERU</title>

    <link rel="stylesheet" href="assets/css/register.css">
</head>

<body class="register-page">

    <main class="register-card">

        <div class="register-header">
            <h1 class="brand">SIPERU</h1>
            <p class="subtitle">
                Buat akun untuk mengajukan
                peminjaman ruangan kampus.
            </p>
        </div>

        <!-- PESAN GAGAL -->
        <?php if ($error !== ""): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon">!</span>
                <span>
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- PESAN BERHASIL -->
        <?php if ($sukses !== ""): ?>
            <div class="alert alert-success" role="status">
                <span class="alert-icon">✓</span>
                <span>
                    <?= htmlspecialchars(
                        $sukses,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                    <a href="login.php">Masuk sekarang</a>
                </span>
            </div>
        <?php endif; ?>

        <!-- FORM REGISTER -->
        <form action="register.php" method="POST">

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>

                <input
                    type="text"
                    id="nama"
                    name="nama"
                    placeholder="Masukkan nama lengkap"
                    autocomplete="name"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $nama,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Masukkan alamat email"
                    autocomplete="email"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        $email,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <small>Gunakan minimal 8 karakter.</small>
            </div>

            <div class="form-group">
                <label for="konfirmasi_password">
                    Konfirmasi Password
                </label>

                <input
                    type="password"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    placeholder="Ulangi password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="register-btn">
                Daftar Akun
            </button>

        </form>

        <p class="login-text">
            Sudah punya akun?
            <a href="login.php">Masuk di sini</a>
        </p>

    </main>

</body>
</html>
