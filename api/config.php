<?php
/**
 * Database Configuration & System Helper Functions
 * Project: HR Management System (HR GO)
 */

// ตั้งค่า Timezone ของระบบให้เป็นประเทศไทย (Asia/Bangkok UTC+7)
date_default_timezone_set('Asia/Bangkok');

// เริ่มการใช้งาน Session สำหรับระบบ Login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// 1. ตั้งค่าการเชื่อมต่อ Database (PDO)
// -------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'hrgo');
define('DB_USER', 'root');
define('DB_PASS', '');

// ตั้งค่าสำหรับ IP Verification (จำกัด IP ในออฟฟิศ)
define('ENABLE_IP_CHECK', false); // ตั้งค่าเป็น false เพื่อให้ทดสอบบน Local Server ได้ง่าย
define('ALLOWED_IP_PREFIXES', ['127.0.0.1', '::1', '192.168.']); // IP Loopback หรือ LAN ออฟฟิศ

// ตั้งค่าพิกัด GPS ออฟฟิศบริษัท (Latitude / Longitude) และรัศมีอนุญาต (เมตร)
define('ENABLE_LOCATION_CHECK', true);   // เปิดการตรวจสอบพิกัด GPS
define('COMPANY_LAT', 13.60238935151);        // ละติจูดบริษัท (ตั้งค่าตัวอย่าง กรุงเทพฯ หรือพิกัดที่ต้องการ)
define('COMPANY_LNG', 100.27052180362);       // ลองจิจูดบริษัท
define('MAX_DISTANCE_METERS', 300);      // รัศมีอนุญาตให้ลงเวลาได้ (เช่น 300 เมตร)

/**
 * คำนวณระยะทางระหว่างพิกัด 2 จุดด้วย Haversine Formula (คืนค่าเป็นเมตร)
 */
function calculateDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // รัศมีโลกในหน่วยเมตร
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c);
}

/**
 * ดึงค่าตั้งค่าระบบแบบ Dynamic จากตาราง system_settings
 */
function getSystemSettings() {
    static $settings = null;
    if ($settings === null) {
        $defaults = [
            'company_lat'           => COMPANY_LAT,
            'company_lng'           => COMPANY_LNG,
            'max_distance_meters'   => MAX_DISTANCE_METERS,
            'enable_location_check' => ENABLE_LOCATION_CHECK ? '1' : '0',
            'enable_ip_check'       => ENABLE_IP_CHECK ? '1' : '0'
        ];
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // ใช้ค่า constant สำรองกรณีเกิดข้อผิดพลาด
        }
        $settings = [
            'company_lat'           => (float)$defaults['company_lat'],
            'company_lng'           => (float)$defaults['company_lng'],
            'max_distance_meters'   => (int)$defaults['max_distance_meters'],
            'enable_location_check' => $defaults['enable_location_check'] === '1' || $defaults['enable_location_check'] === 'true',
            'enable_ip_check'       => $defaults['enable_ip_check'] === '1' || $defaults['enable_ip_check'] === 'true'
        ];
    }
    return $settings;
}

/**
 * เชื่อมต่อ Database ด้วย PDO (พร้อม Prepared Statements)
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // บังคับใช้ Native Prepared Statements เพื่อป้องกัน SQL Injection
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4");
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage(), null, 500);
            exit;
        }
    }
    return $pdo;
}

// -------------------------------------------------------------
// 2. Helper Functions สำหรับ JSON Response & Auth Protection
// -------------------------------------------------------------

/**
 * ส่งออก Response ในรูปแบบ JSON
 * @param bool $success
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 */
function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ตรวจสอบสิทธิ์การเข้าใช้งาน API (Session & Role Base Access Control)
 * @param array $allowedRoles รายชื่อ Role ที่อนุญาต เช่น ['employee', 'manager', 'admin']
 * @return array ข้อมูล User ที่ล็อกอินอยู่
 */
function requireAuth(array $allowedRoles = []) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        sendJsonResponse(false, 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)', null, 401);
    }

    $currentUser = [
        'user_id'  => $_SESSION['user_id'],
        'emp_code' => $_SESSION['emp_code'] ?? '',
        'name'     => $_SESSION['name'] ?? '',
        'role'     => $_SESSION['role'] ?? 'employee',
        'dept_id'  => $_SESSION['dept_id'] ?? null
    ];

    if (!empty($allowedRoles) && !in_array($currentUser['role'], $allowedRoles)) {
        sendJsonResponse(false, 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลส่วนนี้ (Forbidden)', null, 403);
    }

    return $currentUser;
}

