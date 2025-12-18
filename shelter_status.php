<?php
// shelter_status.php
require_once 'config/db.php';

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: index.php"); exit();
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($id) {
    if ($action == 'toggle') {
        // สลับสถานะ OPEN <-> CLOSED
        $stmt = $pdo->prepare("SELECT status FROM shelters WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        
        $new_status = ($current == 'OPEN') ? 'CLOSED' : 'OPEN';
        
        $update = $pdo->prepare("UPDATE shelters SET status = ? WHERE id = ?");
        $update->execute([$new_status, $id]);
        
        header("Location: shelter_list.php");
        
    } elseif ($action == 'delete') {
        // ลบศูนย์ (ต้องเช็คก่อนว่ามีผู้พักพิงผูกอยู่ไหม)
        $check = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE shelter_id = ?");
        $check->execute([$id]);
        $count = $check->fetchColumn();

        if ($count > 0) {
            // ถ้ามีคนอยู่ ห้ามลบ
            echo "<script>
                alert('ไม่สามารถลบศูนย์นี้ได้ เนื่องจากมีประวัติผู้พักพิงอยู่ $count คน กรุณาจำหน่ายออกหรือย้ายข้อมูลก่อน');
                window.location.href = 'shelter_list.php';
            </script>";
        } else {
            // ถ้าว่าง ลบได้เลย
            $del = $pdo->prepare("DELETE FROM shelters WHERE id = ?");
            $del->execute([$id]);
            header("Location: shelter_list.php");
        }
    }
} else {
    header("Location: shelter_list.php");
}
?>