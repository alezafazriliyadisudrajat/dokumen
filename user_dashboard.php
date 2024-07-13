<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: user_login.php");
    exit();
}

$npwp = $_SESSION['user'];

$sql = "SELECT * FROM data_upload WHERE npwp_pemotong = '$npwp'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="user_dashboard.php">User Dashboard</a>
        <a class="btn btn-danger ms-auto" href="logout.php">Logout</a>
    </nav>

    <div class="container mt-4">
        <h1 class="mt-4">Dokumen Anda</h1>
        <div class="mt-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No Bukti</th>
                        <th>Tanggal Bukti</th>
                        <th>Nama Pemotong</th>
                        <th>Identitas Penerima</th>
                        <th>Nama Penerima</th>
                        <th>Penghasilan Bruto</th>
                        <th>PPH</th>
                        <th>Kode Objek Pajak</th>
                        <th>File PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['no_bukti']; ?></td>
                            <td><?php echo $row['tanggal_bukti']; ?></td>
                            <td><?php echo $row['nama_pemotong']; ?></td>
                            <td><?php echo $row['identitas_penerima']; ?></td>
                            <td><?php echo $row['nama_penerima']; ?></td>
                            <td><?php echo $row['penghasilan_bruto']; ?></td>
                            <td><?php echo $row['pph']; ?></td>
                            <td><?php echo $row['kode_objek_pajak']; ?></td>
                            <td><a href="uploads/<?php echo $row['file_pdf']; ?>" target="_blank">View PDF</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
