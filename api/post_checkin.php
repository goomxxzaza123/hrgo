<?php
/**
 * API: Time Check-in / Check-out (post_checkin.php)
 * Method: POST
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้งาน (พนักงาน / หัวหน้า / แอดมิน ทุกคนที่ล็อกอิน)
$currentUser = requireAuth();

// รับเฉพาะ POST Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'รองรับเฉพาะ POST Request เท่านั้น', null, 405);
}

// อ่านข้อมูล JSON ที่ส่งมาใน Request
$inputData = json_decode(file_get_contents('php://input'), true) ?? [];

$userLat   = (isset($inputData['latitude']) && $inputData['latitude'] !== null && $inputData['latitude'] !== '') ? (float)$inputData['latitude'] : null;
$userLng   = (isset($inputData['longitude']) && $inputData['longitude'] !== null && $inputData['longitude'] !== '') ? (float)$inputData['longitude'] : null;
$photoData = trim($inputData['photo'] ?? $inputData['photo_data'] ?? $_POST['photo'] ?? $_POST['photo_data'] ?? '');

// ดึงตั้งค่าพิกัดและรัศมีลงเวลาจากฐานข้อมูลแบบ Dynamic
$sysSettings = getSystemSettings();

/**
 * บันทึกรูปถ่าย Base64 เป็นไฟล์ JPEG บนเซิร์ฟเวอร์
 */
function saveBase64Photo($base64String, $prefix, $userId) {
    if (empty($base64String)) return null;

    $uploadDir = __DIR__ . '/../uploads/attendance_photos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if (preg_match('/^data:image\/(\w+);base64,/', $base64String)) {
        $base64String = substr($base64String, strpos($base64String, ',') + 1);
    }

    $data = base64_decode($base64String);
    if ($data === false) return null;

    $fileName = $prefix . '_' . $userId . '_' . time() . '.jpg';
    $filePath = $uploadDir . $fileName;

    @file_put_contents($filePath, $data);
    return 'uploads/attendance_photos/' . $fileName;
}

// 1. ตรวจสอบ IP Address ของผู้ใช้งาน
$userIP = getUserIP();
if ($sysSettings['enable_ip_check'] && !isAllowedOfficeIP($userIP)) {
    sendJsonResponse(false, 'ไม่อนุญาตให้ลงเวลานอกสถานที่ (IP: ' . $userIP . ' ไม่อยู่ในวง Wi-Fi/LAN ของบริษัท)', null, 403);
}

// 2. ตรวจสอบพิกัด GPS ว่าอยู่ในรัศมีบริษัทหรือไม่
$distanceMeters = null;
if ($sysSettings['enable_location_check']) {
    if (empty($userLat) || empty($userLng)) {
        // หากพิกัด GPS ไม่ถูกส่งมา (เช่น บนคอมพิวเตอร์ Desktop หรือ GPS หมดเวลา)
        // และเครื่องอยู่ในวง Wi-Fi/LAN ของบริษัท ให้ใช้พิกัดบริษัทสำรอง
        if (isAllowedOfficeIP($userIP)) {
            $userLat = $sysSettings['company_lat'];
            $userLng = $sysSettings['company_lng'];
        } else {
            sendJsonResponse(false, 'กรุณาเปิดการระบุพิกัดตำแหน่ง (GPS) บนอุปกรณ์เพื่อยืนยันว่าอยู่ในพื้นที่ออฟฟิศ', null, 400);
        }
    }

    $companyLat = $sysSettings['company_lat'];
    $companyLng = $sysSettings['company_lng'];
    $maxMeters  = $sysSettings['max_distance_meters'];

    $distanceMeters = calculateDistanceMeters($userLat, $userLng, $companyLat, $companyLng);

    if ($distanceMeters > $maxMeters) {
        sendJsonResponse(false, "ไม่อนุญาตให้ลงเวลานอกสถานที่ (พิกัดปัจจุบันของคุณ: Lat {$userLat}, Lng {$userLng} อยู่ห่างจากออฟฟิศ {$distanceMeters} เมตร ซึ่งเกินรัศมีที่อนุญาต {$maxMeters} เมตร)", [
            'user_lat'        => $userLat,
            'user_lng'        => $userLng,
            'distance_meters' => $distanceMeters,
            'max_allowed'     => $maxMeters
        ], 403);
    }
}

