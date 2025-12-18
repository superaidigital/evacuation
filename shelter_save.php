<?php
// shelter_save.php
require_once 'config/db.php';

// 1. ตรวจสอบสิทธิ์ (ต้องเป็น ADMIN เท่านั้น)
// เริ่ม Session หากยังไม่มี
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: index.php");
    exit();
}

// 2. ตรวจสอบ Method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // รับค่าข้อมูลสถานที่
    $mode = $_POST['mode'];
    $name = trim($_POST['name']);
    $district = trim($_POST['district']);
    $province = trim($_POST['province']);
    $capacity = (int)$_POST['capacity'];
    $status = $_POST['status'];
    $current_event = trim($_POST['current_event']);

    // รับค่า Array ข้อมูลผู้ประสานงาน (ถ้าไม่มีส่งมา ให้เป็น array ว่าง)
    $coord_names = $_POST['coord_name'] ?? [];
    $coord_phones = $_POST['coord_phone'] ?? [];
    $coord_positions = $_POST['coord_pos'] ?? [];

    try {
        // เริ่ม Transaction (ทำงานเป็นชุด ถ้าพลาดจุดไหนให้ยกเลิกทั้งหมด)
        $pdo->beginTransaction();

        if ($mode == 'add') {
            // --- เพิ่มศูนย์ใหม่ (INSERT) ---
            $sql = "INSERT INTO shelters (name, district, province, capacity, status, current_event) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $district, $province, $capacity, $status, $current_event]);
            
            // รับ ID ของศูนย์ที่เพิ่งสร้าง เพื่อนำไปใช้กับตารางผู้ประสานงาน
            $shelter_id = $pdo->lastInsertId(); 

        } else {
            // --- แก้ไขศูนย์เดิม (UPDATE) ---
            $shelter_id = $_POST['id'];
            $sql = "UPDATE shelters SET 
                    name=?, district=?, province=?, capacity=?, status=?, current_event=? 
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $district, $province, $capacity, $status, $current_event, $shelter_id]);
        }

        // --- จัดการข้อมูลผู้ประสานงาน (Logic: ลบของเก่า -> เพิ่มของใหม่) ---
        // วิธีนี้ง่ายและปลอดภัยที่สุดสำหรับการแก้ไขข้อมูลแบบรายการ (List) ที่มีการเปลี่ยนแปลงบ่อย
        
        // 1. ลบผู้ประสานงานเดิมทั้งหมดของศูนย์นี้ออกก่อน (Reset)
        $del_coord = $pdo->prepare("DELETE FROM shelter_coordinators WHERE shelter_id = ?");
        $del_coord->execute([$shelter_id]);

        // 2. เตรียม SQL สำหรับเพิ่มใหม่
        $insert_coord = $pdo->prepare("INSERT INTO shelter_coordinators (shelter_id, name, phone, position) VALUES (?, ?, ?, ?)");
        
        // 3. วนลูปเพิ่มข้อมูลทีละคน ตามจำนวนข้อมูลที่ส่งมา
        for ($i = 0; $i < count($coord_names); $i++) {
            $c_name = trim($coord_names[$i]);
            $c_phone = trim($coord_phones[$i] ?? '');
            $c_pos = trim($coord_positions[$i] ?? '');

            // บันทึกเฉพาะแถวที่มีการกรอกชื่อ (ป้องกันแถวว่าง)
            if (!empty($c_name)) {
                $insert_coord->execute([$shelter_id, $c_name, $c_phone, $c_pos]);
            }
        }

        // ยืนยันการทำงานทั้งหมด (Commit) เมื่อทุกอย่างราบรื่น
        $pdo->commit();

        // กลับไปหน้ารายการ
        header("Location: shelter_list.php");
        exit();

    } catch (PDOException $e) {
        // ถ้าเกิด Error จุดใดจุดหนึ่ง ให้ยกเลิกการทำงานทั้งหมด (Rollback)
        $pdo->rollBack();
        
        // แสดง Error อย่างเป็นมิตร
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
        echo "<h2 style='color: #dc3545;'>เกิดข้อผิดพลาดในการบันทึกข้อมูล</h2>";
        echo "<p style='color: #6c757d;'>ระบบไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง</p>";
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; display: inline-block; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>Error Detail:</strong> " . $e->getMessage();
        echo "</div><br>";
        echo "<a href='javascript:history.back()' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>ย้อนกลับไปแก้ไข</a>";
        echo "</div>";
    }
} else {
    // ถ้าเข้าหน้านี้โดยตรง (GET Request) ให้กลับไปหน้า list
    header("Location: shelter_list.php");
    exit();
}
?>