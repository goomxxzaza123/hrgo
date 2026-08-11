<?php
/**
 * API: Admin Shift Roster & Schedule Management (admin_roster.php)
 * Method: GET (Fetch monthly roster), POST (Save single/batch roster)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Admin หรือ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// GET: ดึงตารางกะรายเดือนของพนักงาน
// -------------------------------------------------------------
if ($method === 'GET') {
    try {
        $monthStr = trim($_GET['month'] ?? date('Y-m')); // รูปแบบ YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $monthStr = date('Y-m');
        }

        $deptId = isset($_GET['dept_id']) && $_GET['dept_id'] !== '' ? (int)$_GET['dept_id'] : null;

        $startDate = $monthStr . '-01';
        $daysInMonth = (int)date('t', strtotime($startDate));
        $endDate = $monthStr . '-' . sprintf('%02d', $daysInMonth);

        // ดึงรายชื่อพนักงาน
        $whereUser = [];
        $paramsUser = [];
        if ($deptId) {
            $whereUser[] = "u.dept_id = :dept_id";
            $paramsUser[':dept_id'] = $deptId;
        }
        $whereUserSql = !empty($whereUser) ? "WHERE " . implode(" AND ", $whereUser) : "";

        $stmtUsers = $pdo->prepare("
            SELECT u.user_id, u.emp_code, u.name, u.dept_id, d.dept_name, u.shift_type AS default_shift
            FROM users u
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            $whereUserSql
            ORDER BY u.dept_id ASC, u.emp_code ASC
        ");
        $stmtUsers->execute($paramsUser);
        $users = $stmtUsers->fetchAll();

        // ดึงข้อมูลตารางกะของเดือนนี้
        $stmtRoster = $pdo->prepare("
            SELECT roster_id, user_id, roster_date, shift_type, shift_start_time, shift_end_time
            FROM shift_rosters
            WHERE roster_date BETWEEN :start_date AND :end_date
        ");
        $stmtRoster->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
        $rosterRows = $stmtRoster->fetchAll();

        // จัดโครงสร้าง Roster Map [user_id][roster_date] => data
        $rosterMap = [];
        foreach ($rosterRows as $r) {
            $rosterMap[$r['user_id']][$r['roster_date']] = [
                'roster_id'   => $r['roster_id'],
                'shift_type'  => $r['shift_type'],
                'start_time'  => $r['shift_start_time'] ? substr($r['shift_start_time'], 0, 5) : null,
                'end_time'    => $r['shift_end_time'] ? substr($r['shift_end_time'], 0, 5) : null
            ];
        }

        // ดึงวันหยุดบริษัทในเดือนนี้
        $stmtHolidays = $pdo->prepare("
            SELECT holiday_date, holiday_name 
            FROM company_holidays 
            WHERE holiday_date BETWEEN :start_date AND :end_date
        ");
        $stmtHolidays->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $holidayRows = $stmtHolidays->fetchAll();
        $holidayMap = [];
        foreach ($holidayRows as $h) {
            $holidayMap[$h['holiday_date']] = $h['holiday_name'];
        }

        sendJsonResponse(true, 'ดึงข้อมูลตารางกะรายเดือนสำเร็จ', [
            'month'          => $monthStr,
            'days_in_month'  => $daysInMonth,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'users'          => $users,
            'roster_map'     => $rosterMap,
            'holiday_map'    => $holidayMap
        ]);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลตารางกะ: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: บันทึก/แก้ไขตารางกะ (Single หรือ Batch)
// -------------------------------------------------------------
if ($method === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action    = trim($inputData['action'] ?? '');

    // 1. บันทึกกะรายวันทีละรายการ (save_single)
    if ($action === 'save_single') {
        $userId    = (int)($inputData['user_id'] ?? 0);
        $dateStr   = trim($inputData['date'] ?? '');
        $shiftType = trim($inputData['shift_type'] ?? 'day');

        if (!$userId || empty($dateStr) || !in_array($shiftType, ['day', 'night', 'off'])) {
            sendJsonResponse(false, 'ข้อมูลไม่ถูกต้อง (กรุณาระบุ user_id, date, shift_type)', null, 400);
        }

        $startTime = ($shiftType === 'off') ? null : ($shiftType === 'night' ? '20:00:00' : '08:00:00');
        $endTime   = ($shiftType === 'off') ? null : ($shiftType === 'night' ? '05:00:00' : '17:00:00');

        try {
            $stmtUpsert = $pdo->prepare("
                INSERT INTO shift_rosters (user_id, roster_date, shift_type, shift_start_time, shift_end_time, created_by)
                VALUES (:user_id, :roster_date, :shift_type, :shift_start_time, :shift_end_time, :created_by)
                ON DUPLICATE KEY UPDATE
                    shift_type = VALUES(shift_type),
                    shift_start_time = VALUES(shift_start_time),
                    shift_end_time = VALUES(shift_end_time),
                    created_by = VALUES(created_by)
            ");
            $stmtUpsert->execute([
                ':user_id'          => $userId,
                ':roster_date'       => $dateStr,
                ':shift_type'        => $shiftType,
                ':shift_start_time'  => $startTime,
                ':shift_end_time'    => $endTime,
                ':created_by'        => $currentUser['user_id']
            ]);

            // คำนวณสถานะการลงเวลาของพนักงานในวันนั้นใหม่ทันที (หากมีบันทึกการลงเวลาแล้ว)
            recalculateAttendanceOnShiftChange($pdo, $userId, $dateStr);

            sendJsonResponse(true, "บันทึกตารางกะวันที่ {$dateStr} เรียบร้อยแล้ว", [
                'user_id'    => $userId,
                'date'       => $dateStr,
                'shift_type' => $shiftType
            ]);

        } catch (PDOException $e) {
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกกะ: ' . $e->getMessage(), null, 500);
        }
    }

    // 2. จัดกะแบบกลุ่มสำหรับทั้งเดือน (batch_save)
    if ($action === 'batch_save') {
        $monthStr     = trim($inputData['month'] ?? date('Y-m'));
        $deptId       = isset($inputData['dept_id']) && $inputData['dept_id'] !== '' ? (int)$inputData['dept_id'] : null;
        $pattern      = trim($inputData['pattern'] ?? 'default_5day'); // 'default_5day', 'default_6day', 'all_day', 'all_night', 'all_off'
        $targetUserIds = $inputData['target_user_ids'] ?? [];

        if (!preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            sendJsonResponse(false, 'รูปแบบเดือนไม่ถูกต้อง', null, 400);
        }

        try {
            $pdo->beginTransaction();

            // ดึงรายชื่อพนักงานเป้าหมาย
            if (!empty($targetUserIds) && is_array($targetUserIds)) {
                $placeholders = implode(',', array_fill(0, count($targetUserIds), '?'));
                $stmtU = $pdo->prepare("SELECT user_id FROM users WHERE user_id IN ($placeholders)");
                $stmtU->execute($targetUserIds);
            } elseif ($deptId) {
                $stmtU = $pdo->prepare("SELECT user_id FROM users WHERE dept_id = :dept_id");
                $stmtU->execute([':dept_id' => $deptId]);
            } else {
                $stmtU = $pdo->query("SELECT user_id FROM users");
            }
            $targetUsers = $stmtU->fetchAll(PDO::FETCH_COLUMN);

            if (empty($targetUsers)) {
                $pdo->rollBack();
                sendJsonResponse(false, 'ไม่พบพนักงานเป้าหมายสำหรับจัดกะ', null, 404);
            }

            $startDateInput = trim($inputData['start_date'] ?? '');
            $endDateInput   = trim($inputData['end_date'] ?? '');

            if (!empty($startDateInput) && !empty($endDateInput)) {
                $startObj = new DateTime($startDateInput);
                $endObj   = new DateTime($endDateInput);
            } else {
                $startDate   = $monthStr . '-01';
                $daysInMonth = (int)date('t', strtotime($startDate));
                $startObj    = new DateTime($startDate);
                $endObj      = new DateTime($monthStr . '-' . sprintf('%02d', $daysInMonth));
            }

            $stmtUpsert = $pdo->prepare("
                INSERT INTO shift_rosters (user_id, roster_date, shift_type, shift_start_time, shift_end_time, created_by)
                VALUES (:user_id, :roster_date, :shift_type, :shift_start_time, :shift_end_time, :created_by)
                ON DUPLICATE KEY UPDATE
                    shift_type = VALUES(shift_type),
                    shift_start_time = VALUES(shift_start_time),
                    shift_end_time = VALUES(shift_end_time),
                    created_by = VALUES(created_by)
            ");

            $savedCount = 0;

            foreach ($targetUsers as $uId) {
                $cur = clone $startObj;
                while ($cur <= $endObj) {
                    $curDateStr = $cur->format('Y-m-d');
                    $dayOfWeek  = $cur->format('D'); // 'Mon', 'Tue', ..., 'Sun'

                    // คำนวณประเภทกะตาม Pattern
                    $sType = 'day';
                    if ($pattern === 'default_6day_night') {
                        // จันทร์-เสาร์ = กะดึก, อาทิตย์ = วันหยุด (off)
                        $sType = ($dayOfWeek === 'Sun') ? 'off' : 'night';
                    } elseif ($pattern === 'default_6day') {
                        // จันทร์-เสาร์ = กะเช้า, อาทิตย์ = วันหยุด (off)
                        $sType = ($dayOfWeek === 'Sun') ? 'off' : 'day';
                    } elseif ($pattern === 'all_day') {
                        $sType = 'day';
                    } elseif ($pattern === 'all_night') {
                        $sType = 'night';
                    } elseif ($pattern === 'all_off') {
                        $sType = 'off';
                    }

                    $sStart = ($sType === 'off') ? null : ($sType === 'night' ? '20:00:00' : '08:00:00');
                    $sEnd   = ($sType === 'off') ? null : ($sType === 'night' ? '05:00:00' : '17:00:00');

                    $stmtUpsert->execute([
                        ':user_id'          => $uId,
                        ':roster_date'       => $curDateStr,
                        ':shift_type'        => $sType,
                        ':shift_start_time'  => $sStart,
                        ':shift_end_time'    => $sEnd,
                        ':created_by'        => $currentUser['user_id']
                    ]);
                    $savedCount++;
                    recalculateAttendanceOnShiftChange($pdo, $uId, $curDateStr);

                    $cur->modify('+1 day');
                }
            }

            $pdo->commit();

            sendJsonResponse(true, "จัดตารางกะประจำเดือน {$monthStr} สำเร็จสำหรับพนักงาน " . count($targetUsers) . " คน", [
                'month'       => $monthStr,
                'user_count'  => count($targetUsers),
                'saved_count' => $savedCount
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการจัดกะแบบกลุ่ม: ' . $e->getMessage(), null, 500);
        }
    }

    // 3. ล้างตารางกะของเดือน (clear_month)
    if ($action === 'clear_month') {
        $monthStr = trim($inputData['month'] ?? '');
        $deptId   = isset($inputData['dept_id']) && $inputData['dept_id'] !== '' ? (int)$inputData['dept_id'] : null;

        if (!preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            sendJsonResponse(false, 'รูปแบบเดือนไม่ถูกต้อง', null, 400);
        }

        $startDate = $monthStr . '-01';
        $endDate   = $monthStr . '-' . sprintf('%02d', date('t', strtotime($startDate)));

        try {
            if ($deptId) {
                $stmtDel = $pdo->prepare("
                    DELETE sr FROM shift_rosters sr
                    JOIN users u ON sr.user_id = u.user_id
                    WHERE u.dept_id = :dept_id AND sr.roster_date BETWEEN :start_date AND :end_date
                ");
                $stmtDel->execute([
                    ':dept_id'    => $deptId,
                    ':start_date' => $startDate,
                    ':end_date'   => $endDate
                ]);
            } else {
                $stmtDel = $pdo->prepare("
                    DELETE FROM shift_rosters 
                    WHERE roster_date BETWEEN :start_date AND :end_date
                ");
                $stmtDel->execute([
                    ':start_date' => $startDate,
                    ':end_date'   => $endDate
                ]);
            }

            sendJsonResponse(true, "ล้างข้อมูลตารางกะประจำเดือน {$monthStr} เรียบร้อยแล้ว");

        } catch (PDOException $e) {
            sendJsonResponse(false, 'เกิดข้อผิดพลาดในการล้างตารางกะ: ' . $e->getMessage(), null, 500);
        }
    }

    sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);
}

/**
 * คำนวณคำนวณสถานะการลงเวลาของพนักงานใหม่เมื่อมีการเปลี่ยนกะในตารางกะ
 */
