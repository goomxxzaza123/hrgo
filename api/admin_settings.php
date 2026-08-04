<?php
/**
 * API: Admin System & Location Settings (admin_settings.php)
 * Method: GET (Fetch settings), POST (Update radius & GPS coordinates)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Admin หรือ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$pdo = getDBConnection();

// -------------------------------------------------------------
// GET: ดึงค่าตั้งค่าพิกัดและรัศมีลงเวลา
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $settings = getSystemSettings();
        sendJsonResponse(true, 'ดึงการตั้งค่าสำเร็จ', $settings);
    } catch (Exception $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงการตั้งค่า: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: บันทึกการตั้งค่าพิกัดออฟฟิศและรัศมีอนุญาต
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $maxDistance   = (int)($inputData['max_distance_meters'] ?? 300);
    $companyLat    = isset($inputData['company_lat']) ? (float)$inputData['company_lat'] : null;
    $companyLng    = isset($inputData['company_lng']) ? (float)$inputData['company_lng'] : null;
    $enableLocCheck = isset($inputData['enable_location_check']) ? ($inputData['enable_location_check'] ? '1' : '0') : '1';
    $enableIpCheck  = isset($inputData['enable_ip_check']) ? ($inputData['enable_ip_check'] ? '1' : '0') : '0';

    if (empty($companyLat) || empty($companyLng) || $maxDistance < 10) {
        sendJsonResponse(false, 'กรุณาระบุพิกัดละติจูด/ลองจิจูดที่ถูกต้อง และรัศมีต้องอย่างน้อย 10 เมตร', null, 400);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        $stmt->execute([':key' => 'max_distance_meters',   ':value' => (string)$maxDistance]);
        $stmt->execute([':key' => 'company_lat',           ':value' => (string)$companyLat]);
        $stmt->execute([':key' => 'company_lng',           ':value' => (string)$companyLng]);
        $stmt->execute([':key' => 'enable_location_check', ':value' => $enableLocCheck]);
        $stmt->execute([':key' => 'enable_ip_check',       ':value' => $enableIpCheck]);

        sendJsonResponse(true, "บันทึกการตั้งค่าเรียบร้อยแล้ว (ตั้งรัศมีอนุญาตเป็น {$maxDistance} เมตร)", [
            'max_distance_meters'   => $maxDistance,
            'company_lat'           => $companyLat,
            'company_lng'           => $companyLng,
            'enable_location_check' => ($enableLocCheck === '1'),
            'enable_ip_check'       => ($enableIpCheck === '1')
        ]);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกการตั้งค่า: ' . $e->getMessage(), null, 500);
    }
}
