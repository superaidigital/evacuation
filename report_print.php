<?php
// report_print.php
require_once 'config/db.php';

// ดึงข้อมูลเหมือนหน้า Report
$sql = "SELECT s.name, s.district, 
        SUM(CASE WHEN e.check_out_date IS NULL THEN 1 ELSE 0 END) as current_stay,
        SUM(CASE WHEN e.check_out_date IS NULL AND e.health_condition = 'ผู้สูงอายุ' THEN 1 ELSE 0 END) as elderly,
        SUM(CASE WHEN e.check_out_date IS NULL AND e.health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled
        FROM shelters s
        LEFT JOIN evacuees e ON s.id = e.shelter_id
        GROUP BY s.id";
$reports = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปยอดผู้พักพิง</title>
    <!-- ใช้ CSS ตัวเดียวกับหลัก แต่เพิ่ม Custom Style สำหรับ Print -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: white; }
        .print-header { text-align: center; margin-bottom: 30px; margin-top: 20px; }
        .signature-box { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-line { width: 200px; border-bottom: 1px dotted black; display: inline-block; margin: 0 5px; }
        
        /* สั่งให้ตอนสั่งพิมพ์ ซ่อนปุ่ม */
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()"> <!-- สั่งพิมพ์อัตโนมัติเมื่อโหลดเสร็จ -->

    <div class="container">
        <!-- ปุ่มย้อนกลับ (ไม่แสดงตอนพิมพ์) -->
        <div class="no-print mt-3 mb-3">
            <a href="report.php" class="btn btn-secondary">&laquo; กลับหน้าหลัก</a>
            <button onclick="window.print()" class="btn btn-primary">พิมพ์หน้านี้</button>
        </div>

        <div class="print-header">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/Emblem_of_the_Ministry_of_Interior_of_Thailand.svg/150px-Emblem_of_the_Ministry_of_Interior_of_Thailand.svg.png" width="60" class="mb-2">
            <h4 class="fw-bold">รายงานสรุปสถานการณ์ศูนย์พักพิงชั่วคราว</h4>
            <p>ข้อมูล ณ วันที่ <?php echo date("d"); ?> เดือน <?php 
                $thai_months = [1=>"มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
                echo $thai_months[(int)date("n")]; 
            ?> พ.ศ. <?php echo date("Y")+543; ?> เวลา <?php echo date("H:i"); ?> น.</p>
        </div>

        <table class="table table-bordered border-dark text-center">
            <thead>
                <tr>
                    <th rowspan="2" class="align-middle">ที่</th>
                    <th rowspan="2" class="align-middle text-start">ศูนย์พักพิงชั่วคราว</th>
                    <th rowspan="2" class="align-middle">อำเภอ</th>
                    <th colspan="3">ยอดผู้พักพิงคงเหลือ (คน)</th>
                </tr>
                <tr>
                    <th>รวม</th>
                    <th>ผู้สูงอายุ</th>
                    <th>ผู้พิการ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i=1; 
                $total_stay=0; $total_eld=0; $total_dis=0;
                foreach($reports as $row): 
                    $total_stay += $row['current_stay'];
                    $total_eld += $row['elderly'];
                    $total_dis += $row['disabled'];
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td class="text-start"><?php echo $row['name']; ?></td>
                    <td><?php echo $row['district']; ?></td>
                    <td class="fw-bold"><?php echo $row['current_stay']; ?></td>
                    <td><?php echo $row['elderly']; ?></td>
                    <td><?php echo $row['disabled']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td colspan="3" class="text-end">รวมทั้งสิ้น</td>
                    <td><?php echo number_format($total_stay); ?></td>
                    <td><?php echo number_format($total_eld); ?></td>
                    <td><?php echo number_format($total_dis); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="signature-box">
            <div class="text-center">
                ลงชื่อ .................................................... ผู้รายงาน<br>
                ( .................................................... )<br>
                ตำแหน่ง ....................................................
            </div>
            <div class="text-center">
                ลงชื่อ .................................................... ผู้รับรอง<br>
                ( .................................................... )<br>
                ตำแหน่ง ....................................................
            </div>
        </div>
    </div>

</body>
</html>