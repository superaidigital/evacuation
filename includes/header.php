<?php 
// includes/header.php

// 1. เริ่ม Session หากยังไม่มี
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. ตรวจสอบการ Login (ถ้ายังไม่ Login และไม่ได้อยู่ที่หน้า login.php ให้ดีดออก)
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: login.php");
    exit();
}

// 3. ดึงชื่อไฟล์ปัจจุบันเพื่อทำ Active Menu Highlight
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการข้อมูลผู้พักพิง</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Sarabun) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Select2 CSS (สำหรับ Dropdown ค้นหาได้) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Custom CSS (Theme Modern) -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Custom Style สำหรับ Select2 ให้เข้ากับ Bootstrap theme -->
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
            color: #212529;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 10px;
        }
        /* Mobile Overlay */
        .sidebar-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1040;
            opacity: 0; visibility: hidden; transition: all 0.3s;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
    
    <!-- ฉากหลังสำหรับมือถือ (คลิกเพื่อปิดเมนู) -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <!-- Header: Logo -->
        <div class="sidebar-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check fs-3 text-primary"></i>
                <div>
                    <h5 class="fw-bold mb-0 text-white">ระบบศูนย์พักพิง</h5>
                    <small class="text-secondary" style="font-size: 0.75rem;">Disaster Management System</small>
                </div>
            </div>
        </div>

        <!-- Scrollable Menu Area -->
        <div class="sidebar-menu">
            
            <!-- User Profile Section -->
            <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-10">
                <div class="rounded-circle bg-gradient-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; flex-shrink: 0;">
                    <i class="bi bi-person-fill fs-5"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-white fw-bold text-truncate" style="max-width: 140px; font-size: 0.95rem;">
                        <?php echo $_SESSION['fullname']; ?>
                    </div>
                    <div class="text-secondary small d-flex align-items-center gap-1">
                        <span class="badge bg-<?php echo $_SESSION['role'] == 'ADMIN' ? 'danger' : 'success'; ?> bg-opacity-25 text-white" style="font-size: 0.65rem;">
                            <?php echo $_SESSION['role']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Menu Items -->
            <ul class="nav flex-column">
                
                <!-- 1. เมนูค้นหา (เลขบัตร) - ไว้บนสุดเพื่อความสะดวก -->
                <li class="nav-item mb-3">
                    <a class="nav-link <?php echo ($current_page == 'search_evacuee.php') ? 'active' : ''; ?>" 
                       href="search_evacuee.php" 
                       style="background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff;">
                        <i class="bi bi-search text-warning"></i> <span class="fw-bold">ค้นหา (เลขบัตร)</span>
                    </a>
                </li>

                <!-- 2. Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i> ภาพรวมสถานการณ์
                    </a>
                </li>
                
                <?php if($_SESSION['role'] == 'ADMIN'): ?>
                    <!-- === ส่วนของ ADMIN === -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'shelter_list.php' || $current_page == 'shelter_form.php') ? 'active' : ''; ?>" href="shelter_list.php">
                            <i class="bi bi-house-door-fill"></i> จัดการศูนย์พักพิงชั่วคราว
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'caretaker_list.php' || $current_page == 'caretaker_form.php') ? 'active' : ''; ?>" href="caretaker_list.php">
                            <i class="bi bi-person-badge-fill"></i> ข้อมูลผู้ดูแลศูนย์
                        </a>
                    </li>
                <?php else: ?>
                    <!-- === ส่วนของ STAFF === -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'evacuee_list.php' || $current_page == 'evacuee_form.php') ? 'active' : ''; ?>" 
                           href="evacuee_list.php?shelter_id=<?php echo $_SESSION['shelter_id']; ?>">
                            <i class="bi bi-people-fill"></i> ทะเบียนผู้พักพิง
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'report_shelter.php') ? 'active' : ''; ?>" href="report_shelter.php">
                            <i class="bi bi-clipboard-data-fill"></i> รายงานศูนย์ของฉัน
                        </a>
                    </li>
                <?php endif; ?>

                <!-- 3. ส่วนทั่วไป -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'import_csv.php') ? 'active' : ''; ?>" href="import_csv.php">
                        <i class="bi bi-cloud-arrow-up-fill"></i> นำเข้าข้อมูล (CSV)
                    </a>
                </li>

                <!-- 4. ส่วนรายงานกลาง -->
                <li class="nav-item mt-4">
                    <div class="text-uppercase text-secondary small fw-bold px-3 mb-2 letter-spacing-1">รายงานส่วนกลาง</div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'report.php') ? 'active' : ''; ?>" href="report.php">
                        <i class="bi bi-file-earmark-bar-graph-fill"></i> สรุปยอดรายวัน
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'report_export.php') ? 'active' : ''; ?>" href="report_export.php">
                        <i class="bi bi-cloud-download-fill"></i> ดาวน์โหลดข้อมูล (Excel)
                    </a>
                </li>
            </ul>
        </div>

        <!-- Footer: Logout Button -->
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link text-danger d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-left"></i> ออกจากระบบ
            </a>
        </div>
    </nav>

    <!-- Mobile Toggle Button (Visible only on mobile) -->
    <button class="btn btn-primary d-md-none position-fixed top-0 start-0 m-3 z-3 shadow rounded-circle d-flex align-items-center justify-content-center" 
            style="width:45px; height:45px;"
            onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Main Content Wrapper Starts Here -->
    <div class="main-content">
<?php endif; ?>