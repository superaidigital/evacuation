<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// 1. ตรวจสอบค่าและการเข้าถึง
if (!isset($_GET['shelter_id'])) { header("Location: index.php"); exit(); }
$shelter_id = $_GET['shelter_id'];

if ($_SESSION['role'] == 'STAFF' && $_SESSION['shelter_id'] != $shelter_id) {
    echo "<div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าถึงข้อมูลของศูนย์นี้</div>"; 
    require_once 'includes/footer.php'; exit();
}

// 2. รับค่าพารามิเตอร์
$search = $_GET['search'] ?? '';
$current_tab = $_GET['tab'] ?? 'inside';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

// 3. ดึงข้อมูลศูนย์
$stmt_shelter = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
$stmt_shelter->execute([$shelter_id]);
$shelter = $stmt_shelter->fetch();

// 4. คำนวณจำนวนคน (Badge Counts)
$sql_count_base = "SELECT 
    SUM(CASE WHEN check_out_date IS NULL AND accommodation_type = 'inside' THEN 1 ELSE 0 END) as count_inside,
    SUM(CASE WHEN check_out_date IS NULL AND accommodation_type = 'outside' THEN 1 ELSE 0 END) as count_outside,
    SUM(CASE WHEN check_out_date IS NOT NULL THEN 1 ELSE 0 END) as count_history
FROM evacuees WHERE shelter_id = :sid";

$count_params = ['sid' => $shelter_id];

if ($search) {
    $search_clean = preg_replace('/[^0-9]/', '', $search);
    $sql_count_base .= " AND (first_name LIKE :search OR last_name LIKE :search OR citizen_id LIKE :search";
    if(!empty($search_clean)) {
        $sql_count_base .= " OR citizen_id LIKE :search_clean";
        $count_params['search_clean'] = "%$search_clean%";
    }
    $sql_count_base .= ")";
    $count_params['search'] = "%$search%";
}

$stmt_counts = $pdo->prepare($sql_count_base);
$stmt_counts->execute($count_params);
$tab_counts = $stmt_counts->fetch(PDO::FETCH_ASSOC);

// 5. Query หลัก
$sql_base = "FROM evacuees WHERE shelter_id = :sid";
$params = ['sid' => $shelter_id];

if ($search) {
    $search_clean = preg_replace('/[^0-9]/', '', $search); 
    $sql_base .= " AND (first_name LIKE :search OR last_name LIKE :search OR citizen_id LIKE :search";
    if (!empty($search_clean)) {
        $sql_base .= " OR citizen_id LIKE :search_clean";
        $params['search_clean'] = "%$search_clean%";
    }
    $sql_base .= ")";
    $params['search'] = "%$search%";
}

if ($current_tab == 'inside') {
    $sql_base .= " AND check_out_date IS NULL AND accommodation_type = 'inside'";
} elseif ($current_tab == 'outside') {
    $sql_base .= " AND check_out_date IS NULL AND accommodation_type = 'outside'";
} elseif ($current_tab == 'history') {
    $sql_base .= " AND check_out_date IS NOT NULL";
}

$stmt_count_page = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count_page->execute($params);
$total_rows = $stmt_count_page->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT * " . $sql_base . " ORDER BY check_out_date ASC, id DESC LIMIT $limit OFFSET $offset";
$stmt_evacuees = $pdo->prepare($sql_data);
$stmt_evacuees->execute($params);
$evacuees = $stmt_evacuees->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                <li class="breadcrumb-item active" aria-current="page">ทะเบียนผู้อพยพ</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-dark"><?php echo $shelter['name']; ?></h3>
        <span class="text-muted small">
            <i class="bi bi-geo-alt-fill text-danger"></i> อ.<?php echo $shelter['district']; ?> จ.<?php echo $shelter['province']; ?>
        </span>
    </div>
    
    <div class="d-flex gap-2">
        <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm fw-bold px-4 rounded-pill">
            <i class="bi bi-person-plus-fill"></i> <span>ลงทะเบียนใหม่</span>
        </a>
    </div>
</div>

