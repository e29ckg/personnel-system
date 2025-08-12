<?php
require 'db.php';
require_once 'config.php';
require_once 'logger.php';

// ================== ส่วนของการตรวจสอบสิทธิ์ ==================
// ตรวจสอบว่าได้ Login แล้ว และมี role เป็น 'admin' เท่านั้น
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access. Admin role required.']);
    exit;
}

function encrypt_data($data)
{
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decrypt_data($data)
{
    // ใช้ strict mode เพื่อให้แน่ใจว่า base64 string ถูกต้อง
    $decoded_data = base64_decode($data, true);

    // ตรวจสอบว่าข้อมูลสามารถ decode ได้ และมีตัวคั่น '::' หรือไม่
    if ($decoded_data === false || strpos($decoded_data, '::') === false) {
        // ถ้าไม่ถูกต้อง แสดงว่าเป็นข้อมูลเก่า/ผิดรูปแบบ ให้ส่งค่าเดิมกลับไป
        return $data;
    }

    // ใช้ @ เพื่อซ่อน Notice กรณีที่ข้อมูลผิดรูปแบบจริงๆ
    @list($iv, $encrypted_data) = explode('::', $decoded_data, 2);

    // --- เพิ่มการตรวจสอบความยาวของ IV (สำคัญที่สุด) ---
    // ตรวจสอบว่า IV ที่แยกออกมาได้ มีความยาวตรงตามที่ cipher ต้องการหรือไม่
    if (strlen($iv) !== openssl_cipher_iv_length(ENCRYPTION_CIPHER)) {
        // ถ้าความยาวไม่ถูกต้อง ให้คืนค่าเป็นข้อความแจ้งข้อผิดพลาด หรือค่าว่าง
        // เพื่อให้รู้ว่าข้อมูลรายการนี้มีปัญหา
        error_log("Decryption failed: Invalid IV length for target data.");
        return "[ข้อมูลเสียหาย]"; // หรือ return '';
    }
    // --- จบส่วนที่เพิ่มเข้ามา ---

    // ถ้าทุกอย่างถูกต้อง ให้ทำการถอดรหัส
    $decrypted_data = openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);

    // คืนค่าที่ถอดรหัสแล้ว หรือค่าว่างถ้าการถอดรหัสล้มเหลว
    return $decrypted_data === false ? "" : $decrypted_data;
}

// รับค่า action จาก POST (สำหรับ create/update ที่มีไฟล์) หรือ GET (สำหรับ read)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$uploadDir = '../uploads/';

// ================== ฟังก์ชันเสริม ==================

/**
 * จัดการการอัปโหลดไฟล์รูปภาพ
 * @param array|null $file - ข้อมูลไฟล์จาก $_FILES
 * @param string $uploadDir - โฟลเดอร์ที่จะบันทึกไฟล์
 * @return string|null - คืนค่าชื่อไฟล์ที่บันทึกสำเร็จ หรือ null ถ้าล้มเหลว
 */
function handleFileUpload($file, $uploadDir)
{
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกันเพื่อป้องกันการเขียนทับ
        $fileName = uniqid('personnel_', true) . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;

        // ย้ายไฟล์ที่อัปโหลดไปยังโฟลเดอร์ปลายทาง
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $fileName;
        }
    }
    return null;
}

/**
 * ลบไฟล์เก่าออกจากเซิร์ฟเวอร์
 * @param string|null $filename - ชื่อไฟล์ที่ต้องการลบ
 * @param string $uploadDir - โฟลเดอร์ที่เก็บไฟล์
 */
function deleteOldFile($filename, $uploadDir)
{
    if ($filename && file_exists($uploadDir . $filename)) {
        unlink($uploadDir . $filename);
    }
}

// ================== ส่วนหลัก (Controller) ==================

