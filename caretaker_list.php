<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ Admin
if ($_SESSION['role'] != 'ADMIN') {
    header("Location: index.php"); exit();
}

// --- 1. รับค่าค้นหาและกรอง ---
$search = $_GET['search'] ?? '';
$shelter_filter = $_GET['shelter_id'] ?? 'all';

$params = [];
$sql = "SELECT u.*, s.name as shelter_name, s.district 
        FROM users u 
        LEFT JOIN shelters s ON u.shelter_id = s.id 
        WHERE u.role = 'STAFF'";

if ($search) {
    $sql .= " AND (u.fullname LIKE :search OR u.username LIKE :search OR u.phone LIKE :search)";
    $params['search'] = "%$search%";
}

if ($shelter_filter != 'all') {
    $sql .= " AND u.shelter_id = :sid";
    $params['sid'] = $shelter_filter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ดึงรายชื่อศูนย์ทั้งหมดเพื่อทำ Filter
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status='OPEN'")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                <li class="breadcrumb-item active">จัดการผู้ใช้งาน</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark">ข้อมูลผู้ดูแลศูนย์ (Staff)</h3>
        <span class="text-muted small">พบบัญชีผู้ใช้งานทั้งหมด <?php echo count($users); ?> รายการ</span>
    </div>
    
    <a href="caretaker_form.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm fw-bold rounded-pill px-4">
        <i class="bi bi-person-plus-fill"></i> 
        <span>เพิ่มผู้ดูแลใหม่</span>
    </a>
</div>

<!-- Search & Filter Bar -->
<div class="card card-modern border-0 mb-4 bg-white shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, Username, เบอร์โทร..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="shelter_id" class="form-select" onchange="this.form.submit()">
                    <option value="all">🏢 ทุกศูนย์พักพิง</option>
                    <?php foreach($shelters as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo $shelter_filter == $id ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">กรองข้อมูล</button>
                    <?php if($search || $shelter_filter != 'all'): ?>
                        <a href="caretaker_list.php" class="btn btn-light border text-danger" title="ล้างค่า"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Section -->
<div class="card card-modern border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">เจ้าหน้าที่ / Username</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">ข้อมูลติดต่อ</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">ศูนย์ที่รับผิดชอบ</th>
                    <th class="py-3 text-secondary text-uppercase small fw-bold">วันที่สร้าง</th>
                    <th class="py-3 text-end pe-4 text-secondary text-uppercase small fw-bold" style="min-width: 140px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $row): 
                        $avatar_char = mb_substr($row['fullname'], 0, 1);
                        // สุ่มสี Avatar จาก ID
                        $colors = ['primary', 'success', 'warning', 'info', 'danger', 'secondary'];
                        $color = $colors[$row['id'] % count($colors)];
                    ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                         style="width: 45px; height: 45px; font-size: 1.2rem;">
                                        <?php echo $avatar_char; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $row['fullname']; ?></div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-secondary border font-monospace small">
                                                <i class="bi bi-person-lock me-1"></i><?php echo $row['username']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <?php if($row['phone']): ?>
                                        <div class="small text-dark">
                                            <i class="bi bi-telephone-fill text-success me-2"></i><?php echo $row['phone']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($row['email']): ?>
                                        <div class="small text-muted">
                                            <i class="bi bi-envelope-fill text-primary me-2"></i><?php echo $row['email']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!$row['phone'] && !$row['email']): ?>
                                        <span class="text-muted small">- ไม่ระบุ -</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['shelter_name']): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-building-fill text-warning"></i>
                                        <div>
                                            <div class="text-dark small fw-bold"><?php echo $row['shelter_name']; ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;">อ.<?php echo $row['district']; ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">ไม่ระบุศูนย์</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-dark small fw-bold"><?php echo date('d/m/y', strtotime($row['created_at'])); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <?php echo date('H:i', strtotime($row['created_at'])); ?> น.
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if($row['phone']): ?>
                                        <a href="tel:<?php echo $row['phone']; ?>" class="btn btn-sm btn-light border text-success" data-bs-toggle="tooltip" title="โทรออก">
                                            <i class="bi bi-telephone-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="caretaker_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border text-primary" data-bs-toggle="tooltip" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    
                                    <button onclick="confirmDeleteUser(<?php echo $row['id']; ?>, '<?php echo $row['username']; ?>')" 
                                            class="btn btn-sm btn-light border text-danger" data-bs-toggle="tooltip" title="ลบบัญชี">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted bg-light">ไม่พบข้อมูลผู้ดูแลระบบตามเงื่อนไข</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Initialize Tooltips
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});

function confirmDeleteUser(id, username) {
    Swal.fire({
        title: 'ยืนยันการลบบัญชี?',
        html: `คุณต้องการลบผู้ใช้งาน <b>${username}</b> ใช่หรือไม่?<br><small class="text-danger">การกระทำนี้ไม่สามารถย้อนกลับได้</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#e5e7eb',
        cancelButtonText: '<span class="text-dark">ยกเลิก</span>',
        confirmButtonText: 'ยืนยันลบ'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `caretaker_status.php?action=delete&id=${id}`;
        }
    })
}
</script>

<?php require_once 'includes/footer.php'; ?>