function recalculateAttendanceOnShiftChange($pdo, $userId, $workDate) {
    try {
        $stmtAtt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = :uid AND work_date = :wdate LIMIT 1");
        $stmtAtt->execute([':uid' => $userId, ':wdate' => $workDate]);
        $att = $stmtAtt->fetch();

        if ($att && !empty($att['check_in_time'])) {
            $shiftInfo = getUserShiftForDate($pdo, $userId, $workDate);
            $shiftType = $shiftInfo['shift_type'];
            $shiftStartStr = $shiftInfo['shift_start_time'];

            $shiftStartTs = strtotime($workDate . ' ' . $shiftStartStr);
            $checkInTs    = strtotime($att['check_in_time']);

            $lateMinutes = 0;
            if ($checkInTs > $shiftStartTs + 59) {
                $status = 'late';
                $lateMinutes = (int)floor(($checkInTs - $shiftStartTs) / 60);
            } else {
                $status = 'on_time';
            }

            $workHours = $att['work_hours'];
            $otHours   = $att['ot_hours'];

            if (!empty($att['check_out_time'])) {
                $checkOutTs = strtotime($att['check_out_time']);
                $calc = calculateWorkAndOtHours($pdo, $workDate, $checkInTs, $checkOutTs, $shiftType, $userId);
                $workHours = $calc['work_hours'];
                $otHours   = $calc['ot_hours'];
            }

            $stmtUpd = $pdo->prepare("
                UPDATE attendances 
                SET late_minutes = :late_m, status = :status, work_hours = :wh, ot_hours = :ot
                WHERE attendance_id = :aid
            ");
            $stmtUpd->execute([
                ':late_m' => $lateMinutes,
                ':status' => $status,
                ':wh'     => $workHours,
                ':ot'     => $otHours,
                ':aid'    => $att['attendance_id']
            ]);
        }
    } catch (Exception $e) {
        // Ignore error
    }
}
