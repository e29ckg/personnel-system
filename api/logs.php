<?php
// api/logs.php
require 'db.php';
// ... (คัดลอกโค้ด Server-Side Pagination จาก api/users.php หรือ personnel.php มาใช้ได้เลย) ...
// แต่ให้เปลี่ยน Query เป็นการ SELECT จากตาราง activity_logs
// และเปลี่ยน Whitelist ของ sortKey เป็น ['id', 'username', 'action', 'timestamp']


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
            $allowedSortKeys = ['id', 'username', 'action', 'timestamp'];
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
            $countSql = "SELECT COUNT(id) FROM activity_logs " . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();

            // 5. Query เพื่อดึงข้อมูลผู้ใช้เฉพาะหน้าที่ต้องการ (ห้าม SELECT password)
            $offset = ($page - 1) * $limit;
            $dataSql = "
                SELECT *
                FROM activity_logs
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






        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}