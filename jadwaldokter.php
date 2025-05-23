<?php
ob_start();
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
    die(json_encode(['error' => 'Data konfigurasi tidak ditemukan.']));
}

$conn->close();

// Timestamp UTC
date_default_timezone_set('UTC');
$tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));

// Signature
$signature = base64_encode(hash_hmac('sha256', $cons_id . "&" . $tStamp, $secretKey, true));

// Dapatkan daftar poli
$urlPoli = "https://apijkn.bpjs-kesehatan.go.id/antreanrs/ref/poli";
$headers = [
    "x-cons-id: $cons_id",
    "x-timestamp: $tStamp",
    "x-signature: $signature",
    "user_key: $user_key"
];

$ch = curl_init($urlPoli);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responsePoli = curl_exec($ch);
curl_close($ch);

$dataPoli = json_decode($responsePoli, true);

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



?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Jadwal Dokter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-4">
    <h1 class="mb-4">Pilih Jadwal Dokter</h1>
    <form action="jadwaldokter.php" method="get">
        <div class="mb-3">
            <label for="kodepoli" class="form-label">Kode Poli</label>
            <select class="form-select" id="kodepoli" name="kodepoli" required>
                <!-- <option value="ANA">ANA</option>
                <option value="BED">BED</option> -->
                <!-- Tambahkan opsi lain sesuai kebutuhan -->
                <?php
                // Dekripsi respons daftar poli
                if (isset($dataPoli['response'])) {
                    $encryptedResponsePoli = $dataPoli['response'];
                    $decryptionKeyPoli = $cons_id . $secretKey . $tStamp;
                    $decryptedPoli = stringDecrypt($decryptionKeyPoli, $encryptedResponsePoli);
                    
                    if ($decryptedPoli) {
                        $decompressedPoli = decompress($decryptedPoli);
                        $dataPoliList = json_decode($decompressedPoli, true);
                        if (is_array($dataPoliList)) {
                            foreach ($dataPoliList as $dataPoli) {
                                echo '<option value="' . htmlspecialchars($dataPoli['kdpoli']) . '">' . htmlspecialchars($dataPoli['kdpoli']) . ' - ' . htmlspecialchars($dataPoli['nmsubspesialis']) . '</option>';
                            }
                        } else {
                            die("<div class='alert alert-warning'>Data poli tidak valid.</div>");
                        }
                    } else {
                        die("<div class='alert alert-danger'>Gagal dekripsi data poli.</div>");
                    }
                } else {
                    die("<div class='alert alert-warning'>Data poli tidak ditemukan.</div>");
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="tanggal" class="form-label">Tanggal</label>
            <input type="date" class="form-control" id="tanggal" name="tanggal" required>
        </div>
        <button type="submit" class="btn btn-primary">Lihat Jadwal</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Ambil parameter dari URL
$kodepoli = isset($_GET['kodepoli']) ? $_GET['kodepoli'] : '';
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';

if (!empty($kodepoli) && !empty($tanggal)) {
    $urlJadwal = "https://apijkn.bpjs-kesehatan.go.id/antreanrs/jadwaldokter/kodepoli/$kodepoli/tanggal/$tanggal";

    $ch = curl_init($urlJadwal);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    // Dekripsi respons jadwal dokter
    if (isset($responseData['response'])) {
        $encryptedResponse = $responseData['response'];
        $decryptionKey = $cons_id . $secretKey . $tStamp;
        $decrypted = stringDecrypt($decryptionKey, $encryptedResponse);
        if ($decrypted) {
            $decompressed = decompress($decrypted);
            // Debug: Tampilkan data jadwal setelah dekripsi
            // echo '<pre>' . print_r(json_decode($decompressed, true), true) . '</pre>';
            // Tampilkan hasil dalam bentuk tabel
            header('Content-Type: text/html');

            echo '<div class="container my-4">';
            echo '<h2 class="mb-4">Jadwal Dokter</h2>';
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-bordered table-hover">';
            echo '<thead class="table-dark">';
            echo '<tr><th>No</th><th>Nama Dokter</th><th>Nama Poli</th><th>Hari</th><th>Jadwal</th><th>Kapasitas Pasien</th></tr>';
            echo '</thead><tbody>';

            $no = 1;
            foreach (json_decode($decompressed, true) as $jadwal) {
                echo '<tr>';
                echo '<td>' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($jadwal['namadokter']) . '</td>';
                echo '<td>' . htmlspecialchars($jadwal['namapoli']) . '</td>';
                echo '<td>' . htmlspecialchars($jadwal['namahari']) . '</td>';
                echo '<td>' . htmlspecialchars($jadwal['jadwal']) . '</td>';
                echo '<td>' . htmlspecialchars($jadwal['kapasitaspasien']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
            echo '</div>';
        } else {
            die(json_encode(['error' => 'Gagal dekripsi jadwal dokter.']));
        }
    } else {
        echo json_encode(['error' => 'HTTP Status: ' . $httpCode, 'response' => $response]);
    }
}
ob_end_flush(); 