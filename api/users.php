<?php
require 'db.php';

// ป้องกัน: ต้องเป็นผู้ดูแลระบบที่ login แล้วเท่านั้นถึงจะใช้งานได้
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'read':
            // สำคัญ: ห้าม SELECT password hash มาเด็ดขาด
            $stmt = $pdo->query("SELECT id, username, full_name, created_at FROM users ORDER BY username ASC");
            $users = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'create':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $full_name = $_POST['full_name'] ?? '';

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

            $sql = "INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashedPassword, $full_name]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มผู้ใช้งานสำเร็จ']);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $full_name = $_POST['full_name'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!$id || empty($full_name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
                exit;
            }

            // ถ้ามีการส่งรหัสผ่านใหม่มาด้วย ให้ทำการอัปเดต
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name = ?, password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $hashedPassword, $id]);
            } else { // ถ้าไม่ส่งรหัสผ่านใหม่มา ก็อัปเดตแค่ชื่อ
                $sql = "UPDATE users SET full_name = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $id]);
            }
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