<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// 1. ตรวจสอบสิทธิ์และรับค่า ID
$shelter_id = null;
if ($_SESSION['role'] == 'STAFF') {
    $shelter_id = $_SESSION['shelter_id'];
} else if ($_SESSION['role'] == 'ADMIN') {
    $shelter_id = $_GET['id'] ?? null;
}

if (!$shelter_id) { header("Location: index.php"); exit(); }

// 2. ดึงข้อมูลศูนย์พักพิง
$stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
$stmt->execute([$shelter_id]);
$shelter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shelter) die("ไม่พบข้อมูลศูนย์พักพิงชั่วคราว");

// 3. ดึงข้อมูลผู้ประสานงานประจำศูนย์
$stmt_coords = $pdo->prepare("SELECT * FROM shelter_coordinators WHERE shelter_id = ?");
$stmt_coords->execute([$shelter_id]);
$coordinators = $stmt_coords->fetchAll(PDO::FETCH_ASSOC);

// 4. สถิติภาพรวม (คนปัจจุบัน)
$sql_current = "SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_stay = $pdo->prepare($sql_current);
$stmt_stay->execute([$shelter_id]);
$count_stay = $stmt_stay->fetchColumn();

// 5. สถิติเข้า-ออก วันนี้
$today = date('Y-m-d');
$sql_move = "SELECT 
    SUM(CASE WHEN check_in_date = ? THEN 1 ELSE 0 END) as in_today, 
    SUM(CASE WHEN check_out_date = ? THEN 1 ELSE 0 END) as out_today 
FROM evacuees WHERE shelter_id = ?";
$stmt_move = $pdo->prepare($sql_move);
$stmt_move->execute([$today, $today, $shelter_id]);
$movement = $stmt_move->fetch(PDO::FETCH_ASSOC);

// 6. การจัดกลุ่มประชากร (ละเอียด)
// - แบ่งช่วงอายุ
// - แบ่งเพศ
// - หาผู้หญิงวัยเจริญพันธุ์ (12-50 ปี) สำหรับ Hygiene Kits
$sql_demo = "SELECT 
    SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male, 
    SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN age BETWEEN 0 AND 2 THEN 1 ELSE 0 END) as age_0_2,
    SUM(CASE WHEN age BETWEEN 3 AND 12 THEN 1 ELSE 0 END) as age_3_12,
    SUM(CASE WHEN age BETWEEN 13 AND 19 THEN 1 ELSE 0 END) as age_13_19,
    SUM(CASE WHEN age BETWEEN 20 AND 59 THEN 1 ELSE 0 END) as age_20_59,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as age_60_up,
    SUM(CASE WHEN (prefix IN ('นาง', 'น.ส.', 'ด.ญ.') AND age BETWEEN 12 AND 50) THEN 1 ELSE 0 END) as female_reproductive
FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_demo = $pdo->prepare($sql_demo);
$stmt_demo->execute([$shelter_id]);
$demographics = $stmt_demo->fetch(PDO::FETCH_ASSOC);

// 7. กลุ่มเปราะบาง (Medical Needs)
$sql_vul = "SELECT 
    SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden, 
    SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled, 
    SUM(CASE WHEN health_condition = 'หญิงตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant,
    SUM(CASE WHEN health_condition = 'ผู้ป่วยไตวาย' THEN 1 ELSE 0 END) as kidney,
    SUM(CASE WHEN health_condition IN ('โรคเบาหวาน', 'โรคความดันโลหิตสูง', 'โรคหัวใจ') THEN 1 ELSE 0 END) as ncd
FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_vul = $pdo->prepare($sql_vul);
$stmt_vul->execute([$shelter_id]);
$vul_stats = $stmt_vul->fetch(PDO::FETCH_ASSOC);

// 8. กราฟย้อนหลัง 7 วัน (Movement Chart)
$dates = [];
$chart_in = [];
$chart_out = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[$d] = ['label' => date('d/m', strtotime($d)), 'in' => 0, 'out' => 0];
}

