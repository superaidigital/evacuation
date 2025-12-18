<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';
// ค่าเริ่มต้นของฟอร์ม (รวมเบอร์โทรและอีเมล์)
$data = [
    'username' => '', 
    'fullname' => '', 
    'phone' => '', 
    'email' => '', 
    'shelter_id' => ''
];
$mode = 'add';
$title = 'เพิ่มผู้ดูแลศูนย์ใหม่';

// โหมดแก้ไข: ดึงข้อมูลเดิมมาแสดง
if ($id) {
    $mode = 'edit';
    $title = 'แก้ไขข้อมูลผู้ดูแล';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $data = $fetched;
    }
}

// ดึงรายชื่อศูนย์ทั้งหมดที่เปิดใช้งาน (สำหรับ Dropdown)
$shelters = $pdo->query("SELECT * FROM shelters WHERE status = 'OPEN' ORDER BY name")->fetchAll();
?>

<!-- Select2 CSS (สำหรับ Dropdown ค้นหาได้) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ปรับแต่ง Select2 ให้เข้ากับ Bootstrap theme */
    .select2-container .select2-selection--single {
        height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 12px;
        color: #212529;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
        right: 10px;
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <!-- ปุ่มย้อนกลับ -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="caretaker_list.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left"></i> กลับหน้ารายชื่อ
            </a>
        </div>

        <div class="card card-modern animate-fade-in">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-person-badge-fill fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><?php echo $title; ?></h5>
                        <p class="text-muted small mb-0">กำหนดข้อมูลส่วนตัวและสิทธิ์การเข้าใช้งาน</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form action="caretaker_save.php" method="POST">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <!-- ส่วนที่ 1: ข้อมูลส่วนตัว -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3 border-bottom pb-2">ข้อมูลทั่วไป</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อ-นามสกุลจริง <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-person"></i></span>
                            <input type="text" name="fullname" class="form-control border-start-0 ps-0" required 
                                   value="<?php echo $data['fullname']; ?>" placeholder="เช่น นายสมชาย ใจดี">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="phone" class="form-control border-start-0 ps-0" 
                                       value="<?php echo $data['phone']; ?>" placeholder="08x-xxxxxxx">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">อีเมล์ (Email)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0" 
                                       value="<?php echo $data['email']; ?>" placeholder="name@example.com">
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: ข้อมูลเข้าระบบ -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3 mt-4 border-bottom pb-2">ข้อมูลเข้าสู่ระบบ (Login)</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control font-monospace" required 
                                   value="<?php echo $data['username']; ?>" 
                                   <?php echo ($mode == 'edit') ? 'readonly style="background-color: #f8f9fa;"' : ''; ?> 
                                   placeholder="ตั้งชื่อผู้ใช้ (ภาษาอังกฤษ)">
                            <?php if($mode == 'edit'): ?>
                                <div class="form-text small text-muted">Username ไม่สามารถแก้ไขได้</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password</label>
                            <input type="password" name="password" class="form-control font-monospace" 
                                   <?php echo ($mode == 'add') ? 'required' : ''; ?> 
                                   placeholder="<?php echo ($mode == 'edit') ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'กำหนดรหัสผ่าน'; ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-primary">ประจำการที่ศูนย์พักพิงชั่วคราว <span class="text-danger">*</span></label>
                        <select name="shelter_id" id="shelterSelect" class="form-select" required>
                            <option value="">-- พิมพ์ชื่อศูนย์เพื่อค้นหา --</option>
                            <?php foreach($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $data['shelter_id']) ? 'selected' : ''; ?>>
                                    <?php echo $s['name']; ?> (อ.<?php echo $s['district']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i> ผู้ดูแลจะเห็นข้อมูลและจัดการได้เฉพาะศูนย์ที่เลือกนี้เท่านั้น
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <div class="d-grid gap-2 border-top pt-4">
                        <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูลผู้ดูแล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Select2 Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // เปิดใช้งาน Select2 กับ Dropdown ศูนย์พักพิงชั่วคราว
        $('#shelterSelect').select2({
            placeholder: "-- พิมพ์ชื่อศูนย์เพื่อค้นหา --",
            allowClear: true,
            width: '100%' // บังคับให้กว้างเต็ม container
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>