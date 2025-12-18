<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'ADMIN') {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';

// ค่าเริ่มต้นของฟอร์ม
$data = [
    'username' => '', 
    'fullname' => '', 
    'phone' => '', 
    'email' => '', 
    'role' => 'STAFF', // ค่าเริ่มต้นเป็น STAFF
    'shelter_id' => ''
];

$mode = 'add';
$title = 'เพิ่มผู้ใช้งานใหม่';
$subtitle = 'สร้างบัญชีผู้ดูแลระบบหรือเจ้าหน้าที่ประจำศูนย์';

// โหมดแก้ไข: ดึงข้อมูลเดิมมาแสดง
if ($id) {
    $mode = 'edit';
    $title = 'แก้ไขข้อมูลผู้ใช้งาน';
    $subtitle = 'ปรับปรุงข้อมูลส่วนตัวหรือสิทธิ์การเข้าถึง';
    
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

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 38px; border: 1px solid #ced4da; border-radius: 0.375rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 12px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; right: 10px; }
    
    /* Custom Radio Button for Role */
    .role-option { cursor: pointer; transition: all 0.2s; }
    .role-option:hover { transform: translateY(-2px); }
    .btn-check:checked + .role-option { border-color: var(--bs-primary) !important; background-color: rgba(13, 110, 253, 0.05); }
    .btn-check:checked + .role-option .role-icon { background-color: var(--bs-primary); color: white; }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="caretaker_list.php" class="text-decoration-none text-secondary small">
                <i class="bi bi-arrow-left"></i> กลับหน้ารายชื่อ
            </a>
        </div>

        <div class="card card-modern animate-fade-in border-0 shadow-lg">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi <?php echo ($mode=='add') ? 'bi-person-plus-fill' : 'bi-pencil-square'; ?> fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><?php echo $title; ?></h5>
                        <p class="text-muted small mb-0"><?php echo $subtitle; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4">
                <form action="caretaker_save.php" method="POST" id="userForm">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <!-- 1. ส่วนกำหนดสิทธิ์ (Role Selection) -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-secondary mb-3">ประเภทผู้ใช้งาน <span class="text-danger">*</span></label>
                        <div class="row g-3">
                            <!-- STAFF Option -->
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="role" id="role_staff" value="STAFF" 
                                       <?php echo ($data['role'] == 'STAFF') ? 'checked' : ''; ?> 
                                       onchange="toggleShelterSelect()">
                                <label class="card h-100 role-option border shadow-sm p-3" for="role_staff">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="role-icon rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; color: #6c757d;">
                                            <i class="bi bi-person-badge fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1">เจ้าหน้าที่ประจำศูนย์ (Staff)</h6>
                                            <small class="text-muted d-block" style="font-size: 0.8rem; line-height: 1.3;">
                                                ดูแลจัดการข้อมูลเฉพาะศูนย์พักพิงที่ได้รับมอบหมายเท่านั้น
                                            </small>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- ADMIN Option -->
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="role" id="role_admin" value="ADMIN" 
                                       <?php echo ($data['role'] == 'ADMIN') ? 'checked' : ''; ?> 
                                       onchange="toggleShelterSelect()">
                                <label class="card h-100 role-option border shadow-sm p-3" for="role_admin">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="role-icon rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; color: #dc3545;">
                                            <i class="bi bi-shield-lock-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1 text-danger">ผู้ดูแลระบบสูงสุด (Admin)</h6>
                                            <small class="text-muted d-block" style="font-size: 0.8rem; line-height: 1.3;">
                                                สามารถเข้าถึงทุกเมนู จัดการผู้ใช้ และดูรายงานภาพรวมทั้งจังหวัด
                                            </small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <!-- 2. ข้อมูลส่วนตัว -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><i class="bi bi-person-vcard me-2"></i> ข้อมูลส่วนตัว</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อ-นามสกุลจริง <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control" required 
                               value="<?php echo $data['fullname']; ?>" placeholder="ระบุชื่อจริงและนามสกุล">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo $data['phone']; ?>" placeholder="08x-xxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">อีเมล (Email)</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo $data['email']; ?>" placeholder="name@example.com">
                        </div>
                    </div>

                    <!-- ส่วนเลือกศูนย์พักพิง (จะแสดงเฉพาะเมื่อเลือก Role = STAFF) -->
                    <div id="shelter_section" class="mb-4 p-3 bg-light rounded border border-warning border-opacity-25">
                        <label class="form-label small fw-bold text-dark">
                            <i class="bi bi-house-door-fill text-warning me-1"></i> ประจำการที่ศูนย์พักพิง <span class="text-danger">*</span>
                        </label>
                        <select name="shelter_id" id="shelterSelect" class="form-select">
                            <option value="">-- พิมพ์ชื่อศูนย์เพื่อค้นหา --</option>
                            <?php foreach($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $data['shelter_id']) ? 'selected' : ''; ?>>
                                    <?php echo $s['name']; ?> (อ.<?php echo $s['district']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small mt-1 text-muted">เจ้าหน้าที่จะมองเห็นและจัดการได้เฉพาะข้อมูลของศูนย์นี้เท่านั้น</div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <!-- 3. ข้อมูลเข้าระบบ -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><i class="bi bi-key-fill me-2"></i> ข้อมูลเข้าสู่ระบบ</h6>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control font-monospace" required 
                                       value="<?php echo $data['username']; ?>" 
                                       <?php echo ($mode == 'edit') ? 'readonly style="background-color: #f8f9fa;"' : ''; ?> 
                                       placeholder="ภาษาอังกฤษเท่านั้น">
                            </div>
                            <?php if($mode == 'edit'): ?>
                                <div class="form-text small text-muted"><i class="bi bi-lock-fill"></i> ไม่สามารถแก้ไข Username ได้</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">รหัสผ่าน (Password)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="passwordInput" class="form-control font-monospace" 
                                       <?php echo ($mode == 'add') ? 'required' : ''; ?> 
                                       placeholder="<?php echo ($mode == 'edit') ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'กำหนดรหัสผ่าน'; ?>">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="bi bi-eye-slash" id="toggleIcon"></i>
                                </button>
                            </div>
                            <?php if($mode == 'add'): ?>
                                <div class="form-text small text-muted">แนะนำให้ใช้อย่างน้อย 6 ตัวอักษร</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-2">
                        <a href="caretaker_list.php" class="btn btn-light px-4 border">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2 shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Init Select2
        $('#shelterSelect').select2({
            placeholder: "-- เลือกศูนย์พักพิง --",
            allowClear: true,
            width: '100%'
        });

        // เรียกใช้ครั้งแรกเพื่อซ่อน/แสดงตามค่า Default
        toggleShelterSelect();
    });

    // ฟังก์ชันซ่อน/แสดงช่องเลือกศูนย์ ตาม Role
    function toggleShelterSelect() {
        const isAdmin = document.getElementById('role_admin').checked;
        const shelterSection = document.getElementById('shelter_section');
        const shelterSelect = document.getElementById('shelterSelect');
        
        if (isAdmin) {
            // ถ้าเป็น Admin -> ซ่อนช่องเลือกศูนย์ และไม่ต้อง Required
            shelterSection.style.display = 'none';
            shelterSelect.removeAttribute('required');
        } else {
            // ถ้าเป็น Staff -> แสดงช่องเลือกศูนย์ และบังคับเลือก (Required)
            shelterSection.style.display = 'block';
            shelterSelect.setAttribute('required', 'required');
        }
    }

    // ฟังก์ชันเปิด/ปิดตาดูรหัสผ่าน
    function togglePassword() {
        const passInput = document.getElementById('passwordInput');
        const icon = document.getElementById('toggleIcon');
        
        if (passInput.type === 'password') {
            passInput.type = 'text';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        } else {
            passInput.type = 'password';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>