$sql_chart_in = "SELECT check_in_date, COUNT(*) as count FROM evacuees WHERE shelter_id = ? AND check_in_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY check_in_date";
$stmt = $pdo->prepare($sql_chart_in);
$stmt->execute([$shelter_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { if (isset($dates[$row['check_in_date']])) $dates[$row['check_in_date']]['in'] = $row['count']; }

$sql_chart_out = "SELECT check_out_date, COUNT(*) as count FROM evacuees WHERE shelter_id = ? AND check_out_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY check_out_date";
$stmt = $pdo->prepare($sql_chart_out);
$stmt->execute([$shelter_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { if (isset($dates[$row['check_out_date']])) $dates[$row['check_out_date']]['out'] = $row['count']; }

$js_labels = []; $js_data_in = []; $js_data_out = [];
foreach ($dates as $day) {
    $js_labels[] = $day['label']; $js_data_in[] = $day['in']; $js_data_out[] = $day['out'];
}

// 9. ข้อมูลกราฟช่วงอายุ (Bar Chart Data)
$age_labels = ['0-2 ปี', '3-12 ปี', '13-19 ปี', '20-59 ปี', '60+ ปี'];
$age_data = [
    $demographics['age_0_2'], 
    $demographics['age_3_12'], 
    $demographics['age_13_19'], 
    $demographics['age_20_59'], 
    $demographics['age_60_up']
];
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Print Settings */
@media print {
    @page { size: A4; margin: 1cm; }
    body { background-color: white !important; -webkit-print-color-adjust: exact; }
    .sidebar, .btn, .no-print, a[href], .breadcrumb { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; page-break-inside: avoid; }
    .print-header-only { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    .bg-light { background-color: #f8f9fa !important; }
    .badge { border: 1px solid #000; color: #000 !important; }
    canvas { max-height: 250px !important; width: 100% !important; }
}
.print-header-only { display: none; }
.stat-card-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.5rem; }
</style>

<!-- ส่วนหัวกระดาษ (แสดงเฉพาะตอน Print) -->
<div class="print-header-only">
    <h3>รายงานสถานการณ์ศูนย์พักพิงชั่วคราว</h3>
    <h4 class="mb-0"><?php echo $shelter['name']; ?></h4>
    <p>ที่ตั้ง: อ.<?php echo $shelter['district']; ?> จ.<?php echo $shelter['province']; ?> | เหตุการณ์: <?php echo $shelter['current_event']; ?></p>
    <small>ข้อมูล ณ วันที่ <?php echo date('d/m/Y เวลา H:i น.'); ?></small>
</div>

<!-- Header หน้าเว็บ -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
                <?php if($_SESSION['role']=='ADMIN'): ?>
                    <li class="breadcrumb-item"><a href="shelter_list.php">จัดการศูนย์</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active">รายงานรายศูนย์</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark"><?php echo $shelter['name']; ?></h3>
        <span class="text-muted small"><i class="bi bi-geo-alt-fill"></i> อ.<?php echo $shelter['district']; ?> | เหตุการณ์: <?php echo $shelter['current_event']; ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="export_process.php?type=shelter_specific&shelter_id=<?php echo $shelter_id; ?>" class="btn btn-success text-white shadow-sm">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
        <button onclick="window.print()" class="btn btn-dark shadow-sm">
            <i class="bi bi-printer-fill"></i> พิมพ์รายงาน
        </button>
    </div>
</div>

<!-- 1. KPI Cards (สถิติหลัก) -->
<div class="row g-3 mb-4">
    <!-- Card: ยอดรวม -->
    <div class="col-md-3">
        <div class="card card-modern h-100 border-primary border-opacity-25">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-secondary small fw-bold mb-1">ยอดผู้พักพิง</h6>
                    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($count_stay); ?></h2>
                    <small class="text-muted">คน</small>
                </div>
            </div>
        </div>
    </div>
    <!-- Card: ความจุ -->
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-building-fill"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-secondary small fw-bold mb-1">ความจุ / ว่าง</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo number_format($shelter['capacity']); ?></h3>
                    <?php $vacant = $shelter['capacity'] - $count_stay; ?>
                    <small class="<?php echo $vacant < 0 ? 'text-danger' : 'text-success'; ?> fw-bold">
                        (ว่าง <?php echo number_format($vacant); ?>)
                    </small>
                </div>
            </div>
        </div>
    </div>
    <!-- Card: เข้าใหม่ -->
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-box-arrow-in-right"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-secondary small fw-bold mb-1">เข้าใหม่วันนี้</h6>
                    <h2 class="fw-bold text-success mb-0">+<?php echo number_format($movement['in_today']); ?></h2>
                    <small class="text-muted">คน</small>
                </div>
            </div>
        </div>
    </div>
    <!-- Card: ออกวันนี้ -->
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-card-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="bi bi-box-arrow-left"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-secondary small fw-bold mb-1">จำหน่ายออกวันนี้</h6>
                    <h2 class="fw-bold text-secondary mb-0">-<?php echo number_format($movement['out_today']); ?></h2>
                    <small class="text-muted">คน</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Charts Section -->
<div class="row g-4 mb-4">
    <!-- กราฟการเข้า-ออก -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0"><i class="bi bi-graph-up-arrow text-primary"></i> แนวโน้มการเข้า-ออก (7 วันย้อนหลัง)</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="movementChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- กราฟช่วงอายุ -->
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0"><i class="bi bi-bar-chart-fill text-info"></i> โครงสร้างประชากร (อายุ)</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="ageChart"></canvas>
                </div>
                <div class="mt-2 text-center small text-muted">
                    ช่วยในการวางแผนอาหารและเวชภัณฑ์
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Detailed Data Tables -->
<div class="row g-4 mb-4">
    
    <!-- กลุ่มเปราะบาง -->
    <div class="col-md-6">
        <div class="card card-modern h-100">
            <div class="card-header bg-danger bg-opacity-10 border-danger border-opacity-25 py-3">
                <h6 class="fw-bold m-0 text-danger"><i class="bi bi-heart-pulse-fill"></i> กลุ่มเปราะบาง (Medical Needs)</h6>
            </div>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-hospital text-danger me-2"></i> ผู้ป่วยติดเตียง</span>
                    <span class="badge bg-danger rounded-pill fs-6"><?php echo $vul_stats['bedridden']; ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-wheelchair text-warning me-2"></i> ผู้พิการ</span>
                    <span class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $vul_stats['disabled']; ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-gender-female text-info me-2"></i> หญิงตั้งครรภ์</span>
                    <span class="badge bg-info text-dark rounded-pill fs-6"><?php echo $vul_stats['pregnant']; ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-capsule text-secondary me-2"></i> ผู้ป่วยไตวาย (ฟอกไต)</span>
                    <span class="badge bg-secondary rounded-pill fs-6"><?php echo $vul_stats['kidney']; ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard2-pulse text-dark me-2"></i> โรคเรื้อรัง (NCDs)</span>
                    <span class="badge bg-light text-dark border rounded-pill fs-6"><?php echo $vul_stats['ncd']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Logistics Estimation -->
    <div class="col-md-6">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-box-seam-fill text-success"></i> ประมาณการสิ่งของจำเป็น (Logistics)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light h-100">
                            <small class="text-muted d-block">อาหาร (3 มื้อ/วัน)</small>
                            <span class="fw-bold fs-5 text-dark"><?php echo number_format($count_stay * 3); ?></span> <small>ชุด</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light h-100">
                            <small class="text-muted d-block">น้ำดื่ม (2 ลิตร/คน)</small>
                            <span class="fw-bold fs-5 text-primary"><?php echo number_format($count_stay * 2); ?></span> <small>ลิตร</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light h-100">
                            <small class="text-muted d-block">นมผง/ของใช้เด็ก (0-2 ปี)</small>
                            <span class="fw-bold fs-5 text-warning"><?php echo number_format($demographics['age_0_2']); ?></span> <small>ชุด</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light h-100">
                            <small class="text-muted d-block">Hygiene Kits (หญิง 12-50ปี)</small>
                            <span class="fw-bold fs-5 text-danger"><?php echo number_format($demographics['female_reproductive']); ?></span> <small>ชุด</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-2 border rounded bg-light">
                            <small class="text-muted d-block">เครื่องนอน (มุ้ง/เสื่อ) *ประมาณการครอบครัวละ 3 คน</small>
                            <span class="fw-bold fs-5 text-dark"><?php echo number_format(ceil($count_stay / 3)); ?></span> <small>ชุด</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- 4. Contact Information -->
<div class="card card-modern mb-5">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold m-0"><i class="bi bi-telephone-fill text-primary"></i> ข้อมูลการติดต่อประสานงาน (Contact Information)</h6>
    </div>
    <div class="card-body">
        <?php if(count($coordinators) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ตำแหน่ง</th>
                            <th>เบอร์โทรศัพท์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($coordinators as $coord): ?>
                            <tr>
                                <td><?php echo $coord['name']; ?></td>
                                <td><?php echo $coord['position']; ?></td>
                                <td class="fw-bold"><?php echo $coord['phone']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mb-0">ยังไม่มีข้อมูลผู้ประสานงาน (สามารถเพิ่มได้ในเมนู "ตั้งค่าศูนย์")</p>
        <?php endif; ?>
    </div>
</div>

<!-- Footer Signature (Print Only) -->
<div class="d-none d-print-block mt-5 pt-4">
    <div class="d-flex justify-content-between px-5">
        <div class="text-center">
            <p>ลงชื่อ ........................................................... ผู้รายงาน</p>
            <p>( ........................................................... )</p>
            <p>ตำแหน่ง ...........................................................</p>
        </div>
        <div class="text-center">
            <p>ลงชื่อ ........................................................... ผู้รับรอง</p>
            <p>( ........................................................... )</p>
            <p>ตำแหน่ง หัวหน้าศูนย์พักพิงชั่วคราว</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Movement Chart (Line)
    const ctxMove = document.getElementById('movementChart').getContext('2d');
    new Chart(ctxMove, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($js_labels); ?>,
            datasets: [
                {
                    label: 'รับเข้า (In)',
                    data: <?php echo json_encode($js_data_in); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.3, fill: true
                },
                {
                    label: 'จำหน่ายออก (Out)',
                    data: <?php echo json_encode($js_data_out); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3, fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // 2. Age Distribution Chart (Bar)
    const ctxAge = document.getElementById('ageChart').getContext('2d');
    new Chart(ctxAge, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($age_labels); ?>,
            datasets: [{
                label: 'จำนวนคน',
                data: <?php echo json_encode($age_data); ?>,
                backgroundColor: [
                    '#fbbf24', // 0-2 (Yellow)
                    '#38bdf8', // 3-12 (Blue)
                    '#818cf8', // 13-19 (Indigo)
                    '#34d399', // 20-59 (Green)
                    '#9ca3af'  // 60+ (Gray)
                ],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>