/**
 * ดึง IP Address ของผู้ใช้งานปัจจุบัน
 * @return string
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // ในกรณีผ่าน Proxy
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * ตรวจสอบว่า IP อยู่ในเครือข่ายออฟฟิศที่อนุญาตหรือไม่
 * @param string $ip
 * @return bool
 */
function isAllowedOfficeIP(string $ip): bool {
    if (!ENABLE_IP_CHECK) {
        return true; // ถ้าปิดระบบตรวจ IP ให้ผ่านได้ตลอด
    }

    foreach (ALLOWED_IP_PREFIXES as $prefix) {
        if (str_starts_with($ip, $prefix)) {
            return true;
        }
    }
    return false;
}

/**
 * แปลงนาทีสายให้อ่านง่าย (เช่น 45 นาที -> สาย (45 นาที), 137 นาที -> สาย (2 ชม. 17 นาที), 120 นาที -> สาย (2 ชม.))
 * @param int $mins
 * @return string
 */
function formatLateText($mins) {
    $mins = (int)$mins;
    if ($mins <= 0) return 'สาย';
    if ($mins < 60) {
        return "สาย ({$mins} นาที)";
    }
    $hours = (int)floor($mins / 60);
    $remMins = $mins % 60;
    if ($remMins > 0) {
        return "สาย ({$hours} ชม. {$remMins} นาที)";
    } else {
        return "สาย ({$hours} ชม.)";
    }
}

/**
 * ดึงข้อมูลกะการทำงาน (Shift) ของพนักงานในวันที่กำหนด
 * 1. ตรวจสอบตาราง shift_rosters ก่อน
 * 2. หากไม่มีใน shift_rosters ให้ดึงจากข้อมูลประจำตัวพนักงานในตาราง users
 * @return array ['shift_type' => 'day'|'night'|'off', 'shift_start_time' => '08:00:00', 'shift_end_time' => '17:00:00', 'is_roster' => bool, 'is_off' => bool]
 */
