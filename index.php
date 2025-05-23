<?php
require_once 'vendor/autoload.php'; // LZString
require_once 'koneksi.php';

$sql = "SELECT cons_id, secret_key, user_key FROM bpjs_config WHERE id = 1 LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $cons_id   = $row['cons_id'];
    $secretKey = $row['secret_key'];
    $user_key  = $row['user_key'];
} else {
    die("Data konfigurasi tidak ditemukan.");
}

$conn->close();

// Timestamp UTC
date_default_timezone_set('UTC');
$tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));

// Signature
$signature = base64_encode(hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true));


$url = "https://apijkn.bpjs-kesehatan.go.id/antreanrs/ref/dokter";

$headers = [
    "x-cons-id: $cons_id",
    "x-timestamp: $tStamp",
    "x-signature: $signature",
    "user_key: $user_key"
];

// Kirim request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Decode JSON response
$responseData = json_decode($response, true);

// Fungsi dekripsi
function stringDecrypt($key, $string){
    $encrypt_method = 'AES-256-CBC';
    $key_hash = hex2bin(hash('sha256', $key));
    $iv = substr($key_hash, 0, 16);
    return openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
}

// Fungsi decompress
function decompress($string){
    return \LZCompressor\LZString::decompressFromEncodedURIComponent($string);
}
// Catat waktu mulai
$startTime = microtime(true);
// Catat waktu selesai
$endTime = microtime(true);
// Hitung durasi dalam detik
$duration = $endTime - $startTime;
// Konversi durasi ke menit
$durationInMinutes = $duration / 60;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Daftar Dokter BPJS</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.datatables.net/2.3.1/css/dataTables.dataTables.min.css" rel="stylesheet" />
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Navbar</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Link</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Dropdown
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Action</a></li>
            <li><a class="dropdown-item" href="#">Another action</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#">Something else here</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link disabled" aria-disabled="true">Disabled</a>
        </li>
      </ul>
      <form class="d-flex" role="search">
        <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search"/>
        <button class="btn btn-outline-success" type="submit">Search</button>
      </form>
    </div>
  </div>
</nav>    
<div class="container my-4">
    <h1 class="mb-4">Daftar Dokter BPJS</h1>
    <?php
    // Proses
    if (isset($responseData['response'])) {
        $encryptedResponse = $responseData['response'];
        // Key untuk dekripsi (consid + secretKey + timestamp)
        $decryptionKey = $cons_id . $secretKey . $tStamp;
        $decrypted = stringDecrypt($decryptionKey, $encryptedResponse);
        if (!$decrypted) {
            echo "<br><span style='color:red;'>❌ Gagal dekripsi. Periksa key dan format.</span><br>";
        }
        $decompressed = decompress($decrypted);
        // Decode decompressed data
        $data = json_decode($decompressed, true);
        if (is_array($data)) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-bordered table-hover" id="doctorTable">';
            echo '<thead class="table-dark">';
            echo '<tr><th scope="col">No</th><th scope="col">Kode Dokter</th><th scope="col">Nama Dokter</th></tr>';
            echo '</thead><tbody>';
            $no = 1;
            foreach ($data as $dokter) {
                if (isset($dokter['kodedokter']) && isset($dokter['namadokter'])) {
                    echo '<tr>';
                    echo '<td>' . $no++ . '</td>';
                    echo '<td>' . htmlspecialchars($dokter['kodedokter']) . '</td>';
                    echo '<td>' . htmlspecialchars($dokter['namadokter']) . '</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody>
            </table>
            </div>';
        } else {
            echo '<div class="alert alert-warning">Data tidak valid atau tidak dapat ditampilkan dalam tabel.</div>';
        }
    } else {
        echo "HTTP Status: $httpCode<br>Response: $response";
    }
    ?>
    
</div>
<!-- Bootstrap 5 JS Bundle (optional, for components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/2.3.1/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#doctorTable').DataTable();
    });
</script>
<script>
    // Tampilkan notifikasi dengan SweetAlert
    Swal.fire({
        title: 'Data Loaded',
        text: 'Data berhasil dimuat dalam waktu <?php echo round($durationInMinutes, 2); ?> menit.',
        icon: 'success',
        timer: 3000,
        showConfirmButton: false
    });
</script>
</body>
</html>
