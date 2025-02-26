<?php
// checklist.php

// Koneksi ke database MySQL
$mysqli = new mysqli("localhost", "root", "", "vrt");
if ($mysqli->connect_error) {
    die("Koneksi gagal: " . $mysqli->connect_error);
}

// Ambil ID target dari parameter GET
$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($target_id <= 0) {
    die("Target tidak valid.");
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

// Fungsi rekursif untuk menelusuri JSON dan memasukkan checklist
function processJsonItem($item, $target_id, $mysqli) {
    // Jika item adalah variant dan memiliki 'name'
    if (isset($item['type']) && $item['type'] === 'variant' && isset($item['name'])) {
        $desc = $item['name'];
        // Periksa apakah entri dengan deskripsi yang sama sudah ada untuk target ini
        $stmt = $mysqli->prepare("SELECT id FROM checklist WHERE target_id = ? AND description = ?");
        $stmt->bind_param("is", $target_id, $desc);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            $stmt->close();
            $stmt2 = $mysqli->prepare("INSERT INTO checklist (target_id, description, status) VALUES (?, ?, 'belum')");
            $stmt2->bind_param("is", $target_id, $desc);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $stmt->close();
        }
    }
    // Jika item memiliki children, proses secara rekursif
    if (isset($item['children']) && is_array($item['children'])) {
        foreach ($item['children'] as $child) {
            processJsonItem($child, $target_id, $mysqli);
        }
    }
}

function loadChecklistFromJson($target_id, $mysqli) {
    $json_file = 'vrt.json';
    if (file_exists($json_file)) {
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        if ($data === null) {
            echo "Error decoding JSON: " . json_last_error_msg();
            return;
        }
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $item) {
                processJsonItem($item, $target_id, $mysqli);
            }
        } else {
            echo "Struktur JSON tidak sesuai.";
        }
    } else {
        echo "File vrt.json tidak ditemukan.";
    }
}

// Proses request POST untuk update status atau menambah checklist baru
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_status'])) {
        $checklist_id = intval($_POST['checklist_id']);
        $status = $_POST['status']; // nilai: 'belum', 'berhasil', 'gagal'
        if (!in_array($status, ['belum', 'berhasil', 'gagal'])) {
            $status = 'belum';
        }
        $stmt = $mysqli->prepare("UPDATE checklist SET status = ? WHERE id = ? AND target_id = ?");
        $stmt->bind_param("sii", $status, $checklist_id, $target_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['add_item'])) {
        $description = $_POST['description'];
        $stmt = $mysqli->prepare("INSERT INTO checklist (target_id, description, status) VALUES (?, ?, 'belum')");
        $stmt->bind_param("is", $target_id, $description);
        $stmt->execute();
        $stmt->close();
    }
}

// Panggil fungsi loadChecklistFromJson setiap kali halaman diakses agar entri baru dari vrt.json ikut dimasukkan
loadChecklistFromJson($target_id, $mysqli);

// Ambil data checklist terbaru untuk target ini, dengan fitur pencarian (search) jika parameter disediakan
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
if ($search !== "") {
    $stmt = $mysqli->prepare("SELECT * FROM checklist WHERE target_id = ? AND description LIKE ?");
    $like = '%' . $search . '%';
    $stmt->bind_param("is", $target_id, $like);
    $stmt->execute();
    $result_checklist = $stmt->get_result();
    $stmt->close();
} else {
    $result_checklist = $mysqli->query("SELECT * FROM checklist WHERE target_id = $target_id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checklist - <?= htmlspecialchars($target['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .btn-status { width: 100px; margin-right: 5px; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Checklist Bug Bounty - <?= htmlspecialchars($target['name']) ?></h2>
    <p>Website: <a href="<?= htmlspecialchars($target['url']) ?>" target="_blank"><?= htmlspecialchars($target['url']) ?></a></p>
    
	<!-- Tombol untuk menambahkan deskripsi baru -->
    <a href="add-descriptions.php?id=<?= $target_id ?>" class="btn btn-primary mb-3">Tambah Deskripsi Baru</a>
		<!--kembali kedaftar target-->
	<a href="index.php" class="btn btn-primary mb-3">Kembali ke Daftar Target</a>
	
    <!-- Form Pencarian -->
    <form method="get" class="mb-3">
        <!-- Pertahankan parameter target id -->
        <input type="hidden" name="id" value="<?= $target_id ?>">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Cari deskripsi checklist..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Cari</button>
        </div>
    </form>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_checklist->fetch_assoc()) {
                $badge = 'bg-dark'; // default: belum diuji
                if ($row['status'] == 'berhasil') {
                    $badge = 'bg-success';
                } elseif ($row['status'] == 'gagal') {
                    $badge = 'bg-danger';
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td>
                    <form method="post" class="d-inline-block">
                        <input type="hidden" name="checklist_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="status" value="">
                        <button type="submit" name="update_status" class="btn btn-success btn-status" onclick="this.form.status.value='berhasil';">Berhasil</button>
                        <button type="submit" name="update_status" class="btn btn-danger btn-status" onclick="this.form.status.value='gagal';">Gagal</button>
                        <button type="submit" name="update_status" class="btn btn-dark btn-status" onclick="this.form.status.value='belum';">Reset</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    
    <!-- Form untuk menambah checklist baru -->
    <div class="mt-4">
        <h4>Tambah Checklist Baru</h4>
        <form method="post">
            <div class="mb-3">
                <input type="text" name="description" placeholder="Deskripsi Checklist" class="form-control" required>
            </div>
            <button type="submit" name="add_item" class="btn btn-primary">Tambah</button>
        </form>
    </div>
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">Kembali ke Daftar Target</a>
    </div>
</div>
</body>
</html>
<?php $mysqli->close(); ?>
