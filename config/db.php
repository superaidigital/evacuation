<?php
// config/db.php

$host = 'localhost';      // ชื่อ Host ปกติจะเป็น localhost
$dbname = 'evacuation_db'; // ชื่อฐานข้อมูลที่เราสร้าง
$username = 'root';       // ชื่อผู้ใช้ MySQL (XAMPP ปกติคือ root)
$password = '';           // รหัสผ่าน MySQL (XAMPP ปกติจะเป็นค่าว่าง)

try {
    // สร้างการเชื่อมต่อด้วย PDO (ปลอดภัยและทันสมัยกว่า mysqli)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // ตั้งค่าให้แสดง Error หากเกิดปัญหา SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // หากเชื่อมต่อไม่ได้ ให้แสดงข้อความ
    die("Connection failed: " . $e->getMessage());
}

// ตั้งค่า Timezone เป็นไทย
date_default_timezone_set('Asia/Bangkok');

// เริ่ม Session สำหรับระบบ Login ทุกครั้งที่มีการเรียกใช้ไฟล์นี้
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>