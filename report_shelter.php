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

// 2. ดึงข้อมูลศูนย์พักพิงชั่วคราว
$stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
$stmt->execute([$shelter_id]);
$shelter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shelter) die("ไม่พบข้อมูลศูนย์พักพิงชั่วคราว");

// 3. ดึงสถิติต่างๆ
$sql_current = "SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$current_stay = $pdo->prepare($sql_current);
$current_stay->execute([$shelter_id]);
$count_stay = $current_stay->fetchColumn();

// ยอดเข้า/ออก วันนี้
$today = date('Y-m-d');
$sql_move = "SELECT SUM(CASE WHEN check_in_date = ? THEN 1 ELSE 0 END) as in_today, SUM(CASE WHEN check_out_date = ? THEN 1 ELSE 0 END) as out_today FROM evacuees WHERE shelter_id = ?";
$stmt_move = $pdo->prepare($sql_move);
$stmt_move->execute([$today, $today, $shelter_id]);
$movement = $stmt_move->fetch(PDO::FETCH_ASSOC);

// กลุ่มเปราะบาง
$sql_vul = "SELECT SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden, SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled, SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant, SUM(CASE WHEN health_condition = 'ผู้สูงอายุ' THEN 1 ELSE 0 END) as elderly, SUM(CASE WHEN age <= 2 THEN 1 ELSE 0 END) as infants FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_vul = $pdo->prepare($sql_vul);
$stmt_vul->execute([$shelter_id]);
$vul_stats = $stmt_vul->fetch(PDO::FETCH_ASSOC);

// เพศ
$sql_gender = "SELECT SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male, SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_gender = $pdo->prepare($sql_gender);
$stmt_gender->execute([$shelter_id]);
$gender_stats = $stmt_gender->fetch(PDO::FETCH_ASSOC);

// --- Logic ใหม่: เตรียมข้อมูลกราฟย้อนหลัง 7 วัน ---
$dates = [];
$chart_in = [];
$chart_out = [];

// สร้าง Array วันที่ย้อนหลัง 7 วัน
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[$d] = [
        'label' => date('d/m', strtotime($d)), // แสดงแค่วัน/เดือน
        'in' => 0,
        'out' => 0
    ];
}

// ดึงยอดเข้าย้อนหลัง
$sql_chart_in = "SELECT check_in_date, COUNT(*) as count FROM evacuees WHERE shelter_id = ? AND check_in_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY check_in_date";
$stmt = $pdo->prepare($sql_chart_in);
$stmt->execute([$shelter_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dates[$row['check_in_date']])) {
        $dates[$row['check_in_date']]['in'] = $row['count'];
    }
}

// ดึงยอดออกย้อนหลัง
$sql_chart_out = "SELECT check_out_date, COUNT(*) as count FROM evacuees WHERE shelter_id = ? AND check_out_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY check_out_date";
$stmt = $pdo->prepare($sql_chart_out);
$stmt->execute([$shelter_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($dates[$row['check_out_date']])) {
        $dates[$row['check_out_date']]['out'] = $row['count'];
    }
}

