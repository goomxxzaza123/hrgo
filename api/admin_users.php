<?php
/**
 * API: Admin Employee & Quota Management (admin_users.php)
 * Method: GET (List users), POST (Create/Update/Toggle/Quota)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Admin หรือ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$pdo = getDBConnection();

// -------------------------------------------------------------
// GET: ดึงรายชื่อพนักงานและโควตาวันลา
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // ดึงรายการแผนกทั้งหมด
        $stmtDepts = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_id ASC");
        $departments = $stmtDepts->fetchAll();

        // ดึงรายการพนักงาน
        $stmtUsers = $pdo->query("
            SELECT u.user_id, u.emp_code, u.name, u.role, u.dept_id, d.dept_name, u.avatar_url, u.phone,
                   u.shift_type, u.shift_start_time, u.shift_end_time, u.ot_cap_time, u.is_active, u.created_at 
            FROM users u
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            ORDER BY u.user_id ASC
        ");
        $users = $stmtUsers->fetchAll();

        // ดึงโควตาวันลาของทุกคน
        $stmtBalances = $pdo->query("SELECT user_id, leave_type, total_quota, used_days FROM leave_balances");
        $allBalances = $stmtBalances->fetchAll();

        $balancesGrouped = [];
        foreach ($allBalances as $b) {
            $balancesGrouped[$b['user_id']][$b['leave_type']] = [
                'total_quota' => (int)$b['total_quota'],
                'used_days'   => (int)$b['used_days'],
                'remaining'   => max(0, (int)$b['total_quota'] - (int)$b['used_days'])
            ];
        }

        $formattedUsers = array_map(function($u) use ($balancesGrouped) {
            $shiftLabel = ($u['shift_type'] === 'night') ? '🌙 กะกลางคืน (20:00-05:00)' : '☀️ กะกลางวัน (08:00-17:00)';
            return [
                'user_id'          => $u['user_id'],
                'emp_code'         => $u['emp_code'],
                'name'             => $u['name'],
                'role'             => $u['role'],
                'dept_id'          => $u['dept_id'],
                'dept_name'        => $u['dept_name'] ?? 'ไม่ระบุ',
                'avatar_url'       => $u['avatar_url'],
                'phone'            => $u['phone'] ?? '',
                'shift_type'       => $u['shift_type'] ?? 'day',
                'shift_start_time' => substr($u['shift_start_time'] ?? '08:00:00', 0, 5),
                'shift_end_time'   => substr($u['shift_end_time'] ?? '17:00:00', 0, 5),
                'ot_cap_time'      => substr($u['ot_cap_time'] ?? '20:00:00', 0, 5),
                'shift_label'      => $shiftLabel,
                'is_active'        => (bool)$u['is_active'],
                'created_at'       => date('d/m/Y', strtotime($u['created_at'])),
                'balances'         => $balancesGrouped[$u['user_id']] ?? []
            ];
        }, $users);

        sendJsonResponse(true, 'ดึงข้อมูลพนักงานสำเร็จ', [
            'departments' => $departments,
            'users'       => $formattedUsers
        ]);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลพนักงาน: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: สร้าง / แก้ไข / เปลี่ยนสถานะ / ตั้งโควตาพนักงาน
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action    = trim($inputData['action'] ?? '');

    try {
        // Case 1: สร้างพนักงานใหม่ (Create User)
        if ($action === 'create') {
            $empCode  = trim($inputData['emp_code'] ?? '');
            $name     = trim($inputData['name'] ?? '');
            $password = trim($inputData['password'] ?? '');
            $role     = trim($inputData['role'] ?? 'employee');
            $deptId   = (int)($inputData['dept_id'] ?? 0);
            $phone    = trim($inputData['phone'] ?? '');

            if (empty($empCode) || empty($name) || empty($password)) {
                sendJsonResponse(false, 'กรุณากรอกรหัสพนักงาน ชื่อ-นามสกุล และรหัสผ่าน', null, 400);
            }

            // เช็ครหัสพนักงานซ้ำ
            $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE emp_code = :emp_code LIMIT 1");
            $stmtCheck->execute([':emp_code' => $empCode]);
            if ($stmtCheck->fetch()) {
                sendJsonResponse(false, 'รหัสพนักงานนี้มีในระบบแล้ว', null, 400);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("
                INSERT INTO users (emp_code, name, password_hash, role, dept_id, phone, is_active) 
                VALUES (:emp_code, :name, :password_hash, :role, :dept_id, :phone, 1)
            ");
            $stmtIns->execute([
                ':emp_code'      => $empCode,
                ':name'          => $name,
                ':password_hash' => $passwordHash,
                ':role'          => $role,
                ':dept_id'       => $deptId ?: null,
                ':phone'         => $phone ?: null
            ]);
            $newUserId = $pdo->lastInsertId();

            // เพิ่มโควตาวันลาเริ่มต้นอัตโนมัติ (Sick: 30, Personal: 6, Vacation: 10)
            $stmtBal = $pdo->prepare("
                INSERT INTO leave_balances (user_id, leave_type, total_quota, used_days) VALUES
                (:user_id, 'sick', 30, 0),
                (:user_id, 'personal', 6, 0),
                (:user_id, 'vacation', 10, 0)
            ");
            $stmtBal->execute([':user_id' => $newUserId]);

            $pdo->commit();

            sendJsonResponse(true, 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว', ['user_id' => $newUserId]);
        }

        // Case 2: แก้ไขข้อมูลพนักงาน & รีเซ็ตรหัสผ่าน (Update User)
        if ($action === 'update') {
            $userId   = (int)($inputData['user_id'] ?? 0);
            $empCode  = trim($inputData['emp_code'] ?? '');
            $name     = trim($inputData['name'] ?? '');
            $role     = trim($inputData['role'] ?? 'employee');
            $deptId   = (int)($inputData['dept_id'] ?? 0);
            $phone    = trim($inputData['phone'] ?? '');
            $password = trim($inputData['password'] ?? '');

            if (!$userId || empty($name) || empty($empCode)) {
                sendJsonResponse(false, 'กรุณากรอกรหัสพนักงานและชื่อ-นามสกุล', null, 400);
            }

            // เช็ครหัสพนักงานซ้ำกับคนอื่น
            $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE emp_code = :emp_code AND user_id != :user_id LIMIT 1");
            $stmtCheck->execute([':emp_code' => $empCode, ':user_id' => $userId]);
            if ($stmtCheck->fetch()) {
                sendJsonResponse(false, 'รหัสพนักงานนี้ซ้ำกับพนักงานคนอื่นในระบบ', null, 400);
            }

            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("
                    UPDATE users 
                    SET emp_code = :emp_code, name = :name, role = :role, dept_id = :dept_id, phone = :phone, password_hash = :hash 
                    WHERE user_id = :user_id
                ");
                $stmtUpd->execute([
                    ':emp_code' => $empCode,
                    ':name'     => $name,
                    ':role'     => $role,
                    ':dept_id'  => $deptId ?: null,
                    ':phone'    => $phone ?: null,
                    ':hash'     => $passwordHash,
                    ':user_id'  => $userId
                ]);
            } else {
                $stmtUpd = $pdo->prepare("
                    UPDATE users 
                    SET emp_code = :emp_code, name = :name, role = :role, dept_id = :dept_id, phone = :phone 
                    WHERE user_id = :user_id
                ");
                $stmtUpd->execute([
                    ':emp_code' => $empCode,
                    ':name'     => $name,
                    ':role'     => $role,
                    ':dept_id'  => $deptId ?: null,
                    ':phone'    => $phone ?: null,
                    ':user_id'  => $userId
                ]);
            }

            sendJsonResponse(true, 'อัปเดตข้อมูลพนักงานเรียบร้อยแล้ว', ['user_id' => $userId]);
        }

        // Case 3: เปลี่ยนสถานะเปิดใช้งาน / ระงับพนักงาน (Toggle Active)
        if ($action === 'toggle_active') {
            $userId = (int)($inputData['user_id'] ?? 0);
            if (!$userId) {
                sendJsonResponse(false, 'กรุณาระบุพนักงาน', null, 400);
            }

            if ($userId === $currentUser['user_id']) {
                sendJsonResponse(false, 'คุณไม่สามารถระงับบัญชีของตัวเองได้', null, 400);
            }

            $stmtToggle = $pdo->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE user_id = :user_id");
            $stmtToggle->execute([':user_id' => $userId]);

            sendJsonResponse(true, 'สลับสถานะการใช้งานพนักงานเรียบร้อยแล้ว');
        }

        // Case 4: ลบพนักงานออกจากระบบ (Delete User)
        if ($action === 'delete') {
            $userId = (int)($inputData['user_id'] ?? 0);
            if (!$userId) {
                sendJsonResponse(false, 'กรุณาระบุพนักงาน', null, 400);
            }

            if ($userId === $currentUser['user_id']) {
                sendJsonResponse(false, 'คุณไม่สามารถลบบัญชีของตัวเองได้', null, 400);
            }

            $stmtDel = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmtDel->execute([':user_id' => $userId]);

            sendJsonResponse(true, 'ลบพนักงานออกจากระบบเรียบร้อยแล้ว');
        }

        // Case 5: ตั้งค่าโควตาวันลา (Update Quotas)
        if ($action === 'update_quota') {
            $userId    = (int)($inputData['user_id'] ?? 0);
            $sick      = (int)($inputData['sick_quota'] ?? 30);
            $personal  = (int)($inputData['personal_quota'] ?? 6);
            $vacation  = (int)($inputData['vacation_quota'] ?? 10);

            if (!$userId) {
                sendJsonResponse(false, 'กรุณาระบุพนักงาน', null, 400);
            }

            $stmtUpdBal = $pdo->prepare("
                INSERT INTO leave_balances (user_id, leave_type, total_quota, used_days) 
                VALUES (:user_id, :leave_type, :quota, 0)
                ON DUPLICATE KEY UPDATE total_quota = :quota
            ");

            $stmtUpdBal->execute([':user_id' => $userId, ':leave_type' => 'sick', ':quota' => $sick]);
            $stmtUpdBal->execute([':user_id' => $userId, ':leave_type' => 'personal', ':quota' => $personal]);
            $stmtUpdBal->execute([':user_id' => $userId, ':leave_type' => 'vacation', ':quota' => $vacation]);

            sendJsonResponse(true, 'อัปเดตโควตาวันลาเรียบร้อยแล้ว');
        }

        // Case 6: กำหนดกะการทำงานพนักงาน (Update Work Shift)
        if ($action === 'update_shift') {
            $userId     = (int)($inputData['user_id'] ?? 0);
            $shiftType  = trim($inputData['shift_type'] ?? 'day');
            $shiftStart = trim($inputData['shift_start_time'] ?? '08:00:00');
            $shiftEnd   = trim($inputData['shift_end_time'] ?? '17:00:00');
            $otCapTime  = trim($inputData['ot_cap_time'] ?? '20:00:00');

            if (!$userId) {
                sendJsonResponse(false, 'กรุณาระบุพนักงาน', null, 400);
            }

            if ($shiftType === 'night') {
                $shiftStart = '20:00:00';
                $shiftEnd   = '05:00:00';
                $otCapTime  = '08:00:00';
            } elseif ($shiftType === 'day') {
                $shiftStart = '08:00:00';
                $shiftEnd   = '17:00:00';
                $otCapTime  = '20:00:00';
            }

            $stmtShift = $pdo->prepare("
                UPDATE users 
                SET shift_type = :shift_type, 
                    shift_start_time = :shift_start, 
                    shift_end_time = :shift_end, 
                    ot_cap_time = :ot_cap 
                WHERE user_id = :user_id
            ");
            $stmtShift->execute([
                ':shift_type' => $shiftType,
                ':shift_start' => $shiftStart,
                ':shift_end'   => $shiftEnd,
                ':ot_cap'      => $otCapTime,
                ':user_id'     => $userId
            ]);

            // คำนวณสถานะและเวลาสายของวันนี้ย้อนหลังให้อัตโนมัติ (กรณี Admin/Manager เพิ่งเปลี่ยนกะให้หลังพนักงานลงเวลา)
            $todayDate = date('Y-m-d');
            $stmtTodayAtt = $pdo->prepare("
                SELECT attendance_id, check_in_time 
                FROM attendances 
                WHERE user_id = :user_id AND work_date = :today AND check_in_time IS NOT NULL 
                LIMIT 1
            ");
            $stmtTodayAtt->execute([':user_id' => $userId, ':today' => $todayDate]);
            $todayAtt = $stmtTodayAtt->fetch();

            if ($todayAtt && !empty($todayAtt['check_in_time'])) {
                $checkInTs   = strtotime($todayAtt['check_in_time']);
                $shiftStartTs= strtotime($todayDate . ' ' . $shiftStart);

                $recalcLateMins = 0;
                if ($checkInTs > $shiftStartTs) {
                    $recalcStatus = 'late';
                    $recalcLateMins = (int)floor(($checkInTs - $shiftStartTs) / 60);
                } else {
                    $recalcStatus = 'on_time';
                }

                $stmtUpdToday = $pdo->prepare("
                    UPDATE attendances 
                    SET late_minutes = :late_mins, status = :status 
                    WHERE attendance_id = :att_id
                ");
                $stmtUpdToday->execute([
                    ':late_mins' => $recalcLateMins,
                    ':status'    => $recalcStatus,
                    ':att_id'    => $todayAtt['attendance_id']
                ]);
            }

            sendJsonResponse(true, 'อัปเดตกะการทำงานและคำนวณสถานะลงเวลาวันนี้ใหม่เรียบร้อยแล้ว');
        }

        sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
    }
}
