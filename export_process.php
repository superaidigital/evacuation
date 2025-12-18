<?php
// export_process.php
require_once 'config/db.php';

// ตรวจสอบสิทธิ์ (Admin หรือ Staff)
if (!isset($_SESSION['user_id'])) { exit(); }

$type = $_GET['type'] ?? '';
$today = date('Y-m-d');
$filename = "export_data.csv";
$sql = "";
$params = [];

// เงื่อนไขเสริมสำหรับ Staff (เห็นแค่ศูนย์ตัวเอง)
$staff_condition = "";
if ($_SESSION['role'] == 'STAFF') {
    $staff_condition = " AND e.shelter_id = " . $_SESSION['shelter_id'];
}

// กำหนด Query ตามประเภทรายงาน
switch ($type) {
    case 'daily_in':
        $filename = "รายชื่อผู้เข้าใหม่_{$today}.csv";
        $sql = "SELECT e.citizen_id, CONCAT(e.prefix, e.first_name, ' ', e.last_name) as fullname, e.age, e.phone, s.name as shelter_name, e.check_in_date 
                FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.check_in_date = ? $staff_condition";
        $params = [$today];
        break;

    case 'daily_out':
        $filename = "รายชื่อผู้จำหน่ายออก_{$today}.csv";
        $sql = "SELECT e.citizen_id, CONCAT(e.prefix, e.first_name, ' ', e.last_name) as fullname, e.check_out_reason, s.name as shelter_name, e.check_out_date 
                FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.check_out_date = ? $staff_condition";
        $params = [$today];
        break;

    case 'all_active':
        $filename = "รายชื่อผู้อพยพทั้งหมด_{$today}.csv";
        // เลือกทุกคอลัมน์ + ชื่อศูนย์
        $sql = "SELECT e.*, s.name as shelter_name 
                FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.check_out_date IS NULL $staff_condition";
        break;

    case 'shelter_specific':
        // เฉพาะ Admin เท่านั้นที่ใช้เคสนี้ได้ผ่านฟอร์ม
        $sid = $_GET['shelter_id'] ?? 'all';
        if ($sid == 'all') {
            $filename = "ข้อมูลทุกศูนย์_{$today}.csv";
            $sql = "SELECT e.*, s.name as shelter_name FROM evacuees e JOIN shelters s ON e.shelter_id = s.id";
        } else {
            $filename = "ข้อมูลศูนย์_{$sid}_{$today}.csv";
            $sql = "SELECT e.*, s.name as shelter_name FROM evacuees e JOIN shelters s ON e.shelter_id = s.id WHERE e.shelter_id = ?";
            $params = [$sid];
        }
        break;

    case 'vul_medical':
        $filename = "กลุ่มเปราะบาง_การแพทย์_{$today}.csv";
        $sql = "SELECT e.citizen_id, CONCAT(e.prefix, e.first_name, ' ', e.last_name) as fullname, e.age, e.health_condition, e.phone, s.name as shelter_name 
                FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.check_out_date IS NULL 
                AND (e.health_condition LIKE '%ติดเตียง%' OR e.health_condition LIKE '%พิการ%' OR e.health_condition LIKE '%โรค%' OR e.health_condition LIKE '%ไต%') 
                $staff_condition";
        break;

    case 'vul_kids':
        $filename = "กลุ่มเด็กและสตรี_{$today}.csv";
        $sql = "SELECT e.citizen_id, CONCAT(e.prefix, e.first_name, ' ', e.last_name) as fullname, e.age, e.health_condition, s.name as shelter_name 
                FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.check_out_date IS NULL 
                AND (e.age <= 5 OR e.health_condition LIKE '%ครรภ์%' OR e.health_condition LIKE '%หญิง%') 
                $staff_condition";
        break;

    default:
        die("Invalid Export Type");
}

// --- ส่งออกไฟล์ CSV ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Thai Support

// ดึงข้อมูล
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) > 0) {
    // เขียน Header (Key ของ Array)
    fputcsv($output, array_keys($rows[0]));
    
    // เขียนข้อมูล
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} else {
    // ถ้าไม่มีข้อมูล ให้เขียนบอก
    fputcsv($output, ['ไม่พบข้อมูลตามเงื่อนไข']);
}

fclose($output);
exit();
?>