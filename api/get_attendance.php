<?php
/**
 * API: Get Attendance Status & History (get_attendance.php)
 * Method: GET
 * Supports: optional start_date and end_date query params for personal history filtering
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
$currentUser = requireAuth();

try {
    $pdo = getDBConnection();
    $userId = $currentUser['user_id'];
    $today  = date('Y-m-d');

    // 0. ดึงตั้งค่ากะการทำงานของผู้ใช้
    $stmtShift = $pdo->prepare("SELECT shift_type, shift_start_time, shift_end_time, ot_cap_time FROM users WHERE user_id = :user_id LIMIT 1");
    $userShift = getUserShiftForDate($pdo, $userId, $today);

    $shiftLabel = ($userShift['shift_type'] === 'off')
        ? 'วันหยุดประจำสัปดาห์ / วันหยุดตามตารางกะ'
        : (($userShift['shift_type'] === 'night') 
            ? "กะกลางคืน ({$userShift['shift_start_time']} - {$userShift['shift_end_time']} น.)" 
            : "กะกลางวัน ({$userShift['shift_start_time']} - {$userShift['shift_end_time']} น. | OT สูงสุด 20:00 น.)");

    // 1. ดึงข้อมูลการลงเวลาของวันนี้
    $stmtToday = $pdo->prepare("
        SELECT attendance_id, work_date, check_in_time, check_out_time, ip_address, status, check_in_photo, check_out_photo, work_hours, ot_hours, late_minutes 
        FROM attendances 
        WHERE user_id = :user_id AND work_date = :work_date 
        LIMIT 1
    ");
    $stmtToday->execute([
        ':user_id'   => $userId,
        ':work_date' => $today
    ]);
    $todayRecord = $stmtToday->fetch();

    // 2. ดึงประวัติการลงเวลาตามเงื่อนไขวันที่ (ถ้าระบุ) หรือ 30 รายการล่าสุด
    $startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate   = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

    if ($startDate && $endDate) {
        $stmtHistory = $pdo->prepare("
            SELECT attendance_id, work_date, check_in_time, check_out_time, ip_address, status, check_in_photo, check_out_photo, work_hours, ot_hours, late_minutes 
            FROM attendances 
            WHERE user_id = :user_id AND work_date BETWEEN :start_date AND :end_date
            ORDER BY work_date DESC, attendance_id DESC 
        ");
        $stmtHistory->execute([
            ':user_id'    => $userId,
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
    } else {
        $stmtHistory = $pdo->prepare("
            SELECT attendance_id, work_date, check_in_time, check_out_time, ip_address, status, check_in_photo, check_out_photo, work_hours, ot_hours, late_minutes 
            FROM attendances 
            WHERE user_id = :user_id 
            ORDER BY work_date DESC, attendance_id DESC 
            LIMIT 60
        ");
        $stmtHistory->execute([':user_id' => $userId]);
    }

    $historyRecords = $stmtHistory->fetchAll();

    $totalWorkHours   = 0;
    $totalOtHours     = 0;
    $totalLateMinutes = 0;
    $onTimeCount      = 0;
    $lateCount        = 0;

    // ปรับฟอร์แมตข้อมูลประวัติให้อ่านง่าย และคำนวณยอดรวมชั่วโมงทำงาน/OT/นาทีสาย
    $formattedHistory = array_map(function($row) use (&$totalWorkHours, &$totalOtHours, &$totalLateMinutes, &$onTimeCount, &$lateCount) {
        $wHours   = (float)($row['work_hours'] ?? 0);
        $oHours   = (float)($row['ot_hours'] ?? 0);
        $lateMins = (int)($row['late_minutes'] ?? 0);

        $totalWorkHours   += $wHours;
        $totalOtHours     += $oHours;
        $totalLateMinutes += $lateMins;

        $status = $row['status'];
        if ($status === 'on_time' || $status === 'normal') {
            $onTimeCount++;
        } elseif ($status === 'late') {
            $lateCount++;
        }

        return [
            'attendance_id'   => $row['attendance_id'],
            'work_date'       => $row['work_date'],
            'work_date_th'    => date('d/m/Y', strtotime($row['work_date'])),
            'check_in_time'   => $row['check_in_time'] ? date('H:i:s', strtotime($row['check_in_time'])) : '-',
            'check_out_time'  => $row['check_out_time'] ? date('H:i:s', strtotime($row['check_out_time'])) : '-',
            'check_in_photo'  => $row['check_in_photo'],
            'check_out_photo' => $row['check_out_photo'],
            'work_hours'      => number_format($wHours, 2),
            'ot_hours'        => number_format($oHours, 2),
            'late_minutes'    => $lateMins,
            'ip_address'      => $row['ip_address'],
            'status'          => $row['status'],
            'status_label'    => ($row['status'] === 'on_time' || $row['status'] === 'normal') 
                ? 'ตรงเวลา' 
                : (($row['status'] === 'late') ? formatLateText($lateMins) : 'ขาดงาน')
        ];
    }, $historyRecords);

    sendJsonResponse(true, 'ดึงข้อมูลการเข้างานสำเร็จ', [
        'user_ip'       => getUserIP(),
        'ip_restricted' => ENABLE_IP_CHECK,
        'user_shift'    => [
            'shift_type'       => $userShift['shift_type'] ?? 'day',
            'shift_start_time' => substr($userShift['shift_start_time'] ?? '08:00:00', 0, 5),
            'shift_end_time'   => substr($userShift['shift_end_time'] ?? '17:00:00', 0, 5),
            'ot_cap_time'      => substr($userShift['ot_cap_time'] ?? '20:00:00', 0, 5),
            'shift_label'      => $shiftLabel
        ],
        'today'         => $todayRecord ? [
            'attendance_id'     => $todayRecord['attendance_id'],
            'work_date'         => $todayRecord['work_date'],
            'check_in_time'     => $todayRecord['check_in_time'] ? date('H:i:s', strtotime($todayRecord['check_in_time'])) : null,
            'check_out_time'    => $todayRecord['check_out_time'] ? date('H:i:s', strtotime($todayRecord['check_out_time'])) : null,
            'check_in_raw'      => $todayRecord['check_in_time'],
            'check_out_raw'     => $todayRecord['check_out_time'],
            'check_in_photo'    => $todayRecord['check_in_photo'],
            'check_out_photo'   => $todayRecord['check_out_photo'],
            'work_hours'        => (float)($todayRecord['work_hours'] ?? 0),
            'ot_hours'          => (float)($todayRecord['ot_hours'] ?? 0),
            'late_minutes'      => (int)($todayRecord['late_minutes'] ?? 0),
            'status'            => $todayRecord['status'],
            'status_label'      => ($todayRecord['status'] === 'on_time' || $todayRecord['status'] === 'normal') 
                ? 'ตรงเวลา' 
                : formatLateText($todayRecord['late_minutes'] ?? 0)
        ] : null,
        'summary'       => [
            'total_records'      => count($historyRecords),
            'total_work_hours'   => number_format($totalWorkHours, 2),
            'total_ot_hours'     => number_format($totalOtHours, 2),
            'total_late_minutes' => $totalLateMinutes,
            'on_time_count'      => $onTimeCount,
            'late_count'         => $lateCount
        ],
        'history'       => $formattedHistory
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลประวัติการลงเวลา: ' . $e->getMessage(), null, 500);
}