<!-- Search Bar -->
<div class="card card-modern border-0 mb-4 bg-white shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
            <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
            <div class="col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, นามสกุล, หรือเลขบัตร..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-dark px-4 fw-bold">ค้นหา</button>
                    <?php if($search): ?>
                        <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>&tab=<?php echo $current_tab; ?>" class="btn btn-light border text-danger" title="ล้างค่า"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-3 gap-2" id="evacueeTabs">
    <li class="nav-item">
        <a class="nav-link <?php echo $current_tab == 'inside' ? 'active shadow-sm fw-bold' : 'bg-white border text-secondary'; ?> d-flex align-items-center gap-2 px-4 py-2" 
           href="?shelter_id=<?php echo $shelter_id; ?>&tab=inside&search=<?php echo $search; ?>">
            <i class="bi bi-building"></i> พักในศูนย์
            <span class="badge bg-white text-primary rounded-pill border"><?php echo number_format($tab_counts['count_inside']); ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $current_tab == 'outside' ? 'active shadow-sm fw-bold' : 'bg-white border text-secondary'; ?> d-flex align-items-center gap-2 px-4 py-2" 
           href="?shelter_id=<?php echo $shelter_id; ?>&tab=outside&search=<?php echo $search; ?>">
            <i class="bi bi-house-door"></i> พักนอกศูนย์
            <span class="badge bg-secondary text-white rounded-pill"><?php echo number_format($tab_counts['count_outside']); ?></span>
        </a>
    </li>
    <li class="nav-item ms-auto">
        <a class="nav-link <?php echo $current_tab == 'history' ? 'active bg-secondary shadow-sm fw-bold text-white' : 'bg-light border text-muted'; ?> d-flex align-items-center gap-2 px-4 py-2" 
           href="?shelter_id=<?php echo $shelter_id; ?>&tab=history&search=<?php echo $search; ?>">
            <i class="bi bi-clock-history"></i> จำหน่ายแล้ว
            <span class="badge bg-white text-dark rounded-pill border"><?php echo number_format($tab_counts['count_history']); ?></span>
        </a>
    </li>
</ul>

