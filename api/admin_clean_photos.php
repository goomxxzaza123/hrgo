<?php
/**
 * API: Admin Photo Storage Metrics & Cleanup Tool (admin_clean_photos.php)
 * Method: GET (Get storage size & file count), POST (Clean photos older than X days)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Admin หรือ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$pdo = getDBConnection();
$uploadDir = __DIR__ . '/../uploads/attendance_photos/';

// -------------------------------------------------------------
// GET: คำนวณจำนวนไฟล์และขนาดพื้นที่ที่ใช้อยู่ในปัจจุบัน
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $fileCount = 0;
        $totalSizeBytes = 0;

        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*.jpg');
            if ($files) {
                $fileCount = count($files);
                foreach ($files as $file) {
                    $totalSizeBytes += filesize($file);
                }
            }
        }

        $totalSizeMb = round($totalSizeBytes / (1024 * 1024), 2);

        sendJsonResponse(true, 'คำนวณพื้นที่จัดเก็บรูปสำเร็จ', [
            'file_count'     => $fileCount,
            'size_bytes'     => $totalSizeBytes,
            'size_mb'        => $totalSizeMb,
            'upload_dir'     => 'uploads/attendance_photos/'
        ]);

    } catch (Exception $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการคำนวณพื้นที่: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: ลบรูปถ่ายที่เก่ากว่าจำนวนวันที่กำหนด (Retention Cleanup)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $days = (int)($inputData['older_than_days'] ?? 90);

    if ($days < 7) {
        sendJsonResponse(false, 'จำนวนวันในการลบรูปเก่าต้องอย่างน้อย 7 วันขึ้นไป', null, 400);
    }

    try {
        $thresholdDate = date('Y-m-d', strtotime("-{$days} days"));

        // 1. ดึงชื่อไฟล์รูปถ่ายที่เก่ากว่าวัน threshold จากตาราง attendances
        $stmtSel = $pdo->prepare("
            SELECT attendance_id, check_in_photo, check_out_photo 
            FROM attendances 
            WHERE work_date < :threshold_date 
              AND (check_in_photo IS NOT NULL OR check_out_photo IS NOT NULL)
        ");
        $stmtSel->execute([':threshold_date' => $thresholdDate]);
        $oldRecords = $stmtSel->fetchAll();

        $deletedFilesCount = 0;
        $freedBytes = 0;

        foreach ($oldRecords as $rec) {
            // ลบรูปเข้างาน
            if (!empty($rec['check_in_photo'])) {
                $fullInPath = __DIR__ . '/../' . $rec['check_in_photo'];
                if (file_exists($fullInPath)) {
                    $freedBytes += filesize($fullInPath);
                    @unlink($fullInPath);
                    $deletedFilesCount++;
                }
            }
            // ลบรูปออกงาน
            if (!empty($rec['check_out_photo'])) {
                $fullOutPath = __DIR__ . '/../' . $rec['check_out_photo'];
                if (file_exists($fullOutPath)) {
                    $freedBytes += filesize($fullOutPath);
                    @unlink($fullOutPath);
                    $deletedFilesCount++;
                }
            }
        }

        // 2. เคลียร์คอลัมน์รูปในตาราง attendances ให้เป็น NULL สำหรับเรคคอร์ดเก่า
        $stmtUpd = $pdo->prepare("
            UPDATE attendances 
            SET check_in_photo = NULL, check_out_photo = NULL 
            WHERE work_date < :threshold_date
        ");
        $stmtUpd->execute([':threshold_date' => $thresholdDate]);

        $freedMb = round($freedBytes / (1024 * 1024), 2);

        sendJsonResponse(true, "ลบรูปถ่ายที่เก่ากว่า {$days} วันเรียบร้อยแล้ว (ลบได้ {$deletedFilesCount} ไฟล์ คืนพื้นที่ {$freedMb} MB)", [
            'deleted_files' => $deletedFilesCount,
            'freed_mb'      => $freedMb,
            'threshold'     => $thresholdDate
        ]);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการลบรูปเก่า: ' . $e->getMessage(), null, 500);
    }
}
