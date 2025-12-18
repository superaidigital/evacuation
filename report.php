<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// --- 1. จัดการ Event Context ---
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : 'active';
$current_event = [];

if ($event_id == 'active') {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $current_event = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $current_event = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$current_event) {
    $current_event = ['id' => 0, 'name' => 'ข้อมูลทั้งหมด (Legacy)', 'start_date' => date('Y-m-d'), 'status' => 'ACTIVE'];
}

$filter_event_id = $current_event['id'];
$is_history_mode = ($current_event['status'] == 'CLOSED');
$all_events = $pdo->query("SELECT id, name, status, start_date FROM events ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- 2. SQL Queries ---

// 2.1 ข้อมูลภาพรวม & เปรียบเทียบเมื่อวาน
// ยอดปัจจุบัน
$sql_stay = "SELECT COUNT(*) FROM evacuees WHERE event_id = ? AND check_out_date IS NULL";
$stmt = $pdo->prepare($sql_stay);
$stmt->execute([$filter_event_id]);
$total_stay = $stmt->fetchColumn();

// ยอดเมื่อวาน (ณ เวลาสิ้นวัน)
$yesterday_date = date('Y-m-d', strtotime("-1 day"));
$sql_yesterday = "SELECT COUNT(*) FROM evacuees WHERE event_id = ? AND check_in_date <= ? AND (check_out_date IS NULL OR check_out_date > ?)";
$stmt = $pdo->prepare($sql_yesterday);
$stmt->execute([$filter_event_id, $yesterday_date, $yesterday_date]);
$total_yesterday = $stmt->fetchColumn();

// คำนวณความเปลี่ยนแปลง
$diff = $total_stay - $total_yesterday;
$diff_text = ($diff > 0) ? "+".number_format($diff) : number_format($diff);
$diff_color = ($diff > 0) ? "text-danger" : (($diff < 0) ? "text-success" : "text-muted");
$diff_icon = ($diff > 0) ? "bi-arrow-up" : (($diff < 0) ? "bi-arrow-down" : "bi-dash");

// ยอดสะสม
$sql_total_reg = "SELECT COUNT(*) FROM evacuees WHERE event_id = ?";
$stmt = $pdo->prepare($sql_total_reg);
$stmt->execute([$filter_event_id]);
$total_registered = $stmt->fetchColumn();

// ศูนย์ที่เปิด
$sql_shelter_involved = "SELECT COUNT(DISTINCT shelter_id) FROM evacuees WHERE event_id = ?";
$stmt = $pdo->prepare($sql_shelter_involved);
$stmt->execute([$filter_event_id]);
$total_shelters = $stmt->fetchColumn();

// กลุ่มเปราะบาง
$sql_vul = "SELECT 
    SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden,
    SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled,
    SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age <= 5 THEN 1 ELSE 0 END) as kids
FROM evacuees WHERE event_id = ?";
$stmt = $pdo->prepare($sql_vul);
$stmt->execute([$filter_event_id]);
$vul = $stmt->fetch(PDO::FETCH_ASSOC);
$total_vul = array_sum($vul);

// เพศ
$sql_gender = "SELECT 
    SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female
FROM evacuees WHERE event_id = ?";
$stmt = $pdo->prepare($sql_gender);
$stmt->execute([$filter_event_id]);
$gender = $stmt->fetch(PDO::FETCH_ASSOC);

// 2.2 กราฟ Timeline
$sql_date_range = "SELECT MIN(check_in_date) as start_d, MAX(check_in_date) as end_d FROM evacuees WHERE event_id = ?";
$stmt = $pdo->prepare($sql_date_range);
$stmt->execute([$filter_event_id]);
$range = $stmt->fetch(PDO::FETCH_ASSOC);

$graph_start = $range['start_d'] ? $range['start_d'] : date('Y-m-d', strtotime('-7 days'));
$graph_end = $range['end_d'] ? $range['end_d'] : date('Y-m-d');

if (strtotime($graph_end) - strtotime($graph_start) > (15 * 86400)) {
   $graph_start = date('Y-m-d', strtotime($graph_end . ' -14 days'));
}

$dates_labels = []; $data_in = []; $data_out = []; $data_stay_history = [];

