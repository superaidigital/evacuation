<?php
require_once 'config/db.php';

// 1. รับค่า ID
$id = $_GET['id'] ?? null;
if (!$id) die("Invalid Request");

// ดึงข้อมูลศูนย์
$stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
$stmt->execute([$id]);
$shelter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shelter) die("ไม่พบข้อมูลศูนย์พักพิงชั่วคราว");

// 2. ดึงสถิติ
// ยอดรวม
$sql_total = "SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute([$id]);
$total = $stmt_total->fetchColumn();

// แยกชาย/หญิง
$sql_gender = "SELECT 
    SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female
FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_gender = $pdo->prepare($sql_gender);
$stmt_gender->execute([$id]);
$gender = $stmt_gender->fetch(PDO::FETCH_ASSOC);

// ช่วงอายุ
$sql_age = "SELECT 
    SUM(CASE WHEN age <= 5 THEN 1 ELSE 0 END) as kids,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age > 5 AND age < 60 THEN 1 ELSE 0 END) as adults
FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_age = $pdo->prepare($sql_age);
$stmt_age->execute([$id]);
$age_group = $stmt_age->fetch(PDO::FETCH_ASSOC);

// กลุ่มเปราะบาง
$sql_vul = "SELECT 
    SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden,
    SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled,
    SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant
FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
$stmt_vul = $pdo->prepare($sql_vul);
$stmt_vul->execute([$id]);
$vul = $stmt_vul->fetch(PDO::FETCH_ASSOC);

// ผู้ประสานงาน
$stmt_coords = $pdo->prepare("SELECT * FROM shelter_coordinators WHERE shelter_id = ?");
$stmt_coords->execute([$id]);
$coordinators = $stmt_coords->fetchAll(PDO::FETCH_ASSOC);

// คำนวณ Logistics
$food_needed = $total * 3;
$water_needed = $total * 2;

// คำนวณสถานะ
$capacity = $shelter['capacity'];
$percent = ($capacity > 0) ? ($total / $capacity) * 100 : 0;
$vacant = max(0, $capacity - $total);

if ($percent >= 100) { $status_color = 'danger'; $status_text = 'เต็มพื้นที่'; }
else if ($percent >= 80) { $status_color = 'warning'; $status_text = 'หนาแน่น'; }
else { $status_color = 'success'; $status_text = 'ปกติ'; }

