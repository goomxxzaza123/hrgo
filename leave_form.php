<?php
require_once __DIR__ . '/api/config.php';

// ตรวจสอบสิทธิ์ (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userName = $_SESSION['name'] ?? 'พนักงาน';
$userRole = $_SESSION['role'] ?? 'employee';
$deptName = $_SESSION['dept_name'] ?? 'ไม่ระบุ';
$empCode  = $_SESSION['emp_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยื่นคำขอลางาน | HR Management System</title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Theme Manager -->
    <script src="assets/js/theme.js"></script>
</head>
<body style="padding-bottom: 70px;">

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <a href="employee_home.php" class="brand">
            <div class="brand-icon">HR</div>
            <span>HR GO</span>
        </a>
        <div style="display:flex; align-items:center; gap:12px;">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
            <div class="user-badge">
                <a href="profile.php" style="text-decoration:none; display:flex; align-items:center; gap:8px; color:inherit;">
                    <div class="user-avatar"><?= mb_substr($userName, 0, 1, 'UTF-8') ?></div>
                    <div>
                        <strong><?= htmlspecialchars($userName) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($empCode) ?></div>
                    </div>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- Section: สรุปโควตาวันลาคงเหลือ -->
        <h2 style="font-size:1.1rem; margin-bottom:12px;"><i class="fa-solid fa-calendar-check"></i> โควตาวันลาคงเหลือปีนี้</h2>
        <div class="quota-grid" id="leaveBalancesGrid">
            <div class="quota-card">
                <div class="quota-title">กำลังโหลด...</div>
            </div>
        </div>

        <!-- Card: ฟอร์มยื่นคำขอลางาน -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-file-signature"></i> แบบฟอร์มยื่นขอลางาน</div>
            </div>

            <form id="leaveForm">
                <div class="form-group">
                    <label for="leave_type" class="form-label">ประเภทการลา</label>
                    <select id="leave_type" name="leave_type" class="form-control" required>
                        <option value="">-- กรุณาเลือกประเภทการลา --</option>
                        <option value="sick">ลาป่วย (Sick Leave)</option>
                        <option value="personal">ลากิจ (Personal Leave)</option>
                        <option value="vacation">ลาพักร้อน (Vacation Leave)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="start_date" class="form-label">วันที่เริ่มลา</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" required>
                    </div>
                </div>

                <!-- ตัวแสดงการคำนวณจำนวนวันลา -->
                <div id="calculatedDaysDisplay" style="font-size:0.88rem; color:var(--text-muted); margin-bottom:14px; text-align:right;">
                    กรุณาเลือกวันที่
                </div>

                <div class="form-group">
                    <label for="reason" class="form-label">เหตุผลการลา</label>
                    <textarea id="reason" name="reason" class="form-control" placeholder="กรอกรายละเอียดหรือเหตุผลในการขอลางาน..." required></textarea>
                </div>

                <button type="submit" id="leaveSubmitBtn" class="btn btn-primary" style="margin-top: 6px;">
                    <i class="fa-solid fa-paper-plane"></i> ส่งคำขอลางาน
                </button>
            </form>
        </div>

        <!-- Card: ประวัติคำขอลางาน -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติคำขอลางานของฉัน</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ประเภท</th>
                            <th>ช่วงวันที่</th>
                            <th>เหตุผล</th>
                            <th>สถานะ</th>
                            <th>ผู้อนุมัติ</th>
                        </tr>
                    </thead>
                    <tbody id="leaveHistoryTable">
                        <tr>
                            <td colspan="5" style="text-align:center; color:var(--text-muted); padding: 15px;">
                                กำลังโหลดประวัติการลา...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Bottom Mobile Navigation Bar -->
    <nav class="bottom-nav">
        <a href="employee_home.php" class="nav-item">
            <i class="fa-solid fa-clock"></i>
            <span>ลงเวลา</span>
        </a>
        <a href="leave_form.php" class="nav-item active">
            <i class="fa-solid fa-calendar-plus"></i>
            <span>ยื่นลางาน</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fa-solid fa-user"></i>
            <span>โปรไฟล์</span>
        </a>
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <a href="admin/dashboard.php" class="nav-item">
            <i class="fa-solid fa-user-shield"></i>
            <span>จัดการระบบ</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="handleLogout()" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>ออกระบบ</span>
        </a>
    </nav>

    <!-- Core Scripts -->
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/leave.js"></script>
</body>
</html>
