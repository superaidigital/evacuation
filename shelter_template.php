<?php
$filename = "แบบฟอร์มนำเข้าศูนย์พักพิงชั่วคราว.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Thai

// หัวตาราง
$headers = ['ชื่อศูนย์พักพิงชั่วคราว', 'อำเภอ', 'จังหวัด (ถ้าว่าง=ศรีสะเกษ)', 'ความจุ (คน)'];
fputcsv($output, $headers);

// ตัวอย่าง
$example_row = ['โรงเรียนบ้านหนองคู', 'ขุนหาญ', 'ศรีสะเกษ', '200'];
fputcsv($output, $example_row);

fclose($output);
exit();
?>