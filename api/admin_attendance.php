<?php
/**
 * API: Admin & Manager Attendance Management (admin_attendance.php)
 * Method: POST (Update or Add Attendance record)
 */

require_once __DIR__ . '/config.php';

// เฉพาะ Admin และ Manager เท่านั้น
$currentUser = requireAuth(['admin', 'manager']);
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', null, 405);
}

$inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action    = trim($inputData['action'] ?? 'update_attendance');

try {
    if ($action === 'update_attendance') {
        $attendanceId = (int)($inputData['attendance_id'] ?? 0);
        $checkInStr   = trim($inputData['check_in_time'] ?? ''); // "08:00" หรือ "08:00:00"
        $checkOutStr  = trim($inputData['check_out_time'] ?? ''); // "17:00" หรือ "17:00:00"
        $statusOverride = trim($inputData['status'] ?? ''); // "normal", "late", "absent"

        if (!$attendanceId) {
            sendJsonResponse(false, 'กรุณาระบุรายการลงเวลาที่ต้องการแก้ไข', null, 400);
        }

        // ดึงรายการเดิมและข้อมูลกะงานของพนักงาน
        $stmtAtt = $pdo->prepare("
            SELECT a.attendance_id, a.user_id, a.work_date, a.check_in_time, a.check_out_time, a.status,
                   u.shift_type, u.shift_start_time, u.shift_end_time, u.ot_cap_time, u.name
            FROM attendances a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.attendance_id = :att_id
            LIMIT 1
        ");
        $stmtAtt->execute([':att_id' => $attendanceId]);
        $att = $stmtAtt->fetch();

        if (!$att) {
            sendJsonResponse(false, 'ไม่พบรายการลงเวลานี้ในระบบ', null, 404);
        }

        $workDate = $att['work_date'];

        // ปรับ Format ของ เวลาเข้า และ เวลาออก
        $fullCheckIn  = !empty($checkInStr) ? ($workDate . ' ' . (strlen($checkInStr) === 5 ? $checkInStr . ':00' : $checkInStr)) : null;
        $fullCheckOut = !empty($checkOutStr) ? ($workDate . ' ' . (strlen($checkOutStr) === 5 ? $checkOutStr . ':00' : $checkOutStr)) : null;

        // คำนวณชั่วโมงทำงาน และ OT ใหม่
        $workHours = 0.00;
        $otHours   = 0.00;

        if ($fullCheckIn && $fullCheckOut) {
            $inTs  = strtotime($fullCheckIn);
            $outTs = strtotime($fullCheckOut);

            if ($outTs < $inTs) {
                // กรณีกะกลางคืน ข้ามวัน
                $outTs += 86400;
            }

            $shiftType = $att['shift_type'] ?? 'day';

            if ($shiftType === 'night') {
                $otTriggerTs = strtotime(date('Y-m-d', strtotime($workDate . ' +1 day')) . ' 08:00:00');
            } else {
                $otTriggerTs = strtotime($workDate . ' 20:00:00');
            }

            $diffSec   = max(0, $outTs - $inTs);
            $workHours = round(min(8.00, $diffSec / 3600), 2);
            $otHours   = ($outTs >= $otTriggerTs) ? 3.00 : 0.00;
        }

        // คำนวณสายและนาทีสาย
        $lateMinutes = 0;
        if ($fullCheckIn) {
            $shiftType     = $att['shift_type'] ?? 'day';
            $shiftStartStr = ($shiftType === 'night') 
                ? (($att['shift_start_time'] && $att['shift_start_time'] !== '08:00:00') ? $att['shift_start_time'] : '20:00:00') 
                : ($att['shift_start_time'] ?? '08:00:00');
            $shiftStartTs  = strtotime($workDate . ' ' . $shiftStartStr);
            $checkInTs     = strtotime($fullCheckIn);
            if ($checkInTs > $shiftStartTs) {
                $lateMinutes = (int)floor(($checkInTs - $shiftStartTs) / 60);
            }
        }

        // คำนวณสถานะ (สาย / ตรงเวลา)
        $newStatus = $statusOverride;
        if ($newStatus === 'normal') {
            $newStatus = 'on_time';
        }

        if (empty($newStatus)) {
            if ($fullCheckIn) {
                $newStatus = ($lateMinutes > 0) ? 'late' : 'on_time';
            } else {
                $newStatus = 'absent';
            }
        }

        // อัปเดตตาราง attendances
        $stmtUpd = $pdo->prepare("
            UPDATE attendances 
            SET check_in_time = :check_in,
                check_out_time = :check_out,
                work_hours = :work_hours,
                ot_hours = :ot_hours,
                late_minutes = :late_minutes,
                status = :status
            WHERE attendance_id = :att_id
        ");

        $stmtUpd->execute([
            ':check_in'     => $fullCheckIn,
            ':check_out'    => $fullCheckOut,
            ':work_hours'   => $workHours,
            ':ot_hours'     => $otHours,
            ':late_minutes' => $lateMinutes,
            ':status'       => $newStatus,
            ':att_id'       => $attendanceId
        ]);

        sendJsonResponse(true, "แก้ไขเวลาลงเวลาสำหรับ {$att['name']} เรียบร้อยแล้ว", [
            'attendance_id'  => $attendanceId,
            'check_in_time'  => $fullCheckIn ? date('H:i', strtotime($fullCheckIn)) : null,
            'check_out_time' => $fullCheckOut ? date('H:i', strtotime($fullCheckOut)) : null,
            'work_hours'     => $workHours,
            'ot_hours'       => $otHours,
            'status'         => $newStatus
        ]);
    } else {
        sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);
    }

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการแก้ไขเวลาลงเวลา: ' . $e->getMessage(), null, 500);
}
