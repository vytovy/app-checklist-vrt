<?php
$mysqli = new mysqli("localhost", "root", "", "vrt");
if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $url = $_POST["url"];
    $stmt = $mysqli->prepare("INSERT INTO targets (name, url) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $url);
    $stmt->execute();
    $stmt->close();
}

$result = $mysqli->query("SELECT * FROM targets");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <title>Bug Bounty Checklist</title>
</head>
<body>
<div class="container mt-5">
    <h2>Daftar Target Bug Bounty</h2>
    <form method="post" class="mb-3">
        <input type="text" name="name" placeholder="Nama Program" required class="form-control">
        <input type="url" name="url" placeholder="Website" required class="form-control mt-2">
        <button type="submit" class="btn btn-primary mt-2">Tambah Target</button>
    </form>
    <table class="table table-bordered">
        <tr>
            <th>Nama Program</th>
            <th>Website</th>
            <th>Aksi</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><a href="<?= htmlspecialchars($row['url']) ?>" target="_blank"><?= htmlspecialchars($row['url']) ?></a></td>
                <td><a href="checklist.php?id=<?= $row['id'] ?>" class="btn btn-info">Checklist</a></td>
            </tr>
        <?php } ?>
    </table>
</div>
</body>
</html>
