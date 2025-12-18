<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if ($_SESSION['role'] != 'ADMIN') { 
    header("Location: index.php"); 
    exit(); 
}

$id = $_GET['id'] ?? '';
// ค่าเริ่มต้น
$data = [
    'name' => '', 
    'district' => '', 
    'province' => 'ศรีสะเกษ', 
    'capacity' => '100', 
    'current_event' => '', 
    'status' => 'OPEN'
];
$coordinators = []; // เก็บรายชื่อผู้ประสานงาน

$mode = 'add';
$title = 'เพิ่มศูนย์พักพิงชั่วคราวใหม่';

if ($id) {
    // --- โหมดแก้ไข (Edit) ---
    $mode = 'edit';
    $title = 'แก้ไขข้อมูลศูนย์';
    
    // 1. ดึงข้อมูลศูนย์
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) $data = $fetched;

    // 2. ดึงข้อมูลผู้ประสานงาน
    $stmt_coord = $pdo->prepare("SELECT * FROM shelter_coordinators WHERE shelter_id = ?");
    $stmt_coord->execute([$id]);
    $coordinators = $stmt_coord->fetchAll(PDO::FETCH_ASSOC);

} else {
    // --- โหมดเพิ่มใหม่ (Add) ---
    // ดึงชื่อเหตุการณ์ปัจจุบันมาเติมให้อัตโนมัติ (Auto-fill)
    $stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
    $stmt_event->execute();
    $global_event = $stmt_event->fetchColumn();
    if ($global_event) {
        $data['current_event'] = $global_event;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <!-- Breadcrumb & Back Button -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="shelter_list.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
            </a>
        </div>

        <div class="card card-modern animate-fade-in">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-house-door-fill fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><?php echo $title; ?></h5>
                        <p class="text-muted small mb-0">จัดการข้อมูลสถานที่และทีมงานผู้ประสานงาน</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form action="shelter_save.php" method="POST">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <!-- ส่วนที่ 1: ข้อมูลสถานที่ -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3 border-bottom pb-2">
                        <i class="bi bi-geo-alt-fill me-1"></i> ข้อมูลสถานที่
                    </h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อศูนย์พักพิงชั่วคราว <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?php echo $data['name']; ?>" 
                               placeholder="เช่น วัดป่า..., โรงเรียน...">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">อำเภอ <span class="text-danger">*</span></label>
                            <input type="text" name="district" class="form-control" required 
                                   value="<?php echo $data['district']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">จังหวัด</label>
                            <input type="text" name="province" class="form-control" 
                                   value="<?php echo $data['province']; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">ความจุสูงสุด (คน)</label>
                            <input type="number" name="capacity" class="form-control" required 
                                   value="<?php echo $data['capacity']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">สถานะ</label>
                            <select name="status" class="form-select">
                                <option value="OPEN" <?php echo ($data['status'] == 'OPEN') ? 'selected' : ''; ?>>
                                    เปิดใช้งาน (Active)
                                </option>
                                <option value="CLOSED" <?php echo ($data['status'] == 'CLOSED') ? 'selected' : ''; ?>>
                                    ปิดทำการ (Closed)
                                </option>
                            </select>
                            <?php if($mode == 'add'): ?>
                                <div class="form-text text-success small">
                                    <i class="bi bi-check-circle"></i> ตั้งค่าเริ่มต้นเป็น "เปิดใช้งาน"
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-primary">เหตุการณ์ (สถานการณ์ปัจจุบัน)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary bg-opacity-10 text-primary border-primary border-opacity-25">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </span>
                            <input type="text" name="current_event" class="form-control" 
                                   value="<?php echo $data['current_event']; ?>" 
                                   placeholder="เช่น อุทกภัย ต.ค. 2568">
                        </div>
                        <div class="form-text small text-muted mt-1">
                            * ระบบดึงชื่อจาก Dashboard มาใส่ให้โดยอัตโนมัติ (แก้ไขได้)
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: ข้อมูลผู้ประสานงาน (Dynamic) -->
                    <div class="d-flex justify-content-between align-items-center mb-3 mt-4 border-bottom pb-2">
                        <h6 class="text-uppercase text-secondary small fw-bold mb-0">
                            <i class="bi bi-people-fill me-1"></i> ผู้ประสานงานประจำศูนย์
                        </h6>
                        <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="addCoordinator()">
                            <i class="bi bi-plus-lg"></i> เพิ่มผู้ติดต่อ
                        </button>
                    </div>

                    <div id="coordinator-container">
                        <?php if(count($coordinators) > 0): ?>
                            <!-- กรณีแก้ไข: วนลูปแสดงข้อมูลเดิม -->
                            <?php foreach($coordinators as $index => $coord): ?>
                                <div class="row g-2 mb-2 coordinator-row" id="row-old-<?php echo $index; ?>">
                                    <div class="col-md-4">
                                        <input type="text" name="coord_name[]" class="form-control form-control-sm" 
                                               placeholder="ชื่อ-นามสกุล" value="<?php echo $coord['name']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="coord_phone[]" class="form-control form-control-sm" 
                                               placeholder="เบอร์โทร" value="<?php echo $coord['phone']; ?>">
                                    </div>
                                    <div class="col-md-4 d-flex gap-1">
                                        <input type="text" name="coord_pos[]" class="form-control form-control-sm" 
                                               placeholder="ตำแหน่ง (เช่น หน.ศูนย์)" value="<?php echo $coord['position']; ?>">
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                onclick="removeRow('row-old-<?php echo $index; ?>')" title="ลบแถวนี้">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- กรณีเพิ่มใหม่: แสดงแถวว่าง 1 แถว -->
                            <div class="row g-2 mb-2 coordinator-row" id="row-0">
                                <div class="col-md-4">
                                    <input type="text" name="coord_name[]" class="form-control form-control-sm" 
                                           placeholder="ชื่อ-นามสกุล (ผู้ประสานงาน)">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="coord_phone[]" class="form-control form-control-sm" 
                                           placeholder="เบอร์โทร">
                                </div>
                                <div class="col-md-4 d-flex gap-1">
                                    <input type="text" name="coord_pos[]" class="form-control form-control-sm" 
                                           placeholder="ตำแหน่ง (เช่น หน.ศูนย์)">
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="removeRow('row-0')"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-text small mb-4">* กดปุ่ม "เพิ่มผู้ติดต่อ" เพื่อเพิ่มรายชื่อทีมงานได้ไม่จำกัด</div>

                    <!-- ปุ่มบันทึก -->
                    <div class="d-grid gap-2 border-top pt-4">
                        <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับเพิ่ม/ลบแถว -->
<script>
    // ฟังก์ชันเพิ่มแถวใหม่
    function addCoordinator() {
        const container = document.getElementById('coordinator-container');
        const timestamp = new Date().getTime(); // ใช้เวลาเป็น ID ไม่ซ้ำ
        const html = `
            <div class="row g-2 mb-2 coordinator-row animate-fade-in" id="row-${timestamp}">
                <div class="col-md-4">
                    <input type="text" name="coord_name[]" class="form-control form-control-sm" placeholder="ชื่อ-นามสกุล" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="coord_phone[]" class="form-control form-control-sm" placeholder="เบอร์โทร">
                </div>
                <div class="col-md-4 d-flex gap-1">
                    <input type="text" name="coord_pos[]" class="form-control form-control-sm" placeholder="ตำแหน่ง">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow('row-${timestamp}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    // ฟังก์ชันลบแถว
    function removeRow(id) {
        const row = document.getElementById(id);
        if(row) row.remove();
    }
</script>

<style>
    .animate-fade-in { animation: fadeIn 0.3s ease-in; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php require_once 'includes/footer.php'; ?>