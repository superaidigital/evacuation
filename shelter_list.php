<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์: ต้องเป็น ADMIN เท่านั้น
if ($_SESSION['role'] != 'ADMIN') {
    echo "<div class='alert alert-danger m-4'>เฉพาะผู้ดูแลระบบเท่านั้น</div>";
    require_once 'includes/footer.php';
    exit();
}

// 1. รับค่าพารามิเตอร์สำหรับการค้นหาและแบ่งหน้า
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; 

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit;

// 2. สร้าง Query แบบ Dynamic
$sql_base = "FROM shelters WHERE 1=1";
$params = [];

if ($search) {
    $sql_base .= " AND (name LIKE :search OR district LIKE :search)";
    $params['search'] = "%$search%";
}

if ($filter_status != 'all') {
    $sql_base .= " AND status = :status";
    $params['status'] = $filter_status;
}

// 3. นับจำนวนและดึงข้อมูล
$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT * " . $sql_base . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt_shelters = $pdo->prepare($sql_data);
$stmt_shelters->execute($params);
$shelters = $stmt_shelters->fetchAll();
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                <li class="breadcrumb-item active" aria-current="page">ตั้งค่าระบบ</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark">จัดการข้อมูลศูนย์พักพิงชั่วคราว</h3>
        <span class="text-muted small">พบข้อมูลทั้งหมด <?php echo number_format($total_rows); ?> แห่ง</span>
    </div>
    
    <div class="d-flex gap-2">
        <a href="shelter_import.php" class="btn btn-success d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i> 
            <span>นำเข้า (CSV)</span>
        </a>
        <a href="shelter_form.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-plus-circle-fill"></i> 
            <span>เพิ่มศูนย์ใหม่</span>
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card card-modern border-0 mb-4 bg-white">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0 bg-light" placeholder="ค้นหาชื่อศูนย์, อำเภอ..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select bg-light" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>สถานะทั้งหมด</option>
                    <option value="OPEN" <?php echo $filter_status=='OPEN'?'selected':''; ?>>เปิดใช้งาน (Open)</option>
                    <option value="CLOSED" <?php echo $filter_status=='CLOSED'?'selected':''; ?>>ปิดทำการ (Closed)</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">ค้นหา</button>
            </div>
            
            <?php if($search || $filter_status != 'all'): ?>
            <div class="col-md-auto">
                <a href="shelter_list.php" class="btn btn-link text-decoration-none text-danger btn-sm">
                    <i class="bi bi-x-circle"></i> ล้างค่า
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table Section -->
<div class="card card-modern border-0">
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">ชื่อศูนย์พักพิงชั่วคราว</th>
                    <th>ที่ตั้ง</th>
                    <th>ความจุ</th>
                    <th>สถานะปัจจุบัน</th>
                    <th>เหตุการณ์</th>
                    <th class="text-end pe-4" style="min-width: 240px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($shelters) > 0): ?>
                    <?php foreach ($shelters as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">
                                <?php echo $row['name']; ?>
                            </td>
                            <td>
                                <span class="d-block text-dark small">อ.<?php echo $row['district']; ?></span>
                                <span class="text-muted small">จ.<?php echo $row['province']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-soft bg-light text-secondary border">
                                    <?php echo number_format($row['capacity']); ?> คน
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'OPEN'): ?>
                                    <span class="badge badge-soft bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                        <i class="bi bi-check-circle-fill"></i> เปิดใช้งาน
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-soft bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                        <i class="bi bi-dash-circle-fill"></i> ปิดทำการ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo $row['current_event'] ? $row['current_event'] : '-'; ?></small></td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    
                                    <!-- ปุ่ม Monitor (War Room) -->
                                    <a href="monitor_dashboard.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-warning text-dark" 
                                       title="เปิดจอมอนิเตอร์" target="_blank">
                                        <i class="bi bi-display"></i>
                                    </a>

                                    <!-- ปุ่มดูรายงานภายใน -->
                                    <a href="report_shelter.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-info text-white" 
                                       title="ดูรายงานละเอียด" target="_blank">
                                        <i class="bi bi-clipboard-data-fill"></i>
                                    </a>

                                    <!-- ปุ่มเปลี่ยนสถานะ -->
                                    <a href="shelter_status.php?action=toggle&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm <?php echo $row['status']=='OPEN' ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                       title="<?php echo $row['status']=='OPEN' ? 'ปิดศูนย์' : 'เปิดศูนย์'; ?>">
                                        <i class="bi <?php echo $row['status']=='OPEN' ? 'bi-power' : 'bi-play-fill'; ?>"></i>
                                    </a>
                                    
                                    <!-- ปุ่มแก้ไข -->
                                    <a href="shelter_form.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-light text-primary border" 
                                       title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <!-- ปุ่มลบ -->
                                    <button onclick="confirmDeleteShelter(<?php echo $row['id']; ?>)" 
                                            class="btn btn-sm btn-light text-danger border" 
                                            title="ลบข้อมูล">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิงชั่วคราวตามเงื่อนไข</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Smart Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center bg-light">
        <div class="small text-muted mb-2 mb-md-0">
            แสดงหน้า <strong><?php echo $page; ?></strong> จาก <strong><?php echo $total_pages; ?></strong>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <!-- ปุ่มย้อนกลับ -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                $range = 2; // จำนวนหน้าที่จะแสดงรอบๆ หน้าปัจจุบัน
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);

                // แสดงหน้า 1 และ ... ถ้าจำเป็น
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search='.$search.'&status='.$filter_status.'">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // ลูปแสดงเลขหน้าช่วงกลาง
                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.'&search='.$search.'&status='.$filter_status.'">'.$i.'</a></li>';
                }

                // แสดงหน้าสุดท้าย และ ... ถ้าจำเป็น
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.$search.'&status='.$filter_status.'">'.$total_pages.'</a></li>';
                }
                ?>

                <!-- ปุ่มถัดไป -->
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&status=<?php echo $filter_status; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

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
</script>

<?php require_once 'includes/footer.php'; ?>