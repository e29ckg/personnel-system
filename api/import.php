<?php
// api/import.php (สำหรับไฟล์ CSV รูปแบบใหม่)

require 'db.php';
require 'config.php';

// ================== ฟังก์ชันเข้ารหัสและตรวจสอบสิทธิ์ (เหมือนเดิม) ==================
function encrypt_data($data)
{
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access.']);
    exit;
}

// ================== ฟังก์ชันใหม่สำหรับแปลงวันที่ พ.ศ. เป็น ค.ศ. ==================
function parseThaiDate($thaiDateStr)
{
    if (empty($thaiDateStr) || strpos($thaiDateStr, '- / - / -') !== false) {
        return null;
    }
    $thaiMonths = [
        'ม.ค.' => '01',
        'ก.พ.' => '02',
        'มี.ค.' => '03',
        'เม.ย.' => '04',
        'พ.ค.' => '05',
        'มิ.ย.' => '06',
        'ก.ค.' => '07',
        'ส.ค.' => '08',
        'ก.ย.' => '09',
        'ต.ค.' => '10',
        'พ.ย.' => '11',
        'ธ.ค.' => '12'
    ];

    $parts = explode(' ', $thaiDateStr);
    if (count($parts) !== 3) {
        return null;
    }

    $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    $month = $thaiMonths[$parts[1]] ?? '00';
    $buddhistYear = (int) $parts[2];
    $gregorianYear = $buddhistYear - 543;

    return "{$gregorianYear}-{$month}-{$day}";
}

// ================== ส่วนประมวลผลไฟล์ CSV ==================
if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {

    $csvFilePath = $_FILES['excel_file']['tmp_name'];
    $importedCount = 0;
    $skippedCount = 0;

    $pdo->beginTransaction();

    try {
        $sql = "INSERT IGNORE INTO personnel (
            first_name, last_name, `rank`, position, national_id, national_id_hash, 
            date_of_birth, position_start_date, position_end_date, term_years,
            addr_moo, addr_villagename, addr_tambon, addr_amphoe, addr_changwat,
            education, phone_number, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $stmt = $pdo->prepare($sql);

        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {

            stream_filter_append($handle, 'convert.iconv.UTF-8/UTF-8');

            // ข้าม 2 แถวแรก (หัวข้อหลัก และหัวข้อตาราง)
            fgetcsv($handle);
            fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== FALSE) {
                // --- การจับคู่คอลัมน์ CSV กับตัวแปร ---
                $fullNameString = isset($data[1]) ? trim($data[1]) : '';
                $position = isset($data[2]) ? trim($data[2]) : '';
                $dob = isset($data[3]) ? parseThaiDate(trim($data[3])) : null;
                $geoCode = isset($data[5]) ? trim($data[5]) : ''; // ใช้เป็น national_id ชั่วคราว
                $addr_moo = isset($data[6]) ? trim($data[6]) : '';
                $addr_villagename = isset($data[7]) ? trim($data[7]) : '';
                $addr_tambon = isset($data[8]) ? trim($data[8]) : '';
                $addr_amphoe = isset($data[9]) ? trim($data[9]) : '';
                $addr_changwat = isset($data[10]) ? trim($data[10]) : '';
                $position_start_date = isset($data[11]) ? parseThaiDate(trim($data[11])) : null;
                $position_end_date = isset($data[12]) ? parseThaiDate(trim($data[12])) : null;
                $term_string = isset($data[13]) ? trim($data[13]) : '';
                $education = isset($data[14]) ? trim($data[14]) : '';
                $phone_number = isset($data[15]) ? trim($data[15]) : '';

                // --- ตรวจสอบข้อมูลเบื้องต้น ---
                if (empty($geoCode) || empty($fullNameString)) {
                    $skippedCount++;
                    continue;
                }

                // --- จัดการข้อมูลที่ซับซ้อน ---
                // แยกชื่อ
                $rank = '';
                $firstName = '';
                $lastName = '';
                $nameParts = explode(' ', $fullNameString, 3);
                $prefix = $nameParts[0];
                if (in_array($prefix, ['นาย', 'นาง', 'นางสาว'])) {
                    $rank = $prefix;
                    $firstName = $nameParts[1] ?? '';
                    $lastName = $nameParts[2] ?? '';
                } else {
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';
                }

                // แยกวาระ
                $term_years = null;
                $remarks = '';
                if (strpos($term_string, 'ปี') !== false) {
                    preg_match('/(\d+)/', $term_string, $matches);
                    $term_years = isset($matches[1]) ? (int) $matches[1] : null;
                } else {
                    $remarks = $term_string;
                }

                // เข้ารหัสและ Hash เลขบัตร (โดยใช้ GeoCode แทน)
                $encrypted_nid = encrypt_data($geoCode);
                $nid_hash = hash('sha256', $geoCode);

                // เพิ่มข้อมูลลงฐานข้อมูล
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $rank,
                    $position,
                    $encrypted_nid,
                    $nid_hash,
                    $dob,
                    $position_start_date,
                    $position_end_date,
                    $term_years,
                    $addr_moo,
                    $addr_villagename,
                    $addr_tambon,
                    $addr_amphoe,
                    $addr_changwat,
                    $education,
                    $phone_number,
                    $remarks
                ]);

                if ($stmt->rowCount() > 0) {
                    $importedCount++;
                } else {
                    $skippedCount++;
                }
            }
            fclose($handle);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Import completed.',
            'imported' => $importedCount,
            'skipped' => $skippedCount,
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error processing file: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
}