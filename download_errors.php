<?php
// download_errors.php

// เริ่ม Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ล้าง Output Buffer เพื่อป้องกันหน้าขาวหรือไฟล์เสียจากช่องว่างที่เกินมา
ob_end_clean(); 
ob_start();

// ตรวจสอบว่ามีข้อมูล Error หรือไม่
if (!isset($_SESSION['import_errors']) || empty($_SESSION['import_errors'])) {
    // ถ้าไม่มีข้อมูล ให้แจ้งเตือนและดีดกลับไปหน้า Import
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'ไม่พบข้อมูลข้อผิดพลาด',
            text: 'ข้อมูลอาจหมดอายุหรือถูกดาวน์โหลดไปแล้ว',
            confirmButtonText: 'กลับไปหน้าหลัก'
        }).then(() => {
            window.location.href = 'import_csv.php';
        });
    </script>";
    echo "</body></html>";
    exit();
}

// กำหนดชื่อไฟล์
$filename = "รายการนำเข้าไม่สำเร็จ_" . date('Y-m-d_His') . ".csv";

// ตั้งค่า Header สำหรับดาวน์โหลดไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// สร้าง Output Stream
$output = fopen('php://output', 'w');

// ใส่ BOM (Byte Order Mark) เพื่อให้ Excel อ่านภาษาไทยออก
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// วนลูปเขียนข้อมูลลงไฟล์
foreach ($_SESSION['import_errors'] as $row) {
    // แปลงข้อมูลแต่ละช่องให้เป็น CSV Format ที่ถูกต้อง
    fputcsv($output, $row);
}

fclose($output);

// ล้างค่า Session หลังดาวน์โหลดเสร็จ (เพื่อไม่ให้ข้อมูลค้าง)
// หมายเหตุ: หากต้องการให้กดดาวน์โหลดซ้ำได้ ให้คอมเมนต์บรรทัด unset นี้ออก
unset($_SESSION['import_errors']);

// จบการทำงานทันที (สำคัญมาก ห้ามมี HTML ต่อท้าย)
exit();
?>