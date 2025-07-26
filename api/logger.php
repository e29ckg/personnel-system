<?php
// api/logger.php

/**
 * บันทึกกิจกรรมของผู้ใช้ลงในฐานข้อมูล
 *
 * @param PDO $pdo - PDO connection object
 * @param string $action - ประเภทของกิจกรรม e.g., 'login', 'create_personnel'
 * @param int|null $target_id - ID ของข้อมูลที่ถูกกระทำ
 * @param string $details - รายละเอียดเพิ่มเติม
 */
function log_activity($pdo, $action, $target_id = null, $details = '')
{
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'System';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $sql = "INSERT INTO activity_logs (user_id, username, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $username, $action, $target_id, $details, $ip_address]);
    } catch (PDOException $e) {
        // ในระบบจริง อาจจะบันทึก error นี้ลงไฟล์แทน
        error_log('Log failed: ' . $e->getMessage());
    }
}