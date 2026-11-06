<?php
$host = "127.0.0.1";
$dbname = "siperu";
$username = "root";
$password = "";

try {
    $koneksi = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $koneksi->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Koneksi database gagal.");
}
