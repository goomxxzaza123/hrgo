<?php
/**
 * API: HR Reports & Attendance History Filtering (admin_reports.php)
 * Method: GET
 */

require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น Manager หรือ Admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
        $endDate   = trim($_GET['end_date'] ?? date('Y-m-t'));
        $deptId    = (int)($_GET['dept_id'] ?? 0);
        $userId    = (int)($_GET['user_id'] ?? 0);
        $status    = trim($_GET['status'] ?? '');
        $exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

        $where = ["a.work_date BETWEEN :start_date AND :end_date"];
        $params = [
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ];

        if ($deptId > 0) {
            $where[] = "u.dept_id = :dept_id";
            $params[':dept_id'] = $deptId;
        }

        if ($userId > 0) {
            $where[] = "a.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if (!empty($status)) {
            if ($status === 'on_time') {
                $where[] = "a.status IN ('on_time', 'normal')";
            } elseif (in_array($status, ['late', 'absent'])) {
                $where[] = "a.status = :status";
                $params[':status'] = $status;
            }
        }

        $whereSql = "WHERE " . implode(" AND ", $where);

        // -------------------------------------------------------------
        // ดึงพนักงานที่จะทำรายงาน
        // -------------------------------------------------------------
        $empWhere = ["u.is_active = 1"];
        $empParams = [];
        if ($deptId > 0) {
            $empWhere[] = "u.dept_id = :dept_id";
            $empParams[':dept_id'] = $deptId;
        }
        if ($userId > 0) {
            $empWhere[] = "u.user_id = :user_id";
            $empParams[':user_id'] = $userId;
        }
        $empWhereSql = "WHERE " . implode(" AND ", $empWhere);

        $sqlEmps = "
            SELECT u.user_id, u.emp_code, u.name AS employee_name, d.dept_name, u.shift_type, u.shift_start_time
            FROM users u
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            $empWhereSql
            ORDER BY u.emp_code ASC
        ";
        $stmtEmps = $pdo->prepare($sqlEmps);
        $stmtEmps->execute($empParams);
        $reportUsers = $stmtEmps->fetchAll();

        // -------------------------------------------------------------
        // ดึงข้อมูลการลงเวลาทั้งหมดในช่วงวันที่ที่ระบุ
        // -------------------------------------------------------------
        $attWhere = ["a.work_date BETWEEN :start_date AND :end_date"];
        $attParams = [':start_date' => $startDate, ':end_date' => $endDate];

        if ($deptId > 0) {
            $attWhere[] = "u.dept_id = :dept_id";
            $attParams[':dept_id'] = $deptId;
        }
        if ($userId > 0) {
            $attWhere[] = "a.user_id = :user_id";
            $attParams[':user_id'] = $userId;
        }
        $attWhereSql = "WHERE " . implode(" AND ", $attWhere);

        $sqlAtt = "
            SELECT a.attendance_id, a.user_id, a.work_date, a.check_in_time, a.check_out_time, 
                   a.ip_address, a.status, a.check_in_photo, a.check_out_photo, a.work_hours, a.ot_hours, a.late_minutes
            FROM attendances a
            JOIN users u ON a.user_id = u.user_id
            $attWhereSql
        ";
        $stmtAtt = $pdo->prepare($sqlAtt);
        $stmtAtt->execute($attParams);
        $attRows = $stmtAtt->fetchAll();

        $attMap = []; // $attMap[user_id][work_date] = $row
        foreach ($attRows as $ar) {
            $attMap[$ar['user_id']][$ar['work_date']] = $ar;
        }

        // -------------------------------------------------------------
        // ดึงรายการคำขอลาที่อนุมัติแล้ว (Approved Leave Requests)
        // -------------------------------------------------------------
        $leaveTypesTh = [
            'sick'     => 'ลาป่วย',
            'personal' => 'ลากิจ',
            'vacation' => 'ลาพักร้อน'
        ];

        $leaveParams = [':start_date' => $startDate, ':end_date' => $endDate];
        $leaveWhere  = ["l.status = 'approved'", "l.start_date <= :end_date", "l.end_date >= :start_date"];

        if ($deptId > 0) {
            $leaveWhere[] = "u.dept_id = :dept_id";
            $leaveParams[':dept_id'] = $deptId;
        }
        if ($userId > 0) {
            $leaveWhere[] = "l.user_id = :user_id";
            $leaveParams[':user_id'] = $userId;
        }
        $leaveWhereSql = "WHERE " . implode(" AND ", $leaveWhere);

        $sqlLeaves = "
            SELECT l.leave_id, l.user_id, l.leave_type, l.start_date, l.end_date, l.reason
            FROM leave_requests l
            JOIN users u ON l.user_id = u.user_id
            $leaveWhereSql
        ";
        $stmtLeaves = $pdo->prepare($sqlLeaves);
        $stmtLeaves->execute($leaveParams);
        $approvedLeaves = $stmtLeaves->fetchAll();

        // สร้าง Map วันลาสำหรับจับคู่ตาม User และ วันที่
        $leaveMap = [];
        foreach ($approvedLeaves as $l) {
            $cur = new DateTime(max($l['start_date'], $startDate));
            $end = new DateTime(min($l['end_date'], $endDate));
            while ($cur <= $end) {
                $dStr = $cur->format('Y-m-d');
                $leaveMap[$l['user_id']][$dStr] = [
                    'leave_id'   => $l['leave_id'],
                    'leave_type' => $l['leave_type'],
                    'status_label' => $leaveTypesTh[$l['leave_type']] ?? 'วันลา',
                    'reason'     => $l['reason']
                ];
                $cur->modify('+1 day');
            }
        }

        // ดึงวันหยุดบริษัท/วันหยุดนักขัตฤกษ์จากตาราง company_holidays
        $holidayMap = [];
        try {
            $stmtHolidays = $pdo->prepare("
                SELECT holiday_date, holiday_name, holiday_type 
                FROM company_holidays 
                WHERE holiday_date BETWEEN :start_date AND :end_date
            ");
            $stmtHolidays->execute([':start_date' => $startDate, ':end_date' => $endDate]);
            foreach ($stmtHolidays->fetchAll() as $hRow) {
                $holidayMap[$hRow['holiday_date']] = [
                    'name' => $hRow['holiday_name'],
                    'type' => $hRow['holiday_type']
                ];
            }
        } catch (Exception $ex) {
            // Ignore if table doesn't exist
        }

        // -------------------------------------------------------------
        // รันวนสร้างรายงานตามรายชื่อพนักงาน และทุกๆ วันจาก start_date ถึง end_date (เรียง ASC วันเก่าอยู่บน วันล่าสุดอยู่ล่าง)
        // -------------------------------------------------------------
        $onTimeCount = 0;
        $lateCount   = 0;
        $leaveCount  = 0;
        $sundayCount = 0;
        $companyHolidayCount = 0;
        $leaveTypeCounts = ['sick' => 0, 'personal' => 0, 'vacation' => 0];

        $formatted = [];

        foreach ($reportUsers as $u) {
            $uId       = (int)$u['user_id'];
            $empCode   = $u['emp_code'];
            $empName   = $u['employee_name'];
            $deptName  = $u['dept_name'] ?? 'ไม่ระบุ';

            $curDate    = new DateTime($startDate);
            $endDateObj = new DateTime($endDate);

            while ($curDate <= $endDateObj) {
                $dStr        = $curDate->format('Y-m-d');
                $workDateTh  = $curDate->format('d/m/Y');
                $dayShort    = $curDate->format('D');
                $isSunday    = ($dayShort === 'Sun');
                $isHoliday   = isset($holidayMap[$dStr]);
                $holidayInfo = $isHoliday ? $holidayMap[$dStr] : null;

                // 1. ตรวจสอบว่าเป็นวันลาอนุมัติหรือไม่ (วันลาสำคัญที่สุด)
                if (isset($leaveMap[$uId][$dStr])) {
                    $lInfo = $leaveMap[$uId][$dStr];
                    $leaveCount++;
                    if (isset($leaveTypeCounts[$lInfo['leave_type']])) {
                        $leaveTypeCounts[$lInfo['leave_type']]++;
                    }

                    $formatted[] = [
                        'attendance_id'   => 'leave_' . $uId . '_' . $dStr,
                        'user_id'         => $uId,
                        'work_date'       => $dStr,
                        'work_date_th'    => $workDateTh,
                        'emp_code'        => $empCode,
                        'employee_name'   => $empName,
                        'dept_name'       => $deptName,
                        'check_in_time'   => '-',
                        'check_out_time'  => '-',
                        'check_in_photo'  => null,
                        'check_out_photo' => null,
                        'work_hours'      => 0.00,
                        'ot_hours'        => 0.00,
                        'late_minutes'    => 0,
                        'ip_address'      => '-',
                        'status'          => 'leave',
                        'status_label'    => $lInfo['status_label'],
                        'is_sunday'       => $isSunday
                    ];
                }
                // 2. มีรายการลงเวลาในตาราง attendances
                elseif (isset($attMap[$uId][$dStr])) {
                    $ar       = $attMap[$uId][$dStr];
                    $st       = $ar['status'];
                    $lateMins = (int)($ar['late_minutes'] ?? 0);
                    $otH      = (float)($ar['ot_hours'] ?? 0);

                    $isOnTime = ($st === 'on_time' || $st === 'normal');
                    if ($isOnTime) $onTimeCount++;
                    if ($st === 'late') $lateCount++;

                    $statusLabel = $isHoliday
                        ? "ทำงาน ({$holidayInfo['name']})"
                        : ($isSunday 
                            ? ($otH > 0 ? "OT วันหยุด ({$otH} ชม.)" : 'ตรงเวลา (วันหยุด)') 
                            : ($isOnTime ? 'ตรงเวลา' : formatLateText($lateMins)));

                    $formatted[] = [
                        'attendance_id'   => $ar['attendance_id'],
                        'user_id'         => $uId,
                        'work_date'       => $dStr,
                        'work_date_th'    => $workDateTh,
                        'emp_code'        => $empCode,
                        'employee_name'   => $empName,
                        'dept_name'       => $deptName,
                        'check_in_time'   => $ar['check_in_time'] ? date('H:i:s', strtotime($ar['check_in_time'])) : '-',
                        'check_out_time'  => $ar['check_out_time'] ? date('H:i:s', strtotime($ar['check_out_time'])) : '-',
                        'check_in_photo'  => $ar['check_in_photo'],
                        'check_out_photo' => $ar['check_out_photo'],
                        'work_hours'      => (float)($ar['work_hours'] ?? 0),
                        'ot_hours'        => $otH,
                        'late_minutes'    => $lateMins,
                        'ip_address'      => $ar['ip_address'],
                        'status'          => $st,
                        'status_label'    => $statusLabel,
                        'is_sunday'       => $isSunday
                    ];
                }
                // 3. เป็นวันหยุดบริษัท / วันหยุดนักขัตฤกษ์
                elseif ($isHoliday) {
                    $companyHolidayCount++;
                    $formatted[] = [
                        'attendance_id'   => 'holiday_' . $uId . '_' . $dStr,
                        'user_id'         => $uId,
                        'work_date'       => $dStr,
                        'work_date_th'    => $workDateTh,
                        'emp_code'        => $empCode,
                        'employee_name'   => $empName,
                        'dept_name'       => $deptName,
                        'check_in_time'   => '-',
                        'check_out_time'  => '-',
                        'check_in_photo'  => null,
                        'check_out_photo' => null,
                        'work_hours'      => 0.00,
                        'ot_hours'        => 0.00,
                        'late_minutes'    => 0,
                        'ip_address'      => '-',
                        'status'          => 'company_holiday',
                        'status_label'    => 'วันหยุด (' . $holidayInfo['name'] . ')',
                        'is_sunday'       => $isSunday
                    ];
                }
                // 4. วันอาทิตย์ (วันหยุดประจำสัปดาห์) ที่ไม่มีการลงเวลา
                elseif ($isSunday) {
                    $sundayCount++;
                    $formatted[] = [
                        'attendance_id'   => 'sun_' . $uId . '_' . $dStr,
                        'user_id'         => $uId,
                        'work_date'       => $dStr,
                        'work_date_th'    => $workDateTh,
                        'emp_code'        => $empCode,
                        'employee_name'   => $empName,
                        'dept_name'       => $deptName,
                        'check_in_time'   => '-',
                        'check_out_time'  => '-',
                        'check_in_photo'  => null,
                        'check_out_photo' => null,
                        'work_hours'      => 0.00,
                        'ot_hours'        => 0.00,
                        'late_minutes'    => 0,
                        'ip_address'      => '-',
                        'status'          => 'sunday',
                        'status_label'    => 'วันหยุด',
                        'is_sunday'       => true
                    ];
                }
                // 4. วันทำงานปกติที่ไม่มีบันทึก (ขาดงาน)
                else {
                    $formatted[] = [
                        'attendance_id'   => 'absent_' . $uId . '_' . $dStr,
                        'user_id'         => $uId,
                        'work_date'       => $dStr,
                        'work_date_th'    => $workDateTh,
                        'emp_code'        => $empCode,
                        'employee_name'   => $empName,
                        'dept_name'       => $deptName,
                        'check_in_time'   => '-',
                        'check_out_time'  => '-',
                        'check_in_photo'  => null,
                        'check_out_photo' => null,
                        'work_hours'      => 0.00,
                        'ot_hours'        => 0.00,
                        'late_minutes'    => 0,
                        'ip_address'      => '-',
                        'status'          => 'absent',
                        'status_label'    => 'ขาดงาน',
                        'is_sunday'       => false
                    ];
                }

                $curDate->modify('+1 day');
            }
        }

        // เรียงลำดับรายการทั้งหมดตาม work_date ASC (วันเก่าอยู่บน 01/07/2026 ... วันล่าสุดอยู่ล่าง 01/08/2026)
        usort($formatted, function($a, $b) {
            $cmp = strcmp($a['work_date'], $b['work_date']);
            if ($cmp === 0) {
                return strcmp($a['emp_code'], $b['emp_code']);
            }
            return $cmp;
        });

        // หากร้องขอส่งออกเป็นไฟล์ CSV / Excel
        $exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';
        $isPrint   = isset($_GET['print']) && $_GET['print'] === '1';

        if ($exportCsv || $isPrint) {
            $thaiDays = [
                'Sun' => 'อาทิตย์',
                'Mon' => 'จันทร์',
                'Tue' => 'อังคาร',
                'Wed' => 'พุธ',
                'Thu' => 'พฤหัสบดี',
                'Fri' => 'ศุกร์',
                'Sat' => 'เสาร์'
            ];

            // จัดกลุ่มรายการตามพนักงาน
            $groupedByEmp = [];
            foreach ($formatted as $item) {
                $empKey = $item['emp_code'];
                if (!isset($groupedByEmp[$empKey])) {
                    $groupedByEmp[$empKey] = [
                        'emp_code'      => $item['emp_code'],
                        'employee_name' => $item['employee_name'],
                        'dept_name'     => $item['dept_name'],
                        'records'       => []
                    ];
                }
                $groupedByEmp[$empKey]['records'][] = $item;
            }

            $isSingleEmp   = ($userId > 0 || count($groupedByEmp) === 1);
            $singleEmpInfo = null;

            if ($isSingleEmp) {
                if (count($groupedByEmp) > 0) {
                    $singleEmpInfo = reset($groupedByEmp);
                } elseif ($userId > 0) {
                    $stmtUser = $pdo->prepare("
                        SELECT u.emp_code, u.name as employee_name, d.dept_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.dept_id = d.dept_id 
                        WHERE u.user_id = :u_id LIMIT 1
                    ");
                    $stmtUser->execute([':u_id' => $userId]);
                    $uInfo = $stmtUser->fetch();
                    if ($uInfo) {
                        $singleEmpInfo = [
                            'emp_code'      => $uInfo['emp_code'],
                            'employee_name' => $uInfo['employee_name'],
                            'dept_name'     => $uInfo['dept_name'] ?? 'ไม่ระบุ',
                            'records'       => []
                        ];
                    }
                }
            }

            if ($isSingleEmp && $singleEmpInfo) {
                $empCodeClean = preg_replace('/[^A-Za-z0-9_-]/', '', $singleEmpInfo['emp_code']);
                $filename = "attendance_report_" . $empCodeClean . "_" . date('Y-m-d') . ".xls";
            } else {
                $filename = "attendance_report_all_employees_" . date('Y-m-d') . ".xls";
            }

            if (!$isPrint) {
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo chr(0xEF).chr(0xBB).chr(0xBF);
            } else {
                header('Content-Type: text/html; charset=utf-8');
            }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= $isSingleEmp && $singleEmpInfo ? "รายงานลงเวลา_{$singleEmpInfo['emp_code']}" : "รายงานการลงเวลาพนักงาน" ?></title>
<style>
    body { 
        font-family: 'Segoe UI', 'Prompt', 'Tahoma', 'Leelawadee UI', Arial, sans-serif; 
        font-size: 12pt; 
        margin: 15px; 
        background: #FFFFFF; 
        color: #0F172A; 
    }
    table { 
        border-collapse: collapse; 
        width: 100%; 
        margin-bottom: 20px; 
        page-break-inside: avoid; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    th, td { 
        border: 1px solid #94A3B8; 
        padding: 8px 10px; 
        text-align: center; 
        font-size: 11.5pt; 
        height: 30px;
        vertical-align: middle;
    }
    th { 
        background-color: #1E3A8A; 
        color: #FFFFFF; 
        font-weight: bold; 
        font-size: 12pt;
        height: 36px;
    }
    .banner-title { 
        font-size: 15pt; 
        font-weight: bold; 
        text-align: center; 
        background-color: #1E293B; 
        color: #FFFFFF; 
        padding: 12px; 
        height: 40px;
    }
    .meta-box { 
        background-color: #F1F5F9; 
        font-weight: bold; 
        font-size: 12pt;
        text-align: left; 
        height: 34px;
        color: #1E293B;
    }
    .summary-box { 
        background-color: #EFF6FF; 
        font-weight: bold; 
        font-size: 12pt;
        text-align: left; 
        padding: 10px 14px;
        height: 36px;
        border-top: 2px solid #2563EB;
        color: #1E3A8A;
    }
    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        body { margin: 0; font-size: 11pt; }
        th, td { font-size: 10pt; padding: 6px; height: auto; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body>
<?php if ($isPrint): ?>
<div class="no-print" style="margin-bottom:15px; text-align:right;">
    <button onclick="window.print()" style="padding:8px 18px; background:#10B981; color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:bold; cursor:pointer;">🖨️ พิมพ์เอกสาร / เซฟเป็น PDF</button>
</div>
<?php endif; ?>
<?php if ($isSingleEmp && $singleEmpInfo): ?>
    <table>
        <tr>
            <td colspan="9" class="banner-title">รายงานสรุปการลงเวลาและชั่วโมง OT ประจำบุคคล</td>
        </tr>
        <tr class="meta-box">
            <td colspan="3"><b>ชื่อ-นามสกุล:</b> <?= htmlspecialchars($singleEmpInfo['employee_name']) ?></td>
            <td colspan="2"><b>รหัสพนักงาน:</b> <?= htmlspecialchars($singleEmpInfo['emp_code']) ?></td>
            <td colspan="2"><b>แผนก:</b> <?= htmlspecialchars($singleEmpInfo['dept_name']) ?></td>
            <td colspan="2"><b>ช่วงวันที่:</b> <?= date('d/m/Y', strtotime($startDate)) ?> ถึง <?= date('d/m/Y', strtotime($endDate)) ?></td>
        </tr>
    </table>
<?php else: ?>
    <table>
        <tr>
            <td colspan="9" class="banner-title">รายงานสรุปการลงเวลาทำงานพนักงาน (ทุกแผนก)</td>
        </tr>
        <tr class="meta-box">
            <td colspan="3"><b>บริษัท / องค์กร:</b> ระบบบริหารจัดการทรัพยากรบุคคล (HR GO)</td>
            <td colspan="3"><b>ช่วงวันที่รายงาน:</b> <?= date('d/m/Y', strtotime($startDate)) ?> ถึง <?= date('d/m/Y', strtotime($endDate)) ?></td>
            <td colspan="3"><b>วันที่ออกเอกสาร:</b> <?= date('d/m/Y H:i:s') ?></td>
        </tr>
    </table>
<?php endif; ?>

<?php foreach ($groupedByEmp as $empData): ?>
    <?php if (!$isSingleEmp): ?>
        <table>
            <tr class="meta-box">
                <td colspan="3"><b>พนักงาน:</b> <?= htmlspecialchars($empData['employee_name']) ?></td>
                <td colspan="3"><b>รหัส:</b> <?= htmlspecialchars($empData['emp_code']) ?></td>
                <td colspan="3"><b>แผนก:</b> <?= htmlspecialchars($empData['dept_name']) ?></td>
            </tr>
        </table>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>วันในสัปดาห์</th>
                <th>วันที่</th>
                <th>เวลาเข้างาน</th>
                <th>เวลาออกงาน</th>
                <th>ชั่วโมงทำงาน (ชม.)</th>
                <th>เวลา OT (ชม.)</th>
                <th>เวลาสาย (นาที)</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalWork  = 0;
            $totalOt    = 0;
            $totalLate  = 0;
            $onTimeCnt  = 0;
            $lateCnt    = 0;
            $empLeaveCnt = 0;
            $empLeaveBreakdown = ['sick' => 0, 'personal' => 0, 'vacation' => 0];
            $seq = 1;

            foreach ($empData['records'] as $rec):
                $dayShort = date('D', strtotime($rec['work_date']));
                $isSunday = ($dayShort === 'Sun' || !empty($rec['is_sunday']));
                $isLeave  = ($rec['status'] === 'leave');
                $dayName  = $isSunday ? 'อาทิตย์ (วันหยุด)' : ($thaiDays[$dayShort] ?? '');

                $workH = (float)$rec['work_hours'];
                $otH   = (float)$rec['ot_hours'];
                $lMins = (int)$rec['late_minutes'];

                $totalWork += $workH;
                $totalOt   += $otH;
                $totalLate += $lMins;

                $rowStyle = '';
                if ($isSunday) {
                    $rowStyle = 'style="background-color: #FFF2CC;"'; // แถบสีเหลืองสดใสสำหรับวันอาทิตย์
                } elseif ($isLeave) {
                    $rowStyle = 'style="background-color: #E0F2FE;"'; // แถบสีฟ้าสำหรับวันลา
                }

                if ($isLeave) {
                    $empLeaveCnt++;
                    if (strpos($rec['status_label'], 'ป่วย') !== false) $empLeaveBreakdown['sick']++;
                    elseif (strpos($rec['status_label'], 'กิจ') !== false) $empLeaveBreakdown['personal']++;
                    elseif (strpos($rec['status_label'], 'พักร้อน') !== false) $empLeaveBreakdown['vacation']++;
                } elseif ($rec['status'] === 'late') {
                    $lateCnt++;
                } elseif ($rec['status'] === 'on_time' || $rec['status'] === 'normal') {
                    $onTimeCnt++;
                }
            ?>
            <tr <?= $rowStyle ?>>
                <td><?= $seq++ ?></td>
                <td><?= htmlspecialchars($dayName) ?></td>
                <td><?= htmlspecialchars($rec['work_date_th']) ?></td>
                <td><?= htmlspecialchars($rec['check_in_time']) ?></td>
                <td><?= htmlspecialchars($rec['check_out_time']) ?></td>
                <td><?= number_format($workH, 2) ?></td>
                <td><?= number_format($otH, 2) ?></td>
                <td><?php 
                    if ($lMins <= 0) echo '-';
                    elseif ($lMins < 60) echo "{$lMins} นาที";
                    else {
                        $h = floor($lMins / 60);
                        $m = $lMins % 60;
                        echo $m > 0 ? "{$h} ชม. {$m} นาที" : "{$h} ชม.";
                    }
                ?></td>
                <td><b><?= htmlspecialchars($rec['status_label']) ?></b></td>
            </tr>
            <?php endforeach; ?>

            <?php 
            $lParts = [];
            if ($empLeaveBreakdown['sick'] > 0)     $lParts[] = "ป่วย {$empLeaveBreakdown['sick']} วัน";
            if ($empLeaveBreakdown['personal'] > 0) $lParts[] = "กิจ {$empLeaveBreakdown['personal']} วัน";
            if ($empLeaveBreakdown['vacation'] > 0) $lParts[] = "พักร้อน {$empLeaveBreakdown['vacation']} วัน";
            $leaveStr = !empty($lParts) ? implode(', ', $lParts) : '0 วัน';

            $totalLateText = '0 นาที';
            if ($totalLate > 0) {
                if ($totalLate < 60) {
                    $totalLateText = "{$totalLate} นาที";
                } else {
                    $hTotal = floor($totalLate / 60);
                    $mTotal = $totalLate % 60;
                    $totalLateText = $mTotal > 0 ? "{$hTotal} ชม. {$mTotal} นาที" : "{$hTotal} ชม.";
                }
            }
            ?>
            <tr class="summary-box">
                <td colspan="9">
                    <b>สรุปยอดรวมประจำบุคคล:</b> รวมทั้งหมด: <?= count($empData['records']) ?> วัน | 
                    <b>มาเข้างาน: <?= ($onTimeCnt + $lateCnt) ?> วัน</b> (ตรงเวลา: <?= $onTimeCnt ?> วัน, สาย: <?= $lateCnt ?> วัน) | 
                    วันลา: <?= $empLeaveCnt ?> วัน (<?= $leaveStr ?>) | 
                    รวมชั่วโมงทำงาน: <?= number_format($totalWork, 2) ?> ชม. | 
                    รวมเวลา OT: <?= number_format($totalOt, 2) ?> ชม. | 
                    รวมเวลาสาย: <?= $totalLateText ?>
                </td>
            </tr>
        </tbody>
    </table>
<?php endforeach; ?>
</body>
</html>
<?php
            exit;
        }

        // ดึงรายชื่อพนักงานทั้งหมดสำหรับ Select Dropdown ในหน้าบ้าน
        $empSql = "SELECT user_id, emp_code, name FROM users WHERE is_active = 1 ORDER BY emp_code ASC";
        $employees = $pdo->query($empSql)->fetchAll();

        sendJsonResponse(true, 'ดึงรายงานการลงเวลาสำเร็จ', [
            'summary' => [
                'total'   => ($onTimeCount + $lateCount + $leaveCount),
                'on_time' => $onTimeCount,
                'late'    => $lateCount,
                'leave'   => $leaveCount,
                'leave_breakdown' => $leaveTypeCounts
            ],
            'reports'   => $formatted,
            'employees' => $employees
        ]);

    } catch (PDOException $e) {
        sendJsonResponse(false, 'เกิดข้อผิดพลาดในการดึงรายงาน: ' . $e->getMessage(), null, 500);
    }
}
