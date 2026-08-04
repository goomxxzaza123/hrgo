<?php
/**
 * API: Department Management (admin_departments.php)
 * Method: GET (Fetch departments), POST (Create, Update, Delete)
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (เฉพาะ Admin และ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);
$pdo = getDBConnection();

// -------------------------------------------------------------
// GET: ดึงรายการแผนกทั้งหมด พร้อมจำนวนพนักงานในแต่ละแผนก
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "
            SELECT d.dept_id, d.dept_name, COUNT(u.user_id) AS employee_count
            FROM departments d
            LEFT JOIN users u ON (d.dept_id = u.dept_id AND u.is_active = 1)
            GROUP BY d.dept_id, d.dept_name
            ORDER BY d.dept_id ASC
        ";
        $stmt = $pdo->query($sql);
        $departments = $stmt->fetchAll();

        sendJsonResponse(true, 'ดึงรายการแผนกสำเร็จ', $departments);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงรายการแผนก: ' . $e->getMessage(), null, 500);
    }
}

// -------------------------------------------------------------
// POST: สร้าง / แก้ไข / ลบแผนก
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action    = trim($inputData['action'] ?? '');

    try {
        // Case 1: สร้างแผนกใหม่ (Create Department)
        if ($action === 'create') {
            $deptName = trim($inputData['dept_name'] ?? '');
            if (empty($deptName)) {
                sendJsonResponse(false, 'กรุณากรอกชื่อแผนก', null, 400);
            }

            // ตรวจสอบชื่อแผนกซ้ำ
            $stmtCheck = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name = :dept_name LIMIT 1");
            $stmtCheck->execute([':dept_name' => $deptName]);
            if ($stmtCheck->fetch()) {
                sendJsonResponse(false, 'ชื่อแผนกนี้มีในระบบแล้ว', null, 400);
            }

            $stmtIns = $pdo->prepare("INSERT INTO departments (dept_name) VALUES (:dept_name)");
            $stmtIns->execute([':dept_name' => $deptName]);
            $newDeptId = $pdo->lastInsertId();

            sendJsonResponse(true, "เพิ่มแผนก \"{$deptName}\" เรียบร้อยแล้ว", ['dept_id' => $newDeptId]);
        }

        // Case 2: แก้ไขชื่อแผนก (Update Department)
        if ($action === 'update') {
            $deptId   = (int)($inputData['dept_id'] ?? 0);
            $deptName = trim($inputData['dept_name'] ?? '');

            if (!$deptId || empty($deptName)) {
                sendJsonResponse(false, 'กรุณาระบุรหัสแผนกและชื่อแผนกใหม่', null, 400);
            }

            // ตรวจสอบชื่อแผนกซ้ำกับแผนกอื่น
            $stmtCheck = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name = :dept_name AND dept_id != :dept_id LIMIT 1");
            $stmtCheck->execute([':dept_name' => $deptName, ':dept_id' => $deptId]);
            if ($stmtCheck->fetch()) {
                sendJsonResponse(false, 'ชื่อแผนกนี้ซ้ำกับแผนกอื่นในระบบ', null, 400);
            }

            $stmtUpd = $pdo->prepare("UPDATE departments SET dept_name = :dept_name WHERE dept_id = :dept_id");
            $stmtUpd->execute([':dept_name' => $deptName, ':dept_id' => $deptId]);

            sendJsonResponse(true, "อัปเดตชื่อแผนกเป็น \"{$deptName}\" เรียบร้อยแล้ว", ['dept_id' => $deptId]);
        }

        // Case 3: ลบแผนก (Delete Department)
        if ($action === 'delete') {
            $deptId = (int)($inputData['dept_id'] ?? 0);

            if (!$deptId) {
                sendJsonResponse(false, 'กรุณาระบุแผนกที่ต้องการลบ', null, 400);
            }

            // ตรวจสอบว่ามีพนักงานสังกัดแผนกนี้อยู่หรือไม่
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE dept_id = :dept_id");
            $stmtCount->execute([':dept_id' => $deptId]);
            $empCount = (int)$stmtCount->fetchColumn();

            // ลบแผนกออก (พนักงานในแผนกนี้จะถูกปรับ dept_id เป็น NULL อัตโนมัติด้วย Foreign Key ON DELETE SET NULL)
            $stmtDel = $pdo->prepare("DELETE FROM departments WHERE dept_id = :dept_id");
            $stmtDel->execute([':dept_id' => $deptId]);

            sendJsonResponse(true, "ลบแผนกเรียบร้อยแล้ว (มีพนักงานย้ายออก {$empCount} คน)");
        }

        sendJsonResponse(false, 'ไม่ระบุ action ที่ถูกต้อง', null, 400);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการจัดการแผนก: ' . $e->getMessage(), null, 500);
    }
}
