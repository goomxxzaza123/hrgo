<?php
/**
 * API: Dashboard Overview Statistics (get_dashboard_stats.php)
 * Method: GET
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Manager หรือ Admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

try {
    $pdo = getDBConnection();
    $today = date('Y-m-d');

    // สถิติภาพรวมองค์กรสำหรับ Admin และ Manager (แสดงข้อมูลพนักงานทุกแผนก)
    $deptClause = "";
    $params = [];

    // 1. จำนวนพนักงานทั้งหมดที่ใช้งานอยู่
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM users u WHERE is_active = 1 $deptClause");
    $stmtTotal->execute($params);
    $totalEmployees = (int)$stmtTotal->fetch()['total'];

    // 2. จำนวนพนักงานที่ลงเวลาเข้างานวันนี้
    $stmtPresent = $pdo->prepare("
        SELECT COUNT(DISTINCT a.user_id) AS present 
        FROM attendances a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.work_date = :today $deptClause
    ");
    $paramsToday = array_merge([':today' => $today], $params);
    $stmtPresent->execute($paramsToday);
    $presentToday = (int)$stmtPresent->fetch()['present'];

    // 3. จำนวนพนักงานที่มาสายวันนี้
    $stmtLate = $pdo->prepare("
        SELECT COUNT(DISTINCT a.user_id) AS late 
        FROM attendances a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.work_date = :today AND a.status = 'late' $deptClause
    ");
    $stmtLate->execute($paramsToday);
    $lateToday = (int)$stmtLate->fetch()['late'];

    // 4. จำนวนพนักงานที่ลาวันนี้ (ได้รับอนุมัติแล้ว)
    $stmtLeave = $pdo->prepare("
        SELECT COUNT(DISTINCT lr.user_id) AS on_leave 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.user_id 
        WHERE lr.status = 'approved' AND :today BETWEEN lr.start_date AND lr.end_date $deptClause
    ");
    $stmtLeave->execute($paramsToday);
    $onLeaveToday = (int)$stmtLeave->fetch()['on_leave'];

    // 5. จำนวนใบลาที่รออนุมัติ
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*) AS pending 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.user_id 
        WHERE lr.status = 'pending' $deptClause
    ");
    $stmtPending->execute($params);
    $pendingLeaves = (int)$stmtPending->fetch()['pending'];

    // 6. รายการลงเวลาล่าสุดของวันนี้ (20 รายการ)
    $stmtLog = $pdo->prepare("
        SELECT a.attendance_id, u.emp_code, u.name AS employee_name, d.dept_name, u.shift_type,
               a.check_in_time, a.check_out_time, a.status, a.check_in_photo, a.check_out_photo, a.late_minutes
        FROM attendances a 
        JOIN users u ON a.user_id = u.user_id 
        LEFT JOIN departments d ON u.dept_id = d.dept_id 
        WHERE a.work_date = :today $deptClause 
        ORDER BY a.check_in_time DESC 
        LIMIT 20
    ");
    $stmtLog->execute($paramsToday);
    $recentLog = $stmtLog->fetchAll();

    $formattedLog = array_map(function($row) {
        $lateMins = (int)($row['late_minutes'] ?? 0);
        $shift = $row['shift_type'] ?? 'day';
        return [
            'emp_code'        => $row['emp_code'],
            'employee_name'   => $row['employee_name'],
            'dept_name'       => $row['dept_name'] ?? 'ไม่ระบุ',
            'shift_type'      => $shift,
            'shift_label'     => ($shift === 'night') ? '🌙 กลางคืน' : '☀️ กลางวัน',
            'check_in_time'   => date('H:i:s', strtotime($row['check_in_time'])),
            'check_out_time'  => $row['check_out_time'] ? date('H:i:s', strtotime($row['check_out_time'])) : '-',
            'check_in_photo'  => $row['check_in_photo'],
            'check_out_photo' => $row['check_out_photo'],
            'late_minutes'    => $lateMins,
            'status'          => $row['status'],
            'status_label'    => ($row['status'] === 'on_time') ? 'ตรงเวลา' : ($lateMins > 0 ? "สาย ({$lateMins} นาที)" : "สาย")
        ];
    }, $recentLog);

    sendJsonResponse(true, 'ดึงข้อมูลสถิติภาพรวมสำเร็จ', [
        'total_employees' => $totalEmployees,
        'present_today'   => $presentToday,
        'late_today'      => $lateToday,
        'on_leave_today'  => $onLeaveToday,
        'pending_leaves'  => $pendingLeaves,
        'recent_log'      => $formattedLog
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงสถิติภาพรวม: ' . $e->getMessage(), null, 500);
}