try {
    switch ($action) {
        /**
         * ====================================================================
         * อ่านข้อมูลแบบแบ่งหน้าจาก Backend (Server-Side Pagination)
         * ====================================================================
         */
        case 'read':
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $search = $_GET['search'] ?? '';
            $sortKey = $_GET['sortKey'] ?? 'id';
            $sortOrder = isset($_GET['sortOrder']) && strtolower($_GET['sortOrder']) === 'asc' ? 'ASC' : 'DESC';

            $allowedSortKeys = ['id', 'first_name', 'position', 'age'];
            if (!in_array($sortKey, $allowedSortKeys)) {
                $sortKey = 'id'; // ถ้าส่งคอลัมน์แปลกๆ มา ให้ใช้ id เป็น default
            }

            $whereClauses = [];
            $params = [];
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR position LIKE ?)";
                array_push($params, $searchTerm, $searchTerm, $searchTerm);
            }
            $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            $countSql = "SELECT COUNT(id) FROM personnel " . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();

            $offset = ($page - 1) * $limit;
            $dataSql = "
                SELECT 
                    p.*, 
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age
                FROM personnel p
                {$whereSql}
                ORDER BY {$sortKey} {$sortOrder}
                LIMIT {$limit} OFFSET {$offset}
            ";

            $dataStmt = $pdo->prepare($dataSql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();

            foreach ($data as $key => $row) {
                if (!empty($row['national_id'])) {
                    $data[$key]['national_id'] = decrypt_data($row['national_id']);
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $data,
                'totalRecords' => (int) $totalRecords
            ]);
            break;
        /**
         * สร้างข้อมูลบุคลากรใหม่
         */
        case 'create':
            $imageFileName = handleFileUpload($_FILES['profile_image'] ?? null, $uploadDir);

            $sql = "INSERT INTO personnel (
                        national_id, national_id_hash, `rank`, first_name, last_name, position, position_number, 
                        salary_rate, date_of_birth, education, phone_number, 
                        addr_houseno, addr_moo, addr_tambon, addr_amphoe, addr_changwat, addr_postalcode,
                        appointment_unit, appointment_order, appointment_date, 
                        position_start_date, position_end_date, term_years,
                        retirement_year, remarks, profile_image
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                encrypt_data($_POST['national_id'] ?? null),
                hash('sha256', $_POST['national_id'] ?? ''),
                $_POST['rank'] ?? null,
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                $_POST['position'] ?? null,
                $_POST['position_number'] ?? null,
                $_POST['salary_rate'] ?? null,
                empty($_POST['date_of_birth']) ? null : $_POST['date_of_birth'],
                $_POST['education'] ?? null,
                $_POST['phone_number'] ?? null,
                $_POST['addr_houseno'] ?? null,
                $_POST['addr_moo'] ?? null,
                $_POST['addr_tambon'] ?? null,
                $_POST['addr_amphoe'] ?? null,
                $_POST['addr_changwat'] ?? null,
                $_POST['addr_postalcode'] ?? null,
                $_POST['appointment_unit'] ?? null,
                $_POST['appointment_order'] ?? null,
                empty($_POST['appointment_date']) ? null : $_POST['appointment_date'],
                empty($_POST['position_start_date']) ? null : $_POST['position_start_date'],
                empty($_POST['position_end_date']) ? null : $_POST['position_end_date'],
                empty($_POST['term_years']) ? null : $_POST['term_years'],
                empty($_POST['retirement_year']) ? null : $_POST['retirement_year'],
                $_POST['remarks'] ?? null,
                $imageFileName
            ]);
            $lastId = $pdo->lastInsertId();
            log_activity($pdo, 'create_personnel', $lastId);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลสำเร็จ']);
            break;

        /**
         * อัปเดตข้อมูลบุคลากร
         */
        case 'update':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing ID for update.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
            $stmt->execute([$id]);
            $oldImage = $stmt->fetchColumn();
            $imageFileName = $oldImage; // กำหนดให้ใช้รูปเก่าเป็นค่าเริ่มต้น

            if (isset($_FILES['profile_image'])) {
                $newImageFileName = handleFileUpload($_FILES['profile_image'], $uploadDir);
                if ($newImageFileName) {
                    deleteOldFile($oldImage, $uploadDir); // ลบไฟล์เก่าทิ้ง
                    $imageFileName = $newImageFileName; // อัปเดตเป็นชื่อไฟล์ใหม่
                }
            }

            $sql = "UPDATE personnel SET 
                        national_id = ?,  national_id_hash = ?, `rank` = ?, first_name = ?, last_name = ?, position = ?, position_number = ?, 
                        salary_rate = ?, date_of_birth = ?, education = ?, phone_number = ?, 
                        addr_houseno = ?, addr_moo = ?, addr_tambon = ?, addr_amphoe = ?, addr_changwat = ?, addr_postalcode = ?,
                        appointment_unit = ?, appointment_order = ?, appointment_date = ?, 
                        position_start_date = ?, position_end_date = ?, term_years = ?,
                        retirement_year = ?, remarks = ?, profile_image = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                encrypt_data($_POST['national_id'] ?? null),
                hash('sha256', $_POST['national_id'] ?? ''),
                $_POST['rank'] ?? null,
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                $_POST['position'] ?? null,
                $_POST['position_number'] ?? null,
                $_POST['salary_rate'] ?? null,
                empty($_POST['date_of_birth']) ? null : $_POST['date_of_birth'],
                $_POST['education'] ?? null,
                $_POST['phone_number'] ?? null,
                $_POST['addr_houseno'] ?? null,
                $_POST['addr_moo'] ?? null,
                $_POST['addr_tambon'] ?? null,
                $_POST['addr_amphoe'] ?? null,
                $_POST['addr_changwat'] ?? null,
                $_POST['addr_postalcode'] ?? null,
                $_POST['appointment_unit'] ?? null,
                $_POST['appointment_order'] ?? null,
                empty($_POST['appointment_date']) ? null : $_POST['appointment_date'],
                empty($_POST['position_start_date']) ? null : $_POST['position_start_date'],
                empty($_POST['position_end_date']) ? null : $_POST['position_end_date'],
                empty($_POST['term_years']) ? null : $_POST['term_years'],
                empty($_POST['retirement_year']) ? null : $_POST['retirement_year'],
                $_POST['remarks'] ?? null,
                $imageFileName,
                $id // ID สำหรับ WHERE clause
            ]);
            log_activity($pdo, 'update_personnel', $id);
            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
            break;

        /**
         * ลบข้อมูลบุคลากร
         */
        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ ID สำหรับลบ']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
            $stmt->execute([$id]);
            $imageToDelete = $stmt->fetchColumn();
            deleteOldFile($imageToDelete, $uploadDir);

            $stmt = $pdo->prepare("DELETE FROM personnel WHERE id = ?");
            $stmt->execute([$id]);
            log_activity($pdo, 'delete_personnel', $id);
            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}