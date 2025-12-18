<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// --- Logic ---
$role = $_SESSION['role'];
$shelter_id = $_SESSION['shelter_id'];

// 1. Stats Overview
$sql_stats = "SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN health_condition != 'ไม่มี' THEN 1 ELSE 0 END) as vulnerable,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age <= 12 THEN 1 ELSE 0 END) as kids
    FROM evacuees WHERE check_out_date IS NULL";

if ($role == 'STAFF') {
    $sql_stats .= " AND shelter_id = :sid";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(['sid' => $shelter_id]);
} else {
    $stmt_stats = $pdo->query($sql_stats);
}
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// 2. Recent Activity
$sql_activity = "SELECT e.first_name, e.last_name, s.name as shelter_name, e.check_in_date, e.created_at, 'checkin' as type 
                 FROM evacuees e JOIN shelters s ON e.shelter_id = s.id 
                 WHERE e.check_out_date IS NULL ";
if ($role == 'STAFF') $sql_activity .= " AND e.shelter_id = $shelter_id ";
$sql_activity .= " ORDER BY e.id DESC LIMIT 5";
$activities = $pdo->query($sql_activity)->fetchAll(PDO::FETCH_ASSOC);

// 3. Table Data & Filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

$sql_shelters = "FROM shelters s WHERE 1=1";
$params = [];

if ($role == 'STAFF') {
    $sql_shelters .= " AND s.id = :my_sid";
    $params['my_sid'] = $shelter_id;
}
if ($search) {
    $sql_shelters .= " AND (s.name LIKE :search OR s.district LIKE :search)";
    $params['search'] = "%$search%";
}
if ($filter_status != 'all') {
    $sql_shelters .= " AND s.status = :status";
    $params['status'] = $filter_status;
}

$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $sql_shelters);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT s.*, 
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_occupancy 
            " . $sql_shelters . " ORDER BY 
            CASE WHEN s.status = 'OPEN' THEN 1 ELSE 2 END, 
            current_occupancy DESC 
            LIMIT $limit OFFSET $offset";

$stmt_shelters = $pdo->prepare($sql_data);
$stmt_shelters->execute($params);
$shelters = $stmt_shelters->fetchAll(PDO::FETCH_ASSOC);

// 4. Event Name (Safe Load)
$current_event_name = 'สถานการณ์ปกติ'; // ค่าเริ่มต้น
try {
    $stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
    $stmt_event->execute();
    $result = $stmt_event->fetchColumn();
    if (!empty($result)) {
        $current_event_name = $result;
    }
} catch (Exception $e) { }

// Save Event Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_event']) && $_SESSION['role'] == 'ADMIN') {
    $new_event = $_POST['event_name'];
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'current_event'");
        $check->execute();
        if ($check->fetchColumn() > 0) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'current_event'");
            $stmt->execute([$new_event]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('current_event', ?)");
            $stmt->execute([$new_event]);
        }
    } catch (Exception $e) { }
    echo "<script>window.location.href='index.php';</script>";
    exit();
}
?>

