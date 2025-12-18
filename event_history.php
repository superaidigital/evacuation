<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ดึงข้อมูลเหตุการณ์ทั้งหมด
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM evacuees WHERE event_id = e.id) as total_registered,
        (SELECT COUNT(*) FROM evacuees WHERE event_id = e.id AND check_out_date IS NULL) as current_stay
        FROM events e 
        ORDER BY e.start_date DESC";
$events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark"><i class="bi bi-clock-history text-secondary"></i> ประวัติเหตุการณ์ภัยพิบัติ</h3>
        <p class="text-muted small">เลือกเหตุการณ์เพื่อดูรายงานย้อนหลัง</p>
    </div>
    <?php if($_SESSION['role'] == 'ADMIN'): ?>
    <a href="event_manager.php" class="btn btn-primary btn-sm"><i class="bi bi-gear-fill"></i> จัดการ/สร้างใหม่</a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php foreach($events as $row): 
        $is_active = ($row['status'] == 'ACTIVE');
        $card_border = $is_active ? 'border-primary' : 'border-secondary';
        $badge_color = $is_active ? 'bg-success' : 'bg-secondary';
        $status_text = $is_active ? 'กำลังดำเนินการ' : 'จบภารกิจแล้ว';
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-modern h-100 <?php echo $is_active ? 'shadow-sm border-primary' : 'border-0 shadow-sm opacity-75'; ?>">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="badge <?php echo $badge_color; ?> rounded-pill mb-2"><?php echo $status_text; ?></span>
                    <?php if($is_active): ?><span class="spinner-grow spinner-grow-sm text-primary" role="status"></span><?php endif; ?>
                </div>
                <h5 class="fw-bold text-dark mb-1 text-truncate"><?php echo $row['name']; ?></h5>
                <small class="text-muted">
                    <i class="bi bi-calendar-event"></i> เริ่ม: <?php echo date('d/m/Y', strtotime($row['start_date'])); ?>
                    <?php echo $row['end_date'] ? ' - สิ้นสุด: '.date('d/m/Y', strtotime($row['end_date'])) : ''; ?>
                </small>
            </div>
            <div class="card-body">
                <div class="row g-2 mt-2 text-center bg-light rounded p-2 mx-0">
                    <div class="col-6 border-end">
                        <h4 class="fw-bold text-primary mb-0"><?php echo number_format($row['total_registered']); ?></h4>
                        <small class="text-secondary" style="font-size: 0.7rem;">ยอดสะสม (คน)</small>
                    </div>
                    <div class="col-6">
                        <h4 class="fw-bold text-success mb-0"><?php echo number_format($row['current_stay']); ?></h4>
                        <small class="text-secondary" style="font-size: 0.7rem;">คงเหลือ (คน)</small>
                    </div>
                </div>
                
                <p class="card-text small text-muted mt-3 mb-0 text-truncate">
                    <?php echo $row['description'] ? $row['description'] : '- ไม่มีรายละเอียดเพิ่มเติม -'; ?>
                </p>
            </div>
            <div class="card-footer bg-white border-top-0 pb-4 pt-0">
                <a href="report.php?event_id=<?php echo $row['id']; ?>" class="btn btn-outline-primary w-100 fw-bold">
                    <i class="bi bi-file-earmark-bar-graph"></i> ดูสรุปผลการดำเนินงาน
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>