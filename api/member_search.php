<?php
header("Content-Type: application/json; charset=UTF-8");
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
    exit;
}

$term = $_GET['term'] ?? '';

if (empty($term)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}
$term_hash = hash('sha256', $term);
$searchTerm = '%' . $term . '%';

// ใช้เครื่องหมาย ? แทน :term
$sql = "
    SELECT 
        id, `rank`, first_name, last_name, position, position_number,
        salary_rate, date_of_birth, education, phone_number,
        addr_houseno, addr_moo, addr_tambon, addr_amphoe, addr_changwat, addr_postalcode,
        appointment_unit, appointment_order, appointment_date,
        position_start_date, position_end_date, term_years,
        retirement_year, remarks, profile_image
    FROM 
        personnel
    WHERE
        first_name LIKE ?
        OR last_name LIKE ?
        OR phone_number LIKE ?
        OR CONCAT_WS(' ', addr_houseno, addr_moo, addr_tambon, addr_amphoe, addr_changwat, addr_postalcode) LIKE ?
        OR national_id_hash = ?
";

try {
    $stmt = $pdo->prepare($sql);

    // ส่งค่า $searchTerm เข้าไป 5 ครั้งให้ตรงกับจำนวน ?
    $stmt->execute([
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $term_hash
    ]);

    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    http_response_code(500);
    // หลังจากแก้ไขเสร็จแล้ว อย่าลืมลบ . $e->getMessage() ออกเพื่อความปลอดภัย
    echo json_encode(['success' => false, 'error' => 'Database query failed: ' . $e->getMessage()]);
}