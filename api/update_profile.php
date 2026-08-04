<?php
/**
 * API: User Profile & Change Password (update_profile.php)
 * Method: GET (Fetch profile & quotas), POST (Update name & password)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้งาน
$currentUser = requireAuth();

$pdo = getDBConnection();

// -------------------------------------------------------------
// GET: ดึงข้อมูลโปรไฟล์และโควตาของตนเอง
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.emp_code, u.name, u.role, u.dept_id, d.dept_name, u.avatar_url, u.phone, u.created_at
            FROM users u
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $currentUser['user_id']]);
        $user = $stmt->fetch();

        // ดึงโควตาวันลา
        $stmtBal = $pdo->prepare("SELECT leave_type, total_quota, used_days FROM leave_balances WHERE user_id = :user_id");
        $stmtBal->execute([':user_id' => $currentUser['user_id']]);
        $balances = $stmtBal->fetchAll();

        $user['balances'] = $balances;
        $user['created_at_th'] = date('d/m/Y', strtotime($user['created_at']));

        sendJsonResponse(true, 'ดึงข้อมูลโปรไฟล์สำเร็จ', $user);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงโปรไฟล์: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: แก้ไขข้อมูลส่วนตัว (ชื่อ, เบอร์โทร) หรือ เปลี่ยนรหัสผ่าน
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action    = trim($inputData['action'] ?? '');

    try {
        // Case 1: แก้ไขข้อมูลส่วนตัว (ชื่อ และ เบอร์โทรศัพท์)
        if ($action === 'update_name' || $action === 'update_profile') {
            $name  = trim($inputData['name'] ?? '');
            $phone = trim($inputData['phone'] ?? '');

            if (empty($name)) {
                sendJsonResponse(false, 'กรุณากรอกชื่อ-นามสกุล', null, 400);
            }

            $stmtUpd = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE user_id = :user_id");
            $stmtUpd->execute([
                ':name'    => $name,
                ':phone'   => $phone ?: null,
                ':user_id' => $currentUser['user_id']
            ]);

            // อัปเดต Session
            $_SESSION['name'] = $name;

            sendJsonResponse(true, 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว');
        }

        // Case 2: เปลี่ยนรหัสผ่าน (Change Password)
        if ($action === 'change_password') {
            $currentPassword = trim($inputData['current_password'] ?? '');
            $newPassword     = trim($inputData['new_password'] ?? '');

            if (empty($currentPassword) || empty($newPassword)) {
                sendJsonResponse(false, 'กรุณากรอกรหัสผ่านปัจจุบันและรหัสผ่านใหม่', null, 400);
            }

            if (strlen($newPassword) < 6) {
                sendJsonResponse(false, 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร', null, 400);
            }

            // ตรวจสอบรหัสผ่านปัจจุบัน
            $stmtUser = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id LIMIT 1");
            $stmtUser->execute([':user_id' => $currentUser['user_id']]);
            $userData = $stmtUser->fetch();

            if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
                sendJsonResponse(false, 'รหัสผ่านปัจจุบันไม่ถูกต้อง', null, 400);
            }

            // แฮชรหัสผ่านใหม่
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtPass = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :user_id");
            $stmtPass->execute([':hash' => $newHash, ':user_id' => $currentUser['user_id']]);

            sendJsonResponse(true, 'เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว');
        }

        sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการอัปเดตโปรไฟล์: ' . $e->getMessage(), null, 500);
    }
}