// แยกข้อมูลใส่ Array เพื่อส่งให้ JS
$js_labels = [];
$js_data_in = [];
$js_data_out = [];
foreach ($dates as $day) {
    $js_labels[] = $day['label'];
    $js_data_in[] = $day['in'];
    $js_data_out[] = $day['out'];
}
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
@media print {
    .sidebar, .btn, .no-print, a[href] { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    body { background-color: white !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
    .print-header-only { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; }
    /* ปรับกราฟตอนพิมพ์ให้ดูดี */
    canvas { max-height: 300px !important; width: 100% !important; }
}
.print-header-only { display: none; }
</style>

<div class="print-header-only">
    <h3>รายงานสถานการณ์ประจำศูนย์พักพิง</h3>
    <h4><?php echo $shelter['name']; ?> (อ.<?php echo $shelter['district']; ?>)</h4>
    <p>ข้อมูล ณ วันที่ <?php echo date('d/m/Y H:i'); ?></p>
</div>

<!-- Header -->
<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <nav aria-label="breadcrumb" class="no-print">
            <ol class="breadcrumb mb-1 small">
                <?php if($_SESSION['role']=='ADMIN'): ?>
                    <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
                    <li class="breadcrumb-item"><a href="report.php">รายงานรวม</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active">รายงานรายศูนย์</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-primary"><?php echo $shelter['name']; ?></h3>
        <p class="text-muted mb-0">
            <i class="bi bi-geo-alt-fill"></i> อ.<?php echo $shelter['district']; ?> | เหตุการณ์: <?php echo $shelter['current_event']; ?>
        </p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul"></i> รายชื่อ
        </a>
        <button onclick="window.print()" class="btn btn-dark">
            <i class="bi bi-printer-fill"></i> พิมพ์รายงาน
        </button>
    </div>
</div>

<!-- 1. KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-modern h-100 border-primary border-opacity-25 bg-primary bg-opacity-10">
            <div class="card-body text-center p-3">
                <h6 class="text-primary fw-bold text-uppercase small">ยอดผู้พักอาศัยปัจจุบัน</h6>
                <h1 class="fw-bold text-dark mb-0 display-5"><?php echo number_format($count_stay); ?></h1>
                <small class="text-muted">คน</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body text-center p-3">
                <h6 class="text-secondary fw-bold text-uppercase small">ความจุ / ว่าง</h6>
                <div class="d-flex justify-content-center align-items-baseline gap-2">
                    <span class="fs-3 fw-bold"><?php echo number_format($shelter['capacity']); ?></span>
                    <span class="text-muted small">ที่นั่ง</span>
                </div>
                <div class="badge <?php echo ($shelter['capacity'] - $count_stay > 0) ? 'bg-success' : 'bg-danger'; ?> bg-opacity-75">
                    ว่าง <?php echo number_format($shelter['capacity'] - $count_stay); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body text-center p-3">
                <h6 class="text-secondary fw-bold text-uppercase small">เข้าใหม่วันนี้</h6>
                <h2 class="fw-bold text-success mb-0">+<?php echo number_format($movement['in_today']); ?></h2>
                <small class="text-muted">คน</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern h-100">
            <div class="card-body text-center p-3">
                <h6 class="text-secondary fw-bold text-uppercase small">กลับบ้านวันนี้</h6>
                <h2 class="fw-bold text-secondary mb-0">-<?php echo number_format($movement['out_today']); ?></h2>
                <small class="text-muted">คน</small>
            </div>
        </div>
    </div>
</div>

<!-- 2. NEW: Daily Movement Graph (กราฟเข้า-ออก 7 วันย้อนหลัง) -->
<div class="card card-modern mb-4 border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold m-0"><i class="bi bi-graph-up-arrow text-info"></i> สถิติการเข้า-ออก (7 วันย้อนหลัง)</h6>
    </div>
    <div class="card-body">
        <div style="height: 300px; position: relative;">
            <canvas id="movementChart"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- 3. Logistics -->
    <div class="col-md-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0"><i class="bi bi-box-seam text-primary"></i> ข้อมูลเพื่อการบริหารจัดการ</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2">ประมาณการเสบียง (ต่อวัน)</h6>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>อาหาร (3 มื้อ x คน)</span>
                                <span class="fw-bold"><?php echo number_format($count_stay * 3); ?> กล่อง</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>น้ำดื่ม (2 ลิตร x คน)</span>
                                <span class="fw-bold"><?php echo number_format($count_stay * 2); ?> ลิตร</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>นมผง/อาหารเด็ก (0-2 ปี)</span>
                                <span class="fw-bold text-danger"><?php echo number_format($vul_stats['infants']); ?> ชุด</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2">การจัดสรรพื้นที่ (Zoning)</h6>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-gender-male text-primary fs-4 me-2"></i>
                            <div class="w-100">
                                <div class="d-flex justify-content-between small">
                                    <span>โซนชาย</span>
                                    <strong><?php echo number_format($gender_stats['male']); ?> คน</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo ($count_stay>0)?($gender_stats['male']/$count_stay)*100:0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-gender-female text-danger fs-4 me-2"></i>
                            <div class="w-100">
                                <div class="d-flex justify-content-between small">
                                    <span>โซนหญิง/ครอบครัว</span>
                                    <strong><?php echo number_format($gender_stats['female']); ?> คน</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo ($count_stay>0)?($gender_stats['female']/$count_stay)*100:0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Medical Needs -->
    <div class="col-md-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0 text-danger"><i class="bi bi-heart-pulse"></i> กลุ่มเปราะบาง (Medical)</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <span class="text-danger"><i class="bi bi-hospital me-2"></i> ผู้ป่วยติดเตียง</span>
                    <span class="badge bg-danger rounded-pill fs-6"><?php echo $vul_stats['bedridden']; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <span class="text-warning text-dark"><i class="bi bi-person-wheelchair me-2"></i> ผู้พิการ</span>
                    <span class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $vul_stats['disabled']; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <span class="text-info text-dark"><i class="bi bi-person-standing-dress me-2"></i> หญิงตั้งครรภ์</span>
                    <span class="badge bg-info text-dark rounded-pill fs-6"><?php echo $vul_stats['pregnant']; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <span class="text-primary"><i class="bi bi-emoji-smile me-2"></i> เด็กเล็ก (0-2 ปี)</span>
                    <span class="badge bg-primary rounded-pill fs-6"><?php echo $vul_stats['infants']; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                    <span class="text-secondary"><i class="bi bi-eyeglasses me-2"></i> ผู้สูงอายุ</span>
                    <span class="badge bg-secondary rounded-pill fs-6"><?php echo $vul_stats['elderly']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-none d-print-block mt-5 pt-5">
    <div class="d-flex justify-content-between">
        <div class="text-center" style="width: 200px;">
            <p class="border-bottom border-dark pb-2 mb-2"></p>
            <p>ผู้รายงาน</p>
        </div>
        <div class="text-center" style="width: 200px;">
            <p class="border-bottom border-dark pb-2 mb-2"></p>
            <p>ผู้รับรอง (หน.ศูนย์)</p>
        </div>
    </div>
</div>

<!-- Script สร้างกราฟ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('movementChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($js_labels); ?>,
                datasets: [
                    {
                        label: 'รับเข้า (คน)',
                        data: <?php echo json_encode($js_data_in); ?>,
                        borderColor: '#22c55e', // Green
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'จำหน่ายออก (คน)',
                        data: <?php echo json_encode($js_data_out); ?>,
                        borderColor: '#ef4444', // Red
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 } // ให้แกน Y แสดงจำนวนเต็ม
                    }
                }
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>