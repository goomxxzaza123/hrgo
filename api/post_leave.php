<?php
/**
 * API: Submit Leave Request (post_leave.php)
 * Method: POST
 * Body: JSON {"leave_type": "sick|personal|vacation", "start_date": "YYYY-MM-DD", "end_date": "YYYY-MM-DD", "reason": "..."}
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้งาน
$currentUser = requireAuth();

// รับเฉพาะ POST Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'รองรับเฉพาะ POST Request เท่านั้น', null, 405);
}

// อ่านข้อมูล JSON ที่ส่งมา
$inputData = json_decode(file_get_contents('php://input'), true);

$leaveType = trim($inputData['leave_type'] ?? $_POST['leave_type'] ?? '');
$startDate = trim($inputData['start_date'] ?? $_POST['start_date'] ?? '');
$endDate   = trim($inputData['end_date'] ?? $_POST['end_date'] ?? '');
$reason    = trim($inputData['reason'] ?? $_POST['reason'] ?? '');

// 1. ตรวจสอบความถูกต้องเบื้องต้นของฟิลด์ข้อมูล
if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
    sendJsonResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง (ประเภทการลา, วันที่เริ่ม, วันที่สิ้นสุด, และเหตุผล)', null, 400);
}

// ตรวจสอบประเภทการลาที่ถูกต้อง
$allowedTypes = ['sick', 'personal', 'vacation'];
if (!in_array($leaveType, $allowedTypes)) {
    sendJsonResponse(false, 'ประเภทการลาไม่ถูกต้อง', null, 400);
}

// 2. ตรวจสอบวันที่เริ่มและสิ้นสุด
$start = DateTime::createFromFormat('Y-m-d', $startDate);
$end   = DateTime::createFromFormat('Y-m-d', $endDate);

if (!$start || !$end) {
    sendJsonResponse(false, 'รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น YYYY-MM-DD)', null, 400);
}

if ($start > $end) {
    sendJsonResponse(false, 'วันที่เริ่มต้นลา ต้องไม่มากกว่า วันที่สิ้นสุดการลา', null, 400);
}

// คำนวณจำนวนวันที่ขอลา (นับรวมวันเริ่มต้นและสิ้นสุด)
$requestedDays = $start->diff($end)->days + 1;

try {
    $pdo = getDBConnection();
    $userId = $currentUser['user_id'];

    // 3. ตรวจสอบโควตาคงเหลือจากตาราง leave_balances
    $stmtBalance = $pdo->prepare("
        SELECT total_quota, used_days 
        FROM leave_balances 
        WHERE user_id = :user_id AND leave_type = :leave_type 
        LIMIT 1
    ");
    $stmtBalance->execute([
        ':user_id'    => $userId,
        ':leave_type' => $leaveType
    ]);
    $balance = $stmtBalance->fetch();

    $totalQuota = $balance ? (int)$balance['total_quota'] : 0;
    $usedDays   = $balance ? (int)$balance['used_days'] : 0;
    $remainingQuota = max(0, $totalQuota - $usedDays);

    if ($requestedDays > $remainingQuota) {
        sendJsonResponse(false, "ไม่สามารถยื่นลาได้ เนื่องจากสิทธิ์คงเหลือไม่เพียงพอ (ต้องการลา $requestedDays วัน แต่มีสิทธิ์คงเหลือ $remainingQuota วัน)", null, 400);
    }

    // 4. ตรวจสอบว่ามีรายการลาที่ซ้อนทับกันที่อยู่ระหว่างรออนุมัติหรืออนุมัติแล้วหรือไม่
    $stmtOverlap = $pdo->prepare("
        SELECT leave_id 
        FROM leave_requests 
        WHERE user_id = :user_id 
          AND status IN ('pending', 'approved')
          AND NOT (end_date < :start_date OR start_date > :end_date)
        LIMIT 1
    ");
    $stmtOverlap->execute([
        ':user_id'    => $userId,
        ':start_date' => $startDate,
        ':end_date'   => $endDate
    ]);

    if ($stmtOverlap->fetch()) {
        sendJsonResponse(false, 'ท่านมีรายการขอลางานในช่วงวันที่ดังกล่าวที่รออนุมัติหรืออนุมัติแล้วอยู่แล้ว', null, 400);
    }

    // 5. บันทึกคำขอลางานลงในตาราง leave_requests ด้วย PDO Prepared Statement
    $stmtInsert = $pdo->prepare("
        INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status) 
        VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, 'pending')
    ");
    $stmtInsert->execute([
        ':user_id'    => $userId,
        ':leave_type' => $leaveType,
        ':start_date' => $startDate,
        ':end_date'   => $endDate,
        ':reason'     => $reason
    ]);

    sendJsonResponse(true, "ยื่นคำขอลางานเรียบร้อยแล้ว (จำนวน $requestedDays วัน) อยู่ระหว่างรออนุมัติ", [
        'leave_id'       => $pdo->lastInsertId(),
        'requested_days' => $requestedDays,
        'remaining'      => $remainingQuota
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกคำขอลางาน: ' . $e->getMessage(), null, 500);
}
