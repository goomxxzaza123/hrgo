<?php
/**
 * API: Get Logged In User Info (auth_me.php)
 * Method: GET
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ว่ามี Session หรือไม่
$currentUser = requireAuth();

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.emp_code, u.name, u.role, u.dept_id, d.dept_name, u.avatar_url, u.phone, 
               u.birth_date, u.start_work_date, u.created_at 
        FROM users u 
        LEFT JOIN departments d ON u.dept_id = d.dept_id 
        WHERE u.user_id = :user_id AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $currentUser['user_id']]);
    $userProfile = $stmt->fetch();

    if (!$userProfile) {
        // บัญชีโดนปิดไปแล้ว
        session_destroy();
        sendJsonResponse(false, 'ไม่พบข้อมูลผู้ใช้ หรือบัญชีถูกระงับ', null, 401);
    }

    // คำนวณอายุ (Age)
    $ageText = '-';
    if (!empty($userProfile['birth_date']) && $userProfile['birth_date'] !== '0000-00-00') {
        $bDate = new DateTime($userProfile['birth_date']);
        $today = new DateTime();
        $diff  = $today->diff($bDate);
        $ageText = $diff->y . ' ปี';
    }

    // คำนวณอายุงาน (Work Tenure)
    $tenureText = '-';
    if (!empty($userProfile['start_work_date']) && $userProfile['start_work_date'] !== '0000-00-00') {
        $sDate = new DateTime($userProfile['start_work_date']);
        $today = new DateTime();
        $diff  = $today->diff($sDate);
        if ($diff->y > 0) {
            $tenureText = $diff->y . ' ปี ' . $diff->m . ' เดือน';
        } elseif ($diff->m > 0) {
            $tenureText = $diff->m . ' เดือน ' . $diff->d . ' วัน';
        } else {
            $tenureText = $diff->d . ' วัน';
        }
    }

    $userProfile['birth_date_th'] = (!empty($userProfile['birth_date']) && $userProfile['birth_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($userProfile['birth_date'])) : '-';
    $userProfile['start_work_th'] = (!empty($userProfile['start_work_date']) && $userProfile['start_work_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($userProfile['start_work_date'])) : '-';
    $userProfile['age']           = $ageText;
    $userProfile['work_tenure']   = $tenureText;

    sendJsonResponse(true, 'ดึงข้อมูลผู้ใช้งานสำเร็จ', $userProfile);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: ' . $e->getMessage(), null, 500);
}