function getUserShiftForDate($pdo, $userId, $workDate) {
    if (!$pdo || !$userId || !$workDate) {
        return [
            'shift_type'       => 'day',
            'shift_start_time' => '08:00:00',
            'shift_end_time'   => '17:00:00',
            'is_roster'        => false,
            'is_off'           => false
        ];
    }

    try {
        // 1. ลองดึงจากตาราง shift_rosters
        $stmtRoster = $pdo->prepare("
            SELECT shift_type, shift_start_time, shift_end_time 
            FROM shift_rosters 
            WHERE user_id = :uid AND roster_date = :rdate 
            LIMIT 1
        ");
        $stmtRoster->execute([':uid' => $userId, ':rdate' => $workDate]);
        $roster = $stmtRoster->fetch();

        if ($roster) {
            $sType = $roster['shift_type'];
            $isOff = ($sType === 'off');
            $sStart = $roster['shift_start_time'] ?? ($sType === 'night' ? '20:00:00' : '08:00:00');
            $sEnd   = $roster['shift_end_time'] ?? ($sType === 'night' ? '05:00:00' : '17:00:00');

            return [
                'shift_type'       => $sType,
                'shift_start_time' => $sStart,
                'shift_end_time'   => $sEnd,
                'is_roster'        => true,
                'is_off'           => $isOff
            ];
        }
    } catch (Exception $e) {
        // Fallback to user default
    }

    // 2. ถ้าไม่มีใน shift_rosters ให้ดึงค่ากะประจำตัวจากตาราง users
    try {
        $stmtUser = $pdo->prepare("SELECT shift_type, shift_start_time, shift_end_time FROM users WHERE user_id = :uid LIMIT 1");
        $stmtUser->execute([':uid' => $userId]);
        $user = $stmtUser->fetch();

        $sType  = $user['shift_type'] ?? 'day';
        $sStart = $user['shift_start_time'] ?? ($sType === 'night' ? '20:00:00' : '08:00:00');
        $sEnd   = $user['shift_end_time'] ?? ($sType === 'night' ? '05:00:00' : '17:00:00');

        return [
            'shift_type'       => $sType,
            'shift_start_time' => $sStart,
            'shift_end_time'   => $sEnd,
            'is_roster'        => false,
            'is_off'           => false
        ];
    } catch (Exception $e) {
        return [
            'shift_type'       => 'day',
            'shift_start_time' => '08:00:00',
            'shift_end_time'   => '17:00:00',
            'is_roster'        => false,
            'is_off'           => false
        ];
    }
}

/**
 * คำนวณชั่วโมงทำงาน (work_hours) และ OT (ot_hours)
 * - วันธรรมดา (Mon-Sat): work_hours = min(8.00, diff/3600), ot_hours = 3.00 (ถ้า checkout >= otTriggerTs) ไม่เช่นนั้น 0.00
 * - วันหยุด (อาทิตย์, วันหยุดบริษัท/นักขัตฤกษ์ หรือ วันหยุดตารางเวร Off):
 *   - ชั่วโมงทำงานปกติ 8.00 ชม. ในวันหยุด ถือเป็น OT วันหยุด = min(8.00, diff/3600)
 *   - หากเลิกงาน >= 20:00 (หรือ 08:00 กะดึก) จะได้ OT เย็นเพิ่มอีก 3.00 ชม.
 *   - สรุป: ot_hours = baseWorkedOnHoliday + (checkout >= otTriggerTs ? 3.00 : 0.00)
 */
function calculateWorkAndOtHours($pdo, $workDate, $checkInTs, $checkOutTs, $shiftType = 'day', $userId = null) {
    if (!$checkInTs || !$checkOutTs) {
        return ['work_hours' => 0.00, 'ot_hours' => 0.00];
    }

    if ($checkOutTs < $checkInTs) {
        $checkOutTs += 86400; // กะดึกข้ามวัน
    }

    $diffSec = max(0, $checkOutTs - $checkInTs);
    $baseWorkedHours = round(min(8.00, $diffSec / 3600), 2);

    if ($shiftType === 'night') {
        $otTriggerTs = strtotime(date('Y-m-d', strtotime($workDate . ' +1 day')) . ' 08:00:00');
    } else {
        $otTriggerTs = strtotime($workDate . ' 20:00:00');
    }

    $hasEveningOt = ($checkOutTs >= $otTriggerTs);
    $eveningOt = $hasEveningOt ? 3.00 : 0.00;

    // ตรวจสอบว่าเป็นวันอาทิตย์ หรือวันหยุดบริษัท/นักขัตฤกษ์ หรือวันหยุดตามตารางเวร (Off) หรือไม่
    $dayShort = date('D', strtotime($workDate));
    $isSunday = ($dayShort === 'Sun');

    $isCompanyHoliday = false;
    $isRosterOff = ($shiftType === 'off');

    if ($pdo) {
        try {
            $stmtH = $pdo->prepare("SELECT holiday_id FROM company_holidays WHERE holiday_date = :wdate LIMIT 1");
            $stmtH->execute([':wdate' => $workDate]);
            if ($stmtH->fetch()) {
                $isCompanyHoliday = true;
            }
        } catch (Exception $e) {
            // Ignore error
        }

        if ($userId && !$isRosterOff) {
            $shiftInfo = getUserShiftForDate($pdo, $userId, $workDate);
            if ($shiftInfo['is_off']) {
                $isRosterOff = true;
            }
        }
    }

    $isHolidayOrSunday = ($isSunday || $isCompanyHoliday || $isRosterOff);

    if ($isHolidayOrSunday) {
        // ในวันหยุด/วันอาทิตย์/วันหยุดตามตารางเวร เวลาทำงานปกติ 8 ชม. จะถือนับเป็น OT ทั้งหมด
        // และหากทำงานล่วงเวลาเกิน 20:00 น. จะบวก OT เย็นเพิ่มอีก 3 ชม.
        $workHours = $baseWorkedHours;
        $otHours   = round($baseWorkedHours + $eveningOt, 2);
    } else {
        // วันทำงานปกติ
        $workHours = $baseWorkedHours;
        $otHours   = $eveningOt;
    }

    return [
        'work_hours' => $workHours,
        'ot_hours'   => $otHours
    ];
}


