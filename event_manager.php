<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') {
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

// --- Helper: แปลงวันที่ไทย ---
function thai_date($strDate) {
    if(!$strDate) return '-';
    $strYear = date("Y",strtotime($strDate))+543;
    $strMonth= date("n",strtotime($strDate));
    $strDay= date("j",strtotime($strDate));
    $strMonthCut = Array("","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค.");
    return "$strDay $strMonthCut[$strMonth] $strYear";
}

// --- Helper: Sync ชื่อเหตุการณ์กับตาราง Settings ---
function syncCurrentEventSetting($pdo, $name) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'current_event'");
    $check->execute();
    if ($check->fetchColumn() > 0) {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'current_event'")->execute([$name]);
    } else {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('current_event', ?)")->execute([$name]);
    }
}

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. สร้างเหตุการณ์ใหม่
    if (isset($_POST['action']) && $_POST['action'] == 'create') {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $start = $_POST['start_date'];
        
        try {
            $pdo->beginTransaction();
            $pdo->query("UPDATE events SET status = 'CLOSED', end_date = IF(end_date IS NULL, CURDATE(), end_date) WHERE status = 'ACTIVE'");
            $stmt = $pdo->prepare("INSERT INTO events (name, description, start_date, status) VALUES (?, ?, ?, 'ACTIVE')");
            $stmt->execute([$name, $desc, $start]);
            syncCurrentEventSetting($pdo, $name);
            $pdo->commit();
            $_SESSION['swal_success'] = ['title' => 'เปิดวาระใหม่แล้ว', 'text' => 'ระบบพร้อมรับข้อมูลสำหรับเหตุการณ์นี้'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_error'] = ['title' => 'เกิดข้อผิดพลาด', 'text' => $e->getMessage()];
        }
    }

    // 2. แก้ไขชื่อด่วน
    if (isset($_POST['action']) && $_POST['action'] == 'quick_edit') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        
        $stmt = $pdo->prepare("UPDATE events SET name=? WHERE id=?");
        $stmt->execute([$name, $id]);
        
        $check = $pdo->prepare("SELECT status FROM events WHERE id = ?");
        $check->execute([$id]);
        if($check->fetchColumn() == 'ACTIVE') {
            syncCurrentEventSetting($pdo, $name);
        }
        $_SESSION['swal_success'] = ['title' => 'บันทึกชื่อเรียบร้อย', 'text' => 'ชื่อเหตุการณ์ถูกปรับปรุงแล้ว'];
    }

    // 3. แก้ไขข้อมูลเต็ม
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $start = $_POST['start_date'];
        $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        $stmt = $pdo->prepare("UPDATE events SET name=?, description=?, start_date=?, end_date=? WHERE id=?");
        $stmt->execute([$name, $desc, $start, $end, $id]);
        
        $check = $pdo->prepare("SELECT status FROM events WHERE id = ?");
        $check->execute([$id]);
        if($check->fetchColumn() == 'ACTIVE') {
            syncCurrentEventSetting($pdo, $name);
        }
        $_SESSION['swal_success'] = ['title' => 'บันทึกข้อมูลเรียบร้อย', 'text' => 'รายละเอียดได้รับการแก้ไข'];
    }

    // 4. เปลี่ยน Active Event
    if (isset($_POST['action']) && $_POST['action'] == 'set_active') {
        $id = $_POST['id'];
        $name = $_POST['name'];

        $pdo->beginTransaction();
        $pdo->query("UPDATE events SET status = 'CLOSED', end_date = IF(end_date IS NULL, CURDATE(), end_date) WHERE status = 'ACTIVE'");
        $pdo->prepare("UPDATE events SET status = 'ACTIVE', end_date = NULL WHERE id = ?")->execute([$id]);
        syncCurrentEventSetting($pdo, $name);
        $pdo->commit();

        $_SESSION['swal_success'] = ['title' => 'เปลี่ยนสถานการณ์แล้ว', 'text' => "กลับมาใช้ข้อมูลของ: $name"];
    }

    echo "<script>window.location.href='event_manager.php';</script>";
    exit();
}

// --- QUERY DATA ---
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM evacuees WHERE event_id = e.id) as total_people,
        (SELECT COUNT(DISTINCT shelter_id) FROM evacuees WHERE event_id = e.id) as total_shelters
        FROM events e 
        ORDER BY FIELD(status, 'ACTIVE', 'CLOSED'), start_date DESC";
