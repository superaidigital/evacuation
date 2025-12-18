<?php
// import_process.php
require_once 'config/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

date_default_timezone_set('Asia/Bangkok'); 

// --- Helper Functions ---

function cleanCitizenId($id) {
    if (empty($id)) return ''; 
    $id = preg_replace('/[^0-9]/', '', $id); 
    return $id;
}

function cleanLocation($text) {
    $text = trim($text);
    $text = str_replace(['อำเภอ', 'จังหวัด', 'จ.', 'อ.', 'ต.', 'ตำบล'], '', $text); 
    return trim($text);
}

function thaiMonthToNumber($str) {
    $thai_months = [
        'ม.ค.'=>'01', 'ก.พ.'=>'02', 'มี.ค.'=>'03', 'เม.ย.'=>'04', 'พ.ค.'=>'05', 'มิ.ย.'=>'06',
        'ก.ค.'=>'07', 'ส.ค.'=>'08', 'ก.ย.'=>'09', 'ต.ค.'=>'10', 'พ.ย.'=>'11', 'ธ.ค.'=>'12',
        'มกราคม'=>'01', 'กุมภาพันธ์'=>'02', 'มีนาคม'=>'03', 'เมษายน'=>'04', 'พฤษภาคม'=>'05', 'มิถุนายน'=>'06',
        'กรกฎาคม'=>'07', 'สิงหาคม'=>'08', 'กันยายน'=>'09', 'ตุลาคม'=>'10', 'พฤศจิกายน'=>'11', 'ธันวาคม'=>'12'
    ];
    return $thai_months[$str] ?? null;
}

function parseDateSmart($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr) || in_array($dateStr, ['-', 'ไม่มี', ''])) return null;

    if (preg_match('/^([0-9]{1,2})[-|\/|\s]([ก-๙.]{2,})[-|\/|\s]([0-9]{2,4})$/u', $dateStr, $matches)) {
        $d = $matches[1];
        $m_str = $matches[2];
        $y = $matches[3];
        $m = thaiMonthToNumber($m_str);
        if ($m) {
            if (strlen($y) == 2) $y = "25" . $y; 
            if ($y > 2400) $y -= 543;
            return sprintf("%04d-%02d-%02d", $y, $m, $d);
        }
    }

    $parts = preg_split('/[\/\-\.\s]/', $dateStr);
    if (count($parts) == 3) {
        $p1 = (int)$parts[0]; $p2 = (int)$parts[1]; $p3 = (int)$parts[2];
        $day = 0; $month = 0; $year = 0;
        if ($p1 > 1000) { $year = $p1; $month = $p2; $day = $p3; }
        else { $day = $p1; $month = $p2; $year = $p3; }
        if (strlen((string)$year) == 2) $year += 2000;
        if ($year > 2400) $year -= 543;
        if (checkdate($month, $day, $year)) {
            return sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
    }
    return null;
}

function parseDateCheckIn($d) {
    $res = parseDateSmart($d);
    return $res ? $res : date('Y-m-d');
}

function parseDateCheckOut($d) {
    return parseDateSmart($d);
}

// --- Main Process ---

