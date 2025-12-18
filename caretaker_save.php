<?php
// caretaker_save.php
require_once 'config/db.php';

if ($_SESSION['role'] != 'ADMIN') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mode = $_POST['mode'];
    $id = $_POST['id'] ?? '';
    
    // รับค่าข้อมูล
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $shelter_id = $_POST['shelter_id'];
    $role = 'STAFF'; 

    try {
        if ($mode == 'add') {
            // 1. ตรวจสอบ Username ซ้ำ
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                echo "<script>alert('Username นี้มีผู้ใช้งานแล้ว กรุณาใช้ชื่ออื่น'); history.back();</script>";
                exit();
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // INSERT (เพิ่ม phone, email)
            $sql = "INSERT INTO users (username, password, fullname, phone, email, role, shelter_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $hashed_password, $fullname, $phone, $email, $role, $shelter_id]);

        } else {
            // โหมดแก้ไข
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // UPDATE มีรหัสผ่าน (เพิ่ม phone, email)
                $sql = "UPDATE users SET fullname=?, phone=?, email=?, shelter_id=?, password=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $phone, $email, $shelter_id, $hashed_password, $id]);
            } else {
                // UPDATE ไม่แก้รหัสผ่าน (เพิ่ม phone, email)
                $sql = "UPDATE users SET fullname=?, phone=?, email=?, shelter_id=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $phone, $email, $shelter_id, $id]);
            }
        }

        header("Location: caretaker_list.php");
        exit();

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>