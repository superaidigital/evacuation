<?php
require_once 'config/db.php';

// 1. Logic การดึงข้อมูล
$id = $_GET['id'] ?? null;
$today = date('Y-m-d'); // วันปัจจุบัน

$stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
$stmt_event->execute();
$current_event = $stmt_event->fetchColumn();

if ($id) {
    // --- SINGLE MODE ---
    $mode = 'SINGLE';
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$id]);
    $shelter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$shelter) die("ไม่พบข้อมูล");

    $title_text = $shelter['name'];
    $sub_title = "อ." . $shelter['district'];

    $sql_total = "SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([$id]);
    $total_evacuees = $stmt_total->fetchColumn();
    
    $total_shelters = 1;
    $total_capacity = $shelter['capacity'];
    
    // Movement Today (รายศูนย์)
    $sql_move = "SELECT 
        SUM(CASE WHEN check_in_date = ? THEN 1 ELSE 0 END) as in_today,
        SUM(CASE WHEN check_out_date = ? THEN 1 ELSE 0 END) as out_today
    FROM evacuees WHERE shelter_id = ?";
    $stmt_move = $pdo->prepare($sql_move);
    $stmt_move->execute([$today, $today, $id]);
    $movement = $stmt_move->fetch(PDO::FETCH_ASSOC);

    // Gender Stats
    $sql_gender = "SELECT SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male, SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
    $stmt_gender = $pdo->prepare($sql_gender);
    $stmt_gender->execute([$id]);
    $gender = $stmt_gender->fetch(PDO::FETCH_ASSOC);

    // Vulnerable Stats
    $sql_vul = "SELECT SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden, SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled, SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant, SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly, SUM(CASE WHEN age <= 2 THEN 1 ELSE 0 END) as infants FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
    $stmt_vul = $pdo->prepare($sql_vul);
    $stmt_vul->execute([$id]);
    $vul = $stmt_vul->fetch(PDO::FETCH_ASSOC);

    // Logistics Calculations
    $food_needed = $total_evacuees * 3; 
    $water_needed = $total_evacuees * 2; 
    $blankets_needed = $total_evacuees; 
    $hygiene_kits = $total_evacuees; 
    $mosquito_nets = ceil($total_evacuees / 3); 

    $stmt_coords = $pdo->prepare("SELECT * FROM shelter_coordinators WHERE shelter_id = ?");
    $stmt_coords->execute([$id]);
    $coordinators = $stmt_coords->fetchAll(PDO::FETCH_ASSOC);

} else {
    // --- GLOBAL MODE ---
    $mode = 'GLOBAL';
    $title_text = "ศูนย์บัญชาการเหตุการณ์ (EOC)";
    $sub_title = "ภาพรวมทั้งจังหวัด";

    $sql_total = "SELECT COUNT(*) FROM evacuees WHERE check_out_date IS NULL";
    $total_evacuees = $pdo->query($sql_total)->fetchColumn();

    $sql_shelters = "SELECT COUNT(*) FROM shelters WHERE status = 'OPEN'";
    $total_shelters = $pdo->query($sql_shelters)->fetchColumn();

    $sql_capacity = "SELECT SUM(capacity) FROM shelters WHERE status = 'OPEN'";
    $total_capacity = $pdo->query($sql_capacity)->fetchColumn();

    // Movement Today (ภาพรวมทั้งจังหวัด)
    $sql_move = "SELECT 
        SUM(CASE WHEN check_in_date = ? THEN 1 ELSE 0 END) as in_today,
        SUM(CASE WHEN check_out_date = ? THEN 1 ELSE 0 END) as out_today
    FROM evacuees";
    $stmt_move = $pdo->prepare($sql_move);
    $stmt_move->execute([$today, $today]);
    $movement = $stmt_move->fetch(PDO::FETCH_ASSOC);

    // Gender Stats
    $sql_gender = "SELECT SUM(CASE WHEN prefix IN ('นาย', 'ด.ช.') THEN 1 ELSE 0 END) as male, SUM(CASE WHEN prefix IN ('นาง', 'น.ส.', 'ด.ญ.') THEN 1 ELSE 0 END) as female FROM evacuees WHERE check_out_date IS NULL";
    $gender = $pdo->query($sql_gender)->fetch(PDO::FETCH_ASSOC);

    $sql_vul = "SELECT SUM(CASE WHEN health_condition = 'ผู้ป่วยติดเตียง' THEN 1 ELSE 0 END) as bedridden, SUM(CASE WHEN health_condition = 'ผู้พิการ' THEN 1 ELSE 0 END) as disabled, SUM(CASE WHEN health_condition = 'ตั้งครรภ์' THEN 1 ELSE 0 END) as pregnant, SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly, SUM(CASE WHEN age <= 2 THEN 1 ELSE 0 END) as infants FROM evacuees WHERE check_out_date IS NULL";
    $vul = $pdo->query($sql_vul)->fetch(PDO::FETCH_ASSOC);

    $sql_list = "SELECT s.name, s.district, s.capacity, s.last_updated, COUNT(e.id) as current_stay FROM shelters s LEFT JOIN evacuees e ON s.id = e.shelter_id AND e.check_out_date IS NULL WHERE s.status = 'OPEN' GROUP BY s.id ORDER BY current_stay DESC LIMIT 8";
    $shelter_list = $pdo->query($sql_list)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Dashboard</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            /* Theme Colors (Default Dark) */
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-glass: rgba(30, 41, 59, 0.7);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255,255,255,0.05);
            --header-bg: rgba(15, 23, 42, 0.8);
            
            /* Accents */
            --accent-blue: #38bdf8;
            --accent-green: #34d399;
            --accent-red: #f87171;
            --accent-yellow: #fbbf24;
            --accent-pink: #ec4899;
            --header-height: 70px;
        }

        [data-theme="light"] {
            --bg-body: #f0f9ff;
            --bg-card: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.8);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: rgba(0,0,0,0.05);
            --header-bg: rgba(255, 255, 255, 0.9);
            
            --accent-blue: #0ea5e9;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --accent-yellow: #f59e0b;
            --accent-pink: #db2777;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Sarabun', sans-serif;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .top-bar {
            height: var(--header-height);
            padding: 0 1.5rem;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(10px);
            flex-shrink: 0;
            transition: background-color 0.3s ease;
        }

        .event-tag {
            background: rgba(248, 113, 113, 0.15);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
            padding: 0.25rem 1rem;
            border-radius: 99px;
            font-weight: 600;
            font-size: 1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        
        .live-dot {
            width: 8px; height: 8px; background-color: var(--accent-red); border-radius: 50%;
            box-shadow: 0 0 8px var(--accent-red); animation: blink 2s infinite;
        }
        @keyframes blink { 0% {opacity: 1;} 50% {opacity: 0.4;} 100% {opacity: 1;} }

        /* Theme Toggle Button */
        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover { transform: scale(1.1); box-shadow: 0 0 10px rgba(0,0,0,0.1); }

        .dashboard-container {
            height: calc(100vh - var(--header-height));
            padding: 1rem;
            display: grid;
            grid-template-rows: 20% 1fr;
            gap: 1rem;
        }

        .row-kpi {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .row-content {
            display: grid;
            grid-template-columns: 30% 70%;
            gap: 1rem;
            min-height: 0;
        }

        .kpi-card {
            background: var(--bg-card);
            border-radius: 0.75rem; padding: 1rem;
            display: flex; flex-direction: column; justify-content: center;
            border-left: 4px solid transparent;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: background-color 0.3s ease;
        }
        .kpi-card.c-blue { border-color: var(--accent-blue); }
        .kpi-card.c-green { border-color: var(--accent-green); }
        .kpi-card.c-yellow { border-color: var(--accent-yellow); }
        .kpi-card.c-red { border-color: var(--accent-red); }

        .kpi-title { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; }
        .kpi-number { font-size: 2.5rem; font-weight: 700; line-height: 1; margin-top: 0.25rem; color: var(--text-main); }
        .kpi-sub { font-size: 0.8rem; color: var(--text-muted); opacity: 0.8; }

        .content-card {
            background: var(--bg-card);
            border-radius: 0.75rem; padding: 1rem;
            height: 100%; 
            display: flex; flex-direction: column;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: background-color 0.3s ease;
        }

        .section-header {
            font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;
            padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;
            color: var(--text-main);
        }

        .table-wrapper { flex-grow: 1; overflow-y: auto; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { 
            text-align: left; color: var(--text-muted); font-weight: 500; 
            padding: 0.5rem; border-bottom: 1px solid var(--border-color); 
            position: sticky; top: 0; background: var(--bg-card); 
        }
        .table-custom td { 
            padding: 0.75rem 0.5rem; 
            border-bottom: 1px solid var(--border-color); 
            font-size: 0.95rem; color: var(--text-main);
        }
        
        .progress-custom { height: 6px; background: var(--border-color); border-radius: 3px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 3px; }

        .clock-time { font-size: 1.5rem; font-weight: 700; color: var(--accent-blue); line-height: 1; }
        .clock-date { font-size: 0.8rem; color: var(--text-muted); text-align: right; }

        .col-logistics { display: flex; flex-direction: column; gap: 0.5rem; height: 100%; overflow-y: auto; }
        .supply-item { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding: 0.6rem 0; color: var(--text-main); }
        .supply-val { font-size: 1.1rem; font-weight: 700; }

        /* Movement Box Styles */
        .movement-box {
            background: rgba(255,255,255,0.03);
            border-radius: 0.5rem;
            padding: 0.8rem 0.5rem;
            text-align: center;
            flex: 1;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s;
        }
        .movement-box:hover { transform: translateY(-2px); }
        .move-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem; }
        .move-val { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        .val-in { color: var(--accent-green); }
        .val-out { color: var(--accent-red); }

    </style>
</head>
<body>

    <!-- Header -->
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 p-2 rounded">
                <i class="bi bi-display fs-4" style="color: var(--accent-blue);"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 text-main"><?php echo $title_text; ?></div>
                <div class="text-muted small"><?php echo $sub_title; ?></div>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="event-tag">
                <div class="live-dot"></div> <?php echo $current_event; ?>
            </div>
            
            <div class="text-end me-3">
                <div class="clock-time" id="clock">00:00</div>
                <div class="clock-date"><?php echo date('d M Y'); ?></div>
            </div>

            <!-- Theme Switcher -->
            <button class="theme-toggle" onclick="toggleTheme()" title="Switch Theme">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        
        <!-- Row 1: KPIs -->
        <div class="row-kpi">
            <div class="kpi-card c-blue">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-title">ยอดผู้พักพิง</div>
                    <i class="bi bi-people-fill text-muted fs-5"></i>
                </div>
                <div class="kpi-number"><?php echo number_format($total_evacuees); ?></div>
                <div class="kpi-sub">คน (พักอาศัยปัจจุบัน)</div>
            </div>

            <div class="kpi-card c-green">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-title"><?php echo ($mode=='SINGLE') ? 'ที่นั่งว่าง' : 'ศูนย์ที่เปิด'; ?></div>
                    <i class="bi bi-house-check-fill text-muted fs-5"></i>
                </div>
                <div class="kpi-number" style="color: var(--accent-green);">
                    <?php echo ($mode=='SINGLE') ? number_format(max(0, $total_capacity - $total_evacuees)) : $total_shelters; ?>
                </div>
                <div class="kpi-sub"><?php echo ($mode=='SINGLE') ? 'จากความจุ '.$total_capacity : 'แห่งทั่วจังหวัด'; ?></div>
            </div>

            <div class="kpi-card c-yellow">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-title">ความหนาแน่น</div>
                    <i class="bi bi-pie-chart-fill text-muted fs-5"></i>
                </div>
                <div class="kpi-number" style="color: var(--accent-yellow);">
                    <?php echo ($total_capacity > 0) ? round(($total_evacuees / $total_capacity) * 100) : 0; ?><small class="fs-5">%</small>
                </div>
                <div class="kpi-sub">อัตราการใช้พื้นที่</div>
            </div>

            <div class="kpi-card c-red">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-title">กลุ่มเปราะบาง</div>
                    <i class="bi bi-heart-pulse-fill text-muted fs-5"></i>
                </div>
                <div class="kpi-number" style="color: var(--accent-red);">
                    <?php echo number_format($vul['bedridden'] + $vul['disabled'] + $vul['pregnant'] + $vul['elderly']); ?>
                </div>
                <div class="kpi-sub">คน (ต้องการดูแลพิเศษ)</div>
            </div>
        </div>

        <!-- Row 2: Content -->
        <div class="row-content">
            
            <!-- Left: Movement & Gender -->
            <div class="content-card">
                <div style="flex: 1; display: flex; flex-direction: column; gap: 0.5rem;">
                    
                    <!-- 1. Movement Today -->
                    <div style="flex-shrink: 0;">
                        <div class="section-header" style="color: var(--accent-green); margin-bottom: 0.5rem;">
                            <i class="bi bi-arrow-left-right"></i> ความเคลื่อนไหววันนี้
                        </div>
                        <div class="d-flex gap-2">
                            <div class="movement-box" style="border-color: rgba(52, 211, 153, 0.3);">
                                <div class="move-label text-uppercase">รับเข้าใหม่</div>
                                <div class="move-val val-in">+<?php echo number_format($movement['in_today']); ?></div>
                            </div>
                            <div class="movement-box" style="border-color: rgba(248, 113, 113, 0.3);">
                                <div class="move-label text-uppercase">กลับบ้าน/ย้าย</div>
                                <div class="move-val val-out">-<?php echo number_format($movement['out_today']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Gender Chart + Stats -->
                    <div style="flex: 1; display: flex; flex-direction: column; border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.5rem;">
                        <div class="section-header" style="color: var(--accent-blue); margin-bottom: 0.5rem;">
                            <i class="bi bi-gender-ambiguous"></i> สัดส่วนชาย/หญิง
                        </div>
                        <div style="flex: 1; position: relative;">
                            <canvas id="genderChart"></canvas>
                        </div>
                         <!-- Gender Numbers -->
                        <div class="row text-center mt-2 g-0" style="border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                            <div class="col-6 border-end" style="border-color: var(--border-color) !important;">
                                <div class="fw-bold fs-4" style="color: var(--accent-blue); line-height:1;"><?php echo number_format($gender['male']); ?></div>
                                <div class="small" style="color: var(--text-muted);">ชาย</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold fs-4" style="color: var(--accent-pink); line-height:1;"><?php echo number_format($gender['female']); ?></div>
                                <div class="small" style="color: var(--text-muted);">หญิง</div>
                            </div>
                        </div>
                    </div>

                     <!-- 3. Vulnerable Chart (Compressed or Removed if too tight) -->
                     <!-- We can keep it but maybe as a small bar below or skip if space is tight. 
                          Since we have space, let's put it at the bottom. -->
                    <div style="flex: 1; display: flex; flex-direction: column; border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.5rem;">
                         <div class="section-header" style="color: var(--accent-red); margin-bottom: 0.5rem;">
                            <i class="bi bi-activity"></i> กลุ่มเปราะบาง
                        </div>
                        <div style="flex: 1; position: relative;">
                            <canvas id="vulChart"></canvas>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right: Table / Details -->
            <div class="content-card">
                <?php if($mode == 'GLOBAL'): ?>
                    <div class="section-header" style="color: var(--accent-green);">
                        <i class="bi bi-list-check"></i> สถานะศูนย์พักพิงชั่วคราว (8 อันดับแรก)
                        <span class="ms-auto badge bg-opacity-10 fw-normal" style="background: var(--border-color); color: var(--text-main); font-size: 0.7rem;">Real-time</span>
                    </div>
                    <div class="table-wrapper">
                        <table class="table-custom">
                            <thead><tr><th>ชื่อศูนย์</th><th>อำเภอ</th><th class="text-center">ยอดคน</th><th>ความหนาแน่น</th><th class="text-end">อัปเดต</th></tr></thead>
                            <tbody>
                                <?php foreach($shelter_list as $row): 
                                    $percent = ($row['capacity'] > 0) ? ($row['current_stay'] / $row['capacity']) * 100 : 0;
                                    $is_today = (substr($row['last_updated'], 0, 10) == $today);
                                    $bar_bg = ($percent > 90) ? '#f87171' : (($percent > 70) ? '#fbbf24' : '#34d399');
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $row['name']; ?></td>
                                    <td style="color: var(--text-muted);"><?php echo $row['district']; ?></td>
                                    <td class="text-center fs-5 fw-bold" style="color: var(--accent-blue);"><?php echo number_format($row['current_stay']); ?></td>
                                    <td width="25%">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-custom flex-grow-1"><div class="progress-bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $bar_bg; ?>;"></div></div>
                                            <small style="width: 35px; text-align:right; color: var(--text-muted);"><?php echo number_format($percent); ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-end small">
                                        <?php if($is_today): ?><i class="bi bi-check2 text-success"></i> <?php echo date('H:i', strtotime($row['last_updated'])); ?>
                                        <?php else: ?><span class="text-danger">ล่าช้า</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- SINGLE MODE -->
                    <div class="row h-100 g-3">
                        <div class="col-6 col-logistics border-end border-opacity-10 pe-3" style="border-color: var(--border-color) !important;">
                            <div class="section-header" style="color: var(--accent-yellow);"><i class="bi bi-basket"></i> เสบียงและของใช้</div>
                            <div class="supply-item"><span>อาหาร (3 มื้อ)</span><span class="supply-val" style="color: var(--accent-yellow);"><?php echo number_format($food_needed); ?></span></div>
                            <div class="supply-item"><span>น้ำดื่ม (ลิตร)</span><span class="supply-val" style="color: var(--accent-blue);"><?php echo number_format($water_needed); ?></span></div>
                            <div class="supply-item"><span>ผ้าห่ม</span><span class="supply-val"><?php echo number_format($blankets_needed); ?></span></div>
                            <div class="supply-item"><span>มุ้ง</span><span class="supply-val"><?php echo number_format($mosquito_nets); ?></span></div>
                            <div class="supply-item"><span>ชุดสุขอนามัย</span><span class="supply-val"><?php echo number_format($hygiene_kits); ?></span></div>
                        </div>
                        <div class="col-6 col-logistics ps-3">
                            <div class="section-header" style="color: var(--accent-red);"><i class="bi bi-heart-pulse"></i> เวชภัณฑ์เฉพาะทาง</div>
                            <div class="supply-item"><span>นมผงเด็กเล็ก</span><span class="supply-val"><?php echo number_format($vul['infants']); ?></span></div>
                            <div class="supply-item"><span>แพมเพิสผู้ใหญ่</span><span class="supply-val" style="color: var(--accent-red);"><?php echo number_format($vul['bedridden'] + $vul['elderly']); ?></span></div>
                            
                            <div class="mt-auto pt-3 text-center">
                                <div class="p-2 rounded d-inline-block" style="background: white;">
                                    <?php 
                                        $public_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace('monitor_dashboard.php', 'public_dashboard.php', $_SERVER['REQUEST_URI']);
                                    ?>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($public_url); ?>" width="90">
                                </div>
                                <div class="small mt-1" style="color: var(--text-muted);">สแกนดูผ่านมือถือ</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script>
        function updateClock() { document.getElementById('clock').innerText = new Date().toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'}); }
        setInterval(updateClock, 1000); updateClock();
        
        let currentTheme = localStorage.getItem('theme') || 'dark';
        const htmlEl = document.documentElement;
        const iconEl = document.getElementById('themeIcon');
        
        function applyTheme(theme) {
            htmlEl.setAttribute('data-theme', theme);
            iconEl.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
            
            if(window.genderChart && window.vulChart) {
                const textColor = theme === 'dark' ? '#94a3b8' : '#64748b';
                const gridColor = theme === 'dark' ? '#334155' : '#e2e8f0';
                Chart.defaults.color = textColor;
                window.vulChart.options.scales.x.grid.color = gridColor;
                window.genderChart.update();
                window.vulChart.update();
            }
        }

        function toggleTheme() {
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', currentTheme);
            applyTheme(currentTheme);
        }
        applyTheme(currentTheme);
        setTimeout(() => window.location.reload(), 60000);

        Chart.defaults.color = '#94a3b8'; Chart.defaults.font.family = 'Sarabun'; Chart.defaults.font.size = 11;

        if(document.getElementById('genderChart')) {
            window.genderChart = new Chart(document.getElementById('genderChart'), {
                type: 'doughnut',
                data: {
                    labels: ['ชาย', 'หญิง'],
                    datasets: [{ data: [<?php echo $gender['male']; ?>, <?php echo $gender['female']; ?>], backgroundColor: ['#38bdf8', '#ec4899'], borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '70%' }
            });
        }

        if(document.getElementById('vulChart')) {
            window.vulChart = new Chart(document.getElementById('vulChart'), {
                type: 'bar',
                data: {
                    labels: ['สูงอายุ', 'เด็ก', 'พิการ', 'ติดเตียง', 'ครรภ์'],
                    datasets: [{ data: [<?php echo $vul['elderly']; ?>, <?php echo $vul['infants']; ?>, <?php echo $vul['disabled']; ?>, <?php echo $vul['bedridden']; ?>, <?php echo $vul['pregnant']; ?>], backgroundColor: ['#fbbf24', '#34d399', '#f87171', '#ef4444', '#60a5fa'], borderRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { grid: { display: false } } } }
            });
        }
    </script>
</body>
</html>