if (isset($_POST['submit'])) {
    
    $fileName = $_FILES['csv_file']['tmp_name'];

    // [Fix Warning] ประกาศตัวแปรทั้งหมดไว้ก่อนเริ่มทำงาน
    $current_event = '';
    $cnt_new = 0;
    $cnt_update = 0;
    $cnt_transfer = 0;
    $cnt_reactivate = 0;
    $cnt_fail = 0;
    
    $shelter_cache = []; 
    $failed_rows = [];

    // ดึงชื่อเหตุการณ์ปัจจุบัน
    $stmt_event = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_event'");
    $stmt_event->execute();
    $current_event = $stmt_event->fetchColumn();

    if ($_FILES['csv_file']['size'] > 0) {
        
        $file = fopen($fileName, "r");
        
        // อ่าน Header และเตรียม Error Log Header
        $header = fgetcsv($file); 
        if ($header) {
            $error_header = $header;
            $error_header[] = "สาเหตุ (System Message)"; 
            $failed_rows[] = $error_header;
        }

        // --- SQL Statements ---
        $stmt_find_by_id = $pdo->prepare("SELECT id, shelter_id, check_out_date FROM evacuees WHERE citizen_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_find_by_name = $pdo->prepare("SELECT id, shelter_id, check_out_date, citizen_id FROM evacuees WHERE first_name = ? AND last_name = ? ORDER BY id DESC LIMIT 1");

        $stmt_checkout = $pdo->prepare("UPDATE evacuees SET check_out_date = ?, check_out_reason = ? WHERE id = ?");
        $stmt_reactivate = $pdo->prepare("UPDATE evacuees SET check_out_date = NULL, check_out_reason = NULL WHERE id = ?");
        $stmt_update_info = $pdo->prepare("UPDATE evacuees SET prefix=?, age=?, health_condition=?, phone=?, house_no=?, village_no=?, subdistrict=?, district=?, province=? WHERE id=?");

        $stmt_insert = $pdo->prepare("INSERT INTO evacuees (prefix, first_name, last_name, citizen_id, age, health_condition, house_no, village_no, subdistrict, district, province, phone, check_in_date, shelter_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_find_shelter = $pdo->prepare("SELECT id FROM shelters WHERE name = ? LIMIT 1");
        $stmt_update_time = $pdo->prepare("UPDATE shelters SET last_updated = NOW() WHERE id = ?");

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            
            if (count($column) < 5) continue;

            // --- 1. เตรียมข้อมูล ---
            $prefix = trim($column[1] ?? '');
            $first_name = trim($column[2] ?? '');
            $last_name = trim($column[3] ?? '');
            $raw_id = $column[4] ?? '';
            $age = (int)($column[5] ?? 0);
            
            // Health Mapping
            $health_raw = $column[6] ?? '';
            $health_condition = 'ไม่มี';
            if (strpos($health_raw, 'สูงอายุ') !== false) $health_condition = 'ผู้สูงอายุ';
            elseif (strpos($health_raw, 'พิการ') !== false) $health_condition = 'ผู้พิการ';
            elseif (strpos($health_raw, 'ติดเตียง') !== false) $health_condition = 'ผู้ป่วยติดเตียง';
            elseif (strpos($health_raw, 'ครรภ์') !== false) $health_condition = 'หญิงตั้งครรภ์';
            elseif (strpos($health_raw, 'ไต') !== false) $health_condition = 'ผู้ป่วยไตวาย';
            elseif (strpos($health_raw, 'เบาหวาน') !== false) $health_condition = 'โรคเบาหวาน';
            elseif (strpos($health_raw, 'ความดัน') !== false) $health_condition = 'โรคความดันโลหิตสูง';
            elseif (strpos($health_raw, 'หัวใจ') !== false) $health_condition = 'โรคหัวใจ';

            $house_no    = trim($column[7] ?? '');
            $village_no  = trim($column[8] ?? '');
            $subdistrict = cleanLocation($column[9] ?? '');
            $district    = cleanLocation($column[10] ?? '');
            $province    = cleanLocation($column[11] ?? '');
            if(empty($province)) $province = 'ศรีสะเกษ';
            $phone = preg_replace('/[^0-9]/', '', $column[13] ?? '');

            // Dates
            $check_in_date = parseDateCheckIn($column[14] ?? '');
            $check_out_date = parseDateCheckOut($column[15] ?? '');

            // --- 2. Identity Resolution ---
            $citizen_id = cleanCitizenId($raw_id);
            $search_mode = 'ID';
            
            // ตรวจสอบเลขบัตรผิดปกติ (E+ หรือ <13 หลัก)
            if (empty($citizen_id) || strlen($citizen_id) < 13 || stripos($raw_id, 'E+') !== false) {
                $search_mode = 'NAME';
                
                if (empty($first_name) || empty($last_name)) {
                    $cnt_fail++; // [Fix Warning] ใช้ตัวแปรที่ประกาศแล้ว
                    $column[] = "เลขบัตรเสียหาย และไม่มีชื่อ-สกุล";
                    $failed_rows[] = $column;
                    continue;
                }
                
                $citizen_id = "NOID-" . substr(md5($first_name.$last_name), 0, 10); 
            }

            // --- 3. Shelter Determination ---
            $csv_shelter_name = trim($column[12] ?? ''); 
            $target_shelter_id = 0; 

            if (empty($csv_shelter_name)) {
                $cnt_fail++;
                $column[] = "ไม่ระบุชื่อศูนย์ในไฟล์ CSV (Column 12)";
                $failed_rows[] = $column;
                continue;
            }

            if (isset($shelter_cache[$csv_shelter_name])) {
                $target_shelter_id = $shelter_cache[$csv_shelter_name];
            } else {
                $stmt_find_shelter->execute([$csv_shelter_name]);
                $sid = $stmt_find_shelter->fetchColumn();
                
                if ($sid) {
                    $target_shelter_id = $sid;
                    $shelter_cache[$csv_shelter_name] = $sid;
                } else {
                    $cnt_fail++;
                    $column[] = "ไม่พบศูนย์ชื่อ '$csv_shelter_name' ในระบบ";
                    $failed_rows[] = $column;
                    continue;
                }
            }

            // --- 4. Core Logic ---
            try {
                $existing = false;
                
                if ($search_mode == 'ID') {
                    $stmt_find_by_id->execute([$citizen_id]);
                    $existing = $stmt_find_by_id->fetch(PDO::FETCH_ASSOC);
                } else {
                    $stmt_find_by_name->execute([$first_name, $last_name]);
                    $existing = $stmt_find_by_name->fetch(PDO::FETCH_ASSOC);
                }

                if ($existing) {
                    // เจอข้อมูลเก่า
                    $old_id = $existing['id'];
                    $old_shelter = $existing['shelter_id'];
                    $is_active = is_null($existing['check_out_date']);

                    if ($old_shelter == $target_shelter_id) {
                        // A. อยู่ศูนย์เดิม (ซ้ำ) -> Update Info
                        $stmt_update_info->execute([$prefix, $age, $health_condition, $phone, $house_no, $village_no, $subdistrict, $district, $province, $old_id]);
                        
                        if ($is_active && !empty($check_out_date)) {
                            $stmt_checkout->execute([$check_out_date, 'อัปเดตจากไฟล์', $old_id]);
                        } elseif (!$is_active && empty($check_out_date)) {
                            $stmt_reactivate->execute([$old_id]);
                        }
                        $cnt_update++; // [Fix Warning]
                        
                    } else {
                        // B. ย้ายศูนย์ (Transfer)
                        if ($is_active) {
                            $move_date = !empty($check_in_date) ? $check_in_date : date('Y-m-d');
                            $stmt_checkout->execute([$move_date, 'ย้ายศูนย์ (Auto Import)', $old_id]);
                            $stmt_update_time->execute([$old_shelter]);
                        }
                        
                        $final_insert_id = (!empty($existing['citizen_id'])) ? $existing['citizen_id'] : $citizen_id;

                        $stmt_insert->execute([$prefix, $first_name, $last_name, $final_insert_id, $age, $health_condition, $house_no, $village_no, $subdistrict, $district, $province, $phone, $check_in_date, $target_shelter_id]);
                        $cnt_transfer++; // [Fix Warning]
                    }
                } else {
                    // C. คนใหม่ (New)
                    $stmt_insert->execute([$prefix, $first_name, $last_name, $citizen_id, $age, $health_condition, $house_no, $village_no, $subdistrict, $district, $province, $phone, $check_in_date, $target_shelter_id]);
                    
                    if (!empty($check_out_date)) {
                        $lid = $pdo->lastInsertId();
                        $stmt_checkout->execute([$check_out_date, 'นำเข้าประวัติ', $lid]);
                    }
                    $cnt_new++; // [Fix Warning]
                }
                
                $stmt_update_time->execute([$target_shelter_id]);

            } catch (Exception $e) { 
                $cnt_fail++;
                $column[] = "DB Error: " . $e->getMessage();
                $failed_rows[] = $column;
            }
        }
        
        fclose($file);
        
        // --- Result Display ---
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<body></body>"; 
        
        $msg_html = "<div style='text-align:left; font-size:0.9rem;'>";
        $msg_html .= "<b style='color:green'>เพิ่มคนใหม่: $cnt_new</b><br>";
        $msg_html .= "<b style='color:#0d6efd'>อัปเดตข้อมูลเดิม: $cnt_update</b><br>";
        $msg_html .= "<b style='color:purple'>ย้ายศูนย์/รับเข้าใหม่: $cnt_transfer</b><br>";
        if ($cnt_fail > 0) $msg_html .= "<b style='color:red'>ผิดพลาด: $cnt_fail</b>";
        $msg_html .= "</div>";

        if ($cnt_fail > 0) {
            $_SESSION['import_errors'] = $failed_rows;
            echo "<script>
                Swal.fire({
                    title: 'ผลการนำเข้าข้อมูล',
                    html: `$msg_html`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'โหลดไฟล์ข้อผิดพลาด',
                    cancelButtonText: 'กลับหน้าหลัก'
                }).then((r) => {
                    if (r.isConfirmed) window.location.href = 'download_errors.php';
                    else window.location.href = 'index.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'นำเข้าสำเร็จ',
                    html: `$msg_html`,
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then(() => window.location.href = 'index.php');
            </script>";
        }
    } else {
        echo "<script>alert('กรุณาเลือกไฟล์'); window.history.back();</script>";
    }
}
?>