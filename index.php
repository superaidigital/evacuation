<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// --- Logic เดิม: การดึงข้อมูลผู้ใช้ ---
$role = $_SESSION['role'];
$shelter_id = $_SESSION['shelter_id'];

// --- ส่วนที่ 1: Logic สำหรับ Stats Cards ---
$sql_stats = "SELECT COUNT(*) as total, SUM(CASE WHEN health_condition LIKE '%ผู้สูงอายุ%' THEN 1 ELSE 0 END) as elderly, SUM(CASE WHEN health_condition LIKE '%ผู้พิการ%' OR health_condition LIKE '%ติดเตียง%' THEN 1 ELSE 0 END) as vulnerable FROM evacuees WHERE check_out_date IS NULL";
if ($role == 'STAFF') {
    $sql_stats .= " AND shelter_id = :sid";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(['sid' => $shelter_id]);
} else {
    $stmt_stats = $pdo->query($sql_stats);
}
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// --- ส่วนที่ 2: Logic สำหรับ Table ---
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; 
$offset = ($page - 1) * $limit;

$sql_shelters = "FROM shelters WHERE 1=1";
$params = [];

if ($role == 'STAFF') {
    $sql_shelters .= " AND id = :my_sid";
    $params['my_sid'] = $shelter_id;
}

if ($search) {
    $sql_shelters .= " AND (name LIKE :search OR district LIKE :search)";
    $params['search'] = "%$search%";
}

if ($filter_status != 'all') {
    $sql_shelters .= " AND status = :status";
    $params['status'] = $filter_status;
}

$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $sql_shelters);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT * " . $sql_shelters . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt_shelters = $pdo->prepare($sql_data);
$stmt_shelters->execute($params);
$shelters = $stmt_shelters->fetchAll(PDO::FETCH_ASSOC);

// --- ดึงชื่อเหตุการณ์ปัจจุบัน ---
$stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
$stmt_event->execute();
$current_event_name = $stmt_event->fetchColumn();

// --- ส่วนที่ 3: Logic บันทึกชื่อเหตุการณ์ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_event']) && $_SESSION['role'] == 'ADMIN') {
    $new_event = $_POST['event_name'];
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'current_event'");
    $stmt->execute([$new_event]);
    header("Location: index.php");
    exit();
}
?>

<!-- Banner แสดงเหตุการณ์ -->
<div class="card card-modern bg-gradient-primary text-white mb-4 border-0">
    <div class="card-body p-4 d-flex justify-content-between align-items-center">
        <div class="w-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-white-50">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <small class="text-uppercase fw-bold letter-spacing-1">สถานการณ์ปัจจุบัน (Current Event)</small>
            </div>
            
            <!-- ส่วนแสดงชื่อและปุ่มแก้ไข -->
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 class="fw-bold mb-0 text-white" id="eventDisplay">
                    <?php echo $current_event_name; ?>
                </h2>
                
                <?php if($_SESSION['role'] == 'ADMIN'): ?>
                    <!-- ปุ่มแก้ไขที่ปรับปรุงใหม่: ชัดเจน สวยงาม -->
                    <button class="btn btn-sm btn-warning text-dark fw-bold shadow-sm d-flex align-items-center gap-2 px-3 rounded-pill" 
                            onclick="toggleEventEdit()"
                            title="คลิกเพื่อแก้ไขชื่อเหตุการณ์">
                        <i class="bi bi-pencil-square"></i> <span>แก้ไข</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- ฟอร์มแก้ไขชื่อเหตุการณ์ -->
            <form method="POST" id="eventEditForm" class="d-none mt-3" style="max-width: 500px;">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white text-primary fw-bold"><i class="bi bi-tag-fill"></i></span>
                    <input type="text" name="event_name" class="form-control fw-bold text-primary" 
                           value="<?php echo $current_event_name; ?>" required placeholder="ระบุชื่อเหตุการณ์...">
                    <button type="submit" name="save_event" class="btn btn-warning fw-bold text-dark px-4">บันทึก</button>
                    <button type="button" class="btn btn-light px-3" onclick="toggleEventEdit()">ยกเลิก</button>
                </div>
                <div class="form-text text-white-50 mt-1">* ชื่อเหตุการณ์นี้จะถูกนำไปใช้เป็นค่าเริ่มต้นเมื่อเปิดศูนย์พักพิงชั่วคราวใหม่</div>
            </form>
        </div>
        <div class="d-none d-md-block opacity-25">
            <i class="bi bi-activity" style="font-size: 5rem;"></i>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card card-modern h-100">
            <div class="card-body p-4 d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-secondary small fw-bold mb-1">ผู้พักพิงปัจจุบัน</p>
                    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($stats['total']); ?></h2>
                    <small class="text-muted">คน (ยังไม่ Check-out)</small>
                </div>
                <div class="stat-icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-modern h-100">
            <div class="card-body p-4 d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-secondary small fw-bold mb-1">ศูนย์ที่เปิดอยู่ (รวม)</p>
                    <h2 class="fw-bold text-dark mb-0">
                        <?php 
                            $open_count = $pdo->query("SELECT COUNT(*) FROM shelters WHERE status='OPEN'")->fetchColumn();
                            echo $open_count;
                        ?>
                    </h2>
                    <small class="text-muted">แห่ง</small>
                </div>
                <div class="stat-icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-house-door-fill fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-modern h-100">
            <div class="card-body p-4 d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-secondary small fw-bold mb-1">ผู้สูงอายุ</p>
                    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($stats['elderly']); ?></h2>
                    <small class="text-muted">คน</small>
                </div>
                <div class="stat-icon-box bg-warning bg-opacity-10 text-warning"><i class="bi bi-person-wheelchair fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-modern h-100">
            <div class="card-body p-4 d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-secondary small fw-bold mb-1">กลุ่มเปราะบางอื่น</p>
                    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($stats['vulnerable']); ?></h2>
                    <small class="text-muted">ผู้พิการ / ป่วยติดเตียง</small>
                </div>
                <div class="stat-icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-heart-pulse-fill fs-4"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Section Header with Search -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
    <h5 class="fw-bold text-dark m-0">สถานะศูนย์พักพิงชั่วคราวรายแห่ง</h5>
    
    <form method="GET" class="d-flex gap-2 flex-grow-1 flex-md-grow-0" style="min-width: 300px;">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="ค้นหาชื่อศูนย์, อำเภอ..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>ทั้งหมด</option>
            <option value="OPEN" <?php echo $filter_status=='OPEN'?'selected':''; ?>>เปิด</option>
            <option value="CLOSED" <?php echo $filter_status=='CLOSED'?'selected':''; ?>>ปิด</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        <?php if($search || $filter_status != 'all'): ?>
            <a href="index.php" class="btn btn-outline-secondary btn-sm" title="ล้างค่า"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- Table Section -->
