<?php
// tests/AuthTest.php

use PHPUnit\Framework\TestCase;

// เราจะใช้ PDO เพื่อจัดการ test database
class AuthTest extends TestCase
{
    private static $pdo;
    private static $dotenv;

    // ฟังก์ชันนี้จะทำงานครั้งเดียวก่อนที่เทสต์ในคลาสนี้จะเริ่ม
    public static function setUpBeforeClass(): void
    {
        // โหลด .env.testing
        self::$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env.testing');
        self::$dotenv->load();

        // เชื่อมต่อกับ Test Database
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];

        self::$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    }

    // ฟังก์ชันนี้จะทำงานก่อนทุกๆ test case
    protected function setUp(): void
    {
        // ล้างข้อมูลตาราง users ก่อนทุกเทสต์ เพื่อให้แน่ใจว่าเทสต์ไม่เกี่ยวข้องกัน
        self::$pdo->exec("TRUNCATE TABLE users");
        // รีเซ็ต session
        $_SESSION = [];
    }

    // Test Case 1: ทดสอบการ Login ด้วยข้อมูลที่ถูกต้อง
    public function testLoginWithValidCredentials(): void
    {
        // 1. Arrange: เตรียมข้อมูล
        $username = 'admin';
        $password = 'admin';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // เพิ่ม user จำลองลงใน test database
        $stmt = self::$pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, 'Test User', 'admin']);

        // จำลองการส่งข้อมูลแบบ POST
        $_POST['username'] = $username;
        $_POST['password'] = $password;

        // 2. Act: เรียกใช้โค้ดที่ต้องการทดสอบ
        // ใช้ output buffering เพื่อดักจับผลลัพธ์ (echo)
        ob_start();
        include __DIR__ . '/../api/auth.php'; // เรียกใช้ auth.php
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // 3. Assert: ตรวจสอบผลลัพธ์
        $this->assertTrue($response['success']);
        $this->assertEquals('เข้าสู่ระบบสำเร็จ', $response['message']);
        $this->assertEquals($username, $_SESSION['username']);
        $this->assertEquals('admin', $_SESSION['role']);
    }

    // Test Case 2: ทดสอบการ Login ด้วยรหัสผ่านที่ผิด
    public function testLoginWithInvalidPassword(): void
    {
        // 1. Arrange
        $username = 'admin';
        $hashedPassword = password_hash('correct_password', PASSWORD_DEFAULT);
        $stmt = self::$pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, 'Test User']);

        $_POST['username'] = $username;
        $_POST['password'] = 'wrong_password';

        // 2. Act
        ob_start();
        include __DIR__ . '/../api/auth.php';
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // 3. Assert
        $this->assertFalse($response['success']);
        $this->assertArrayNotHasKey('user_id', $_SESSION); // ตรวจสอบว่า Session ไม่ถูกสร้าง
    }
}