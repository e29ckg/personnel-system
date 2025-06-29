<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? ''; // รับ action จาก POST หรือ GET
$uploadDir = '../uploads/'; // ระบุตำแหน่งโฟลเดอร์ uploads

// --- ฟังก์ชันสำหรับจัดการการอัปโหลดไฟล์ ---
function handleFileUpload($file, $uploadDir)
{
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $fileName = uniqid('personnel_', true) . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $fileName; // คืนค่าชื่อไฟล์ที่บันทึกสำเร็จ
        }
    }
    return null; // คืนค่า null หากไม่มีไฟล์หรืออัปโหลดไม่สำเร็จ
}

// --- ฟังก์ชันสำหรับลบไฟล์เก่า ---
function deleteOldFile($filename, $uploadDir)
{
    if ($filename && file_exists($uploadDir . $filename)) {
        unlink($uploadDir . $filename);
    }
}


switch ($action) {
    case 'read':
        // ส่วนนี้ยังเหมือนเดิม
        $stmt = $pdo->query('
            SELECT 
                p.*, 
                TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age
            FROM personnel p 
            ORDER BY p.id DESC'
        );
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'create':
        $imageFileName = handleFileUpload($_FILES['profile_image'] ?? null, $uploadDir);

        // เปลี่ยนจากการรับ JSON มาเป็น $_POST
        $sql = "INSERT INTO personnel (national_id, `rank`, first_name, last_name, position, education, phone_number, addr_houseno, addr_moo, addr_tambon, addr_amphoe, addr_changwat, addr_postalcode, date_of_birth, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['national_id'] ?? null,
            $_POST['rank'] ?? null,
            $_POST['first_name'] ?? null,
            $_POST['last_name'] ?? null,
            $_POST['position'] ?? null,
            $_POST['education'] ?? null,
            $_POST['phone_number'] ?? null,
            $_POST['addr_houseno'] ?? null,
            $_POST['addr_moo'] ?? null,
            $_POST['addr_tambon'] ?? null,
            $_POST['addr_amphoe'] ?? null,
            $_POST['addr_changwat'] ?? null,
            $_POST['addr_postalcode'] ?? null,
            empty($_POST['date_of_birth']) ? null : $_POST['date_of_birth'],
            $imageFileName
        ]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลสำเร็จ']);
        break;

    case 'update':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing ID for update.']);
            exit;
        }

        // ดึงชื่อไฟล์รูปเก่ามาก่อน
        $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();

        $imageFileName = $oldImage; // ใช้รูปเก่าเป็นค่าเริ่มต้น

        // ถ้ามีการอัปโหลดไฟล์ใหม่เข้ามา
        if (isset($_FILES['profile_image'])) {
            $newImageFileName = handleFileUpload($_FILES['profile_image'], $uploadDir);
            if ($newImageFileName) {
                deleteOldFile($oldImage, $uploadDir); // ลบไฟล์เก่าทิ้ง
                $imageFileName = $newImageFileName; // อัปเดตเป็นชื่อไฟล์ใหม่
            }
        }

        $sql = "UPDATE personnel SET national_id = ?, `rank` = ?, first_name = ?, last_name = ?, position = ?, education = ?, phone_number = ?, addr_houseno = ?, addr_moo = ?, addr_tambon = ?, addr_amphoe = ?, addr_changwat = ?, addr_postalcode = ?, date_of_birth = ?, profile_image = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['national_id'] ?? null,
            $_POST['rank'] ?? null,
            $_POST['first_name'] ?? null,
            $_POST['last_name'] ?? null,
            $_POST['position'] ?? null,
            $_POST['education'] ?? null,
            $_POST['phone_number'] ?? null,
            $_POST['addr_houseno'] ?? null,
            $_POST['addr_moo'] ?? null,
            $_POST['addr_tambon'] ?? null,
            $_POST['addr_amphoe'] ?? null,
            $_POST['addr_changwat'] ?? null,
            $_POST['addr_postalcode'] ?? null,
            empty($_POST['date_of_birth']) ? null : $_POST['date_of_birth'],
            $imageFileName,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
        break;

    case 'delete':
        // รับข้อมูลจาก JSON เพราะส่งมาแค่ id
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ ID สำหรับลบ']);
            exit;
        }

        // ดึงชื่อไฟล์รูปแล้วลบไฟล์ทิ้ง
        $stmt = $pdo->prepare("SELECT profile_image FROM personnel WHERE id = ?");
        $stmt->execute([$id]);
        $imageToDelete = $stmt->fetchColumn();
        deleteOldFile($imageToDelete, $uploadDir);

        // ลบข้อมูลในตาราง
        $stmt = $pdo->prepare("DELETE FROM personnel WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}