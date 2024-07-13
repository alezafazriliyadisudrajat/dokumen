<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
include 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['upload'])) {
    // Cek jika file zip diupload
    if (isset($_FILES['zip_file'])) {
        $zip_file = $_FILES['zip_file'];
        $upload_dir = 'uploads/';
        $upload_file = $upload_dir . basename($zip_file['name']);

        // Pastikan direktori upload ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (move_uploaded_file($zip_file['tmp_name'], $upload_file)) {
            $zip = new ZipArchive;
            if ($zip->open($upload_file) === TRUE) {
                $zip->extractTo($upload_dir);
                $zip->close();

                // Mengambil file Excel dan PDF dari folder yang sudah diekstrak
                $excel_file = $upload_dir . 'data.xlsx';
                $pdf_files = array_diff(scandir($upload_dir), array('.', '..', 'data.xlsx'));

                // Load the PhpSpreadsheet library
                $spreadsheet = IOFactory::load($excel_file);
                $worksheet = $spreadsheet->getActiveSheet();

                // Memulai transaksi
                $conn->begin_transaction();

                try {
                    foreach ($worksheet->getRowIterator() as $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(FALSE);

                        $data = [];
                        foreach ($cellIterator as $cell) {
                            $data[] = $cell->getValue();
                        }

                        // Menghindari baris header
                        if ($data[0] !== 'ID_DIPOTONG' && !empty($data[0])) {
                            $id_dipotong = $data[0];
                            $nama = $data[1];
                            $pasal = $data[2];
                            $kode_objek_pajak = $data[3];
                            $no_bukti_potong = $data[4];
                            
                            // Convert tanggal jika perlu
                            $tanggal_bupot_raw = $data[5];
                            if (is_numeric($tanggal_bupot_raw)) {
                                $tanggal_bupot = Date::excelToDateTimeObject($tanggal_bupot_raw)->format('Y-m-d');
                            } else {
                                $tanggal_bupot = DateTime::createFromFormat('d-m-Y', $tanggal_bupot_raw)->format('Y-m-d');
                            }

                            $pph_dipotong = $data[6];
                            $jumlah_bruto = $data[7];
                            $keterangan = $data[8];
                            $file_pdf = '';

                            foreach ($pdf_files as $pdf) {
                                if (preg_match('/' . $no_bukti_potong . '/i', $pdf)) {
                                    $file_pdf = $pdf;
                                    break;
                                }
                            }

                            // Siapkan query SQL untuk memasukkan data ke dalam tabel
                            $sql = "INSERT INTO data_upload 
                                    (no_bukti, tanggal_bukti, npwp_pemotong, nama_pemotong, identitas_penerima, nama_penerima, penghasilan_bruto, pph, kode_objek_pajak, file_pdf) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $stmt = $conn->prepare($sql);
                            if ($stmt === false) {
                                throw new Exception("Error preparing statement: " . $conn->error);
                            }

                            // Pastikan npwp_pemotong ada di master_perusahaan
                            $npwp_pemotong = $data[2];
                            $sql_check_npwp = "SELECT npwp FROM master_perusahaan WHERE npwp = ?";
                            $stmt_check_npwp = $conn->prepare($sql_check_npwp);
                            $stmt_check_npwp->bind_param('s', $npwp_pemotong);
                            $stmt_check_npwp->execute();
                            $result_check_npwp = $stmt_check_npwp->get_result();

                            if ($result_check_npwp->num_rows == 0) {
                                // npwp_pemotong tidak ada, masukkan ke master_perusahaan
                                $nama_perusahaan = $data[3];
                                $sql_insert_npwp = "INSERT INTO master_perusahaan (npwp, nama_perusahaan) VALUES (?, ?)";
                                $stmt_insert_npwp = $conn->prepare($sql_insert_npwp);
                                $stmt_insert_npwp->bind_param('ss', $npwp_pemotong, $nama_perusahaan);
                                $stmt_insert_npwp->execute();
                            }

                            $stmt->bind_param(
                                'ssssssssss',
                                $no_bukti_potong,
                                $tanggal_bupot,
                                $npwp_pemotong,
                                $nama,
                                $pasal,
                                $pasal,
                                $jumlah_bruto,
                                $pph_dipotong,
                                $kode_objek_pajak,
                                $file_pdf
                            );

                            if (!$stmt->execute()) {
                                throw new Exception($stmt->error);
                            }
                        }
                    }

                    // Commit transaksi jika semua query berhasil
                    $conn->commit();
                    $success_message = "Data berhasil diupload.";
                } catch (Exception $e) {
                    // Rollback transaksi jika ada error
                    $conn->rollback();
                    $error_message = "Error: " . $e->getMessage();
                }

                // Hapus file zip setelah proses selesai
                unlink($upload_file);
            } else {
                $error_message = "Gagal membuka file zip.";
            }
        } else {
            $error_message = "Gagal mengupload file zip.";
        }
    }
}

$sql = "SELECT * FROM data_upload";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php">Dashboard</a>
        <a class="btn btn-danger ms-auto" href="logout.php">Logout</a>
    </nav>

    <div class="container mt-4">
        <h1 class="mt-4">Data Upload</h1>
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Upload Zip File</label>
                <input type="file" class="form-control" name="zip_file" accept=".zip" required />
            </div>
            <button type="submit" name="upload" class="btn btn-primary">Upload</button>
        </form>
        <div class="mt-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No Bukti</th>
                        <th>Tanggal Bukti</th>
                        <th>NPWP Pemotong</th>
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
                            <td><?php echo htmlspecialchars($row['no_bukti']); ?></td>
                            <td><?php echo htmlspecialchars($row['tanggal_bukti']); ?></td>
                            <td><?php echo htmlspecialchars($row['npwp_pemotong']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_pemotong']); ?></td>
                            <td><?php echo htmlspecialchars($row['identitas_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($row['penghasilan_bruto']); ?></td>
                            <td><?php echo htmlspecialchars($row['pph']); ?></td>
                            <td><?php echo htmlspecialchars($row['kode_objek_pajak']); ?></td>
                            <td><a href="uploads/<?php echo htmlspecialchars($row['file_pdf']); ?>" target="_blank">View PDF</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