$events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$active_event = null;
foreach($events as $ev) {
    if($ev['status'] == 'ACTIVE') {
        $active_event = $ev;
        break;
    }
}
?>

<!-- Custom Styles -->
<style>
    :root {
        --color-bg: #f3f4f6;
        --color-card: #ffffff;
    }
    body { background-color: var(--color-bg); }
    
    .card-dashboard {
        border: none;
        border-radius: 16px;
        background: var(--color-card);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    .bg-gradient-brand {
        background: linear-gradient(120deg, #2563eb 0%, #1d4ed8 100%);
    }
    .table-modern thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
    }
    .table-modern tbody td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .btn-white {
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        color: #1e293b;
        transition: all 0.2s;
    }
    .btn-white:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .input-group-text { background-color: white; border-right: 0; }
    .form-control:focus { box-shadow: none; border-color: #ced4da; }
</style>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-0"><i class="bi bi-calendar-range-fill text-primary"></i> จัดการวาระภัยพิบัติ</h3>
        <p class="text-muted small mb-0">Event Manager: ควบคุมวงรอบการดำเนินงานและจัดเก็บประวัติแยกรายเหตุการณ์</p>
    </div>
    <button class="btn btn-primary shadow-sm fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i> เปิดเหตุการณ์ใหม่
    </button>
</div>

<!-- 1. Active Event Banner -->
<?php if($active_event): ?>
<div class="card card-dashboard bg-gradient-brand text-white mb-4 border-0 position-relative overflow-hidden">
    <div class="card-body p-4 position-relative z-1">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="d-flex align-items-center gap-2 mb-2 text-white-50">
                    <span class="badge bg-white bg-opacity-25 rounded-pill px-3 fw-normal">สถานการณ์ปัจจุบัน (Active)</span>
                    <i class="bi bi-broadcast"></i>
                </div>
                
                <div id="activeDisplay" class="mb-3 mb-lg-0">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h2 class="fw-bold mb-0 text-white" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <?php echo htmlspecialchars($active_event['name']); ?>
                        </h2>
                        <button class="btn btn-light text-primary fw-bold shadow-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2 btn-sm" 
                                onclick="toggleActiveEdit()" title="แก้ไขชื่อ">
                            <i class="bi bi-pencil-square"></i> แก้ไขชื่อ
                        </button>
                    </div>
                    <div class="mt-2 text-white-50 small">
                        <span class="me-3"><i class="bi bi-calendar-check me-1"></i> เริ่ม: <?php echo thai_date($active_event['start_date']); ?></span>
                        <span><i class="bi bi-people-fill me-1"></i> ผู้ประสบภัย: <strong><?php echo number_format($active_event['total_people']); ?></strong> คน</span>
                    </div>
                </div>

                <form method="POST" id="activeEditForm" class="d-none" style="max-width: 500px;">
                    <input type="hidden" name="action" value="quick_edit">
                    <input type="hidden" name="id" value="<?php echo $active_event['id']; ?>">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white">
                        <span class="input-group-text ps-3 text-primary"><i class="bi bi-megaphone-fill"></i></span>
                        <input type="text" name="name" class="form-control border-0 py-2" 
                               value="<?php echo htmlspecialchars($active_event['name']); ?>" required>
                        <button type="submit" class="btn btn-success fw-bold text-white px-3 border-0"><i class="bi bi-check-lg"></i></button>
                        <button type="button" class="btn btn-danger fw-bold text-white px-3 border-0" onclick="toggleActiveEdit()"><i class="bi bi-x-lg"></i></button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                 <div class="d-flex justify-content-lg-end flex-wrap gap-2">
                    <!-- ปุ่มสำหรับ Active Event -->
                    <a href="shelter_list.php?event_id=<?php echo $active_event['id']; ?>" class="btn btn-white text-primary fw-bold shadow-sm rounded-pill px-3">
                        <i class="bi bi-building"></i> ข้อมูลศูนย์
                    </a>
                    <a href="search_evacuee.php?event_id=<?php echo $active_event['id']; ?>" class="btn btn-white text-primary fw-bold shadow-sm rounded-pill px-3">
                        <i class="bi bi-people-fill"></i> รายชื่อ
                    </a>
                    <button class="btn btn-white text-dark fw-bold shadow-sm rounded-pill px-3" onclick='openFullEdit(<?php echo json_encode($active_event); ?>)'>
                        <i class="bi bi-sliders"></i> จัดการ
                    </button>
                    <a href="report.php?event_id=<?php echo $active_event['id']; ?>" class="btn btn-warning text-dark fw-bold shadow-sm rounded-pill px-3">
                        <i class="bi bi-bar-chart-fill"></i> สรุปผล
                    </a>
                 </div>
            </div>
        </div>
    </div>
    <i class="bi bi-megaphone-fill position-absolute text-white opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; transform: rotate(-15deg);"></i>
</div>
<?php endif; ?>

<!-- 2. Event History Table -->
<div class="card card-dashboard border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3 px-4">
        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-clock-history text-secondary me-2"></i> ประวัติและรายการทั้งหมด</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">ชื่อเหตุการณ์</th>
                    <th>ระยะเวลา</th>
                    <th class="text-center">ผู้ประสบภัย</th>
                    <th class="text-center">ศูนย์ที่เปิด</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-end pe-4" style="min-width: 250px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($events as $row): 
                    $is_active = ($row['status'] == 'ACTIVE');
                ?>
                <tr class="<?php echo $is_active ? 'bg-primary bg-opacity-10' : ''; ?>">
                    <td class="ps-4">
                        <div class="fw-bold <?php echo $is_active ? 'text-primary' : 'text-dark'; ?>">
                            <?php if($is_active) echo '<i class="bi bi-broadcast me-1"></i>'; ?>
                            <?php echo $row['name']; ?>
                        </div>
                        <div class="text-muted small text-truncate" style="max-width: 300px;">
                            <?php echo $row['description'] ? $row['description'] : '-'; ?>
                        </div>
                    </td>
                    <td>
                        <span class="small text-secondary">
                            <?php echo thai_date($row['start_date']); ?>
                            <br>
                            <?php echo $row['end_date'] ? ' ถึง '.thai_date($row['end_date']) : ' (ปัจจุบัน)'; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border rounded-pill px-3">
                            <?php echo number_format($row['total_people']); ?> คน
                        </span>
                    </td>
                    <td class="text-center">
                        <?php echo number_format($row['total_shelters']); ?> แห่ง
                    </td>
                    <td class="text-center">
                        <?php if($is_active): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill">ใช้งานอยู่</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill">ปิดแล้ว</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <!-- ปุ่มดูข้อมูลเฉพาะเหตุการณ์นั้นๆ -->
                        <div class="d-flex justify-content-end gap-1">
                            <a href="shelter_list.php?event_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="ดูศูนย์ที่เปิดในเหตุการณ์นี้">
                                <i class="bi bi-building"></i> ศูนย์
                            </a>
                            <a href="search_evacuee.php?event_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="ดูรายชื่อในเหตุการณ์นี้">
                                <i class="bi bi-people"></i> รายชื่อ
                            </a>
                            
                            <div class="vr mx-1"></div> <!-- เส้นคั่น -->

                            <div class="btn-group">
                                <?php if(!$is_active): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการเปลี่ยนมาใช้เหตุการณ์นี้? (เหตุการณ์ปัจจุบันจะถูกปิด)');">
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="name" value="<?php echo $row['name']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="เรียกคืนสถานะ">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-secondary" title="แก้ไขละเอียด" 
                                        onclick='openFullEdit(<?php echo json_encode($row); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create New -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle"></i> เปิดวาระภัยพิบัติใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small border-0 bg-info bg-opacity-10 text-dark mb-3">
                        <i class="bi bi-info-circle-fill text-info me-1"></i> 
                        เมื่อสร้างใหม่ <strong>เหตุการณ์เดิมจะถูกปิดอัตโนมัติ</strong> และระบบจะเริ่มบันทึกข้อมูลใหม่ทันที
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">ชื่อเหตุการณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="เช่น อุทกภัย อ.กันทรลักษ์ ปี 2568">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">วันที่เริ่ม</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4">ยืนยันสร้างใหม่</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Full Edit -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold">แก้ไขรายละเอียดเหตุการณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">ชื่อเหตุการณ์</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-secondary">วันที่เริ่ม</label>
                            <input type="date" name="start_date" id="edit_start" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-secondary">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" id="edit_end" class="form-control">
                            <div class="form-text small text-muted">เว้นว่างหากยังดำเนินการอยู่</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">รายละเอียด</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-white border rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleActiveEdit() {
    const display = document.getElementById('activeDisplay');
    const form = document.getElementById('activeEditForm');
    display.classList.toggle('d-none');
    form.classList.toggle('d-none');
}

function openFullEdit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_desc').value = data.description;
    document.getElementById('edit_start').value = data.start_date;
    document.getElementById('edit_end').value = data.end_date || '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>