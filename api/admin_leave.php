<?php
/**
 * API: Admin & Manager Leave Approval (admin_leave.php)
 * Method: GET (List leave requests), POST (Approve/Reject)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Manager หรือ Admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

$pdo = getDBConnection();

// -------------------------------------------------------------
// GET: ดึงรายการคำขอลางาน
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $statusFilter = trim($_GET['status'] ?? '');

        // ทั้ง Admin และ Manager สามารถเห็นใบลาของพนักงานทุกแผนกได้
        $whereClause = [];
        $params = [];

        if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
            $whereClause[] = "lr.status = :status";
            $params[':status'] = $statusFilter;
        }

        $whereSql = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

        $sql = "
            SELECT lr.leave_id, lr.user_id, u.emp_code, u.name AS employee_name, d.dept_name,
                   lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at,
                   u_app.name AS approver_name, lb.total_quota, lb.used_days
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.user_id
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            LEFT JOIN users u_app ON lr.approved_by = u_app.user_id
            LEFT JOIN leave_balances lb ON (lb.user_id = lr.user_id AND lb.leave_type = lr.leave_type)
            $whereSql
            ORDER BY FIELD(lr.status, 'pending', 'approved', 'rejected'), lr.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        $leaveTypesMap = [
            'sick'     => 'ลาป่วย',
            'personal' => 'ลากิจ',
            'vacation' => 'ลาพักร้อน'
        ];

        $formatted = array_map(function($row) use ($leaveTypesMap) {
            $startDate = new DateTime($row['start_date']);
            $endDate   = new DateTime($row['end_date']);
            $daysCount = $startDate->diff($endDate)->days + 1;
            $remaining = max(0, (int)$row['total_quota'] - (int)$row['used_days']);

            return [
                'leave_id'      => $row['leave_id'],
                'user_id'       => $row['user_id'],
                'emp_code'      => $row['emp_code'],
                'employee_name' => $row['employee_name'],
                'dept_name'     => $row['dept_name'] ?? 'ไม่ระบุ',
                'leave_type'    => $row['leave_type'],
                'type_label'    => $leaveTypesMap[$row['leave_type']] ?? $row['leave_type'],
                'start_date'    => $row['start_date'],
                'end_date'      => $row['end_date'],
                'start_date_th' => date('d/m/Y', strtotime($row['start_date'])),
                'end_date_th'   => date('d/m/Y', strtotime($row['end_date'])),
                'days_count'    => $daysCount,
                'remaining'     => $remaining,
                'reason'        => $row['reason'],
                'status'        => $row['status'],
                'approver_name' => $row['approver_name'] ?? '-',
                'created_at_th' => date('d/m/Y H:i', strtotime($row['created_at']))
            ];
        }, $requests);

        sendJsonResponse(true, 'ดึงรายการคำขอลางานสำเร็จ', $formatted);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงรายการคำขอลางาน: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: อนุมัติ (Approve) หรือ ปฏิเสธ (Reject) ใบลา
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);

    $leaveId = (int)($inputData['leave_id'] ?? $_POST['leave_id'] ?? 0);
    $action  = trim($inputData['action'] ?? $_POST['action'] ?? ''); // 'approve' หรือ 'reject'

    if (!$leaveId || !in_array($action, ['approve', 'reject'])) {
        sendJsonResponse(false, 'ข้อมูลไม่ถูกต้อง (กรุณาระบุ leave_id และ action)', null, 400);
    }

    try {
        $pdo->beginTransaction();

        // 1. ตรวจสอบใบลา
        $stmtCheck = $pdo->prepare("
            SELECT lr.leave_id, lr.user_id, lr.leave_type, lr.start_date, lr.end_date, lr.status, u.dept_id 
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.user_id
            WHERE lr.leave_id = :leave_id 
            FOR UPDATE
        ");
        $stmtCheck->execute([':leave_id' => $leaveId]);
        $leave = $stmtCheck->fetch();

        if (!$leave) {
            $pdo->rollBack();
            sendJsonResponse(false, 'ไม่พบข้อมูลคำขอลางานนี้', null, 404);
        }



        if ($leave['status'] !== 'pending') {
            $pdo->rollBack();
            sendJsonResponse(false, 'รายการนี้ได้รับการพิจารณาไปแล้ว', null, 400);
        }

        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

        // 2. อัปเดตสถานะใบลา
        $stmtUpdate = $pdo->prepare("
            UPDATE leave_requests 
            SET status = :status, approved_by = :approved_by 
            WHERE leave_id = :leave_id
        ");
        $stmtUpdate->execute([
            ':status'      => $newStatus,
            ':approved_by' => $currentUser['user_id'],
            ':leave_id'    => $leaveId
        ]);

        // 3. หากกดอนุมัติ (Approve) ให้ตัดโควตาวันลาในตาราง leave_balances โดยอัตโนมัติ
        if ($action === 'approve') {
            $startDate = new DateTime($leave['start_date']);
            $endDate   = new DateTime($leave['end_date']);
            $daysCount = $startDate->diff($endDate)->days + 1;

            $stmtDeduct = $pdo->prepare("
                UPDATE leave_balances 
                SET used_days = used_days + :days 
                WHERE user_id = :user_id AND leave_type = :leave_type
            ");
            $stmtDeduct->execute([
                ':days'       => $daysCount,
                ':user_id'    => $leave['user_id'],
                ':leave_type' => $leave['leave_type']
            ]);
        }

        $pdo->commit();

        $message = ($action === 'approve') ? 'อนุมัติใบลาเรียบร้อยแล้ว และตัดโควตาวันลาอัตโนมัติ' : 'ปฏิเสธคำขอลางานเรียบร้อยแล้ว';
        sendJsonResponse(true, $message, ['leave_id' => $leaveId, 'status' => $newStatus]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดำเนินการ: ' . $e->getMessage(), null, 500);
    }
}
