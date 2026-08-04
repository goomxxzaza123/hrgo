<?php
/**
 * API: Authentication Login (auth_login.php)
 * Method: POST
 * Body: JSON {"emp_code": "...", "password": "..."}
 */

require_once __DIR__ . '/config.php';

// รับเฉพาะ Request แบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'รองรับเฉพาะ POST Request เท่านั้น', null, 405);
}

// อ่านข้อมูล JSON ที่ส่งมาใน Request Body
$inputData = json_decode(file_get_contents('php://input'), true);

// รองรับทั้งแบบ JSON payload และแบบ Form POSTปกติ
$empCode  = trim($inputData['emp_code'] ?? $_POST['emp_code'] ?? '');
$password = trim($inputData['password'] ?? $_POST['password'] ?? '');

// ตรวจสอบความครบถ้วนของข้อมูล
if (empty($empCode) || empty($password)) {
    sendJsonResponse(false, 'กรุณากรอกรหัสพนักงานและรหัสผ่านให้ครบถ้วน', null, 400);
}

try {
    $pdo = getDBConnection();

    // 1. ค้นหาข้อมูลพนักงานด้วย PDO Prepared Statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.emp_code, u.name, u.password_hash, u.role, u.dept_id, u.is_active, d.dept_name 
        FROM users u 
        LEFT JOIN departments d ON u.dept_id = d.dept_id 
        WHERE u.emp_code = :emp_code 
        LIMIT 1
    ");
    $stmt->execute([':emp_code' => $empCode]);
    $user = $stmt->fetch();

    // 2. ตรวจสอบว่ามีผู้ใช้นี้ในระบบหรือไม่
    if (!$user) {
        sendJsonResponse(false, 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง', null, 401);
    }

    // 3. ตรวจสอบสถานะการใช้งานของพนักงาน
    if (!$user['is_active']) {
        sendJsonResponse(false, 'บัญชีพนักงานนี้ถูกระงับการใช้งาน กรุณาติดต่อ HR', null, 403);
    }

    // 4. ตรวจสอบรหัสผ่านด้วย password_verify()
    if (!password_verify($password, $user['password_hash'])) {
        sendJsonResponse(false, 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง', null, 401);
    }

    // 5. บันทึกข้อมูลพนักงานลง Session
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['emp_code']  = $user['emp_code'];
    $_SESSION['name']      = $user['name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['dept_id']   = $user['dept_id'];
    $_SESSION['dept_name'] = $user['dept_name'] ?? 'ไม่ระบุ';

    // 6. กำหนด Redirect Target ตาม Role
    $redirectUrl = ($user['role'] === 'employee') ? 'employee_home.php' : 'admin/dashboard.php';

    sendJsonResponse(true, 'เข้าสู่ระบบสำเร็จ', [
        'user' => [
            'user_id'   => $user['user_id'],
            'emp_code'  => $user['emp_code'],
            'name'      => $user['name'],
            'role'      => $user['role'],
            'dept_name' => $user['dept_name']
        ],
        'redirect_url' => $redirectUrl
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ: ' . $e->getMessage(), null, 500);
}
