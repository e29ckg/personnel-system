<?php
require 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'provinces') {
        $stmt = $pdo->query("SELECT * FROM thai_provinces ORDER BY name_th");
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'amphures' && isset($_GET['province_id'])) {
        $province_id = (int) $_GET['province_id'];
        $stmt = $pdo->prepare("SELECT * FROM thai_amphures WHERE province_id = ? ORDER BY name_th");
        $stmt->execute([$province_id]);
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'amphures' && isset($_GET['name_th'])) {
        $name_th = $_GET['name_th'];
        $stmt = $pdo->prepare("SELECT * FROM thai_amphures WHERE name_th = ? ORDER BY name_th");
        $stmt->execute([$name_th]);
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'tambons' && isset($_GET['amphure_id'])) {
        $amphure_id = (int) $_GET['amphure_id'];
        // ในฐานข้อมูลนี้ ตาราง districts คือ ตำบล
        $stmt = $pdo->prepare("SELECT * FROM thai_tambons WHERE amphure_id = ? ORDER BY name_th");
        $stmt->execute([$amphure_id]);
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'tambons') {
        // ในฐานข้อมูลนี้ ตาราง districts คือ ตำบล
        $stmt = $pdo->prepare("SELECT * FROM thai_tambons WHERE 1 ORDER BY name_th");
        $stmt->execute();
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action or missing parameters.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}