<!-- Table -->
<div class="card card-modern border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0 align-middle table-hover">
                <thead>
                    <tr>
                        <th class="ps-4">ชื่อ-นามสกุล</th>
                        <th>เลขบัตร ปชช.</th>
                        <th>อายุ</th>
                        <th>สุขภาพ</th>
                        <th>สถานะ/ที่พัก</th>
                        <th>เบอร์โทร</th>
                        <th class="text-end pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($evacuees) > 0): ?>
                        <?php foreach ($evacuees as $row): 
                            // เตรียมข้อมูลสำหรับ Popup
                            $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            
                            $is_staying = ($row['check_out_date'] == null);
                            
                            // รวมที่อยู่
                            $full_address = "บ้านเลขที่ " . $row['house_no'];
                            if($row['village_no']) $full_address .= " หมู่ " . $row['village_no'];
                            if($row['subdistrict']) $full_address .= " ต." . $row['subdistrict'];
                            if($row['district']) $full_address .= " อ." . $row['district'];
                            if($row['province']) $full_address .= " จ." . $row['province'];
                        ?>
                            <tr class="<?php echo !$is_staying ? 'bg-light text-muted' : ''; ?>">
                                <td class="ps-4 fw-medium">
                                    <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick='openDetailModal(<?php echo $row_json; ?>)'>
                                        <div class="avatar <?php echo $is_staying ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary'; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.9rem; font-weight: bold;">
                                            <?php echo mb_substr($row['first_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="text-dark fw-bold text-decoration-underline-hover"><?php echo $row['prefix'] . $row['first_name'] . ' ' . $row['last_name']; ?></div>
                                            <small class="text-muted" style="font-size:0.75rem;">
                                                คลิกเพื่อดูรายละเอียด
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-monospace text-secondary small">
                                    <?php echo preg_replace('/[^0-9]/', '', $row['citizen_id']); ?>
                                </td>
                                <td><?php echo $row['age']; ?></td>
                                <td>
                                    <?php if ($row['health_condition'] == 'ไม่มี'): ?>
                                        <span class="badge badge-soft bg-light text-secondary border">-</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                            <?php echo $row['health_condition']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($current_tab == 'outside'): ?>
                                        <div class="small text-truncate" style="max-width: 150px;">
                                            <i class="bi bi-house-door text-warning"></i> <?php echo $row['outside_detail'] ? $row['outside_detail'] : '-'; ?>
                                        </div>
                                    <?php elseif ($current_tab == 'history'): ?>
                                        <div class="small">
                                            <span class="fw-bold"><?php echo date('d/m/y', strtotime($row['check_out_date'])); ?></span>
                                            <div class="text-muted text-xs"><?php echo $row['check_out_reason']; ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-soft bg-success bg-opacity-10 text-success">พักในศูนย์</span>
                                    <?php endif; ?>
                                </td>

                                <td><?php echo $row['phone'] ? $row['phone'] : '-'; ?></td>

                                <td class="text-end pe-4">
                                    <!-- ปุ่มดูรายละเอียด -->
                                    <button class="btn btn-sm btn-info text-white me-1" onclick='openDetailModal(<?php echo $row_json; ?>)' title="ดูรายละเอียด">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php if ($is_staying): ?>
                                        <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&shelter_id=<?php echo $shelter_id; ?>" 
                                           class="btn btn-sm btn-light text-primary border me-1" title="แก้ไข/ย้าย">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button onclick="confirmCheckout(<?php echo $row['id']; ?>)" 
                                                class="btn btn-sm btn-light text-danger border" title="จำหน่ายออก">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">ปิด</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบข้อมูลในหมวดนี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-end bg-light">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?shelter_id=<?php echo $shelter_id; ?>&tab=<?php echo $current_tab; ?>&page=<?php echo $page-1; ?>&search=<?php echo $search; ?>">ก่อนหน้า</a>
                    </li>
                    <?php 
                    $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                    if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?shelter_id='.$shelter_id.'&tab='.$current_tab.'&page=1&search='.$search.'">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    for ($i = $start; $i <= $end; $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<li class="page-item '.$active.'"><a class="page-link" href="?shelter_id='.$shelter_id.'&tab='.$current_tab.'&page='.$i.'&search='.$search.'">'.$i.'</a></li>';
                    }
                    if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    if ($end < $total_pages - 1) echo '<li class="page-item"><a class="page-link" href="?shelter_id='.$shelter_id.'&tab='.$current_tab.'&page='.$total_pages.'&search='.$search.'">'.$total_pages.'</a></li>';
                    ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?shelter_id=<?php echo $shelter_id; ?>&tab=<?php echo $current_tab; ?>&page=<?php echo $page+1; ?>&search=<?php echo $search; ?>">ถัดไป</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Popup แสดงรายละเอียด (เพิ่มปุ่มแก้ไข) -->
<div class="modal fade" id="evacueeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard-fill me-2"></i> รายละเอียดผู้อพยพ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Column 1: Profile -->
                    <div class="col-md-4 text-center border-end">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-primary fw-bold" style="width: 100px; height: 100px; font-size: 2.5rem; border: 3px solid #cfe2ff;">
                            <span id="modal-avatar"></span>
                        </div>
                        <h5 class="fw-bold mb-1" id="modal-name"></h5>
                        <p class="text-muted small mb-3" id="modal-id"></p>
                        
                        <div class="d-grid gap-2">
                            <div class="p-2 bg-light rounded border text-start">
                                <small class="text-muted d-block">อายุ</small>
                                <strong id="modal-age" class="text-dark fs-5"></strong> ปี
                            </div>
                            <div class="p-2 bg-light rounded border text-start">
                                <small class="text-muted d-block">สถานะสุขภาพ</small>
                                <span id="modal-health" class="badge bg-secondary"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Details -->
                    <div class="col-md-8">
                        <h6 class="text-uppercase text-secondary fw-bold small border-bottom pb-2 mb-3">ข้อมูลที่พัก & การติดต่อ</h6>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <small class="text-muted d-block">วันที่เข้า</small>
                                <strong id="modal-checkin" class="text-success"></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">วันที่ออก</small>
                                <strong id="modal-checkout" class="text-secondary">-</strong>
                            </div>
                            
                            <div class="col-12">
                                <small class="text-muted d-block">รูปแบบการพัก</small>
                                <div id="modal-stay-status"></div>
                                <div id="modal-outside-detail" class="mt-1 small text-info fst-italic"></div>
                            </div>
                            
                            <div class="col-12">
                                <small class="text-muted d-block">เบอร์โทรศัพท์</small>
                                <strong id="modal-phone"></strong>
                            </div>

                            <div class="col-12">
                                <h6 class="text-uppercase text-secondary fw-bold small border-bottom pb-2 mb-2 mt-2">ที่อยู่ตามภูมิลำเนา</h6>
                                <p id="modal-address" class="mb-0 text-dark"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- เพิ่มปุ่มแก้ไขใน Footer -->
            <div class="modal-footer bg-light">
                <!-- ปุ่มแก้ไข (Link) จะถูก set href โดย JS -->
                <a href="#" id="modal-btn-edit" class="btn btn-warning shadow-sm">
                    <i class="bi bi-pencil-square me-1"></i> แก้ไขข้อมูล
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
function openDetailModal(data) {
    // Populate Data
    document.getElementById('modal-avatar').innerText = data.first_name.charAt(0);
    document.getElementById('modal-name').innerText = data.prefix + data.first_name + ' ' + data.last_name;
    document.getElementById('modal-id').innerText = data.citizen_id;
    document.getElementById('modal-age').innerText = data.age;
    
    // Health Badge
    const healthEl = document.getElementById('modal-health');
    healthEl.innerText = data.health_condition;
    healthEl.className = (data.health_condition === 'ไม่มี') ? 'badge bg-success' : 'badge bg-danger';

    // Dates
    document.getElementById('modal-checkin').innerText = new Date(data.check_in_date).toLocaleDateString('th-TH');
    document.getElementById('modal-checkout').innerText = data.check_out_date ? new Date(data.check_out_date).toLocaleDateString('th-TH') : '-';
    
    // Stay Status
    const stayEl = document.getElementById('modal-stay-status');
    const outsideEl = document.getElementById('modal-outside-detail');
    const editBtn = document.getElementById('modal-btn-edit');

    // กำหนด Link ปุ่มแก้ไข
    // ถ้ายังพักอยู่ (check_out_date null) ให้แก้ได้ ถ้าออกไปแล้ว (history) อาจจะซ่อนปุ่ม หรือให้แก้ได้แล้วแต่ policy
    // ในที่นี้ให้แก้ได้ตลอด ถ้ามี ID
    if (data.id && data.shelter_id) {
        editBtn.href = `evacuee_form.php?id=${data.id}&shelter_id=${data.shelter_id}`;
        editBtn.style.display = 'inline-block';
    } else {
        editBtn.style.display = 'none';
    }
    
    if (data.check_out_date) {
        stayEl.innerHTML = '<span class="badge bg-secondary">จำหน่ายออกแล้ว</span>';
        outsideEl.innerText = 'สาเหตุ: ' + (data.check_out_reason || '-');
        // ถ้าออกแล้ว อาจจะซ่อนปุ่มแก้ไขก็ได้ ถ้าต้องการ (uncomment บรรทัดล่าง)
        // editBtn.style.display = 'none'; 
    } else {
        if (data.accommodation_type === 'outside') {
            stayEl.innerHTML = '<span class="badge bg-warning text-dark"><i class="bi bi-house"></i> พักนอกศูนย์</span>';
            outsideEl.innerText = 'รายละเอียด: ' + (data.outside_detail || '-');
        } else {
            stayEl.innerHTML = '<span class="badge bg-success"><i class="bi bi-building"></i> พักในศูนย์</span>';
            outsideEl.innerText = '';
        }
    }

    document.getElementById('modal-phone').innerText = data.phone || '-';
    
    let address = `บ้านเลขที่ ${data.house_no || '-'}`;
    if (data.village_no) address += ` หมู่ ${data.village_no}`;
    if (data.subdistrict) address += ` ต.${data.subdistrict}`;
    if (data.district) address += ` อ.${data.district}`;
    if (data.province) address += ` จ.${data.province}`;
    document.getElementById('modal-address').innerText = address;

    new bootstrap.Modal(document.getElementById('evacueeModal')).show();
}

function confirmCheckout(id) {
    Swal.fire({
        title: 'ยืนยันการจำหน่ายออก?',
        text: "ระบบจะย้ายรายชื่อนี้ไปยังประวัติ (Tab จำหน่ายแล้ว)",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#e5e7eb',
        cancelButtonText: '<span class="text-dark">ยกเลิก</span>',
        confirmButtonText: 'ยืนยันจำหน่ายออก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `evacuee_status.php?action=checkout&id=${id}&shelter_id=<?php echo $shelter_id; ?>`;
        }
    })
}
</script>

<?php require_once 'includes/footer.php'; ?>