<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์: ต้องเป็น ADMIN เท่านั้น
if ($_SESSION['role'] != 'ADMIN') {
    echo "<div class='alert alert-danger m-4'>เฉพาะผู้ดูแลระบบเท่านั้น</div>";
    require_once 'includes/footer.php';
    exit();
}

// --- Helper Function: Time Ago ---
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'ปี', 'm' => 'เดือน', 'w' => 'สัปดาห์',
        'd' => 'วัน', 'h' => 'ชม.', 'i' => 'นาที', 's' => 'วิ.'
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . 'ที่แล้ว' : 'เมื่อสักครู่';
}

// --- 1. รับค่าพารามิเตอร์ ---
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; 
$filter_district = $_GET['district'] ?? 'all';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- 2. Query Data ---
$sql_base = "FROM shelters s WHERE 1=1";
$params = [];

if ($search) {
    $sql_base .= " AND (s.name LIKE :search OR s.district LIKE :search)";
    $params['search'] = "%$search%";
}

if ($filter_status != 'all') {
    $sql_base .= " AND s.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_district != 'all') {
    $sql_base .= " AND s.district = :district";
    $params['district'] = $filter_district;
}

$districts = $pdo->query("SELECT DISTINCT district FROM shelters ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);

$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT s.*, 
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_occupancy,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.health_condition != 'ไม่มี') as vulnerable_count
            " . $sql_base . " ORDER BY 
            CASE WHEN s.status = 'OPEN' THEN 1 ELSE 2 END, 
            current_occupancy DESC, 
            s.id DESC 
            LIMIT $limit OFFSET $offset";

$stmt_shelters = $pdo->prepare($sql_data);
$stmt_shelters->execute($params);
$shelters = $stmt_shelters->fetchAll();

// --- Stats ---
$stats_open = $pdo->query("SELECT COUNT(*) FROM shelters WHERE status = 'OPEN'")->fetchColumn();
$stats_capacity = $pdo->query("SELECT SUM(capacity) FROM shelters WHERE status = 'OPEN'")->fetchColumn();
$stats_people = $pdo->query("SELECT COUNT(*) FROM evacuees WHERE check_out_date IS NULL")->fetchColumn();
?>

<!-- Header Section & Mini Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                <li class="breadcrumb-item active" aria-current="page">ตั้งค่าระบบ</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark">จัดการข้อมูลศูนย์พักพิงชั่วคราว</h3>
        <span class="text-muted small">บริหารจัดการสถานะ ความจุ และทรัพยากร</span>
    </div>
    <div class="col-md-4 text-md-end d-flex align-items-center justify-content-md-end gap-2">
         <a href="shelter_import.php" class="btn btn-outline-success d-flex align-items-center gap-2 shadow-sm btn-sm">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i> <span>นำเข้า CSV</span>
        </a>
        <a href="shelter_form.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm btn-sm">
            <i class="bi bi-plus-circle-fill"></i> <span>เพิ่มศูนย์ใหม่</span>
        </a>
    </div>
</div>

