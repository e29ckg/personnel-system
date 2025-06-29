<?php
require 'db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ================== ส่วนของการตรวจสอบสิทธิ์ ==================
// ตรวจสอบว่าได้ Login แล้ว และมี role เป็น 'admin' เท่านั้น
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access. Admin role required.']);
    exit;
}

// รับค่า action จาก POST (สำหรับ create/update ที่มีไฟล์) หรือ GET (สำหรับ read)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$uploadDir = '../uploads/'; // ระบุตำแหน่งโฟลเดอร์สำหรับอัปโหลดไฟล์ (จาก api/ ไปที่ uploads/)

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
            // 1. รับค่า Parameters จาก Frontend
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $search = $_GET['search'] ?? '';
            $sortKey = $_GET['sortKey'] ?? 'id';
            $sortOrder = isset($_GET['sortOrder']) && strtolower($_GET['sortOrder']) === 'asc' ? 'ASC' : 'DESC';

            // 2. ป้องกัน SQL Injection สำหรับการเรียงข้อมูล
            // สร้าง Whitelist ของคอลัมน์ที่อนุญาตให้เรียงข้อมูลได้
            $allowedSortKeys = ['id', 'first_name', 'position', 'age'];
            if (!in_array($sortKey, $allowedSortKeys)) {
                $sortKey = 'id'; // ถ้าส่งคอลัมน์แปลกๆ มา ให้ใช้ id เป็น default
            }

            // 3. สร้าง Query สำหรับการค้นหา (WHERE clause)
            $whereClauses = [];
            $params = [];
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                // เพิ่มเงื่อนไขการค้นหา
                $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR position LIKE ?)";
                // เพิ่ม parameter สำหรับ bind
                array_push($params, $searchTerm, $searchTerm, $searchTerm);
            }
            $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            // 4. Query เพื่อนับจำนวนข้อมูลทั้งหมด (สำหรับคำนวณหน้า)
            $countSql = "SELECT COUNT(id) FROM personnel " . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();

            // 5. Query เพื่อดึงข้อมูลเฉพาะหน้าที่ต้องการ
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

            // 6. ส่งข้อมูลกลับไปในรูปแบบใหม่ ที่มีทั้งข้อมูลและจำนวนทั้งหมด
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
            // จัดการไฟล์รูปภาพก่อน
            $imageFileName = handleFileUpload($_FILES['profile_image'] ?? null, $uploadDir);

            // เตรียมคำสั่ง SQL (ใช้เครื่องหมาย ? เพื่อความปลอดภัย)
            $sql = "INSERT INTO personnel (
                        national_id, `rank`, first_name, last_name, position, position_number, 
                        salary_rate, date_of_birth, education, phone_number, 
                        addr_houseno, addr_moo, addr_tambon, addr_amphoe, addr_changwat, addr_postalcode,
                        appointment_unit, appointment_order, appointment_date, 
                        position_start_date, position_end_date, term_years,
                        retirement_year, remarks, profile_image
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            // ส่งค่าจาก $_POST เข้าไปใน execute (ใช้ ?? null เพื่อป้องกัน error กรณีไม่มีค่า)
            $stmt->execute([
                $_POST['national_id'] ?? null,
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

            // ดึงชื่อไฟล์รูปเก่าจากฐานข้อมูล
            $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
            $stmt->execute([$id]);
            $oldImage = $stmt->fetchColumn();
            $imageFileName = $oldImage; // กำหนดให้ใช้รูปเก่าเป็นค่าเริ่มต้น

            // ถ้ามีการอัปโหลดไฟล์ใหม่เข้ามา
            if (isset($_FILES['profile_image'])) {
                $newImageFileName = handleFileUpload($_FILES['profile_image'], $uploadDir);
                if ($newImageFileName) {
                    deleteOldFile($oldImage, $uploadDir); // ลบไฟล์เก่าทิ้ง
                    $imageFileName = $newImageFileName; // อัปเดตเป็นชื่อไฟล์ใหม่
                }
            }

            $sql = "UPDATE personnel SET 
                        national_id = ?, `rank` = ?, first_name = ?, last_name = ?, position = ?, position_number = ?, 
                        salary_rate = ?, date_of_birth = ?, education = ?, phone_number = ?, 
                        addr_houseno = ?, addr_moo = ?, addr_tambon = ?, addr_amphoe = ?, addr_changwat = ?, addr_postalcode = ?,
                        appointment_unit = ?, appointment_order = ?, appointment_date = ?, 
                        position_start_date = ?, position_end_date = ?, term_years = ?,
                        retirement_year = ?, remarks = ?, profile_image = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $_POST['national_id'] ?? null,
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

            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
            break;

        /**
         * ลบข้อมูลบุคลากร
         */
        case 'delete':
            // การลบรับค่าเป็น JSON เพราะส่งมาแค่ ID
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ ID สำหรับลบ']);
                exit;
            }

            // ดึงชื่อไฟล์รูปแล้วลบไฟล์ออกจากเซิร์ฟเวอร์ก่อน
            $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
            $stmt->execute([$id]);
            $imageToDelete = $stmt->fetchColumn();
            deleteOldFile($imageToDelete, $uploadDir);

            // ลบข้อมูลออกจากฐานข้อมูล
            $stmt = $pdo->prepare("DELETE FROM personnel WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    // ใน Production จริง ไม่ควรแสดง error message ให้ user เห็น
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}