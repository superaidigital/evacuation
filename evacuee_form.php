<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$shelter_id = $_GET['shelter_id'] ?? '';
$id = $_GET['id'] ?? '';
$prefill_citizen_id = $_GET['citizen_id'] ?? '';

// ดึงรายชื่อศูนย์
$shelters = $pdo->query("SELECT * FROM shelters WHERE status = 'OPEN'")->fetchAll(PDO::FETCH_ASSOC);

if ($_SESSION['role'] == 'STAFF') {
    $shelter_id = $_SESSION['shelter_id'];
}

// ค่าเริ่มต้น
$data = [
    'citizen_id' => $prefill_citizen_id, 
    'prefix' => '', 
    'first_name' => '', 'last_name' => '', 
    'age' => '', 'birth_date' => '', 
    'health_condition' => 'ไม่มี', 'phone' => '',
    'house_no' => '', 'village_no' => '', 'subdistrict' => '', 'district' => '', 'province' => 'ศรีสะเกษ',
    'accommodation_type' => 'inside', // ค่าเริ่มต้น: พักในศูนย์
    'outside_detail' => '' // ข้อมูลพักนอกศูนย์ (ถ้ามี)
];

$mode = 'add';
$title = 'ลงทะเบียนผู้พักพิงใหม่';

if ($id) {
    $mode = 'edit';
    $title = 'แก้ไขข้อมูล';
    $stmt = $pdo->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if($fetched) {
        $data = $fetched;
        $shelter_id = $fetched['shelter_id']; 
    }
} else if ($prefill_citizen_id) {
    $stmt_old = $pdo->prepare("SELECT * FROM evacuees WHERE citizen_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_old->execute([$prefill_citizen_id]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
    if ($old_data) {
        $data = $old_data;
        $title = 'ลงทะเบียนรับเข้าใหม่ (ดึงข้อมูลเดิม)';
        $data['accommodation_type'] = 'inside'; 
        $data['outside_detail'] = '';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <div class="d-flex align-items-center gap-2 mb-3 text-secondary small">
            <?php if($shelter_id): ?>
                <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>" class="text-decoration-none text-secondary">&larr; กลับหน้ารายชื่อ</a>
            <?php else: ?>
                <a href="search_evacuee.php" class="text-decoration-none text-secondary">&larr; กลับหน้าค้นหา</a>
            <?php endif; ?>
        </div>
        
        <div class="card card-modern animate-fade-in">
            <div class="card-header bg-white border-bottom p-4">
                <div>
                    <h4 class="mb-0 fw-bold text-primary"><?php echo $title; ?></h4>
                    <p class="text-muted small mb-0">กรอกข้อมูลให้ครบถ้วนเพื่อสิทธิประโยชน์</p>
                </div>
            </div>
            <div class="card-body p-4">
                <!-- เพิ่ม id="evacueeForm" เพื่อใช้อ้างอิงใน JS -->
                <form action="evacuee_save.php" method="POST" id="evacueeForm">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    
                    <!-- Hidden Input สำหรับเก็บรายละเอียดพักนอกศูนย์ -->
                    <input type="hidden" name="outside_detail" id="outside_detail" value="<?php echo htmlspecialchars($data['outside_detail'] ?? ''); ?>">
                    
                    <!-- ส่วนที่ 1: เลือกศูนย์และรูปแบบการพัก -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-8">
                            <label class="fw-bold text-dark mb-1"><i class="bi bi-house-door-fill text-success"></i> สังกัดศูนย์พักพิงชั่วคราว</label>
                            <?php if($_SESSION['role'] == 'STAFF'): ?>
                                <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                                <input type="text" class="form-control bg-light fw-bold" value="<?php 
                                    foreach($shelters as $s) { if($s['id'] == $shelter_id) echo $s['name']; } 
                                ?>" readonly>
                            <?php else: ?>
                                <select name="shelter_id" class="form-select border-success fw-bold" required>
                                    <option value="">-- กรุณาเลือกศูนย์พักพิงชั่วคราว --</option>
                                    <?php foreach($shelters as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $shelter_id) ? 'selected' : ''; ?>>
                                            <?php echo $s['name']; ?> (อ.<?php echo $s['district']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold text-dark mb-1">รูปแบบการพัก</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="accommodation_type" id="acc_inside" value="inside" <?php echo ($data['accommodation_type'] == 'inside') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="acc_inside"><i class="bi bi-building"></i> ในศูนย์</label>

                                <input type="radio" class="btn-check" name="accommodation_type" id="acc_outside" value="outside" <?php echo ($data['accommodation_type'] == 'outside') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-secondary" for="acc_outside"><i class="bi bi-house"></i> นอกศูนย์</label>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: ข้อมูลส่วนตัว (เหมือนเดิม) -->
                    <div class="row g-3 mb-4">
                        <div class="col-12"><h6 class="text-uppercase text-secondary small fw-bold border-bottom pb-2">ข้อมูลส่วนตัว</h6></div>
                        
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">เลขบัตรประชาชน (13 หลัก) <span class="text-danger">*</span></label>
                            <input type="text" name="citizen_id" id="citizen_id" class="form-control font-monospace" maxlength="13" 
                                   value="<?php echo $data['citizen_id']; ?>" required placeholder="x-xxxx-xxxxx-xx-x"
                                   <?php echo ($prefill_citizen_id && $mode == 'add') ? 'readonly' : ''; ?> autofocus>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">คำนำหน้า</label>
                            <input type="text" name="prefix" id="prefix" class="form-control" list="prefix_list" 
                                   value="<?php echo $data['prefix']; ?>" placeholder="ระบุ" required>
                            <datalist id="prefix_list">
                                <option value="นาย"><option value="นาง"><option value="น.ส."><option value="ด.ช."><option value="ด.ญ.">
                            </datalist>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required value="<?php echo $data['first_name']; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required value="<?php echo $data['last_name']; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-primary">วันเดือนปีเกิด (วว/ดด/ปปปป พ.ศ.)</label>
                            <input type="text" id="birth_date_thai" class="form-control" 
                                   placeholder="เช่น 13/04/2520 (ไม่บังคับ)" maxlength="10" 
                                   onkeyup="formatThaiDate(this)" onblur="syncDateToDB()">
                            <input type="hidden" name="birth_date" id="birth_date" value="<?php echo $data['birth_date']; ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">อายุ (ปี) <span class="text-danger">*</span></label>
                            <input type="number" name="age" id="age" class="form-control fw-bold text-primary text-center" 
                                   required value="<?php echo $data['age']; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $data['phone']; ?>">
                        </div>
                    </div>

                    <!-- ส่วนที่ 3: ข้อมูลสุขภาพ -->
                    <div class="row g-3 mb-4">
                        <div class="col-12"><h6 class="text-uppercase text-secondary small fw-bold border-bottom pb-2">ข้อมูลสุขภาพ</h6></div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-danger">กลุ่มเปราะบาง / ปัญหาสุขภาพหลัก</label>
                            <select name="health_condition" id="health_condition" class="form-select border-danger bg-danger bg-opacity-10">
                                <option value="ไม่มี" <?php echo ($data['health_condition'] == 'ไม่มี') ? 'selected' : ''; ?>>ร่างกายแข็งแรง / ปกติ</option>
                                <optgroup label="กลุ่มตามช่วงวัย">
                                    <option value="เด็กเล็ก" <?php echo ($data['health_condition'] == 'เด็กเล็ก') ? 'selected' : ''; ?>>เด็กแรกเกิด - 5 ปี</option>
                                    <option value="ผู้สูงอายุ" <?php echo ($data['health_condition'] == 'ผู้สูงอายุ') ? 'selected' : ''; ?>>ผู้สูงอายุ (60 ปีขึ้นไป)</option>
                                </optgroup>
                                <optgroup label="กลุ่มต้องการดูแลพิเศษ">
                                    <option value="หญิงตั้งครรภ์" <?php echo ($data['health_condition'] == 'หญิงตั้งครรภ์') ? 'selected' : ''; ?>>หญิงตั้งครรภ์</option>
                                    <option value="ผู้พิการ" <?php echo ($data['health_condition'] == 'ผู้พิการ') ? 'selected' : ''; ?>>ผู้พิการ</option>
                                    <option value="ผู้ป่วยติดเตียง" <?php echo ($data['health_condition'] == 'ผู้ป่วยติดเตียง') ? 'selected' : ''; ?>>ผู้ป่วยติดเตียง</option>
                                </optgroup>
                                <optgroup label="โรคประจำตัวเรื้อรัง">
                                    <option value="ผู้ป่วยไตวาย" <?php echo ($data['health_condition'] == 'ผู้ป่วยไตวาย') ? 'selected' : ''; ?>>ผู้ป่วยไตวาย (ต้องฟอกไต)</option>
                                    <option value="โรคเบาหวาน" <?php echo ($data['health_condition'] == 'โรคเบาหวาน') ? 'selected' : ''; ?>>โรคเบาหวาน</option>
                                    <option value="โรคความดันโลหิตสูง" <?php echo ($data['health_condition'] == 'โรคความดันโลหิตสูง') ? 'selected' : ''; ?>>โรคความดันโลหิตสูง</option>
                                    <option value="โรคหัวใจ" <?php echo ($data['health_condition'] == 'โรคหัวใจ') ? 'selected' : ''; ?>>โรคหัวใจ</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- ส่วนที่ 4: ที่อยู่ -->
                    <div class="row g-3 mb-4">
                        <div class="col-12"><h6 class="text-uppercase text-secondary small fw-bold border-bottom pb-2">ที่อยู่ตามภูมิลำเนา (จากบัตร)</h6></div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">บ้านเลขที่</label>
                            <input type="text" name="house_no" id="house_no" class="form-control" value="<?php echo $data['house_no']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">หมู่ที่ / หมู่บ้าน</label>
                            <input type="text" name="village_no" id="village_no" class="form-control" value="<?php echo $data['village_no']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">ตำบล</label>
                            <input type="text" name="subdistrict" id="subdistrict" class="form-control" value="<?php echo $data['subdistrict']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">อำเภอ</label>
                            <input type="text" name="district" id="district" class="form-control" value="<?php echo $data['district']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">จังหวัด</label>
                            <input type="text" name="province" id="province" class="form-control" value="<?php echo $data['province']; ?>">
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก (เปลี่ยน type เป็น button เพื่อคุมด้วย JS) -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-2 border-top">
                        <a href="#" onclick="history.back()" class="btn btn-light border px-4">ยกเลิก</a>
                        <button type="button" onclick="handleSave()" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ฟังก์ชันจัดการการบันทึก
    function handleSave() {
        const form = document.getElementById('evacueeForm');
        const accType = document.querySelector('input[name="accommodation_type"]:checked').value;
        
        // ตรวจสอบความถูกต้องเบื้องต้น (Required Fields)
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // ถ้าเลือก "พักนอกศูนย์" ให้เด้ง Popup ถามข้อมูล
        if (accType === 'outside') {
            Swal.fire({
                title: 'ระบุข้อมูลที่พักนอกศูนย์',
                text: 'เช่น บ้านญาติ, ชื่อเจ้าของบ้าน, เบอร์โทรติดต่อ',
                input: 'textarea',
                inputPlaceholder: 'กรอกรายละเอียด...',
                inputAttributes: {
                    'aria-label': 'Type your message here'
                },
                inputValue: document.getElementById('outside_detail').value, // ดึงค่าเก่ามาแสดง (กรณีแก้ไข)
                showCancelButton: true,
                confirmButtonText: 'ยืนยันและบันทึก',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => {
                    if (!value) {
                        return 'กรุณาระบุรายละเอียดที่พัก';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // ใส่ค่าที่กรอกลงใน Hidden Input แล้ว Submit
                    document.getElementById('outside_detail').value = result.value;
                    form.submit();
                }
            });
        } else {
            // ถ้าพักในศูนย์ บันทึกเลย
            document.getElementById('outside_detail').value = ''; // เคลียร์ค่า
            form.submit();
        }
    }

    function formatThaiDate(input) {
        let v = input.value.replace(/\D/g, '');
        if (v.match(/^\d{2}$/) !== null) { input.value = v + '/'; } 
        else if (v.match(/^\d{2}\d{2}$/) !== null) { input.value = v.substring(0, 2) + '/' + v.substring(2, 4) + '/'; }
    }

    function syncDateToDB() {
        const thaiDate = document.getElementById('birth_date_thai').value;
        const dbInput = document.getElementById('birth_date');
        const parts = thaiDate.split('/');
        
        if (parts.length === 3 && parts[2].length === 4) {
            const d = parseInt(parts[0]);
            const m = parseInt(parts[1]);
            const yBE = parseInt(parts[2]);
            
            if (d > 0 && d <= 31 && m > 0 && m <= 12 && yBE > 2400) {
                const yAD = yBE - 543;
                dbInput.value = `${yAD}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                calculateAge();
            } else {
                dbInput.value = ''; 
            }
        } else {
             dbInput.value = ''; 
        }
    }

    function calculateAge() {
        const birthDateVal = document.getElementById('birth_date').value;
        const ageInput = document.getElementById('age');
        
        if(birthDateVal) {
            const today = new Date();
            const birthDate = new Date(birthDateVal);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            ageInput.value = age;
            
            // Auto Select Health
            const healthSelect = document.getElementById('health_condition');
            if (healthSelect.value === 'ไม่มี') {
                if (age <= 5) healthSelect.value = 'เด็กเล็ก';
                else if (age >= 60) healthSelect.value = 'ผู้สูงอายุ';
            }
        }
    }

    function initThaiDate() {
        const dbVal = document.getElementById('birth_date').value;
        if (dbVal && dbVal !== '0000-00-00') {
            const d = new Date(dbVal);
            if (!isNaN(d.getTime())) {
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const yearBE = d.getFullYear() + 543;
                document.getElementById('birth_date_thai').value = `${day}/${month}/${yearBE}`;
            }
        }
    }

    window.addEventListener('DOMContentLoaded', initThaiDate);

    // (Code Smart Card ตัดออกแล้ว ตามคำขอ)
</script>

<?php require_once 'includes/footer.php'; ?>