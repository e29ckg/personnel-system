<?php
header("Content-Type: application/json; charset=UTF-8");

$host = 'localhost';
$dbname = 'supervisor';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // ใน Production จริง ไม่ควรแสดง error นี้ให้ user เห็น
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// เริ่ม session สำหรับระบบ login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}