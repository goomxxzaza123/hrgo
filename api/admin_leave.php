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
    $action  = trim($inputData['action'] ?? $_POST['action'] ?? ''); // 'approve', 'reject', หรือ 'create_on_behalf'

    // -------------------------------------------------------------
    // กรณีที่ 1: Admin / Manager ยื่นขอลางานแทนพนักงาน
    // -------------------------------------------------------------
    if ($action === 'create_on_behalf') {
        $targetUserId = (int)($inputData['user_id'] ?? $_POST['user_id'] ?? 0);
        $leaveType    = trim($inputData['leave_type'] ?? $_POST['leave_type'] ?? '');
        $startDate    = trim($inputData['start_date'] ?? $_POST['start_date'] ?? '');
        $endDate      = trim($inputData['end_date'] ?? $_POST['end_date'] ?? '');
        $reason       = trim($inputData['reason'] ?? $_POST['reason'] ?? '');

        if (!$targetUserId || empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
            sendJsonResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง (เลือกพนักงาน, ประเภทการลา, วันที่เริ่ม, วันที่สิ้นสุด, และเหตุผล)', null, 400);
        }

        $allowedTypes = ['sick', 'personal', 'vacation'];
        if (!in_array($leaveType, $allowedTypes)) {
            sendJsonResponse(false, 'ประเภทการลาไม่ถูกต้อง', null, 400);
        }

        $start = DateTime::createFromFormat('Y-m-d', $startDate);
        $end   = DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            sendJsonResponse(false, 'รูปแบบวันที่ไม่ถูกต้อง', null, 400);
        }

        if ($start > $end) {
            sendJsonResponse(false, 'วันที่เริ่มต้นลา ต้องไม่มากกว่า วันที่สิ้นสุดการลา', null, 400);
        }

        $requestedDays = $start->diff($end)->days + 1;

        try {
            $pdo->beginTransaction();

            // 1. ดึงพนักงานเป้าหมาย
            $stmtUser = $pdo->prepare("SELECT user_id, name, emp_code FROM users WHERE user_id = :uid LIMIT 1");
            $stmtUser->execute([':uid' => $targetUserId]);
            $targetUser = $stmtUser->fetch();

            if (!$targetUser) {
                $pdo->rollBack();
                sendJsonResponse(false, 'ไม่พบพนักงานเป้าหมายในระบบ', null, 404);
            }

            // 2. ตรวจสอบโควตาคงเหลือ
            $stmtBal = $pdo->prepare("SELECT total_quota, used_days FROM leave_balances WHERE user_id = :uid AND leave_type = :ltype LIMIT 1 FOR UPDATE");
            $stmtBal->execute([':uid' => $targetUserId, ':ltype' => $leaveType]);
            $bal = $stmtBal->fetch();

            $totalQuota = $bal ? (int)$bal['total_quota'] : 0;
            $usedDays   = $bal ? (int)$bal['used_days'] : 0;
            $remaining  = max(0, $totalQuota - $usedDays);

            if ($requestedDays > $remaining) {
                $pdo->rollBack();
                sendJsonResponse(false, "ไม่สามารถยื่นลาแทนได้ เนื่องจากโควตาคงเหลือของ {$targetUser['name']} ไม่เพียงพอ (ต้องการ $requestedDays วัน แต่คงเหลือ $remaining วัน)", null, 400);
            }

            // 3. ตรวจสอบวันลาซ้อนทับ
            $stmtOverlap = $pdo->prepare("
                SELECT leave_id FROM leave_requests 
                WHERE user_id = :uid AND status IN ('pending', 'approved')
                  AND NOT (end_date < :start_date OR start_date > :end_date)
                LIMIT 1
            ");
            $stmtOverlap->execute([':uid' => $targetUserId, ':start_date' => $startDate, ':end_date' => $endDate]);
            if ($stmtOverlap->fetch()) {
                $pdo->rollBack();
                sendJsonResponse(false, "พนักงาน {$targetUser['name']} มีรายการลางานในช่วงวันที่ดังกล่าวอยู่แล้ว", null, 400);
            }

            // 4. บันทึกคำขอลางานด้วยสถานะ 'approved' ทันที
            $stmtInsert = $pdo->prepare("
                INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, approved_by) 
                VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, 'approved', :approved_by)
            ");
            $stmtInsert->execute([
                ':user_id'     => $targetUserId,
                ':leave_type'  => $leaveType,
                ':start_date'  => $startDate,
                ':end_date'    => $endDate,
                ':reason'      => $reason . " (ยื่นลาแทนโดย " . $currentUser['name'] . ")",
                ':approved_by' => $currentUser['user_id']
            ]);
            $leaveId = $pdo->lastInsertId();

            // 5. ตัดโควตาวันลาทันที
            $stmtDeduct = $pdo->prepare("
                UPDATE leave_balances 
                SET used_days = used_days + :days 
                WHERE user_id = :uid AND leave_type = :ltype
            ");
            $stmtDeduct->execute([
                ':days'  => $requestedDays,
                ':uid'   => $targetUserId,
                ':ltype' => $leaveType
            ]);

            $pdo->commit();

            sendJsonResponse(true, "ยื่นขอลางานแทน {$targetUser['name']} เรียบร้อยแล้ว (จำนวน $requestedDays วัน) และอนุมัติอัตโนมัติ", [
                'leave_id'       => $leaveId,
                'target_name'    => $targetUser['name'],
                'requested_days' => $requestedDays
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการยื่นลาแทนพนักงาน: ' . $e->getMessage(), null, 500);
        }
    }

    // -------------------------------------------------------------
    // กรณีที่ 2: แก้ไขคำขอลางาน (action = 'edit')
    // -------------------------------------------------------------
    if ($action === 'edit') {
        $leaveId   = (int)($inputData['leave_id'] ?? 0);
        $leaveType = trim($inputData['leave_type'] ?? '');
        $startDate = trim($inputData['start_date'] ?? '');
        $endDate   = trim($inputData['end_date'] ?? '');
        $reason    = trim($inputData['reason'] ?? '');

        if (!$leaveId || !in_array($leaveType, ['sick', 'personal', 'vacation']) || empty($startDate) || empty($endDate) || empty($reason)) {
            sendJsonResponse(false, 'ข้อมูลไม่ถูกต้อง กรุณากรอกข้อมูลใบลาให้ครบถ้วน', null, 400);
        }

        if ($startDate > $endDate) {
            sendJsonResponse(false, 'วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด', null, 400);
        }

        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT * FROM leave_requests WHERE leave_id = :leave_id FOR UPDATE");
            $stmtCheck->execute([':leave_id' => $leaveId]);
            $leave = $stmtCheck->fetch();

            if (!$leave) {
                $pdo->rollBack();
                sendJsonResponse(false, 'ไม่พบข้อมูลคำขอลางานนี้', null, 404);
            }

            $userId = (int)$leave['user_id'];

            // ตรวจสอบวันลาซ้อนทับกับคำขออื่น
            $stmtOverlap = $pdo->prepare("
                SELECT leave_id FROM leave_requests 
                WHERE user_id = :user_id AND leave_id != :leave_id 
                  AND status IN ('pending', 'approved') 
                  AND NOT (end_date < :start_date OR start_date > :end_date)
                LIMIT 1
            ");
            $stmtOverlap->execute([
                ':user_id'    => $userId,
                ':leave_id'   => $leaveId,
                ':start_date' => $startDate,
                ':end_date'   => $endDate
            ]);
            if ($stmtOverlap->fetch()) {
                $pdo->rollBack();
                sendJsonResponse(false, 'ช่วงวันที่เลือกซ้ำซ้อนกับคำขอลางานอื่นของพนักงาน', null, 400);
            }

            $oldStart = new DateTime($leave['start_date']);
            $oldEnd   = new DateTime($leave['end_date']);
            $oldDays  = $oldStart->diff($oldEnd)->days + 1;

            $newStart = new DateTime($startDate);
            $newEnd   = new DateTime($endDate);
            $newDays  = $newStart->diff($newEnd)->days + 1;

            // หากเป็นใบลาที่อนุมัติแล้ว ต้องคืนโควตาเก่า แล้วตัดโควตาใหม่
            if ($leave['status'] === 'approved') {
                // คืนโควตาเก่า
                $stmtRevert = $pdo->prepare("
                    UPDATE leave_balances 
                    SET used_days = GREATEST(0, used_days - :old_days) 
                    WHERE user_id = :user_id AND leave_type = :leave_type
                ");
                $stmtRevert->execute([
                    ':old_days'   => $oldDays,
                    ':user_id'    => $userId,
                    ':leave_type' => $leave['leave_type']
                ]);

                // ตรวจสอบโควตาใหม่
                $stmtBal = $pdo->prepare("
                    SELECT total_quota, used_days FROM leave_balances 
                    WHERE user_id = :user_id AND leave_type = :leave_type
                ");
                $stmtBal->execute([':user_id' => $userId, ':leave_type' => $leaveType]);
                $bal = $stmtBal->fetch();

                if ($bal) {
                    $remaining = (int)$bal['total_quota'] - (int)$bal['used_days'];
                    if ($remaining < $newDays) {
                        $pdo->rollBack();
                        sendJsonResponse(false, "โควตาวันลาไม่เพียงพอ (ต้องการ {$newDays} วัน แต่สิทธิ์คงเหลือ {$remaining} วัน)", null, 400);
                    }
                }

                // ตัดโควตาใหม่
                $stmtDeduct = $pdo->prepare("
                    UPDATE leave_balances 
                    SET used_days = used_days + :new_days 
                    WHERE user_id = :user_id AND leave_type = :leave_type
                ");
                $stmtDeduct->execute([
                    ':new_days'   => $newDays,
                    ':user_id'    => $userId,
                    ':leave_type' => $leaveType
                ]);
            }

            // อัปเดตข้อมูลใบลา
            $stmtUpd = $pdo->prepare("
                UPDATE leave_requests 
                SET leave_type = :leave_type, start_date = :start_date, end_date = :end_date, reason = :reason
                WHERE leave_id = :leave_id
            ");
            $stmtUpd->execute([
                ':leave_type' => $leaveType,
                ':start_date' => $startDate,
                ':end_date'   => $endDate,
                ':reason'     => $reason,
                ':leave_id'   => $leaveId
            ]);

            $pdo->commit();
            sendJsonResponse(true, 'แก้ไขคำขอลางานเรียบร้อยแล้ว');

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการแก้ไขใบลา: ' . $e->getMessage(), null, 500);
        }
    }

    // -------------------------------------------------------------
    // กรณีที่ 3: ยกเลิก/ลบใบลา (action = 'delete')
    // -------------------------------------------------------------
    if ($action === 'delete') {
        $leaveId = (int)($inputData['leave_id'] ?? 0);
        if (!$leaveId) {
            sendJsonResponse(false, 'ไม่ระบุ leave_id', null, 400);
        }

        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT * FROM leave_requests WHERE leave_id = :leave_id FOR UPDATE");
            $stmtCheck->execute([':leave_id' => $leaveId]);
            $leave = $stmtCheck->fetch();

            if (!$leave) {
                $pdo->rollBack();
                sendJsonResponse(false, 'ไม่พบข้อมูลคำขอลางานนี้', null, 404);
            }

            // หากสถานะเป็นอนุมัติแล้ว คืนโควตาวันลากลับเข้าสู่ระบบ
            if ($leave['status'] === 'approved') {
                $startDate = new DateTime($leave['start_date']);
                $endDate   = new DateTime($leave['end_date']);
                $daysCount = $startDate->diff($endDate)->days + 1;

                $stmtRevert = $pdo->prepare("
                    UPDATE leave_balances 
                    SET used_days = GREATEST(0, used_days - :days) 
                    WHERE user_id = :user_id AND leave_type = :leave_type
                ");
                $stmtRevert->execute([
                    ':days'       => $daysCount,
                    ':user_id'    => $leave['user_id'],
                    ':leave_type' => $leave['leave_type']
                ]);
            }

            // ลบใบลา
            $stmtDel = $pdo->prepare("DELETE FROM leave_requests WHERE leave_id = :leave_id");
            $stmtDel->execute([':leave_id' => $leaveId]);

            $pdo->commit();
            sendJsonResponse(true, 'ลบคำขอลางานเรียบร้อยแล้ว (คืนโควตาวันลาเข้าสู่ระบบสำเร็จ)');

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการลบใบลา: ' . $e->getMessage(), null, 500);
        }
    }

    // -------------------------------------------------------------
    // กรณีที่ 4: อนุมัติ (Approve) หรือ ปฏิเสธ (Reject) ใบลา
    // -------------------------------------------------------------
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