<!-- Mini Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e0f2fe 0%, #ffffff 100%);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="p-3 bg-white rounded-circle shadow-sm text-primary">
                    <i class="bi bi-house-door-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="mb-0 text-muted small text-uppercase fw-bold">ศูนย์ที่เปิดใช้งาน</h6>
                    <h4 class="mb-0 fw-bold text-dark"><?php echo $stats_open; ?> แห่ง</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="p-3 bg-white rounded-circle shadow-sm text-success">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="mb-0 text-muted small text-uppercase fw-bold">ผู้พักพิงปัจจุบัน</h6>
                    <h4 class="mb-0 fw-bold text-dark"><?php echo number_format($stats_people); ?> คน</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="p-3 bg-white rounded-circle shadow-sm text-warning">
                    <i class="bi bi-pie-chart-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="mb-0 text-muted small text-uppercase fw-bold">อัตราการใช้พื้นที่</h6>
                    <h4 class="mb-0 fw-bold text-dark">
                        <?php echo ($stats_capacity > 0) ? round(($stats_people/$stats_capacity)*100, 1) : 0; ?>%
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card card-modern border-0 mb-4 bg-white shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ระบุชื่อศูนย์..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="district" class="form-select" onchange="this.form.submit()">
                    <option value="all">📍 ทุกอำเภอ</option>
                    <?php foreach($districts as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $filter_district==$d?'selected':''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>⚡ สถานะทั้งหมด</option>
                    <option value="OPEN" <?php echo $filter_status=='OPEN'?'selected':''; ?>>เปิดใช้งาน (Active)</option>
                    <option value="CLOSED" <?php echo $filter_status=='CLOSED'?'selected':''; ?>>ปิดทำการ (Closed)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">กรองข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<!-- Table Section -->
<div class="card card-modern border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">ชื่อศูนย์พักพิงชั่วคราว</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">ความหนาแน่น</th>
                    <th class="py-3 text-center text-secondary text-uppercase small fw-bold">กลุ่มเปราะบาง</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">สถานะ</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">อัปเดต</th>
                    <th class="py-3 text-end pe-4 text-secondary text-uppercase small fw-bold" style="min-width: 200px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($shelters) > 0): ?>
                    <?php foreach ($shelters as $row): 
                        $occupancy = $row['current_occupancy'];
                        $capacity = $row['capacity'];
                        $percent = ($capacity > 0) ? ($occupancy / $capacity) * 100 : 0;
                        
                        $progress_color = 'bg-success';
                        if ($percent >= 90) $progress_color = 'bg-danger';
                        else if ($percent >= 70) $progress_color = 'bg-warning';

                        $first_char = mb_substr($row['name'], 0, 1);
                        $avatar_color = $row['status'] == 'OPEN' ? 'bg-primary' : 'bg-secondary';
                    ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle <?php echo $avatar_color; ?> bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" 
                                         style="width: 45px; height: 45px; min-width: 45px; font-size: 1.2rem;">
                                        <?php echo $first_char; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $row['name']; ?></div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <i class="bi bi-geo-alt-fill text-danger"></i> อ.<?php echo $row['district']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="min-width: 160px;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold"><?php echo number_format($occupancy); ?> คน</span>
                                    <span class="text-muted small">/ <?php echo number_format($capacity); ?></span>
                                </div>
                                <div class="progress rounded-pill" style="height: 8px; background-color: #e9ecef;">
                                    <div class="progress-bar rounded-pill <?php echo $progress_color; ?>" role="progressbar" 
                                         style="width: <?php echo $percent; ?>%"></div>
                                </div>
                                <?php if($percent >= 100): ?>
                                    <div class="text-danger small fw-bold mt-1"><i class="bi bi-exclamation-circle-fill"></i> เต็มพื้นที่</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['vulnerable_count'] > 0): ?>
                                    <div class="d-inline-flex align-items-center px-2 py-1 rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 animate-pulse">
                                        <i class="bi bi-heart-pulse-fill me-1"></i> 
                                        <span class="fw-bold"><?php echo $row['vulnerable_count']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted opacity-50"><i class="bi bi-dash-lg"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'OPEN'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="p-1 bg-success border border-light rounded-circle"></span>
                                        <span class="text-success fw-bold small">Active</span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="p-1 bg-secondary border border-light rounded-circle"></span>
                                        <span class="text-muted small">Closed</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small text-dark fw-bold"><?php echo time_elapsed_string($row['last_updated']); ?></div>
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    <?php echo date('d/m/y H:i', strtotime($row['last_updated'])); ?>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <!-- เปลี่ยนจาก Dropdown เป็น Button Group -->
                                <div class="d-flex justify-content-end gap-1">
                                    
                                    <!-- War Room -->
                                    <a href="monitor_dashboard.php?id=<?php echo $row['id']; ?>" target="_blank" 
                                       class="btn btn-sm btn-light border text-warning" data-bs-toggle="tooltip" title="เปิด War Room">
                                        <i class="bi bi-display"></i>
                                    </a>
                                    
                                    <!-- Report -->
                                    <a href="report_shelter.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-light border text-info" data-bs-toggle="tooltip" title="ดูรายงาน">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>

                                    <!-- Edit -->
                                    <a href="shelter_form.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-light border text-primary" data-bs-toggle="tooltip" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <!-- Toggle Status -->
                                    <a href="shelter_status.php?action=toggle&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-light border <?php echo $row['status']=='OPEN' ? 'text-secondary' : 'text-success'; ?>" 
                                       data-bs-toggle="tooltip" 
                                       title="<?php echo $row['status']=='OPEN' ? 'ปิดศูนย์' : 'เปิดศูนย์'; ?>">
                                        <i class="bi <?php echo $row['status']=='OPEN' ? 'bi-power' : 'bi-play-circle-fill'; ?>"></i>
                                    </a>

                                    <!-- Delete -->
                                    <button type="button" onclick="confirmDeleteShelter(<?php echo $row['id']; ?>)" 
                                            class="btn btn-sm btn-light border text-danger" data-bs-toggle="tooltip" title="ลบข้อมูล">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted bg-light">ไม่พบข้อมูลศูนย์พักพิงชั่วคราวตามเงื่อนไข</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="px-4 py-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center bg-white">
        <div class="small text-muted mb-2 mb-md-0">
            แสดง <strong><?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_rows); ?></strong> จาก <strong><?php echo $total_rows; ?></strong> แห่ง
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 text-secondary" href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&district=<?php echo $filter_district; ?>&status=<?php echo $filter_status; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);
                if ($start > 1) { echo '<li class="page-item"><a class="page-link border-0 text-secondary" href="?page=1&search='.$search.'&district='.$filter_district.'&status='.$filter_status.'">1</a></li>'; if ($start > 2) echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>'; }
                for ($i = $start; $i <= $end; $i++) { $active = ($i == $page) ? 'active fw-bold' : 'text-secondary'; echo '<li class="page-item"><a class="page-link border-0 '.$active.'" href="?page='.$i.'&search='.$search.'&district='.$filter_district.'&status='.$filter_status.'">'.$i.'</a></li>'; }
                if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>'; echo '<li class="page-item"><a class="page-link border-0 text-secondary" href="?page='.$total_pages.'&search='.$search.'&district='.$filter_district.'&status='.$filter_status.'">'.$total_pages.'</a></li>'; }
                ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 text-secondary" href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&district=<?php echo $filter_district; ?>&status=<?php echo $filter_status; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<style>
    /* CSS Pulse Animation */
    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .animate-pulse { animation: pulse-red 2s infinite; }
    
    /* Hover Row */
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
    
    /* Card Hover */
    .card:hover { transform: translateY(-2px); transition: transform 0.2s ease-in-out; }
    
    /* Tooltip Fix (Bootstrap 5 tooltip needs init via JS, but browser title attr works as fallback) */
</style>

<script>
function confirmDeleteShelter(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ระบบจะตรวจสอบก่อนว่ามีผู้พักพิงคงค้างหรือไม่",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#e5e7eb',
        cancelButtonText: '<span class="text-dark">ยกเลิก</span>',
        confirmButtonText: 'ยืนยันลบ'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = `shelter_status.php?action=delete&id=${id}`;
    })
}

// Initialize Tooltips
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>

<?php require_once 'includes/footer.php'; ?>