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
        SELECT u.user_id, u.emp_code, u.name, u.role, u.dept_id, d.dept_name, u.avatar_url, u.phone, u.created_at 
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

    sendJsonResponse(true, 'ดึงข้อมูลผู้ใช้งานสำเร็จ', $userProfile);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: ' . $e->getMessage(), null, 500);
}
