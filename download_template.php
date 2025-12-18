<?php
// download_template.php

// กำหนดชื่อไฟล์ที่จะดาวน์โหลด
$filename = "แบบฟอร์มนำเข้าข้อมูลผู้พักพิง.csv";

// กำหนด Header ให้ Browser รู้ว่าเป็นไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// สร้าง Output Stream
$output = fopen('php://output', 'w');

// *สำคัญ* ใส่ BOM (Byte Order Mark) เพื่อให้ Excel เปิดภาษาไทยได้ถูกต้อง
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// กำหนดหัวตาราง (ตรงตามที่ import_process.php คาดหวัง)
$headers = [
    'ที่',              // Col 0
    'คำนำหน้า',         // Col 1
    'ชื่อ',             // Col 2
    'สกุล',             // Col 3
    'เลขบัตรประชาชน',   // Col 4
    'อายุ',             // Col 5
    'สถานะสุขภาพ',      // Col 6
    'บ้านเลขที่',        // Col 7
    'หมู่ที่',           // Col 8
    'ตำบล',            // Col 9
    'อำเภอ',           // Col 10
    'จังหวัด',          // Col 11
    'ศูนย์พักพิงชั่วคราว (ระบุหรือไม่ก็ได้)', // Col 12 (ระบบใช้ Shelter ID จากการเลือกหน้าเว็บเป็นหลัก)
    'เบอร์โทรศัพท์',     // Col 13
    'วันที่เข้า (วว-ดด-ปปปป)', // Col 14
    'วันที่ออก'          // Col 15
];

// เขียนหัวตารางลงไฟล์
fputcsv($output, $headers);

// (Optional) เพิ่มข้อมูลตัวอย่าง 1 แถว เพื่อให้ user เข้าใจรูปแบบ
$example_row = [
    '1', 'นาย', 'รักชาติ', 'ยิ่งชีพ', '1330000000000', '45', 'ไม่มี', 
    '99', '1', 'หนองครก', 'เมือง', 'ศรีสะเกษ', 
    '-', '081-234-5678', '13-12-2568', ''
];
fputcsv($output, $example_row);

fclose($output);
exit();
?>