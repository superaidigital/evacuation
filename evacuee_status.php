<?php
// evacuee_status.php
require_once 'config/db.php';

// ตรวจสอบว่าเป็นคำสั่งอะไร (ตอนนี้เราทำแค่ checkout)
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';
$shelter_id = $_GET['shelter_id'] ?? '';

if ($action == 'checkout' && $id) {
    try {
        // อัปเดตวันที่ออกเป็น "วันนี้"
        $sql = "UPDATE evacuees SET check_out_date = CURRENT_DATE, check_out_reason = 'กลับภูมิลำเนา' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        // กลับไปหน้ารายชื่อ
        header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
        exit();
        
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    // ถ้าไม่มีคำสั่ง ให้กลับหน้าหลัก
    header("Location: index.php");
    exit();
}
?>