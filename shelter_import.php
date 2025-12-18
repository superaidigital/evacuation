<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] != 'ADMIN') { header("Location: index.php"); exit(); }
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="shelter_list.php" class="text-decoration-none text-secondary small">&larr; กลับหน้ารายการ</a>
        </div>

        <div class="card card-modern">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-geo-alt-fill fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold">นำเข้าข้อมูลศูนย์พักพิงชั่วคราว (CSV)</h5>
                        <p class="text-muted small mb-0">เพิ่มสถานที่พักพิงจำนวนมากจากไฟล์ Excel</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                
                <!-- ดาวน์โหลด Template -->
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-download"></i> ไฟล์ต้นแบบ</h6>
                    <p class="text-muted small mb-3">ดาวน์โหลดแบบฟอร์มเพื่อกรอกรายชื่อศูนย์ (ชื่อ, อำเภอ, ความจุ)</p>
                    <a href="shelter_template.php" class="btn btn-outline-success btn-sm w-100 fw-bold">
                        <i class="bi bi-file-earmark-excel"></i> ดาวน์โหลดแบบฟอร์มศูนย์พักพิงชั่วคราว.csv
                    </a>
                </div>

                <div class="alert alert-light border d-flex gap-3 mb-4">
                    <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                    <div class="small text-secondary">
                        <strong>ระบบอัตโนมัติ:</strong><br>
                        - สถานะเริ่มต้นจะเป็น <strong>OPEN (เปิดใช้งาน)</strong><br>
                        - ชื่อเหตุการณ์จะดึงจาก <strong>สถานการณ์ปัจจุบัน</strong> ให้อัตโนมัติ<br>
                        - จังหวัดถ้าไม่ระบุ จะตั้งเป็น "ศรีสะเกษ"
                    </div>
                </div>

                <form action="shelter_import_process.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label small fw-bold">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <button type="submit" name="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="bi bi-cloud-upload me-1"></i> อัปโหลดรายชื่อศูนย์
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>