if ($graph_start && $graph_end) {
    try {
        $period = new DatePeriod(new DateTime($graph_start), new DateInterval('P1D'), (new DateTime($graph_end))->modify('+1 day'));
        foreach ($period as $dt) {
            $d_str = $dt->format("Y-m-d");
            $dates_labels[] = $dt->format("d/m");
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE event_id = ? AND check_in_date = ?");
            $stmt->execute([$filter_event_id, $d_str]);
            $in = $stmt->fetchColumn();
            $data_in[] = $in;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE event_id = ? AND check_out_date = ?");
            $stmt->execute([$filter_event_id, $d_str]);
            $out = $stmt->fetchColumn();
            $data_out[] = $out;
            
            // คำนวณยอดคงเหลือรายวัน (Cumulative) - แบบคร่าวๆ
            // ในทางปฏิบัติควรเก็บ Snapshot รายวันไว้ใน DB อีกตารางเพื่อประสิทธิภาพ
            // แต่นี่คำนวณสดจาก Transaction
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE event_id = ? AND check_in_date <= ? AND (check_out_date IS NULL OR check_out_date > ?)");
            $stmt->execute([$filter_event_id, $d_str, $d_str]);
            $data_stay_history[] = $stmt->fetchColumn();
        }
    } catch (Exception $e) {}
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-bar-graph-fill text-primary"></i> รายงานสรุปสถานการณ์</h3>
            <?php if($is_history_mode): ?>
                <span class="badge bg-secondary">ประวัติย้อนหลัง</span>
            <?php else: ?>
                <span class="badge bg-success">ปัจจุบัน (Active)</span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0 mt-1">
            เหตุการณ์: <strong class="text-dark"><?php echo htmlspecialchars($current_event['name']); ?></strong>
        </p>
    </div>
    
    <div class="d-flex gap-2 align-items-center bg-white p-2 rounded shadow-sm border">
        <label class="small fw-bold text-nowrap ms-2 text-secondary"><i class="bi bi-filter"></i> เลือกเหตุการณ์:</label>
        <select class="form-select form-select-sm border-0 bg-light fw-bold" style="max-width: 250px;" onchange="window.location.href='report.php?event_id='+this.value">
            <?php foreach($all_events as $evt): ?>
                <option value="<?php echo $evt['id']; ?>" <?php echo $filter_event_id == $evt['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($evt['name']); ?> 
                    <?php echo ($evt['status']=='ACTIVE') ? '(ปัจจุบัน)' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="event_history.php" class="btn btn-outline-secondary btn-sm text-nowrap" title="ดูรายการทั้งหมด"><i class="bi bi-list-ul"></i></a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm text-nowrap"><i class="bi bi-printer"></i></button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <!-- คงเหลือในศูนย์ (ตัวเลขสำคัญสุด) -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10 border-start border-4 border-primary">
            <div class="card-body">
                <h6 class="text-primary text-uppercase small fw-bold">ผู้พักพิงคงเหลือ (ปัจจุบัน)</h6>
                <div class="d-flex justify-content-between align-items-end">
                    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_stay); ?></h2>
                    <div class="text-end">
                        <small class="d-block text-secondary" style="font-size: 0.7rem;">เทียบกับเมื่อวาน</small>
                        <span class="fw-bold <?php echo $diff_color; ?> small">
                            <i class="bi <?php echo $diff_icon; ?>"></i> <?php echo $diff_text; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ยอดสะสม -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10 border-start border-4 border-success">
            <div class="card-body">
                <h6 class="text-success text-uppercase small fw-bold">ยอดผู้ประสบภัยสะสม</h6>
                <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_registered); ?></h2>
                <small class="text-muted">คน (ลงทะเบียนทั้งหมด)</small>
            </div>
        </div>
    </div>
    
    <!-- ศูนย์ที่เปิด -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10 border-start border-4 border-warning">
            <div class="card-body">
                <h6 class="text-warning text-dark text-uppercase small fw-bold">ศูนย์ที่เปิดรับ</h6>
                <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_shelters); ?></h2>
                <small class="text-muted">แห่ง (ที่มีการใช้งาน)</small>
            </div>
        </div>
    </div>
    
    <!-- กลุ่มเปราะบาง -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10 border-start border-4 border-danger">
            <div class="card-body">
                <h6 class="text-danger text-uppercase small fw-bold">กลุ่มเปราะบาง</h6>
                <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_vul); ?></h2>
                <small class="text-muted">คน (<?php echo ($total_stay > 0) ? round(($total_vul/$total_stay)*100) : 0; ?>% ของทั้งหมด)</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Left: Charts -->
    <div class="col-lg-8">
        <!-- 1. Trend Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="bi bi-graph-up-arrow text-info me-2"></i> แนวโน้มผู้พักพิง (Timeline)</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px; width: 100%;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 2. Resource Estimation (New Feature!) -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="bi bi-box-seam text-secondary me-2"></i> ประมาณการทรัพยากรต่อวัน (Resource Needs)</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4 border-end">
                        <div class="text-muted small mb-1">อาหาร (3 มื้อ)</div>
                        <h4 class="fw-bold text-warning mb-0"><?php echo number_format($total_stay * 3); ?></h4>
                        <small>กล่อง</small>
                    </div>
                    <div class="col-4 border-end">
                        <div class="text-muted small mb-1">น้ำดื่ม (2 ลิตร)</div>
                        <h4 class="fw-bold text-primary mb-0"><?php echo number_format($total_stay * 2); ?></h4>
                        <small>ลิตร</small>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small mb-1">หน้ากากอนามัย</div>
                        <h4 class="fw-bold text-success mb-0"><?php echo number_format($total_stay); ?></h4>
                        <small>ชิ้น</small>
                    </div>
                </div>
                <div class="alert alert-light mt-3 mb-0 small">
                    <i class="bi bi-info-circle-fill me-1"></i> คำนวณจากยอดผู้พักพิงคงเหลือปัจจุบัน (<?php echo number_format($total_stay); ?> คน) เพื่อใช้ในการเตรียมเสบียงล่วงหน้า
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right: Stats -->
    <div class="col-lg-4">
        <!-- Gender Chart -->
        <div class="card border-0 shadow-sm mb-4 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="bi bi-pie-chart-fill text-secondary me-2"></i> โครงสร้างประชากร</h6>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <div style="height: 200px; width: 200px; position: relative;">
                    <canvas id="genderChart"></canvas>
                </div>
                <div class="mt-4 w-100">
                    <div class="d-flex justify-content-between px-4 mb-2 border-bottom pb-2">
                        <span class="text-muted small"><i class="bi bi-circle-fill text-primary" style="font-size: 8px;"></i> ชาย</span>
                        <span class="fw-bold"><?php echo number_format($gender['male']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between px-4 mb-2 border-bottom pb-2">
                        <span class="text-muted small"><i class="bi bi-circle-fill text-danger" style="font-size: 8px;"></i> หญิง</span>
                        <span class="fw-bold"><?php echo number_format($gender['female']); ?></span>
                    </div>
                    <!-- รายละเอียดกลุ่มเปราะบาง -->
                    <div class="mt-3 px-2">
                        <h6 class="small fw-bold text-muted mb-2">รายละเอียดกลุ่มเปราะบาง:</h6>
                        <div class="d-flex justify-content-between small mb-1"><span>สูงอายุ (60+)</span> <span><?php echo number_format($vul['elderly']); ?></span></div>
                        <div class="d-flex justify-content-between small mb-1"><span>เด็กเล็ก (0-5)</span> <span><?php echo number_format($vul['kids']); ?></span></div>
                        <div class="d-flex justify-content-between small mb-1"><span>ผู้ป่วย/พิการ</span> <span><?php echo number_format($vul['bedridden']+$vul['disabled']); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ctxTrend = document.getElementById('trendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates_labels); ?>,
        datasets: [
            { 
                label: 'ยอดคงเหลือ (คน)', 
                data: <?php echo json_encode($data_stay_history); ?>, 
                borderColor: '#6610f2', 
                backgroundColor: 'rgba(102, 16, 242, 0.1)', 
                borderWidth: 2,
                fill: true, 
                tension: 0.4 
            },
            { 
                label: 'รับเข้าใหม่', 
                data: <?php echo json_encode($data_in); ?>, 
                borderColor: '#0d6efd', 
                borderDash: [5, 5],
                borderWidth: 1,
                pointRadius: 0,
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true } }
    }
});

const ctxGender = document.getElementById('genderChart').getContext('2d');
new Chart(ctxGender, {
    type: 'doughnut',
    data: {
        labels: ['ชาย', 'หญิง'],
        datasets: [{ 
            data: [<?php echo $gender['male']; ?>, <?php echo $gender['female']; ?>], 
            backgroundColor: ['#3b82f6', '#ef4444'], 
            borderWidth: 0 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        cutout: '70%', 
        plugins: { legend: { display: false } } 
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>