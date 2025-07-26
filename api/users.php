<?php
require 'db.php';
require_once 'logger.php';

// ป้องกัน: ต้องเป็นผู้ดูแลระบบที่ login แล้วเท่านั้นถึงจะใช้งานได้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'read':
            // 1. รับค่า Parameters
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $search = $_GET['search'] ?? '';
            $sortKey = $_GET['sortKey'] ?? 'id';
            $sortOrder = isset($_GET['sortOrder']) && strtolower($_GET['sortOrder']) === 'asc' ? 'ASC' : 'DESC';

            // 2. Whitelist สำหรับคอลัมน์ที่เรียงข้อมูลได้ (ป้องกัน SQL Injection)
            $allowedSortKeys = ['id', 'username', 'full_name', 'created_at'];
            if (!in_array($sortKey, $allowedSortKeys)) {
                $sortKey = 'id';
            }

            // 3. สร้าง Query สำหรับการค้นหา
            $whereClauses = [];
            $params = [];
            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $whereClauses[] = "(username LIKE ? OR full_name LIKE ?)";
                array_push($params, $searchTerm, $searchTerm);
            }
            $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            // 4. Query เพื่อนับจำนวนผู้ใช้ทั้งหมด
            $countSql = "SELECT COUNT(id) FROM users " . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();

            // 5. Query เพื่อดึงข้อมูลผู้ใช้เฉพาะหน้าที่ต้องการ (ห้าม SELECT password)
            $offset = ($page - 1) * $limit;
            $dataSql = "
                SELECT id, username, full_name, role, created_at
                FROM users
                {$whereSql}
                ORDER BY {$sortKey} {$sortOrder}
                LIMIT {$limit} OFFSET {$offset}
            ";

            $dataStmt = $pdo->prepare($dataSql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();

            // 6. ส่งข้อมูลกลับในรูปแบบใหม่
            echo json_encode([
                'success' => true,
                'data' => $data,
                'totalRecords' => (int) $totalRecords
            ]);
            break;

        case 'create':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $role = $_POST['role'] ?? 'member';

            if (empty($username) || empty($password) || empty($full_name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
                exit;
            }

            // ตรวจสอบว่า username ซ้ำหรือไม่
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'Username นี้มีผู้ใช้งานแล้ว']);
                exit;
            }

            // เข้ารหัสผ่านก่อนบันทึก
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashedPassword, $full_name, $role]);
            log_activity($pdo, 'create_user', $lastId);
            echo json_encode(['success' => true, 'message' => 'เพิ่มผู้ใช้งานสำเร็จ']);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $full_name = $_POST['full_name'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (!$id || empty($full_name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
                exit;
            }

            // ถ้ามีการส่งรหัสผ่านใหม่มาด้วย ให้ทำการอัปเดต
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name = ?, password = ?, role = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $hashedPassword, $role, $id]);
            } else { // ถ้าไม่ส่งรหัสผ่านใหม่มา ก็อัปเดตแค่ชื่อ
                $sql = "UPDATE users SET full_name = ?, role = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $role, $id]);
            }
            log_activity($pdo, 'update_user', $id);
            echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลผู้ใช้สำเร็จ']);
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ ID สำหรับลบ']);
                exit;
            }

            // ป้องกันผู้ใช้ลบบัญชีตัวเอง
            if ($id == $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'คุณไม่สามารถลบบัญชีของตัวเองได้']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            log_activity($pdo, 'delete_user', $id);
            echo json_encode(['success' => true, 'message' => 'ลบผู้ใช้งานสำเร็จ']);
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