<?php
/**
 * API: Get Leave Balances & History (get_leaves.php)
 * Method: GET
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้งาน
$currentUser = requireAuth();

try {
    $pdo = getDBConnection();
    $userId = $currentUser['user_id'];

    // 1. คำนวณจำนวนวันลาที่ใช้ไปจริงจากรายการใบลาที่อนุมัติแล้ว (status = 'approved') เท่านั้น
    $stmtUsed = $pdo->prepare("
        SELECT leave_type, start_date, end_date 
        FROM leave_requests 
        WHERE user_id = :user_id AND status = 'approved'
    ");
    $stmtUsed->execute([':user_id' => $userId]);
    $approvedLeaves = $stmtUsed->fetchAll();

    $realUsedDays = ['sick' => 0, 'personal' => 0, 'vacation' => 0];
    foreach ($approvedLeaves as $al) {
        $lt = $al['leave_type'];
        $s  = new DateTime($al['start_date']);
        $e  = new DateTime($al['end_date']);
        $days = $s->diff($e)->days + 1;
        if (isset($realUsedDays[$lt])) {
            $realUsedDays[$lt] += $days;
        } else {
            $realUsedDays[$lt] = $days;
        }
    }

    // 2. ดึงโควตาวันลาคงเหลือของพนักงาน (Sick, Personal, Vacation)
    $stmtBalances = $pdo->prepare("
        SELECT leave_type, total_quota 
        FROM leave_balances 
        WHERE user_id = :user_id
    ");
    $stmtBalances->execute([':user_id' => $userId]);
    $balancesRaw = $stmtBalances->fetchAll();

    // Map ข้อมูลโควตาให้อยู่ในโครงสร้างที่ใช้ง่าย
    $leaveTypesMap = [
        'sick'     => 'ลาป่วย',
        'personal' => 'ลากิจ',
        'vacation' => 'ลาพักร้อน'
    ];

    $balances = [];
    foreach ($balancesRaw as $row) {
        $lt = $row['leave_type'];
        $used = $realUsedDays[$lt] ?? 0;
        $total = (int)$row['total_quota'];
        $remaining = max(0, $total - $used);

        $balances[$lt] = [
            'type'        => $lt,
            'type_label'  => $leaveTypesMap[$lt] ?? $lt,
            'total_quota' => $total,
            'used_days'   => $used,
            'remaining'   => $remaining
        ];
    }

    // หากยังไม่มีโควตา ให้สร้างค่า default 0 ให้ครบทุกประเภท
    foreach ($leaveTypesMap as $typeKey => $typeLabel) {
        if (!isset($balances[$typeKey])) {
            $balances[$typeKey] = [
                'type'        => $typeKey,
                'type_label'  => $typeLabel,
                'total_quota' => 0,
                'used_days'   => 0,
                'remaining'   => 0
            ];
        }
    }

    // 2. ดึงประวัติคำขอลางานของพนักงาน พร้อมชื่อผู้อนุมัติ (ถ้ามี)
    $stmtRequests = $pdo->prepare("
        SELECT lr.leave_id, lr.leave_type, lr.start_date, lr.end_date, lr.reason, 
               lr.status, lr.created_at, u_app.name AS approver_name
        FROM leave_requests lr
        LEFT JOIN users u_app ON lr.approved_by = u_app.user_id
        WHERE lr.user_id = :user_id
        ORDER BY lr.created_at DESC
    ");
    $stmtRequests->execute([':user_id' => $userId]);
    $requestsRaw = $stmtRequests->fetchAll();

    // ปรับรูปแบบข้อมูลประวัติใบลา
    $formattedRequests = array_map(function($row) use ($leaveTypesMap) {
        $startDate = new DateTime($row['start_date']);
        $endDate   = new DateTime($row['end_date']);
        $daysCount = $startDate->diff($endDate)->days + 1;

        $statusLabel = 'รออนุมัติ';
        if ($row['status'] === 'approved') $statusLabel = 'อนุมัติแล้ว';
        if ($row['status'] === 'rejected') $statusLabel = 'ปฏิเสธ';

        return [
            'leave_id'      => $row['leave_id'],
            'leave_type'    => $row['leave_type'],
            'type_label'    => $leaveTypesMap[$row['leave_type']] ?? $row['leave_type'],
            'start_date'    => $row['start_date'],
            'end_date'      => $row['end_date'],
            'start_date_th' => date('d/m/Y', strtotime($row['start_date'])),
            'end_date_th'   => date('d/m/Y', strtotime($row['end_date'])),
            'days_count'    => $daysCount,
            'reason'        => $row['reason'],
            'status'        => $row['status'],
            'status_label'  => $statusLabel,
            'approver_name' => $row['approver_name'] ?? '-',
            'created_at_th' => date('d/m/Y H:i', strtotime($row['created_at']))
        ];
    }, $requestsRaw);

    sendJsonResponse(true, 'ดึงข้อมูลโควตาและคำขอลางานสำเร็จ', [
        'balances' => array_values($balances),
        'requests' => $formattedRequests
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลโควตาวันลา: ' . $e->getMessage(), null, 500);
}
