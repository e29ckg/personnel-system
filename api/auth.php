<?php
// ไฟล์ api/auth.php

require 'db.php';

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            require_once 'logger.php';
            log_activity($pdo, 'login_success');

            echo json_encode([
                'success' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()]);
    }

} elseif ($action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'loggedIn' => false]);
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}

?>