try {
    $pdo = getDBConnection();
    $userId  = $currentUser['user_id'];
    $today   = date('Y-m-d');
    $nowTime = date('Y-m-d H:i:s');

    // 3. ตรวจสอบบันทึกเวลาของวันนี้ด้วย Prepared Statement
    $stmt = $pdo->prepare("
        SELECT attendance_id, work_date, check_in_time, check_out_time, status 
        FROM attendances 
        WHERE user_id = :user_id AND work_date = :work_date 
        LIMIT 1
    ");
    $stmt->execute([
        ':user_id'   => $userId,
        ':work_date' => $today
    ]);
    $attendance = $stmt->fetch();

    // -------------------------------------------------------------
    // กรณีที่ 1: ยังไม่ได้ลงเวลาเข้างานวันนี้ -> ทำการ Check-in
    // -------------------------------------------------------------
    if (!$attendance) {
        // บันทึกรูปถ่ายเข้างาน
        $checkInPhoto = saveBase64Photo($photoData, 'checkin', $userId);

        // ดึงข้อมูลกะการทำงานของผู้ใช้จากฐานข้อมูล
        $userStmt = $pdo->prepare("SELECT shift_type, shift_start_time FROM users WHERE user_id = :id LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $userData = $userStmt->fetch() ?: [];
        $shiftType = $userData['shift_type'] ?? 'day';
        $shiftStartStr = $userData['shift_start_time'] ?? ($shiftType === 'night' ? '20:00:00' : '08:00:00');
        
        $shiftStartTs  = strtotime($today . ' ' . $shiftStartStr);
        $nowTs         = strtotime($nowTime);

        $lateMinutes = 0;
        // อนุโลมไม่นับว่าสายถ้าเวลาเข้างานยังไม่ถึง 08:01:00 หรือ 20:01:00 (เช่น 08:00:59 ลงมา ถือว่าตรงเวลา)
        if ($nowTs > $shiftStartTs + 59) {
            $status = 'late';
            $lateMinutes = (int)floor(($nowTs - $shiftStartTs) / 60);
        } else {
            $status = 'on_time';
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO attendances (user_id, work_date, check_in_time, ip_address, latitude, longitude, check_in_photo, late_minutes, status) 
            VALUES (:user_id, :work_date, :check_in_time, :ip_address, :latitude, :longitude, :check_in_photo, :late_minutes, :status)
        ");
        $insertStmt->execute([
            ':user_id'        => $userId,
            ':work_date'      => $today,
            ':check_in_time'  => $nowTime,
            ':ip_address'     => $userIP,
            ':latitude'       => $userLat,
            ':longitude'      => $userLng,
            ':check_in_photo' => $checkInPhoto,
            ':late_minutes'   => $lateMinutes,
            ':status'         => $status
        ]);

        $statusText = ($status === 'late') 
            ? "ลงเวลาเข้างานเรียบร้อย (สาย {$lateMinutes} นาที)" 
            : 'ลงเวลาเข้างานเรียบร้อย (ตรงเวลา)';

        sendJsonResponse(true, $statusText, [
            'action'          => 'check_in',
            'check_in_time'   => date('H:i:s', strtotime($nowTime)),
            'status'          => $status,
            'ip_address'      => $userIP,
            'photo_url'       => $checkInPhoto,
            'distance_meters' => $distanceMeters
        ]);
    }

    // -------------------------------------------------------------
    // กรณีที่ 2: มีบันทึกเข้างานแล้ว แต่ยังไม่ได้ลงเวลาออกงาน -> ทำการ Check-out
    // -------------------------------------------------------------
    if (empty($attendance['check_out_time'])) {
        // บันทึกรูปถ่ายออกงาน
        $checkOutPhoto = saveBase64Photo($photoData, 'checkout', $userId);

        // ดึงข้อมูลกะการทำงานของผู้ใช้เพื่อคำนวณเวลาทำงานและ OT
        $stmtUserShift = $pdo->prepare("SELECT shift_type, shift_start_time, shift_end_time, ot_cap_time FROM users WHERE user_id = :user_id LIMIT 1");
        $stmtUserShift->execute([':user_id' => $userId]);
        $userShift = $stmtUserShift->fetch();

        $shiftType = $userShift['shift_type'] ?? 'day';
        $checkInTs  = strtotime($attendance['check_in_time']);
        $checkOutTs = strtotime($nowTime);
        $workDate   = date('Y-m-d', $checkInTs);

        if ($shiftType === 'night') {
            $otTriggerTs = strtotime(date('Y-m-d', strtotime($workDate . ' +1 day')) . ' 08:00:00');
        } else {
            $otTriggerTs = strtotime($workDate . ' 20:00:00');
        }

        // คำนวณชั่วโมงทำงานปกติ (สูงสุด 8.00 ชม.)
        $diffSec   = max(0, $checkOutTs - $checkInTs);
        $workHours = round(min(8.00, $diffSec / 3600), 2);

        // กฎ OT: ออกงานตั้งแต่ 20:00 น. เป็นต้นไป (กะเช้า) หรือ 08:00 น. เป็นต้นไป (กะดึก) ตีเป็น OT 3.00 ชม. สุทธิ
        $otHours = ($checkOutTs >= $otTriggerTs) ? 3.00 : 0.00;

        $updateStmt = $pdo->prepare("
            UPDATE attendances 
            SET check_out_time = :check_out_time, 
                check_out_photo = :check_out_photo,
                work_hours = :work_hours,
                ot_hours = :ot_hours
            WHERE attendance_id = :attendance_id
        ");
        $updateStmt->execute([
            ':check_out_time'  => $nowTime,
            ':check_out_photo' => $checkOutPhoto,
            ':work_hours'      => $workHours,
            ':ot_hours'        => $otHours,
            ':attendance_id'   => $attendance['attendance_id']
        ]);

        sendJsonResponse(true, "ลงเวลาออกงานเรียบร้อยแล้ว (ชั่วโมงทำงาน: {$workHours} ชม. | OT: {$otHours} ชม.)", [
            'action'          => 'check_out',
            'check_in_time'   => date('H:i:s', strtotime($attendance['check_in_time'])),
            'check_out_time'  => date('H:i:s', strtotime($nowTime)),
            'work_hours'      => $workHours,
            'ot_hours'        => $otHours,
            'status'          => $attendance['status'],
            'photo_url'       => $checkOutPhoto,
            'ip_address'      => $userIP
        ]);
    }

    // -------------------------------------------------------------
    // กรณีที่ 3: ลงเวลาทั้งเข้าและออกงานเรียบร้อยแล้ววันนี้
    // -------------------------------------------------------------
    sendJsonResponse(false, 'คุณได้ลงเวลาเข้าและออกงานสำหรับวันนี้ครบถ้วนแล้ว', [
        'action'         => 'completed',
        'check_in_time'  => date('H:i:s', strtotime($attendance['check_in_time'])),
        'check_out_time' => date('H:i:s', strtotime($attendance['check_out_time'])),
        'status'         => $attendance['status']
    ], 400);

} catch (PDOException $e) {
    sendJsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกเวลา: ' . $e->getMessage(), null, 500);
}