$current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$map_link = "https://www.google.com/maps/search/?api=1&query=" . urlencode($shelter['name'] . " " . $shelter['district'] . " " . $shelter['province']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard: <?php echo $shelter['name']; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bs-body-bg: #f8fafc;
            --bs-body-color: #334155;
            --card-border-radius: 1rem;
        }
        body { font-family: 'Sarabun', sans-serif; padding-bottom: 60px; }
        
        /* Hero Section */
        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            color: white;
            padding: 2.5rem 1rem 4rem; /* Mobile Padding */
            border-radius: 0 0 1.5rem 1.5rem;
            position: relative;
            margin-bottom: -3rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .update-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.85rem;
            display: inline-flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1rem;
        }

        /* Card Styles */
        .card-custom {
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            background: white;
            height: 100%;
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        /* Hover Effect only on devices with hover capability */
        @media (hover: hover) {
            .card-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Progress Circle (CSS Only) */
        .progress-ring {
            width: 120px; height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--bs-<?php echo $status_color; ?>) <?php echo $percent; ?>%, #e2e8f0 0);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto;
        }
        .progress-ring-inner {
            width: 100px; height: 100px;
            background: white; border-radius: 50%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }

        /* Contact Cards */
        .contact-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 0.5rem;
            flex-wrap: wrap; /* Allow wrapping on very small screens */
            gap: 0.5rem;
        }
        .contact-info {
            flex: 1;
            min-width: 150px;
        }

        /* Responsive Breakpoints */
        /* Default: Mobile First (Stack vertically) */
        .dashboard-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .stats-row {
            display: grid;
            grid-template-columns: 1fr; /* 1 column on mobile */
            gap: 0.75rem;
        }

        /* Small Tablet (>= 576px) */
        @media (min-width: 576px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr); /* 3 columns on tablet/desktop */
            }
        }

        /* Desktop (>= 992px) */
        @media (min-width: 992px) {
            .hero-banner { 
                padding: 4rem 2rem 6rem; 
                border-radius: 0 0 3rem 3rem; 
            }
            .dashboard-grid { 
                display: grid; 
                grid-template-columns: 2fr 1fr; /* Left 2/3, Right 1/3 */
                gap: 1.5rem; 
            }
            .stats-row { 
                /* Inherits 3 columns */
                gap: 1rem; 
            }
            .container {
                max-width: 1200px;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Hero Section -->
    <div class="hero-banner text-center">
        <div class="container">
            <div class="update-badge text-warning">
                <span class="spinner-grow spinner-grow-sm" role="status"></span>
                <span>อัปเดต: <?php echo date('H:i น.', strtotime($shelter['last_updated'])); ?></span>
            </div>
            
            <h1 class="fw-bold mb-2 fs-2 fs-md-1"><?php echo $shelter['name']; ?></h1>
            <p class="text-white-50 mb-4">
                <i class="bi bi-geo-alt-fill text-danger"></i> อ.<?php echo $shelter['district']; ?> จ.<?php echo $shelter['province']; ?>
            </p>
            
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="<?php echo $map_link; ?>" target="_blank" class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm">
                    <i class="bi bi-map-fill me-2"></i> แผนที่
                </a>
                <button onclick="shareLink()" class="btn btn-outline-light rounded-pill px-4 fw-bold">
                    <i class="bi bi-share-fill me-2"></i> แชร์
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Main Content -->
    <div class="container" style="position: relative; z-index: 10;">
        <div class="dashboard-grid">
            
            <!-- LEFT COLUMN: Main Stats -->
            <div class="d-flex flex-column gap-3">
                
                <!-- Occupancy Card -->
                <div class="card-custom p-4">
                    <div class="row align-items-center gy-4">
                        <div class="col-md-6 text-center text-md-start">
                            <h6 class="text-secondary fw-bold text-uppercase mb-1">ยอดผู้พักอาศัย</h6>
                            <h1 class="display-4 fw-bold text-dark mb-0"><?php echo number_format($total); ?> <span class="fs-4 text-muted">คน</span></h1>
                            <div class="mt-2">
                                <span class="badge bg-<?php echo $status_color; ?> bg-opacity-10 text-<?php echo $status_color; ?> px-3 py-2 border border-<?php echo $status_color; ?>">
                                    สถานะ: <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="progress-ring">
                                <div class="progress-ring-inner">
                                    <small class="text-muted fw-bold">ความจุ</small>
                                    <span class="fs-4 fw-bold"><?php echo number_format($percent); ?>%</span>
                                </div>
                            </div>
                            <div class="mt-3 d-flex justify-content-center gap-3 small text-muted">
                                <div><i class="bi bi-circle-fill text-secondary me-1"></i> รับได้ <?php echo number_format($capacity); ?></div>
                                <div><i class="bi bi-circle-fill text-success me-1"></i> ว่าง <?php echo number_format($vacant); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Demographics Row (3 Columns on Tablet+) -->
                <div class="stats-row">
                    <div class="card-custom p-3 text-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto"><i class="bi bi-gender-ambiguous"></i></div>
                        <h6 class="text-muted small">ชาย / หญิง</h6>
                        <h4 class="fw-bold text-dark"><?php echo $gender['male']; ?> / <?php echo $gender['female']; ?></h4>
                    </div>
                    <div class="card-custom p-3 text-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto"><i class="bi bi-emoji-smile"></i></div>
                        <h6 class="text-muted small">เด็ก (0-5 ปี)</h6>
                        <h4 class="fw-bold text-dark"><?php echo $age_group['kids']; ?></h4>
                    </div>
                    <div class="card-custom p-3 text-center">
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary mx-auto"><i class="bi bi-eyeglasses"></i></div>
                        <h6 class="text-muted small">ผู้สูงอายุ (60+)</h6>
                        <h4 class="fw-bold text-dark"><?php echo $age_group['elderly']; ?></h4>
                    </div>
                </div>

                <!-- Vulnerable Groups -->
                <?php if($vul['bedridden'] > 0 || $vul['disabled'] > 0 || $vul['pregnant'] > 0): ?>
                <div class="card-custom p-3 border-start border-4 border-danger">
                    <h6 class="fw-bold text-danger mb-3"><i class="bi bi-heart-pulse-fill me-2"></i> กลุ่มเปราะบาง (ดูแลพิเศษ)</h6>
                    <div class="row g-2 text-center">
                        <?php if($vul['bedridden'] > 0): ?>
                        <div class="col-4">
                            <div class="bg-danger bg-opacity-10 p-2 rounded text-danger fw-bold">
                                <div class="fs-4"><?php echo $vul['bedridden']; ?></div>
                                <small style="font-size: 0.75rem;">ติดเตียง</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if($vul['disabled'] > 0): ?>
                        <div class="col-4">
                            <div class="bg-warning bg-opacity-10 p-2 rounded text-warning fw-bold">
                                <div class="fs-4"><?php echo $vul['disabled']; ?></div>
                                <small style="font-size: 0.75rem;">ผู้พิการ</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if($vul['pregnant'] > 0): ?>
                        <div class="col-4">
                            <div class="bg-info bg-opacity-10 p-2 rounded text-info fw-bold">
                                <div class="fs-4"><?php echo $vul['pregnant']; ?></div>
                                <small style="font-size: 0.75rem;">ตั้งครรภ์</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Logistics Needs -->
                <div class="card-custom p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-box-seam-fill text-primary me-2"></i> ความต้องการประจำวัน (โดยประมาณ)</h6>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-basket2-fill text-warning me-2"></i> อาหารกล่อง (3 มื้อ)</span>
                            <span class="fw-bold fs-5"><?php echo number_format($food_needed); ?> <small class="fs-6 fw-normal text-muted">ชุด</small></span>
                        </div>
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-warning" style="width: 100%"></div></div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-droplet-fill text-info me-2"></i> น้ำดื่ม (2 ลิตร)</span>
                            <span class="fw-bold fs-5"><?php echo number_format($water_needed); ?> <small class="fs-6 fw-normal text-muted">ลิตร</small></span>
                        </div>
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-info" style="width: 100%"></div></div>
                    </div>
                </div>

            </div>
            
            <!-- RIGHT COLUMN: Contacts & QR -->
            <div class="d-flex flex-column gap-3">
                
                <div class="card-custom p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-telephone-fill text-success me-2"></i> ติดต่อประสานงาน</h6>
                    
                    <?php if(count($coordinators) > 0): ?>
                        <?php foreach($coordinators as $c): ?>
                            <div class="contact-item">
                                <div class="contact-info">
                                    <div class="fw-bold text-dark"><?php echo $c['name']; ?></div>
                                    <small class="text-muted"><?php echo $c['position']; ?></small>
                                </div>
                                <a href="tel:<?php echo $c['phone']; ?>" class="btn btn-success btn-sm rounded-circle shadow-sm" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-telephone-fill"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted small py-3">ยังไม่มีข้อมูลผู้ประสานงาน</div>
                    <?php endif; ?>

                    <hr class="my-4">
                    <h6 class="fw-bold text-dark mb-2 small text-uppercase">เบอร์ฉุกเฉิน (ส่วนกลาง)</h6>
                    <div class="d-flex gap-2">
                        <a href="tel:1669" class="btn btn-outline-danger btn-sm flex-grow-1"><i class="bi bi-hospital me-1"></i> 1669 เจ็บป่วย</a>
                        <a href="tel:191" class="btn btn-outline-dark btn-sm flex-grow-1"><i class="bi bi-shield-exclamation me-1"></i> 191 เหตุด่วน</a>
                    </div>
                </div>

                <div class="card-custom p-4 text-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($current_url); ?>" width="120" class="mb-3">
                    <h6 class="fw-bold">สแกนเพื่อติดตามข้อมูล</h6>
                    <p class="text-muted small mb-0">ข้อมูลอัปเดต Real-time</p>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function shareLink() {
            if (navigator.share) {
                navigator.share({
                    title: 'สถานการณ์: <?php echo $shelter['name']; ?>',
                    text: 'ติดตามข้อมูลศูนย์พักพิงชั่วคราว <?php echo $shelter['name']; ?>',
                    url: window.location.href,
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                Swal.fire({
                    toast: true, position: 'bottom', icon: 'success', 
                    title: 'คัดลอกลิงก์แล้ว', showConfirmButton: false, timer: 1500
                });
            }
        }
    </script>
</body>
</html>