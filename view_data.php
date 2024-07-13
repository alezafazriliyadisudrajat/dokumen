<?php
include 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT DISTINCT nama_instansi FROM data_upload";
$result = $conn->query($sql);

if (isset($_GET['instansi'])) {
    $instansi = $_GET['instansi'];
    $sql_data = "SELECT * FROM data_upload WHERE nama_instansi = ?";
    $stmt = $conn->prepare($sql_data);
    $stmt->bind_param('s', $instansi);
    $stmt->execute();
    $result_data = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>View Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php">Dashboard</a>
        <a class="btn btn-danger ms-auto" href="logout.php">Logout</a>
    </nav>

    <div class="container mt-4">
        <h1 class="mt-4">View Data per Instansi</h1>
        <form method="get">
            <div class="mb-3">
                <label class="form-label">Select Instansi</label>
                <select class="form-select" name="instansi" onchange="this.form.submit()">
                    <option value="">-- Select Instansi --</option>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['nama_instansi']); ?>" <?php echo isset($instansi) && $instansi === $row['nama_instansi'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['nama_instansi']); ?>
                        </option>
                    <?php endwhile; ?>
                </
