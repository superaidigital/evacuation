<?php
// caretaker_status.php
require_once 'config/db.php';

if ($_SESSION['role'] != 'ADMIN') { header("Location: index.php"); exit(); }

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($action == 'delete' && $id) {
    // ป้องกันการลบตัวเอง (แม้จะเป็น Admin ก็ตาม เพื่อความปลอดภัยเบื้องต้น)
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้'); window.location.href='caretaker_list.php';</script>";
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: caretaker_list.php");
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: caretaker_list.php");
}
?>