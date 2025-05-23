<?php
// Koneksi database
$host = "localhost";
$username = "root";
$password = "";
$dbname = "bpjs_config";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
} ?>