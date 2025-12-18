<?php
require_once 'config/db.php';
require_once 'includes/header.php';
?>

<!-- เพิ่ม CSS ของ Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 38px; border: 1px solid #ced4da; border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px; padding-left: 12px; color: #212529;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px; right: 10px;
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="index.php" class="text-decoration-none text-secondary small">&larr; กลับหน้าหลัก</a>
        </div>

        <div class="card card-modern animate-fade-in">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-file-earmark-spreadsheet-fill fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">นำเข้าข้อมูล (CSV Import)</h5>
                        <p class="text-muted small mb-0">รองรับไฟล์ที่มีรายชื่อผู้พักพิงจากหลายศูนย์รวมกัน</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                
                <!-- ส่วนดาวน์โหลด Template -->
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-download"></i> เตรียมไฟล์ข้อมูล</h6>
                    <p class="text-muted small mb-3">หากยังไม่มีไฟล์ต้นฉบับ สามารถดาวน์โหลดแบบฟอร์มเปล่าได้ที่นี่</p>
                    <a href="download_template.php" class="btn btn-outline-success btn-sm w-100 fw-bold">
                        <i class="bi bi-file-earmark-excel"></i> ดาวน์โหลดไฟล์ต้นแบบ (Excel/CSV)
                    </a>
                </div>

                <form action="import_process.php" method="POST" enctype="multipart/form-data">
                    
                    <?php if($_SESSION['role'] == 'STAFF'): ?>
                        <!-- กรณี STAFF: บังคับเข้าศูนย์ตัวเองเท่านั้น -->
                        <input type="hidden" name="shelter_id" value="<?php echo $_SESSION['shelter_id']; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">นำเข้าสู่ศูนย์พักพิงชั่วคราว</label>
                            <input type="text" class="form-control bg-light" value="ศูนย์ที่คุณรับผิดชอบ (<?php echo $_SESSION['fullname']; ?>)" disabled>
                            <div class="form-text text-success small"><i class="bi bi-lock-fill"></i> ล็อคตามสิทธิ์เจ้าหน้าที่</div>
                        </div>

                    <?php else: ?>
                        <!-- กรณี ADMIN: เลือกได้ หรือ ปล่อยว่างเพื่ออ่านจากไฟล์ -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">เลือกศูนย์พักพิงชั่วคราวปลายทาง (Optional)</label>
                            
                            <!-- เอา attribute 'required' ออก เพื่อให้เลือกหรือไม่เลือกก็ได้ -->
                            <select name="shelter_id" id="shelterSelect" class="form-select">
                                <option value="0">-- อ่านอัตโนมัติจากไฟล์ CSV (แนะนำ) --</option>
                                <?php 
                                $shelters = $pdo->query("SELECT * FROM shelters WHERE status = 'OPEN'")->fetchAll();
                                foreach($shelters as $s) {
                                    echo "<option value='{$s['id']}'>{$s['name']} (อ.{$s['district']})</option>";
                                }
                                ?>
                            </select>

                            <div class="alert alert-info border-info border-opacity-25 bg-info bg-opacity-10 mt-2 mb-0 py-2 px-3 small">
                                <i class="bi bi-info-circle-fill me-1"></i> <strong>โหมดอัตโนมัติ:</strong> 
                                ระบบจะอ่านชื่อศูนย์จากคอลัมน์ <em>"ที่อยู่ปัจจุบัน"</em> ในไฟล์ CSV และจัดสรรลงศูนย์ให้อัตโนมัติ (หากไม่พบชื่อศูนย์ในระบบ จะทำการสร้างศูนย์ใหม่ให้)
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4 mt-4">
                        <label class="form-label small fw-bold">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <div class="form-text small">รองรับไฟล์ .csv (UTF-8) ขนาดไม่เกิน 5MB</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit" class="btn btn-primary fw-bold py-2 shadow-sm">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> อัปโหลดและประมวลผล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Script สำหรับ Select2 -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#shelterSelect').select2({
            placeholder: "-- อ่านอัตโนมัติจากไฟล์ CSV --",
            allowClear: true,
            language: { noResults: () => "ไม่พบข้อมูลศูนย์" }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>