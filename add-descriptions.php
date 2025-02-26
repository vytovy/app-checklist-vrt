<?php
// add_descriptions.php

// Koneksi ke database MySQL
$mysqli = new mysqli("localhost", "root", "", "vrt");
if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

// Ambil ID target dari parameter GET
$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($target_id <= 0) {
    die("Target tidak valid. Pastikan parameter id diberikan.");
}

// Ambil informasi target dari tabel targets
$stmt = $mysqli->prepare("SELECT * FROM targets WHERE id = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$result_target = $stmt->get_result();
$target = $result_target->fetch_assoc();
$stmt->close();

if (!$target) {
    die("Target tidak ditemukan.");
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil input deskripsi dari textarea
    $inputText = isset($_POST["descriptions"]) ? $_POST["descriptions"] : "";
    // Pecah berdasarkan newline (\r\n, \r, atau \n)
    $lines = preg_split('/\r\n|\r|\n/', $inputText);
    $insertedCount = 0;
    foreach ($lines as $line) {
        $desc = trim($line);
        if ($desc !== "") {
            // Periksa apakah entri dengan deskripsi yang sama sudah ada untuk target ini
            $stmt = $mysqli->prepare("SELECT id FROM checklist WHERE target_id = ? AND description = ?");
            $stmt->bind_param("is", $target_id, $desc);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 0) {
                $stmt->close();
                // Masukkan entri baru dengan status default 'belum'
                $stmtInsert = $mysqli->prepare("INSERT INTO checklist (target_id, description, status) VALUES (?, ?, 'belum')");
                $stmtInsert->bind_param("is", $target_id, $desc);
                $stmtInsert->execute();
                $stmtInsert->close();
                $insertedCount++;
            } else {
                $stmt->close();
            }
        }
    }
    $message = "Berhasil menambahkan $insertedCount entri baru.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tambah Deskripsi Baru - <?= htmlspecialchars($target['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Tambah Deskripsi Checklist Baru untuk <?= htmlspecialchars($target['name']) ?></h2>
    <?php if ($message !== ""): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label for="descriptions" class="form-label">Masukkan Deskripsi (setiap baris akan menjadi entri baru):</label>
            <textarea name="descriptions" id="descriptions" class="form-control" rows="10" placeholder="Contoh:
Run amass
Run subfinder
Run assetfinder
Run dnsgen
Run massdns
Use httprobe
Run aquatone (screenshot for alive host)
Single Domain
Scanning
Nmap scan
Burp crawler
ffuf (directory and file fuzzing)
hakrawler/gau/paramspider
Linkfinder
Url with Android application
Manual checking
Shodan
Censys
Google dorks
Pastebin
Github
OSINT
Information Gathering
Manually explore the site
Spider/crawl for missed or hidden content
Check for files that expose content, such as robots.txt, sitemap.xml, .DS_Store
Check the caches of major search engines for publicly accessible sites
Check for differences in content based on User Agent (eg, Mobile sites, access as a Search engine Crawler)
Perform Web Application Fingerprinting
Identify technologies used
Identify user roles
Identify application entry points
Identify client-side code
Identify multiple versions/channels (e.g. web, mobile web, mobile app, web services)
Identify co-hosted and related applications
Identify all hostnames and ports
Identify third-party hosted content
Identify Debug parameters"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Tambah Deskripsi</button>
    </form>
    <div class="mt-3">
        <a href="checklist.php?id=<?= $target_id ?>" class="btn btn-secondary">Kembali ke Checklist</a>
    </div>
</div>
</body>
</html>
<?php $mysqli->close(); ?>
