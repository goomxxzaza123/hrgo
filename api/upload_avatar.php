<?php
/**
 * API: Admin & Manager Only Employee Avatar Upload (upload_avatar.php)
 * Method: POST (Multipart file upload or Base64 data)
 */

require_once __DIR__ . '/config.php';

// เฉพาะ Admin และ Manager เท่านั้นที่สามารถเปลี่ยน/อัปโหลดรูปโปรไฟล์พนักงานได้
$currentUser = requireAuth(['admin', 'manager']);

$pdo = getDBConnection();
$targetUserId = (int)($_POST['user_id'] ?? 0);

if (!$targetUserId) {
    sendJsonResponse(false, 'กรุณาระบุพนักงานที่ต้องการอัปโหลดรูปโปรไฟล์', null, 400);
}

// ตรวจสอบว่ามีพนักงานในระบบหรือไม่
$stmtUser = $pdo->prepare("SELECT user_id, name, emp_code, avatar_url FROM users WHERE user_id = :user_id LIMIT 1");
$stmtUser->execute([':user_id' => $targetUserId]);
$targetUser = $stmtUser->fetch();

if (!$targetUser) {
    sendJsonResponse(false, 'ไม่พบพนักงานในระบบ', null, 404);
}

$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$avatarPathRelative = null;

// กรณีที่ 1: อัปโหลดผ่าน $_FILES (File Input)
if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['avatar_file']['tmp_name'];
    $fileName    = $_FILES['avatar_file']['name'];
    $fileSize    = $_FILES['avatar_file']['size'];
    $fileType    = $_FILES['avatar_file']['type'];

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5 MB)
    if ($fileSize > 5 * 1024 * 1024) {
        sendJsonResponse(false, 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5 MB', null, 400);
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowedExts)) {
        sendJsonResponse(false, 'รองรับเฉพาะไฟล์รูปภาพ .jpg, .jpeg, .png, .webp เท่านั้น', null, 400);
    }

    $newFileName = 'avatar_' . $targetUserId . '_' . time() . '.' . $ext;
    $destPath    = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $avatarPathRelative = 'uploads/avatars/' . $newFileName;
    } else {
        sendJsonResponse(false, 'ไม่สามารถบันทึกไฟล์รูปภาพไปยังเซิร์ฟเวอร์ได้', null, 500);
    }
}

// กรณีที่ 2: อัปโหลดผ่าน Base64 Photo Data (เช่น ถ่ายกล้องสด)
elseif (!empty($_POST['photo_data'])) {
    $photoData = $_POST['photo_data'];
    if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $type)) {
        $data = substr($photoData, strpos($photoData, ',') + 1);
        $data = base64_decode($data);
        if ($data !== false) {
            $newFileName = 'avatar_' . $targetUserId . '_' . time() . '.jpg';
            $destPath    = $uploadDir . $newFileName;
            file_put_contents($destPath, $data);
            $avatarPathRelative = 'uploads/avatars/' . $newFileName;
        }
    }
}

if (!$avatarPathRelative) {
    sendJsonResponse(false, 'กรุณาแนบไฟล์รูปภาพโปรไฟล์ที่ต้องการอัปโหลด', null, 400);
}

// ลบรูปภาพเก่าถ้ามี
if (!empty($targetUser['avatar_url'])) {
    $oldFile = __DIR__ . '/../' . $targetUser['avatar_url'];
    if (file_exists($oldFile)) {
        @unlink($oldFile);
    }
}

// อัปเดต DB
try {
    $stmtUpd = $pdo->prepare("UPDATE users SET avatar_url = :avatar_url WHERE user_id = :user_id");
    $stmtUpd->execute([
        ':avatar_url' => $avatarPathRelative,
        ':user_id'    => $targetUserId
    ]);

    sendJsonResponse(true, "อัปโหลดรูปโปรไฟล์สำหรับ {$targetUser['name']} เรียบร้อยแล้ว", [
        'user_id'    => $targetUserId,
        'avatar_url' => $avatarPathRelative
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกรูปโปรไฟล์: ' . $e->getMessage(), null, 500);
}
