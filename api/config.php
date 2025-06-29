<?php
// api/config.php
require_once __DIR__ . '/bootstrap.php';

define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY']);
define('ENCRYPTION_CIPHER', $_ENV['ENCRYPTION_CIPHER']);

/**
 * คุณสามารถสร้างคีย์ที่แข็งแกร่งได้โดยการรันโค้ดนี้ 1 ครั้งในไฟล์แยก
 * แล้วนำผลลัพธ์มาใส่ใน ENCRYPTION_KEY
 * * $strong_key = openssl_random_pseudo_bytes(32);
 * echo bin2hex($strong_key);
 * * ผลลัพธ์ที่ได้จะมีลักษณะแบบนี้: e.g., '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b'
 * คีย์นี้ต้องมีความยาว 32 bytes (64 ตัวอักษร hex) สำหรับ AES-256
 */
// $strong_key = openssl_random_pseudo_bytes(32);
// echo bin2hex($strong_key);