<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$search_id = $_GET['citizen_id'] ?? '';
$active_stay = null; 
$history = []; 

// ฟังก์ชันคำนวณวัน
function calculateDays($in, $out = null) {
    $d1 = new DateTime($in);
    $d2 = $out ? new DateTime($out) : new DateTime();
    $diff = $d1->diff($d2);
    $days = $diff->days;
    return ($days == 0) ? "วันนี้" : $days . " วัน";
}

if ($search_id) {
    $clean_id = preg_replace('/[^0-9]/', '', $search_id);
    
    $sql_active = "SELECT e.*, s.name as shelter_name, s.district 
                   FROM evacuees e 
                   JOIN shelters s ON e.shelter_id = s.id 
                   WHERE e.citizen_id = ? AND e.check_out_date IS NULL";
    $stmt = $pdo->prepare($sql_active);
    $stmt->execute([$clean_id]);
    $active_stay = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql_history = "SELECT e.*, s.name as shelter_name, s.district 
                    FROM evacuees e 
                    JOIN shelters s ON e.shelter_id = s.id 
                    WHERE e.citizen_id = ? 
                    ORDER BY e.check_in_date DESC, e.id DESC";
    $stmt_hist = $pdo->prepare($sql_history);
    $stmt_hist->execute([$clean_id]);
    $history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        
        <!-- Search Box -->
        <div class="card card-modern mb-4">
            <div class="card-body p-4 text-center">
                <h4 class="fw-bold mb-3"><i class="bi bi-search text-primary"></i> ตรวจสอบข้อมูล/ประวัติผู้พักพิง</h4>
                
                <!-- Form ค้นหา -->
                <form action="" method="GET" id="searchForm">
                    <div class="input-group input-group-lg mb-2">
                        <!-- เพิ่ม autofocus และ oninput -->
                        <input type="text" name="citizen_id" id="citizen_id_input"
                               class="form-control text-center font-monospace rounded-start-pill" 
                               placeholder="เลขบัตรประชาชน 13 หลัก" 
                               value="<?php echo htmlspecialchars($search_id); ?>" 
                               required autofocus maxlength="13"
                               oninput="checkAutoSubmit(this)">
                        <button class="btn btn-primary px-4 rounded-end-pill" type="submit">ค้นหา</button>
                    </div>
                    <div class="form-text small text-muted">
                        <i class="bi bi-lightning-charge-fill text-warning"></i> ระบบจะค้นหาอัตโนมัติเมื่อครบ 13 หลัก
                    </div>
                </form>
            </div>
        </div>

        <?php if ($search_id): ?>
            
            <?php if ($active_stay): ?>
                <!-- Active Stay Card -->
                <div class="card card-modern border-success border-2 shadow-sm mb-4 animate-fade-in">
                    <div class="card-header bg-success bg-opacity-10 border-0 p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2 text-success">
                                <div class="spinner-grow spinner-grow-sm" role="status"></div>
                                <h5 class="mb-0 fw-bold">สถานะ: กำลังพักอาศัยอยู่</h5>
                            </div>
                            <span class="badge bg-success">
                                <i class="bi bi-clock-history"></i> <?php echo calculateDays($active_stay['check_in_date']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold"><?php echo $active_stay['prefix'] . $active_stay['first_name'] . ' ' . $active_stay['last_name']; ?></h3>
                            <span class="badge bg-light text-dark border font-monospace fs-6"><?php echo $active_stay['citizen_id']; ?></span>
                        </div>
                        
                        <div class="alert alert-light border d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">สถานที่พักปัจจุบัน</small>
                                <div class="fw-bold text-primary fs-5"><?php echo $active_stay['shelter_name']; ?></div>
                                <small class="text-secondary">อ.<?php echo $active_stay['district']; ?></small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">วันที่เข้า</small>
                                <div class="fw-bold"><?php echo date('d/m/Y', strtotime($active_stay['check_in_date'])); ?></div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="evacuee_form.php?id=<?php echo $active_stay['id']; ?>&shelter_id=<?php echo $active_stay['shelter_id']; ?>" 
                               class="btn btn-outline-primary px-4">
                                <i class="bi bi-pencil-square"></i> แก้ไขข้อมูล
                            </a>
                            <button onclick="confirmCheckout(<?php echo $active_stay['id']; ?>, <?php echo $active_stay['shelter_id']; ?>)" 
                                    class="btn btn-danger px-4">
                                <i class="bi bi-box-arrow-right"></i> จำหน่ายออก
                            </button>
                        </div>
                    </div>
                </div>

            <?php elseif (count($history) > 0): ?>
                <!-- History Found, No Active -->
                <div class="card card-modern border-warning border-2 shadow-sm mb-4 animate-fade-in">
                    <div class="card-body p-4 text-center">
                        <div class="mb-3 text-warning">
                            <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="fw-bold">พบประวัติเก่าในระบบ</h4>
                        <p class="text-muted mb-4">
                            คุณ <strong><?php echo $history[0]['first_name'] . ' ' . $history[0]['last_name']; ?></strong><br>
                            ปัจจุบันไม่ได้ลงทะเบียนอยู่ในศูนย์ใด
                        </p>
                        
                        <a href="evacuee_form.php?citizen_id=<?php echo $search_id; ?>&action=readmit" 
                           class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold">
                            <i class="bi bi-box-arrow-in-down"></i> ลงทะเบียนเข้าศูนย์ใหม่
                        </a>
                        <p class="small text-muted mt-2">* ระบบจะดึงข้อมูลเดิมมาให้ ท่านเพียงเลือกศูนย์ใหม่</p>
                    </div>
                </div>

            <?php else: ?>
                <!-- Not Found -->
                <div class="card card-modern border-secondary border-opacity-25 shadow-sm mb-4 animate-fade-in">
                    <div class="card-body p-5 text-center">
                        <h4 class="fw-bold text-secondary">ไม่พบข้อมูลในระบบ</h4>
                        <p class="text-muted mb-4">เลขบัตรนี้ยังไม่เคยลงทะเบียนมาก่อน</p>
                        <a href="evacuee_form.php?citizen_id=<?php echo $search_id; ?>" 
                           class="btn btn-success btn-lg rounded-pill px-5 shadow-sm fw-bold">
                            <i class="bi bi-person-plus-fill"></i> ลงทะเบียนใหม่
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Timeline Table -->
            <?php if (count($history) > 0): ?>
            <div class="card card-modern border-0 animate-fade-in">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold m-0"><i class="bi bi-list-task"></i> ประวัติการเข้าพักทั้งหมด</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ศูนย์พักพิงชั่วคราว</th>
                                <th>ช่วงเวลา</th>
                                <th>ระยะเวลา</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-primary"><?php echo $row['shelter_name']; ?></div>
                                    <small class="text-muted">อ.<?php echo $row['district']; ?></small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column small">
                                        <span>เข้า: <?php echo date('d/m/Y', strtotime($row['check_in_date'])); ?></span>
                                        <?php if($row['check_out_date']): ?>
                                            <span class="text-muted">ออก: <?php echo date('d/m/Y', strtotime($row['check_out_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo calculateDays($row['check_in_date'], $row['check_out_date']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['check_out_date']): ?>
                                        <span class="badge bg-secondary rounded-pill">จำหน่ายออก</span>
                                    <?php else: ?>
                                        <span class="badge bg-success rounded-pill">กำลังพักอาศัย</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ฟังก์ชันตรวจสอบความยาวและส่งฟอร์มอัตโนมัติ
    function checkAutoSubmit(input) {
        // ลบอักขระที่ไม่ใช่ตัวเลขออกทันที (ป้องกันการ Paste ข้อมูลผิดรูปแบบ)
        input.value = input.value.replace(/[^0-9]/g, '');
        
        if (input.value.length === 13) {
            document.getElementById('searchForm').submit();
        }
    }

    // ฟังก์ชัน Focus ช่องค้นหาเสมอ เมื่อไม่ได้คลิกที่อื่น (Optional: ถ้าต้องการให้พร้อมยิงตลอดเวลา)
    document.addEventListener('click', function(e) {
        const input = document.getElementById('citizen_id_input');
        // ถ้าคลิกที่ว่างๆ ที่ไม่ใช่ปุ่มหรือลิงก์ ให้โฟกัสกลับมาที่ช่องค้นหา
        if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
            input.focus();
        }
    });

    function confirmCheckout(id, shelter_id) {
        Swal.fire({
            title: 'ยืนยันการจำหน่ายออก?',
            text: "จบการพักอาศัยในรอบนี้ (ข้อมูลจะถูกเก็บเป็นประวัติ)",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `evacuee_status.php?action=checkout&id=${id}&shelter_id=${shelter_id}`;
            }
        })
    }
</script>

<style>
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once 'includes/footer.php'; ?>