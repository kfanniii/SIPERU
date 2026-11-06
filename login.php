
<?php
session_start();
require_once "koneksi.php";

$error = "";
$email = "";

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION["id_user"])) {
    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: peminjam/dashboard.php");
    }
    exit;
}

// Proses login
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {

        $sql = "SELECT id_user, nama, email, password, role
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["id_user"] = $user["id_user"];
            $_SESSION["nama"] = $user["nama"];
            $_SESSION["role"] = $user["role"];

            // Redirect langsung sesuai role
            if ($user["role"] === "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: peminjam/dashboard.php");
            }
            exit;

        } else {
            $error = "Email atau password salah. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SIPERU</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body class="login-page">

    <main class="login-card">

        <h1 class="brand">SIPERU</h1>
        <p class="subtitle">
            Sistem Informasi Peminjaman Ruangan
        </p>

        <!-- PESAN LOGIN GAGAL -->
        <?php if ($error !== ""): ?>
            <div class="login-error" role="alert">
                <span class="error-icon">!</span>
                <span>
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- FORM LOGIN -->
        <form action="login.php" method="POST">

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Masukkan alamat email"
                    autocomplete="email"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="login-btn">
                Masuk
            </button>

        </form>

        <p class="register-text">
            Belum punya akun?
            <a href="register.php">Daftar Sekarang</a>
        </p>

    </main>

</body>
</html>
