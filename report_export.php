<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ดึงรายชื่อศูนย์สำหรับ Dropdown (เฉพาะ Admin ที่เลือกได้)
$shelters = $pdo->query("SELECT * FROM shelters WHERE status = 'OPEN'")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">
            <i class="bi bi-file-earmark-arrow-down-fill text-success"></i> รายงานและการส่งออกข้อมูล
        </h3>
        <p class="text-muted small">ดาวน์โหลดข้อมูลในรูปแบบ Excel (.csv) และ PDF</p>
    </div>
</div>

<div class="row g-4">
    
    <!-- 1. รายงานประจำวัน (Daily) -->
    <div class="col-md-6 col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold m-0 text-primary"><i class="bi bi-calendar-check me-2"></i> รายงานประจำวัน</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="report_print.php" target="_blank" class="btn btn-outline-primary text-start">
                        <i class="bi bi-file-earmark-pdf me-2"></i> สรุปสถานการณ์ (PDF)
                        <small class="d-block text-muted" style="font-size:0.7rem;">แบบฟอร์มทางการพร้อมลายเซ็น</small>
                    </a>
                    <a href="export_process.php?type=daily_in" class="btn btn-outline-success text-start">
                        <i class="bi bi-file-earmark-excel me-2"></i> ผู้เข้าใหม่วันนี้ (Excel)
                    </a>
                    <a href="export_process.php?type=daily_out" class="btn btn-outline-danger text-start">
                        <i class="bi bi-file-earmark-excel me-2"></i> ผู้จำหน่ายออกวันนี้ (Excel)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. ฐานข้อมูลรวม (All Data) -->
    <div class="col-md-6 col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-database me-2"></i> ฐานข้อมูลรวม</h6>
            </div>
            <div class="card-body">
                <?php if($_SESSION['role'] == 'ADMIN'): ?>
                    <form action="export_process.php" method="GET">
                        <input type="hidden" name="type" value="shelter_specific">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">เลือกศูนย์อพยพ</label>
                            <select name="shelter_id" class="form-select form-select-sm" required>
                                <option value="all">ทั้งหมด (ทุกศูนย์)</option>
                                <?php foreach($shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 btn-sm">
                            <i class="bi bi-download me-1"></i> ดาวน์โหลดข้อมูลศูนย์ที่เลือก
                        </button>
                    </form>
                    <hr>
                <?php endif; ?>
                
                <a href="export_process.php?type=all_active" class="btn btn-success w-100 btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> ดาวน์โหลดรายชื่อผู้พักอาศัยปัจจุบัน
                </a>
            </div>
        </div>
    </div>

    <!-- 3. กลุ่มเปราะบาง (Vulnerable) -->
    <div class="col-md-6 col-lg-4">
        <div class="card card-modern h-100 border-danger border-opacity-25">
            <div class="card-header bg-danger bg-opacity-10 border-bottom p-3">
                <h6 class="fw-bold m-0 text-danger"><i class="bi bi-heart-pulse me-2"></i> กลุ่มเปราะบาง (สาธารณสุข)</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="export_process.php?type=vul_medical" class="btn btn-outline-danger text-start">
                        <i class="bi bi-hospital me-2"></i> ผู้ป่วย/พิการ/ติดเตียง
                        <small class="d-block text-muted" style="font-size:0.7rem;">สำหรับทีมแพทย์/กู้ชีพ</small>
                    </a>
                    <a href="export_process.php?type=vul_kids" class="btn btn-outline-warning text-start text-dark">
                        <i class="bi bi-emoji-smile me-2"></i> เด็กและสตรีมีครรภ์
                        <small class="d-block text-muted" style="font-size:0.7rem;">สำหรับนมผง/ของใช้แม่ลูก</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>