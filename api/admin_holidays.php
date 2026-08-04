<?php
/**
 * API: Admin & Manager Company Holidays Management (admin_holidays.php)
 * Method: GET (List holidays), POST (Add/Delete holiday)
 */

require_once __DIR__ . '/config.php';

// สิทธิ์เฉพาะ Admin และ Manager
$currentUser = requireAuth(['admin', 'manager']);
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

// Ensure company_holidays table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS company_holidays (
            holiday_id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL UNIQUE,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_type ENUM('public', 'company') DEFAULT 'company',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Ignore if table exists
}

// -------------------------------------------------------------
// GET: ดึงรายการวันหยุดทั้งหมด
// -------------------------------------------------------------
if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT holiday_id, holiday_date, holiday_name, holiday_type, created_at
            FROM company_holidays
            ORDER BY holiday_date ASC
        ");
        $stmt->execute();
        $holidays = $stmt->fetchAll();

        $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $thaiDays   = ['Sun' => 'อาทิตย์', 'Mon' => 'จันทร์', 'Tue' => 'อังคาร', 'Wed' => 'พุธ', 'Thu' => 'พฤหัสบดี', 'Fri' => 'ศุกร์', 'Sat' => 'เสาร์'];

        $formatted = array_map(function($h) use ($thaiMonths, $thaiDays) {
            $dt = new DateTime($h['holiday_date']);
            $dayNum = (int)$dt->format('j');
            $monthNum = (int)$dt->format('n');
            $yearTh = (int)$dt->format('Y') + 543;
            $dayShort = $dt->format('D');

            $dateTh = "{$dayNum} {$thaiMonths[$monthNum]} {$yearTh} (" . ($thaiDays[$dayShort] ?? '') . ")";
            $typeLabel = ($h['holiday_type'] === 'public') ? 'วันหยุดนักขัตฤกษ์' : 'วันหยุดบริษัท';

            return [
                'holiday_id'   => (int)$h['holiday_id'],
                'holiday_date' => $h['holiday_date'],
                'holiday_name' => $h['holiday_name'],
                'holiday_type' => $h['holiday_type'],
                'type_label'   => $typeLabel,
                'holiday_date_th' => $dateTh
            ];
        }, $holidays);

        sendJsonResponse(true, 'ดึงรายการวันหยุดสำเร็จ', $formatted);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: เพิ่ม หรือ ลบ วันหยุด
// -------------------------------------------------------------
if ($method === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action    = trim($inputData['action'] ?? '');

    try {
        if ($action === 'add_holiday') {
            $holidayDate = trim($inputData['holiday_date'] ?? '');
            $holidayName = trim($inputData['holiday_name'] ?? '');
            $holidayType = trim($inputData['holiday_type'] ?? 'company');

            if (empty($holidayDate) || empty($holidayName)) {
                sendJsonResponse(false, 'กรุณาระบุวันที่และชื่อวันหยุดให้ครบถ้วน', null, 400);
            }

            if (!in_array($holidayType, ['public', 'company'])) {
                $holidayType = 'company';
            }

            // เช็คว่ามีวันที่นี้ในระบบอยู่แล้วหรือไม่
            $stmtCheck = $pdo->prepare("SELECT holiday_id FROM company_holidays WHERE holiday_date = :h_date LIMIT 1");
            $stmtCheck->execute([':h_date' => $holidayDate]);
            if ($stmtCheck->fetch()) {
                sendJsonResponse(false, 'วันที่นี้ถูกบันทึกเป็นวันหยุดในระบบแล้ว', null, 400);
            }

            $stmtAdd = $pdo->prepare("
                INSERT INTO company_holidays (holiday_date, holiday_name, holiday_type) 
                VALUES (:h_date, :h_name, :h_type)
            ");
            $stmtAdd->execute([
                ':h_date' => $holidayDate,
                ':h_name' => $holidayName,
                ':h_type' => $holidayType
            ]);

            sendJsonResponse(true, 'บันทึกเพิ่มวันหยุดเรียบร้อยแล้ว');
        }

        if ($action === 'delete_holiday') {
            $holidayId = (int)($inputData['holiday_id'] ?? 0);
            if (!$holidayId) {
                sendJsonResponse(false, 'กรุณาระบุวันหยุดที่ต้องการลบ', null, 400);
            }

            $stmtDel = $pdo->prepare("DELETE FROM company_holidays WHERE holiday_id = :h_id");
            $stmtDel->execute([':h_id' => $holidayId]);

            sendJsonResponse(true, 'ลบวันหยุดเรียบร้อยแล้ว');
        }

        sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
    }
}
