<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// --- 1. เตรียมข้อมูลช่วงเวลา (7 วันย้อนหลัง) ---
$dates_labels = [];
$data_in = [];
$data_out = [];
$today = date('Y-m-d');

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime($d));
    $dates_labels[] = $label;
    
    // Query ยอดเข้า
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE check_in_date = ?");
    $stmt->execute([$d]);
    $data_in[] = $stmt->fetchColumn();

    // Query ยอดออก
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE check_out_date = ?");
    $stmt->execute([$d]);
    $data_out[] = $stmt->fetchColumn();
}

// --- 2. ข้อมูลภาพรวม (Current Status) ---
$total_stay = $pdo->query("SELECT COUNT(*) FROM evacuees WHERE check_out_date IS NULL")->fetchColumn();
$total_capacity = $pdo->query("SELECT SUM(capacity) FROM shelters WHERE status = 'OPEN'")->fetchColumn();
$total_shelters = $pdo->query("SELECT COUNT(*) FROM shelters WHERE status = 'OPEN'")->fetchColumn();

// แยกเพศ
$sql_gender = "SELECT 
    SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female
FROM evacuees WHERE check_out_date IS NULL";
$gender = $pdo->query($sql_gender)->fetch(PDO::FETCH_ASSOC);

// กลุ่มเปราะบาง
$sql_vul = "SELECT 
    SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden,
    SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled,
    SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age <= 5 THEN 1 ELSE 0 END) as kids
FROM evacuees WHERE check_out_date IS NULL";
$vul = $pdo->query($sql_vul)->fetch(PDO::FETCH_ASSOC);
$total_vul = $vul['bedridden'] + $vul['disabled'] + $vul['pregnant'] + $vul['elderly'];

// --- 3. ข้อมูลรายศูนย์ (Table Data) ---
$sql_table = "SELECT 
    s.name, s.district, s.capacity, s.last_updated,
    COUNT(e.id) as current,
    SUM(CASE WHEN e.health_condition != 'ไม่มี' THEN 1 ELSE 0 END) as vul_count
FROM shelters s
LEFT JOIN evacuees e ON s.id = e.shelter_id AND e.check_out_date IS NULL
WHERE s.status = 'OPEN'
GROUP BY s.id
ORDER BY current DESC"; 
$reports = $pdo->query($sql_table)->fetchAll(PDO::FETCH_ASSOC);

