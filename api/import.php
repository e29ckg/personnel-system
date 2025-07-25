<?php
// api/import.php

// เรียกใช้ไฟล์ที่จำเป็นทั้งหมด
require 'db.php';
require 'config.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ================== ฟังก์ชันเข้ารหัส (จาก personnel.php) ==================
function encrypt_data($data)
{
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    // รวม iv กับข้อมูลที่เข้ารหัสแล้ว เพื่อใช้ในการถอดรหัส
    return base64_encode($iv . '::' . $encrypted);
}

// ================== การตรวจสอบสิทธิ์ Admin ==================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access.']);
    exit;
}

// ================== ส่วนประมวลผลไฟล์ ==================
if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {

    $tmpFilePath = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($tmpFilePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        $sql = "INSERT IGNORE INTO personnel (first_name, last_name, `rank`, position, national_id, national_id_hash, education, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $importedCount = 0;
        $skippedCount = 0;

        // เริ่มวนลูปตั้งแต่แถวที่ 2 (ข้ามหัวข้อ)
        for ($row = 2; $row <= $highestRow; $row++) {
            // --- การจับคู่คอลัมน์ Excel กับฐานข้อมูล (ปรับแก้ตามไฟล์ของคุณ) ---
            // B: ยศ-ชื่อ-สกุล
            $fullNameString = trim($worksheet->getCell('B' . $row)->getValue());
            // C: ตำแหน่ง
            $position = trim($worksheet->getCell('C' . $row)->getValue());
            // F: เลขประจำตัวประชาชน
            $national_id = trim($worksheet->getCell('F' . $row)->getValue());
            // P: เบอร์โทรศัพท์
            $phone_number = trim($worksheet->getCell('P' . $row)->getValue());
            // O: วุฒิการศึกษา
            $education = trim($worksheet->getCell('O' . $row)->getValue());

            // --- การจัดการข้อมูลเบื้องต้น ---
            if (empty($national_id) || empty($fullNameString)) {
                $skippedCount++;
                continue; // ข้ามแถวที่ข้อมูลหลักว่าง
            }

            // แยก ยศ, ชื่อ, นามสกุล
            $rank = '';
            $firstName = '';
            $lastName = '';
            // (นี่เป็น Logic การแยกชื่อแบบง่ายๆ อาจต้องปรับปรุงให้ดีขึ้น)
            $nameParts = explode(' ', $fullNameString, 3);
            if (count($nameParts) === 3) {
                list($rank, $firstName, $lastName) = $nameParts;
            } elseif (count($nameParts) === 2) {
                list($firstName, $lastName) = $nameParts;
            } else {
                $firstName = $fullNameString;
            }

            // เข้ารหัสและ Hash เลขบัตรประชาชน
            $encrypted_nid = encrypt_data($national_id);
            $nid_hash = hash('sha256', $national_id);

            // เพิ่มข้อมูลลงฐานข้อมูล (INSERT IGNORE จะข้ามถ้า national_id ซ้ำ)
            $stmt->execute([
                $firstName,
                $lastName,
                $rank,
                $position,
                $encrypted_nid,
                $nid_hash,
                $education,
                $phone_number
            ]);

            // ตรวจสอบว่ามีการเพิ่มข้อมูลจริงหรือไม่
            if ($stmt->rowCount() > 0) {
                $importedCount++;
            } else {
                $skippedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Import completed.',
            'imported' => $importedCount,
            'skipped' => $skippedCount,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error processing file: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
}
?>