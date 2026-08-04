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
define('DB_NAME', 'hrgogemini');
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