// เตรียมข้อมูล JSON สำหรับส่งให้ JS ทำ Popup
$json_vul = json_encode($vul);
$json_shelters = json_encode($reports);
$json_gender = json_encode($gender);
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Card Hover Effect */
    .card-hover {
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        border-color: var(--bs-primary) !important;
    }

    /* Print Styles */
    @media print {
        .sidebar, .btn, .no-print, a[href] { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        body { background-color: white !important; -webkit-print-color-adjust: exact; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
        .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        canvas { max-height: 250px !important; }
        /* Disable hover effects on print */
        .card-hover { transform: none !important; box-shadow: none !important; }
    }
    .print-header { display: none; }
</style>

<!-- หัวกระดาษ (แสดงเฉพาะตอนสั่งพิมพ์) -->
<div class="print-header">
    <h3>รายงานสรุปสถานการณ์ผู้ประสบภัย (รายวัน)</h3>
    <p>ข้อมูล ณ วันที่ <?php echo date('d/m/Y'); ?> เวลา <?php echo date('H:i'); ?> น.</p>
</div>

<!-- หัวข้อหน้าเว็บ -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-bar-graph-fill text-primary"></i> รายงานสรุปสถานการณ์</h3>
        <p class="text-muted small">คลิกที่การ์ดเพื่อดูรายละเอียดเพิ่มเติม</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark shadow-sm">
            <i class="bi bi-printer-fill"></i> พิมพ์รายงาน
        </button>
    </div>
</div>

<!-- 1. KPI Cards (Clickable) -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-modern card-hover border-primary border-opacity-25 bg-primary bg-opacity-10 h-100" 
             onclick="openDetailModal('total')">
            <div class="card-body text-center">
                <h6 class="text-primary text-uppercase fw-bold small">ผู้อพยพปัจจุบัน</h6>
                <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_stay); ?></h2>
                <small class="text-muted">คน <i class="bi bi-search ms-1"></i></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern card-hover h-100" onclick="openDetailModal('shelters')">
            <div class="card-body text-center">
                <h6 class="text-secondary text-uppercase fw-bold small">ศูนย์ที่เปิดอยู่</h6>
                <h2 class="fw-bold text-dark mb-0"><?php echo $total_shelters; ?></h2>
                <small class="text-muted">แห่งทั่วจังหวัด <i class="bi bi-list-ul ms-1"></i></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern card-hover h-100" onclick="openDetailModal('density')">
            <div class="card-body text-center">
                <h6 class="text-secondary text-uppercase fw-bold small">อัตราการใช้พื้นที่</h6>
                <h2 class="fw-bold text-dark mb-0">
                    <?php echo ($total_capacity > 0) ? round(($total_stay / $total_capacity) * 100) : 0; ?>%
                </h2>
                <small class="text-muted">ความจุรวม <?php echo number_format($total_capacity); ?> <i class="bi bi-bar-chart ms-1"></i></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-modern card-hover border-danger border-opacity-25 bg-danger bg-opacity-10 h-100" 
             onclick="openDetailModal('vulnerable')">
            <div class="card-body text-center">
                <h6 class="text-danger text-uppercase fw-bold small">กลุ่มเปราะบาง</h6>
                <h2 class="fw-bold text-dark mb-0">
                    <?php echo number_format($total_vul); ?>
                </h2>
                <small class="text-danger">คลิกเพื่อดูรายละเอียด <i class="bi bi-heart-pulse ms-1"></i></small>
            </div>
        </div>
    </div>
</div>

<!-- 2. กราฟแนวโน้ม & สัดส่วน -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0"><i class="bi bi-graph-up-arrow text-info"></i> แนวโน้มการอพยพ (7 วันย้อนหลัง)</h6>
            </div>
            <div class="card-body">
                <canvas id="trendChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold m-0"><i class="bi bi-gender-ambiguous text-primary"></i> สัดส่วนประชากร</h6>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <div style="height: 200px; width: 100%;">
                    <canvas id="genderChart"></canvas>
                </div>
                <div class="mt-3 text-center w-100 d-flex justify-content-center gap-4">
                    <div><span class="badge bg-primary rounded-pill me-1">&nbsp;</span> ชาย <?php echo number_format($gender['male']); ?></div>
                    <div><span class="badge bg-danger rounded-pill me-1">&nbsp;</span> หญิง <?php echo number_format($gender['female']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. ตารางรายศูนย์ (Top List) -->
<div class="card card-modern mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold m-0"><i class="bi bi-list-task"></i> สถานะรายศูนย์พักพิง (เรียงตามจำนวนคน)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">ชื่อศูนย์</th>
                    <th>อำเภอ</th>
                    <th class="text-center">ยอดผู้อพยพ</th>
                    <th class="text-center">กลุ่มเปราะบาง</th>
                    <th class="text-center">ความหนาแน่น</th>
                    <th class="text-end pe-4">อัปเดตล่าสุด</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($reports as $row): 
                    $percent = ($row['capacity'] > 0) ? ($row['current'] / $row['capacity']) * 100 : 0;
                    $is_updated = (substr($row['last_updated'], 0, 10) == date('Y-m-d'));
                    $bar_color = ($percent > 90) ? 'bg-danger' : (($percent > 70) ? 'bg-warning' : 'bg-success');
                ?>
                <tr>
                    <td class="ps-4 fw-bold text-dark"><?php echo $row['name']; ?></td>
                    <td class="text-secondary"><?php echo $row['district']; ?></td>
                    <td class="text-center fw-bold fs-5 text-primary"><?php echo number_format($row['current']); ?></td>
                    <td class="text-center text-danger fw-bold"><?php echo number_format($row['vul_count']); ?></td>
                    <td style="width: 20%;">
                        <div class="d-flex align-items-center gap-2 px-3">
                            <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo number_format($percent); ?>%</small>
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <?php if($is_updated): ?>
                            <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i> วันนี้</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border"><?php echo date('d/m', strtotime($row['last_updated'])); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Popup -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold" id="modalTitle">รายละเอียด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Data from PHP
    const vulData = <?php echo $json_vul; ?>;
    const shelterData = <?php echo $json_shelters; ?>;
    const genderData = <?php echo $json_gender; ?>;

    function openDetailModal(type) {
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        const titleEl = document.getElementById('modalTitle');
        const bodyEl = document.getElementById('modalBody');
        let content = '';

        if (type === 'vulnerable') {
            titleEl.innerHTML = '<i class="bi bi-heart-pulse-fill text-danger me-2"></i> รายละเอียดกลุ่มเปราะบาง';
            content = `
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-hospital text-danger me-2"></i> ผู้ป่วยติดเตียง</span>
                        <span class="badge bg-danger rounded-pill">${vulData.bedridden} คน</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-wheelchair text-warning me-2"></i> ผู้พิการ</span>
                        <span class="badge bg-warning text-dark rounded-pill">${vulData.disabled} คน</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-gender-female text-info me-2"></i> หญิงตั้งครรภ์</span>
                        <span class="badge bg-info text-dark rounded-pill">${vulData.pregnant} คน</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-eyeglasses text-secondary me-2"></i> ผู้สูงอายุ (60+)</span>
                        <span class="badge bg-secondary rounded-pill">${vulData.elderly} คน</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-emoji-smile text-primary me-2"></i> เด็กเล็ก (0-5 ปี)</span>
                        <span class="badge bg-primary rounded-pill">${vulData.kids} คน</span>
                    </div>
                </div>`;

        } else if (type === 'total') {
            titleEl.innerHTML = '<i class="bi bi-people-fill text-primary me-2"></i> สรุปประชากรทั้งหมด';
            content = `
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-blue-100 rounded text-primary border border-primary border-opacity-25">
                            <h4>${genderData.male}</h4>
                            <small>ชาย</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-pink-100 rounded text-danger border border-danger border-opacity-25">
                            <h4>${genderData.female}</h4>
                            <small>หญิง</small>
                        </div>
                    </div>
                </div>
                <p class="text-muted small text-center">* ข้อมูลจากผู้ที่ยังพักอาศัยอยู่ในศูนย์ปัจจุบัน</p>
            `;
        } else if (type === 'shelters' || type === 'density') {
            titleEl.innerHTML = '<i class="bi bi-house-door-fill text-success me-2"></i> รายชื่อศูนย์อพยพ';
            content = '<div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">';
            
            // Sort by crowd if density clicked
            let sortedData = [...shelterData];
            if(type === 'density') {
                sortedData.sort((a,b) => (b.current/b.capacity) - (a.current/a.capacity));
            }

            sortedData.forEach(s => {
                let percent = (s.capacity > 0) ? Math.round((s.current / s.capacity) * 100) : 0;
                let color = percent > 90 ? 'danger' : (percent > 70 ? 'warning' : 'success');
                content += `
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark">${s.name}</span>
                            <span class="badge bg-${color}">${percent}%</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>อ.${s.district}</span>
                            <span>${s.current} / ${s.capacity} คน</span>
                        </div>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-${color}" style="width: ${percent}%"></div>
                        </div>
                    </div>
                `;
            });
            content += '</div>';
        }

        bodyEl.innerHTML = content;
        modal.show();
    }

    // Chart 1: Trend
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates_labels); ?>,
            datasets: [
                {
                    label: 'รับเข้า (คน)',
                    data: <?php echo json_encode($data_in); ?>,
                    borderColor: '#22c55e', backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.3, fill: true
                },
                {
                    label: 'จำหน่ายออก (คน)',
                    data: <?php echo json_encode($data_out); ?>,
                    borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3, fill: true
                }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
    });

    // Chart 2: Gender
    const ctxGender = document.getElementById('genderChart').getContext('2d');
    new Chart(ctxGender, {
        type: 'doughnut',
        data: {
            labels: ['ชาย', 'หญิง'],
            datasets: [{
                data: [<?php echo $gender['male']; ?>, <?php echo $gender['female']; ?>],
                backgroundColor: ['#0d6efd', '#dc3545'], borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '60%' }
    });
</script>

<?php require_once 'includes/footer.php'; ?>