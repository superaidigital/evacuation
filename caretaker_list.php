<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ Admin
if ($_SESSION['role'] != 'ADMIN') {
    header("Location: index.php"); exit();
}

// ดึงข้อมูล User ที่เป็น STAFF พร้อมชื่อศูนย์ที่สังกัด
$sql = "SELECT u.*, s.name as shelter_name, s.district 
        FROM users u 
        LEFT JOIN shelters s ON u.shelter_id = s.id 
        WHERE u.role = 'STAFF' 
        ORDER BY u.id DESC";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                <li class="breadcrumb-item active">จัดการผู้ใช้งาน</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark">ข้อมูลผู้ดูแลศูนย์ (เจ้าหน้าที่)</h3>
        <span class="text-muted small">จัดการบัญชีผู้ใช้งานและกำหนดสิทธิ์ดูแลศูนย์</span>
    </div>
    
    <a href="caretaker_form.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
        <i class="bi bi-person-plus-fill"></i> 
        <span>เพิ่มผู้ดูแลใหม่</span>
    </a>
</div>

<div class="card card-modern border-0">
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">ชื่อ-นามสกุล</th>
                    <th>Username</th>
                    <th>ศูนย์ที่รับผิดชอบ</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-end pe-4">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary" style="width: 32px; height: 32px;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <?php echo $row['fullname']; ?>
                                </div>
                            </td>
                            <td class="font-monospace text-secondary"><?php echo $row['username']; ?></td>
                            <td>
                                <?php if ($row['shelter_name']): ?>
                                    <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25">
                                        <i class="bi bi-house-door me-1"></i> <?php echo $row['shelter_name']; ?>
                                    </span>
                                    <small class="text-muted ms-1">(อ.<?php echo $row['district']; ?>)</small>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">ไม่ระบุศูนย์</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?php echo date('d/m/Y', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="caretaker_form.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" title="แก้ไข">
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </a>
                                <button onclick="confirmDeleteUser(<?php echo $row['id']; ?>)" 
                                        class="btn btn-sm btn-outline-danger rounded-pill px-3" title="ลบ">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้ดูแลระบบ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDeleteUser(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "บัญชีผู้ใช้นี้จะไม่สามารถเข้าสู่ระบบได้อีก",
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