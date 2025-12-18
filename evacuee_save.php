<?php
require_once 'config/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $mode = $_POST['mode'];
    $shelter_id = $_POST['shelter_id'];
    $id = $_POST['id'] ?? '';
    
    // ... (รับค่าเดิม) ...
    $citizen_id = $_POST['citizen_id'];
    $prefix = $_POST['prefix'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $health_condition = $_POST['health_condition'];
    $phone = $_POST['phone'];
    
    // รับค่าใหม่
    $accommodation_type = $_POST['accommodation_type'] ?? 'inside';
    $outside_detail = $_POST['outside_detail'] ?? ''; // รายละเอียดที่พักนอกศูนย์
    
    $house_no = $_POST['house_no'];
    $village_no = $_POST['village_no'];
    $subdistrict = $_POST['subdistrict'];
    $district = $_POST['district'];
    $province = $_POST['province'];

    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $age = !empty($_POST['age']) ? $_POST['age'] : 0;
    
    if ($birth_date) {
        try {
            $dob = new DateTime($birth_date);
            $now = new DateTime();
            $diff = $now->diff($dob);
            $age = $diff->y; 
        } catch (Exception $e) { }
    }

    $confirm_transfer = $_POST['confirm_transfer'] ?? '';

    try {
        // --- LOGIC ย้ายศูนย์ (เหมือนเดิม) ---
        if ($mode == 'add') {
            $sql_check = "SELECT e.id, e.shelter_id, s.name as shelter_name FROM evacuees e JOIN shelters s ON e.shelter_id = s.id WHERE e.citizen_id = ? AND e.check_out_date IS NULL";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$citizen_id]);
            $active_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($active_record) {
                if ($active_record['shelter_id'] == $shelter_id) {
                    $_SESSION['swal_error'] = ['title' => 'มีข้อมูลอยู่แล้ว', 'text' => 'บุคคลนี้ลงทะเบียนแล้ว'];
                    header("Location: evacuee_list.php?shelter_id=$shelter_id"); exit();
                } 
                
                if ($confirm_transfer !== 'yes') {
                    require_once 'includes/header.php';
                    ?>
                    <!-- (HTML หน้า Confirm เหมือนเดิม) -->
                    <div class="container mt-5">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card card-modern border-warning border-2 shadow-lg animate-fade-in">
                                    <div class="card-body p-5 text-center">
                                        <div class="mb-4 text-warning"><i class="bi bi-exclamation-triangle-fill" style="font-size: 4rem;"></i></div>
                                        <h3 class="fw-bold text-dark">พบข้อมูลในระบบปัจจุบัน</h3>
                                        <p class="fs-5 mt-3">คุณ <strong><?php echo "$prefix$first_name $last_name"; ?></strong><br>ปัจจุบันพักอยู่ที่ <span class="text-danger fw-bold">"<?php echo $active_record['shelter_name']; ?>"</span></p>
                                        <p class="text-muted small">ต้องการจำหน่ายออกจากศูนย์เดิม และย้ายมาศูนย์นี้หรือไม่?</p>
                                        <form action="evacuee_save.php" method="POST" class="mt-4">
                                            <?php foreach ($_POST as $key => $value): ?>
                                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="confirm_transfer" value="yes">
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark shadow-sm">ยืนยันการย้ายศูนย์</button>
                                                <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-light text-secondary">ยกเลิก</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    require_once 'includes/footer.php';
                    exit();
                } else {
                    $pdo->prepare("UPDATE evacuees SET check_out_date = CURRENT_DATE, check_out_reason = 'ย้ายศูนย์' WHERE id = ?")->execute([$active_record['id']]);
                    $pdo->query("UPDATE shelters SET last_updated = NOW() WHERE id = " . $active_record['shelter_id']);
                }
            }
        }

        // --- SQL INSERT / UPDATE (เพิ่ม outside_detail) ---
        if ($mode == 'add') {
            $sql = "INSERT INTO evacuees (
                        citizen_id, prefix, first_name, last_name, age, birth_date, health_condition, phone, 
                        house_no, village_no, subdistrict, district, province, 
                        shelter_id, check_in_date, accommodation_type, outside_detail
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $citizen_id, $prefix, $first_name, $last_name, $age, $birth_date, $health_condition, $phone,
                $house_no, $village_no, $subdistrict, $district, $province,
                $shelter_id, $accommodation_type, $outside_detail
            ]);
            $msg_title = "ลงทะเบียนสำเร็จ";

        } else if ($mode == 'edit') {
            $sql = "UPDATE evacuees SET 
                    citizen_id=?, prefix=?, first_name=?, last_name=?, age=?, birth_date=?,
                    health_condition=?, phone=?, accommodation_type=?, outside_detail=?,
                    house_no=?, village_no=?, subdistrict=?, district=?, province=?
                    WHERE id=?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $citizen_id, $prefix, $first_name, $last_name, $age, $birth_date,
                $health_condition, $phone, $accommodation_type, $outside_detail,
                $house_no, $village_no, $subdistrict, $district, $province,
                $id
            ]);
            $msg_title = "บันทึกสำเร็จ";
        }

        $pdo->prepare("UPDATE shelters SET last_updated = NOW() WHERE id = ?")->execute([$shelter_id]);

        $_SESSION['swal_success'] = ['title' => $msg_title, 'text' => 'ดำเนินการเรียบร้อยแล้ว'];
        header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['swal_error'] = ['title' => 'เกิดข้อผิดพลาด', 'text' => $e->getMessage()];
        header("Location: evacuee_list.php?shelter_id=" . $shelter_id); 
        exit();
    }
}
?>