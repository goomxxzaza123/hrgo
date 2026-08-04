<?php
/**
 * API: Team Colleagues Attendance Status (get_team_status.php)
 * Method: GET
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
$currentUser = requireAuth();

try {
    $pdo = getDBConnection();
    $today = date('Y-m-d');

    // ดึงรายชื่อพนักงานทั้งหมดที่ active อยู่ พร้อมสถานะการเข้างานวันนี้
    $sql = "
        SELECT u.user_id, u.emp_code, u.name, u.role, u.dept_id, d.dept_name, u.avatar_url, u.phone,
               a.check_in_time, a.check_out_time, a.status AS checkin_status
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN attendances a ON (u.user_id = a.user_id AND a.work_date = :today)
        WHERE u.is_active = 1
        ORDER BY (a.check_in_time IS NOT NULL) DESC, u.dept_id ASC, u.user_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $members = $stmt->fetchAll();

    $formatted = array_map(function($m) use ($currentUser) {
        $hasCheckedIn  = !empty($m['check_in_time']);
        $hasCheckedOut = !empty($m['check_out_time']);
        $initial = mb_substr($m['name'], 0, 1, 'UTF-8');

        $workState = 'not_started'; // ยังไม่เข้างาน
        $statusLabel = 'ยังไม่เข้างาน';

        if ($hasCheckedIn && !$hasCheckedOut) {
            $workState = 'working'; // กำลังทำงานอยู่
            $statusLabel = ($m['checkin_status'] === 'late') ? 'เข้างานสาย' : 'ตรงเวลา';
        } elseif ($hasCheckedIn && $hasCheckedOut) {
            $workState = 'completed'; // ออกงานเรียบร้อยแล้ว
            $statusLabel = 'ออกงานแล้ว';
        }

        return [
            'user_id'         => $m['user_id'],
            'emp_code'        => $m['emp_code'],
            'name'            => $m['name'],
            'dept_name'       => $m['dept_name'] ?? 'ไม่ระบุ',
            'avatar_url'      => $m['avatar_url'],
            'phone'           => $m['phone'] ?? '-',
            'is_me'           => ($m['user_id'] == $currentUser['user_id']),
            'has_checked_in'  => $hasCheckedIn,
            'has_checked_out' => $hasCheckedOut,
            'work_state'      => $workState,
            'check_in_time'   => $hasCheckedIn ? date('H:i', strtotime($m['check_in_time'])) : null,
            'check_out_time'  => $hasCheckedOut ? date('H:i', strtotime($m['check_out_time'])) : null,
            'status'          => $m['checkin_status'] ?? 'absent',
            'status_label'    => $statusLabel,
            'avatar_initial'  => $initial
        ];
    }, $members);

    sendJsonResponse(true, 'ดึงสถานะเพื่อนร่วมงานสำเร็จ', $formatted);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงสถานะเพื่อนร่วมงาน: ' . $e->getMessage(), null, 500);
}
