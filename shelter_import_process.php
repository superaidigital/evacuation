<?php
require_once 'config/db.php';

// ดึงชื่อเหตุการณ์ปัจจุบันมารอไว้
$stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
$stmt_event->execute();
$current_event = $stmt_event->fetchColumn();

if (isset($_POST['submit'])) {
    
    $fileName = $_FILES['csv_file']['tmp_name'];

    if ($_FILES['csv_file']['size'] > 0) {
        
        $file = fopen($fileName, "r");
        fgetcsv($file); // ข้าม Header

        $count = 0;
        $duplicate_count = 0; // ตัวแปรนับจำนวนที่ซ้ำ
        
        // เตรียม SQL สำหรับตรวจสอบข้อมูลซ้ำ (เช็คจาก ชื่อ และ อำเภอ)
        $check_sql = "SELECT COUNT(*) FROM shelters WHERE name = ? AND district = ?";
        $check_stmt = $pdo->prepare($check_sql);

        // เตรียม SQL สำหรับเพิ่มข้อมูล
        $insert_sql = "INSERT INTO shelters (name, district, province, capacity, status, current_event) 
                       VALUES (?, ?, ?, ?, 'OPEN', ?)";
        $insert_stmt = $pdo->prepare($insert_sql);

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            
            $name = trim($column[0] ?? '');
            $district = trim($column[1] ?? '');
            $province = trim($column[2] ?? '');
            $capacity = (int)($column[3] ?? 100);

            if (empty($province)) $province = 'ศรีสะเกษ';
            if ($capacity <= 0) $capacity = 100;

            if (!empty($name) && !empty($district)) {
                try {
                    // 1. ตรวจสอบก่อนว่ามีข้อมูลนี้อยู่แล้วหรือไม่
                    $check_stmt->execute([$name, $district]);
                    $exists = $check_stmt->fetchColumn();

                    if ($exists > 0) {
                        // ถ้าซ้ำ ให้ข้ามและนับยอด
                        $duplicate_count++;
                        continue; 
                    }

                    // 2. ถ้าไม่ซ้ำ ให้บันทึก
                    $insert_stmt->execute([$name, $district, $province, $capacity, $current_event]);
                    $count++;

                } catch (Exception $e) { continue; }
            }
        }
        
        fclose($file);
        
        // แจ้งผลลัพธ์ (เพิ่มยอดที่ข้ามเพราะซ้ำ)
        $msg = "นำเข้าสำเร็จ $count แห่ง";
        if ($duplicate_count > 0) {
            $msg .= "\\n(ข้ามข้อมูลที่ซ้ำกัน $duplicate_count แห่ง)";
        }

        echo "<script>
            alert('$msg');
            window.location.href = 'shelter_list.php';
        </script>";
        
    } else {
        echo "<script>alert('กรุณาเลือกไฟล์'); window.history.back();</script>";
    }
}
?>