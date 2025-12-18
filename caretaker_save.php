<?php
// caretaker_save.php
require_once 'config/db.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') { 
    header("Location: index.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mode = $_POST['mode'];
    $id = $_POST['id'] ?? '';
    
    // รับค่าข้อมูลทั่วไป
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    // รับค่า Login & Role
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'STAFF'; // รับค่า Role จากฟอร์ม (ถ้าไม่มี Default เป็น STAFF)
    $shelter_id = $_POST['shelter_id'] ?? null;

    // Logic: ถ้าเป็น ADMIN ให้ shelter_id เป็น NULL เสมอ
    if ($role === 'ADMIN') {
        $shelter_id = null;
    }

    try {
        if ($mode == 'add') {
            // --- โหมดเพิ่มใหม่ ---
            
            // 1. ตรวจสอบ Username ซ้ำ
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                echo "<script>alert('Username นี้มีผู้ใช้งานแล้ว กรุณาใช้ชื่ออื่น'); history.back();</script>";
                exit();
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // INSERT (เพิ่ม role และ shelter_id ที่ผ่าน Logic แล้ว)
            $sql = "INSERT INTO users (username, password, fullname, phone, email, role, shelter_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashed_password, $fullname, $phone, $email, $role, $shelter_id]);

        } else {
            // --- โหมดแก้ไข ---
            
            if (!empty($password)) {
                // กรณีมีการเปลี่ยนรหัสผ่าน
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET fullname=?, phone=?, email=?, role=?, shelter_id=?, password=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $phone, $email, $role, $shelter_id, $hashed_password, $id]);
            } else {
                // กรณีไม่เปลี่ยนรหัสผ่าน
                $sql = "UPDATE users SET fullname=?, phone=?, email=?, role=?, shelter_id=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $phone, $email, $role, $shelter_id, $id]);
            }
        }

        header("Location: caretaker_list.php");
        exit();

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        echo "<br><a href='javascript:history.back()'>กลับไปแก้ไข</a>";
    }
}
?>