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

        if ($_FILES['zip_file']['error'] == 0) {
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
                    $uploaded = 0;

                    try {
                        foreach ($worksheet->getRowIterator() as $row) {
                            $cellIterator = $row->getCellIterator();
                            $cellIterator->setIterateOnlyExistingCells(FALSE);

                            $data = [];
                            foreach ($cellIterator as $cell) {
                                $data[] = $cell->getValue();
                            }

                            // Debugging: Periksa data baris
                            var_dump($data);

                            // Menghindari baris header
                            if ($data[0] !== 'ID_DIPOTONG' && !empty($data[0])) {
                                $id_dipotong = $data[0];
                                $nama = $data[1];
                                $pasal = $data[2];
                                $kode_objek_pajak = $data[3];
                                $no_bukti_potong = $data[4];
                                
                                // Convert tanggal jika perlu
                                $tanggal_bupot_raw = $data[5];
                                $tanggal_bupot = '';

                                // Cek jika tanggal dalam format numerik atau string
                                if (is_numeric($tanggal_bupot_raw)) {
                                    try {
                                        $tanggal_bupot_obj = Date::excelToDateTimeObject($tanggal_bupot_raw);
                                        if ($tanggal_bupot_obj) {
                                            $tanggal_bupot = $tanggal_bupot_obj->format('Y-m-d');
                                        } else {
                                            $error_message = "Gagal mengonversi tanggal dari format Excel.";
                                        }
                                    } catch (Exception $e) {
                                        $error_message = "Gagal mengonversi tanggal dari format Excel: " . $e->getMessage();
                                    }
                                } else {
                                    try {
                                        $tanggal_bupot_obj = DateTime::createFromFormat('d-m-Y', $tanggal_bupot_raw);
                                        if (!$tanggal_bupot_obj) {
                                            $tanggal_bupot_obj = DateTime::createFromFormat('m/d/Y', $tanggal_bupot_raw);
                                        }
                                        if ($tanggal_bupot_obj) {
                                            $tanggal_bupot = $tanggal_bupot_obj->format('Y-m-d');
                                        } else {
                                            $error_message = "Gagal mengonversi tanggal dari format string.";
                                        }
                                    } catch (Exception $e) {
                                        $error_message = "Gagal mengonversi tanggal dari format string: " . $e->getMessage();
                                    }
                                }

                                if (empty($tanggal_bupot)) {
                                    // Menyimpan data yang tidak valid dalam file log untuk debug
                                    file_put_contents('error_log.txt', "Tanggal BUPOT tidak valid: $tanggal_bupot_raw\n", FILE_APPEND);
                                    continue;
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

                                // Debugging: Cek apakah $file_pdf ditemukan
                                if (empty($file_pdf)) {
                                    file_put_contents('error_log.txt', "File PDF tidak ditemukan untuk no_bukti_potong: $no_bukti_potong\n", FILE_APPEND);
                                    continue;
                                }

                                $npwp_pemotong = $data[2];
                                $sql_check_npwp = "SELECT npwp FROM master_perusahaan WHERE npwp = ?";
                                $stmt_check_npwp = $conn->prepare($sql_check_npwp);
                                if ($stmt_check_npwp === false) {
                                    die('Error preparing check npwp statement: ' . $conn->error);
                                }
                                $stmt_check_npwp->bind_param('s', $npwp_pemotong);
                                $stmt_check_npwp->execute();
                                $result_check_npwp = $stmt_check_npwp->get_result();

                                if ($result_check_npwp->num_rows == 0) {
                                    $nama_perusahaan = $data[3];
                                    $sql_insert_npwp = "INSERT INTO master_perusahaan (npwp, nama_perusahaan) VALUES (?, ?)";
                                    $stmt_insert_npwp = $conn->prepare($sql_insert_npwp);
                                    if ($stmt_insert_npwp === false) {
                                        die('Error preparing insert npwp statement: ' . $conn->error);
                                    }
                                    $stmt_insert_npwp->bind_param('ss', $npwp_pemotong, $nama_perusahaan);
                                    $stmt_insert_npwp->execute();
                                }

                                $sql_check_no_bukti = "SELECT * FROM data_upload WHERE no_bukti = ?";
                                $stmt_check_no_bukti = $conn->prepare($sql_check_no_bukti);
                                if ($stmt_check_no_bukti === false) {
                                    die('Error preparing check no_bukti statement: ' . $conn->error);
                                }
                                $stmt_check_no_bukti->bind_param('s', $no_bukti_potong);
                                $stmt_check_no_bukti->execute();
                                $result_check_no_bukti = $stmt_check_no_bukti->get_result();

                                if ($result_check_no_bukti->num_rows > 0) {
                                    // Data duplikat, lanjutkan ke baris berikutnya
                                    continue;
                                }

                                $sql = "INSERT INTO data_upload 
                                        (no_bukti, tanggal_bukti, npwp_pemotong, nama_pemotong, identitas_penerima, nama_penerima, penghasilan_bruto, pph, kode_objek_pajak, file_pdf) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                        tanggal_bukti = VALUES(tanggal_bukti),
                                        npwp_pemotong = VALUES(npwp_pemotong),
                                        nama_pemotong = VALUES(nama_pemotong),
                                        identitas_penerima = VALUES(identitas_penerima),
                                        nama_penerima = VALUES(nama_penerima),
                                        penghasilan_bruto = VALUES(penghasilan_bruto),
                                        pph = VALUES(pph),
                                        kode_objek_pajak = VALUES(kode_objek_pajak),
                                        file_pdf = VALUES(file_pdf)";
                                $stmt = $conn->prepare($sql);

                                if ($stmt === false) {
                                    die('Error preparing statement: ' . $conn->error);
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

                                // Debugging: Cek query dan parameter
                                echo "Query: $sql\n";
                                echo "Parameters: $no_bukti_potong, $tanggal_bupot, $npwp_pemotong, $nama, $pasal, $pasal, $jumlah_bruto, $pph_dipotong, $kode_objek_pajak, $file_pdf\n";

                                if (!$stmt->execute()) {
                                    file_put_contents('error_log.txt', "Error executing statement: " . $stmt->error . "\n", FILE_APPEND);
                                } else {
                                    $uploaded++;
                                }
                            }
                        }

                        // Commit transaksi
                        $conn->commit();
                    } catch (Exception $e) {
                        // Rollback transaksi jika terjadi kesalahan
                        $conn->rollback();
                        file_put_contents('error_log.txt', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
                    }

                    echo "$uploaded data berhasil diupload dan disimpan.";
                } else {
                    $error_message = 'Gagal membuka file zip.';
                    file_put_contents('error_log.txt', $error_message . "\n", FILE_APPEND);
                }
            } else {
                $error_message = 'Gagal mengupload file zip.';
                file_put_contents('error_log.txt', $error_message . "\n", FILE_APPEND);
            }
        } else {
            $error_message = 'File upload error: ' . $_FILES['zip_file']['error'];
            file_put_contents('error_log.txt', $error_message . "\n", FILE_APPEND);
        }
    }
}
?>
