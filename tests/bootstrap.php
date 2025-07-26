<?php
// tests/bootstrap.php

// เรียกใช้ Composer Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// เริ่ม Session เพื่อให้เราสามารถจำลองการ Login ในเทสต์ได้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}