<?php
// ไฟล์ logout.php

// เริ่ม session เพื่อที่จะทำลาย session ที่มีอยู่
session_start();

// ลบค่าทั้งหมดใน session
session_unset();

// ทำลาย session
session_destroy();

// ส่งผู้ใช้กลับไปที่หน้า login
header("Location: login.html");
exit;
?>