<div class="card card-modern overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-custom mb-0 align-middle">
            <thead>
                <tr>
                    <th class="ps-4">ชื่อศูนย์</th>
                    <th>ที่ตั้ง (อำเภอ)</th>
                    <th>เหตุการณ์ปัจจุบัน</th>
                    <th>ความจุ</th>
                    <th>สถานะ</th>
                    <th class="text-end pe-4">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($shelters) > 0): ?>
                    <?php foreach($shelters as $row): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary"><?php echo $row['name']; ?></td>
                        <td class="text-secondary"><?php echo $row['district']; ?>, <?php echo $row['province']; ?></td>
                        <td>
                            <?php if($row['status'] == 'OPEN'): ?>
                                <div class="d-flex align-items-center gap-2 text-primary small">
                                    <i class="bi bi-activity"></i> <?php echo $row['current_event']; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary"><?php echo $row['capacity']; ?> คน</td>
                        <td>
                            <?php if($row['status'] == 'OPEN'): ?>
                                <span class="badge badge-soft bg-success bg-opacity-10 text-success border border-success border-opacity-25">เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="badge badge-soft bg-secondary bg-opacity-10 text-secondary">ปิดทำการ</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($role == 'ADMIN'): ?>
                                <a href="shelter_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    ตั้งค่า
                                </a>
                            <?php endif; ?>
                            <a href="evacuee_list.php?shelter_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 ms-1">
                                รายชื่อ
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิงชั่วคราวตามเงื่อนไข</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if($total_pages > 1): ?>
    <div class="p-3 border-top d-flex justify-content-end bg-light">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">ก่อนหน้า</a>
                </li>
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleEventEdit() {
    var display = document.getElementById('eventDisplay');
    var form = document.getElementById('eventEditForm');
    var btn = document.querySelector('button[onclick="toggleEventEdit()"]'); // เลือกปุ่มแก้ไขด้านบน
    
    if (form.classList.contains('d-none')) {
        // เปิดโหมดแก้ไข
        form.classList.remove('d-none');
        display.classList.add('d-none');
        if(btn) btn.classList.add('d-none'); // ซ่อนปุ่มแก้ไข
    } else {
        // ปิดโหมดแก้ไข
        form.classList.add('d-none');
        display.classList.remove('d-none');
        if(btn) btn.classList.remove('d-none'); // แสดงปุ่มแก้ไข
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>