<!-- Import Font & Chart.js -->
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Global Styles */
    :root {
        --font-primary: 'Prompt', sans-serif;
        --color-bg: #f3f4f6;
        --color-card: #ffffff;
        --color-primary-soft: #e0f2fe;
        --color-primary-dark: #0284c7;
        --color-success-soft: #dcfce7;
        --color-success-dark: #16a34a;
        --color-warning-soft: #fef3c7;
        --color-warning-dark: #d97706;
        --color-danger-soft: #fee2e2;
        --color-danger-dark: #dc2626;
    }
    
    body {
        font-family: var(--font-primary);
        background-color: var(--color-bg);
    }

    /* Cards */
    .card-dashboard {
        border: none;
        border-radius: 16px;
        background: var(--color-card);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-dashboard:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
    }

    /* Stat Box Icons */
    .icon-box {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
    }
    .icon-box.primary { background-color: var(--color-primary-soft); color: var(--color-primary-dark); }
    .icon-box.success { background-color: var(--color-success-soft); color: var(--color-success-dark); }
    .icon-box.warning { background-color: var(--color-warning-soft); color: var(--color-warning-dark); }
    .icon-box.danger  { background-color: var(--color-danger-soft); color: var(--color-danger-dark); }

    /* Buttons (Muted Style) */
    .btn-quick {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 500;
        border-radius: 50rem;
        transition: all 0.2s;
        box-shadow: none;
    }
    .btn-quick:hover {
        background: #e2e8f0;
        color: #1e293b;
        border-color: #94a3b8;
        transform: translateY(-1px);
    }
    .btn-quick i { opacity: 0.8; }

    /* Table */
    .table-modern thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e5e7eb;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .table-modern tbody td {
        padding-top: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .table-modern tr:last-child td { border-bottom: none; }

    /* Gradient Banner */
    .bg-gradient-brand {
        background-color: #2563eb;
        background: linear-gradient(120deg, #2563eb 0%, #1d4ed8 100%);
    }
</style>

<!-- Quick Actions Bar -->
<div class="d-flex gap-3 mb-4 overflow-auto pb-2 align-items-center">
    <div class="text-secondary fw-bold small text-uppercase me-2 d-none d-md-block">เมนูด่วน:</div>
    <a href="search_evacuee.php" class="btn btn-quick px-4 py-2 d-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-person-plus-fill text-secondary"></i> ลงทะเบียน/ค้นหา
    </a>
    <a href="import_csv.php" class="btn btn-quick px-4 py-2 d-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-file-earmark-excel-fill text-secondary"></i> นำเข้า Excel
    </a>
    <?php if($role == 'ADMIN'): ?>
    <a href="shelter_form.php" class="btn btn-quick px-4 py-2 d-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-building-add text-secondary"></i> เพิ่มศูนย์ใหม่
    </a>
    <?php endif; ?>
    <a href="report.php" class="btn btn-quick px-4 py-2 d-flex align-items-center gap-2 text-decoration-none ms-auto">
        <i class="bi bi-graph-up-arrow text-secondary"></i> รายงานผล
    </a>
</div>

<!-- Event Banner (แสดงเสมอ) -->
<div class="card card-dashboard bg-gradient-brand text-white mb-4 border-0 position-relative overflow-hidden">
    <div class="card-body p-4 position-relative z-1">
        <div class="d-flex justify-content-between align-items-start">
            <div class="w-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-white-50">
                    <span class="badge bg-white bg-opacity-25 rounded-pill px-3 fw-normal">สถานการณ์ปัจจุบัน</span>
                    <i class="bi bi-broadcast"></i>
                </div>
                
                <!-- Display Area -->
                <div id="eventDisplayArea" class="d-flex align-items-center gap-3">
                    <h2 class="fw-bold mb-0 text-white" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <?php echo htmlspecialchars($current_event_name); ?>
                    </h2>
                    <?php if($_SESSION['role'] == 'ADMIN'): ?>
                        <!-- ปุ่มแก้ไข (Trigger) -->
                        <button class="btn btn-light text-primary fw-bold shadow-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2" 
                                onclick="toggleEventEdit()" title="แก้ไขสถานการณ์">
                            <i class="bi bi-pencil-square"></i> แก้ไข
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Edit Form (Input Group) -->
                <form method="POST" id="eventEditForm" class="d-none mt-3" style="max-width: 600px;">
                    <div class="input-group shadow-sm">
                        <!-- ไอคอนหน้าช่อง -->
                        <span class="input-group-text bg-white border-0 ps-3 text-primary">
                            <i class="bi bi-megaphone-fill"></i>
                        </span>
                        
                        <!-- ช่องกรอกข้อมูล -->
                        <input type="text" name="event_name" class="form-control border-0 py-2" 
                               value="<?php echo htmlspecialchars($current_event_name); ?>" 
                               required placeholder="เช่น น้ำท่วมหนัก อ.เมือง...">
                        
                        <!-- ปุ่มบันทึก -->
                        <button type="submit" name="save_event" class="btn btn-success fw-bold text-white px-4 border-0">
                            <i class="bi bi-check-lg me-1"></i> บันทึก
                        </button>
                        
                        <!-- ปุ่มยกเลิก -->
                        <button type="button" class="btn btn-danger fw-bold text-white px-3 border-0" onclick="toggleEventEdit()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Decorative Icon Background -->
    <i class="bi bi-megaphone-fill position-absolute text-white opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; transform: rotate(-15deg);"></i>
</div>

<!-- Dashboard Grid -->
<div class="row g-4 mb-5">
    
    <!-- Left Column: Stats & Table -->
    <div class="col-lg-8">
        
        <!-- Stats Cards Grid -->
        <div class="row g-3 mb-4">
            <!-- Card 1: Total -->
            <div class="col-md-3 col-6">
                <div class="card card-dashboard h-100 p-3">
                    <div class="d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="icon-box primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary small mb-1">ผู้พักพิงทั้งหมด</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($stats['total']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 2: Shelters -->
            <div class="col-md-3 col-6">
                <div class="card card-dashboard h-100 p-3">
                    <div class="d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="icon-box success">
                                <i class="bi bi-house-check-fill"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary small mb-1">ศูนย์เปิดใช้งาน</div>
                            <h3 class="fw-bold text-dark mb-0">
                                <?php echo $pdo->query("SELECT COUNT(*) FROM shelters WHERE status='OPEN'")->fetchColumn(); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Elderly/Kids -->
            <div class="col-md-3 col-6">
                <div class="card card-dashboard h-100 p-3">
                    <div class="d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="icon-box warning">
                                <i class="bi bi-emoji-smile-fill"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary small mb-1">เด็ก/สูงอายุ</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($stats['elderly'] + $stats['kids']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Vulnerable -->
            <div class="col-md-3 col-6">
                <div class="card card-dashboard h-100 p-3">
                    <div class="d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="icon-box danger">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary small mb-1">กลุ่มเปราะบาง</div>
                            <h3 class="fw-bold text-danger mb-0"><?php echo number_format($stats['vulnerable']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Section -->
        <div class="card card-dashboard overflow-hidden">
            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold m-0 text-dark">สถานะศูนย์พักพิง</h5>
                    <small class="text-muted">รายการศูนย์พักพิงและการเข้าพักปัจจุบัน</small>
                </div>
                <form method="GET" class="d-flex gap-2">
                    <select name="status" class="form-select form-select-sm border-light bg-light rounded-pill px-3" onchange="this.form.submit()" style="min-width: 140px;">
                        <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>📌 สถานะทั้งหมด</option>
                        <option value="OPEN" <?php echo $filter_status=='OPEN'?'selected':''; ?>>🟢 เปิดใช้งาน</option>
                    </select>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ชื่อศูนย์พักพิง</th>
                            <th>ที่ตั้ง</th>
                            <th style="width: 30%;">ความหนาแน่น</th>
                            <th class="text-end pe-4">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($shelters) > 0): ?>
                            <?php foreach($shelters as $row): 
                                $percent = ($row['capacity'] > 0) ? ($row['current_occupancy'] / $row['capacity']) * 100 : 0;
                                // Color logic
                                if($percent > 90) $bar_class = 'bg-danger';
                                else if($percent > 70) $bar_class = 'bg-warning';
                                else $bar_class = 'bg-success';
                                
                                $is_full = ($percent >= 100);
                            ?>
                            <tr class="<?php echo $row['status']=='CLOSED' ? 'bg-light text-muted' : 'bg-white'; ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-building <?php echo $row['status']=='OPEN' ? 'text-primary' : 'text-secondary'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold <?php echo $row['status']=='OPEN' ? 'text-dark' : 'text-secondary'; ?>"><?php echo $row['name']; ?></div>
                                            <?php if($row['status'] == 'CLOSED'): ?>
                                                <span class="badge bg-secondary text-white rounded-pill" style="font-size: 0.6rem;">ปิดรับ</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="d-flex align-items-center gap-1 text-secondary small">
                                        <i class="bi bi-geo-alt-fill text-muted"></i> อ.<?php echo $row['district']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-bold <?php echo $is_full ? 'text-danger':'text-dark'; ?>">
                                            <?php echo number_format($row['current_occupancy']); ?> คน
                                        </span>
                                        <span class="text-secondary small">เต็ม <?php echo number_format($row['capacity']); ?></span>
                                    </div>
                                    <div class="progress bg-light" style="height: 8px; border-radius: 4px;">
                                        <div class="progress-bar <?php echo $bar_class; ?>" role="progressbar" 
                                             style="width: <?php echo $percent; ?>%; border-radius: 4px;">
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="evacuee_list.php?shelter_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                        รายชื่อ
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิง</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="p-3 border-top bg-light d-flex justify-content-center">
                <nav>
                    <ul class="pagination pagination-sm mb-0 shadow-sm rounded-pill overflow-hidden">
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link border-0 <?php echo $page == $i ? 'bg-primary text-white' : 'bg-white text-secondary'; ?>" 
                               href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Sidebar -->
    <div class="col-lg-4">
        
        <!-- Chart Widget -->
        <div class="card card-dashboard mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-4 text-dark"><i class="bi bi-pie-chart-fill text-primary me-2"></i>โครงสร้างประชากร</h6>
                <div style="height: 220px; position: relative;">
                    <canvas id="miniChart"></canvas>
                </div>
                <div class="mt-4 d-flex justify-content-center gap-3">
                    <div class="d-flex align-items-center small text-secondary">
                        <span class="d-inline-block rounded-circle me-2" style="width: 10px; height: 10px; background-color: #3b82f6;"></span> ทั่วไป
                    </div>
                    <div class="d-flex align-items-center small text-secondary">
                        <span class="d-inline-block rounded-circle me-2" style="width: 10px; height: 10px; background-color: #f59e0b;"></span> ต้องการดูแลพิเศษ
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="card card-dashboard">
            <div class="card-header bg-white border-bottom py-3 px-3">
                <h6 class="fw-bold m-0"><i class="bi bi-activity text-danger me-2"></i>ความเคลื่อนไหวล่าสุด</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if(count($activities) > 0): ?>
                    <?php foreach($activities as $act): ?>
                    <div class="list-group-item px-3 py-3 border-bottom-0 border-top-0" style="border-bottom: 1px dashed #f0f0f0 !important;">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                                        <?php echo $act['first_name'] . ' ' . $act['last_name']; ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo date('H:i', strtotime($act['created_at'])); ?> น.
                                    </small>
                                </div>
                                <div class="small text-secondary lh-sm">
                                    เข้าพักที่ <span class="text-primary fw-medium"><?php echo $act['shelter_name']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-5 text-center text-muted small">
                        <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                        ยังไม่มีความเคลื่อนไหววันนี้
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light text-center border-0 py-3">
                <a href="report.php" class="text-decoration-none small fw-bold text-primary">
                    ดูประวัติทั้งหมด <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function toggleEventEdit() {
    var display = document.getElementById('eventDisplayArea');
    var form = document.getElementById('eventEditForm');
    display.classList.toggle('d-none');
    form.classList.toggle('d-none');
    // ลบการ toggle d-flex ออก เพราะใช้ input-group จัดการ layout แทนแล้ว
}

// Mini Chart Script
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('miniChart').getContext('2d');
    const total = <?php echo $stats['total']; ?>;
    const vul = <?php echo $stats['vulnerable'] + $stats['elderly'] + $stats['kids']; ?>; 
    const normal = Math.max(0, total - vul);

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ทั่วไป', 'กลุ่มเปราะบาง/เด็ก/สูงอายุ'],
            datasets: [{
                data: [normal, vul],
                backgroundColor: ['#3b82f6', '#f59e0b'],
                hoverBackgroundColor: ['#2563eb', '#d97706'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>