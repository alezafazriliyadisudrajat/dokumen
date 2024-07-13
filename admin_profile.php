<?php
include 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    if ($password) {
        $password_hashed = password_hash($password, PASSWORD_BCRYPT);
        $npwp = $_SESSION['admin']['npwp'];
        $sql = "UPDATE users SET password = ? WHERE npwp = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $password_hashed, $npwp);
        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php">Dashboard</a>
        <a class="btn btn-danger ms-auto" href="logout.php">Logout</a>
    </nav>

    <div class="container mt-4">
        <h1 class="mt-4">Admin Profile</h1>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="password" required />
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</body>
</html>
