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
    <title>ตั้งค่าบัญชีผู้ใช้ | HR Management System</title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Theme Manager -->
    <script src="assets/js/theme.js"></script>
</head>
<body style="padding-bottom: 80px;">

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

    <div class="container" style="max-width: 600px; margin-top: 20px;">
        
        <div style="margin-bottom: 20px;">
            <h2 style="font-size:1.4rem; font-weight:700;">ตั้งค่าบัญชีผู้ใช้</h2>
            <p style="font-size:0.88rem; color:var(--text-muted);">
                ข้อมูลบัญชี ธีมการแสดงผล และสิทธิ์การใช้งานระบบ HR GO
            </p>
        </div>

        <!-- Card: สลับธีม Dark Mode / Light Mode -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="font-size:1.05rem; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                🌙 ธีมการแสดงผล (Display Theme)
            </h3>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:14px;">
                สลับระหว่างโหมดมืด (Dark Mode) และโหมดสว่าง (Light Mode) เพื่อความสบายตาในการใช้งาน
            </p>
            <button type="button" class="btn btn-outline" onclick="toggleTheme()" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:10px;">
                <span>สลับธีม (Toggle Light / Dark Mode)</span>
            </button>
        </div>

        <!-- Card: ข้อมูลพนักงานและผู้ดูแลระบบ -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="font-size:1.05rem; margin-bottom:12px;">👤 ข้อมูลบัญชีผู้ใช้</h3>
            <div style="font-size:0.9rem; line-height:1.8; color:var(--text-color);">
                <div><strong>ชื่อ-นามสกุล:</strong> <?= htmlspecialchars($userName) ?></div>
                <div><strong>รหัสพนักงาน:</strong> <?= htmlspecialchars($empCode) ?></div>
                <div><strong>แผนก:</strong> <?= htmlspecialchars($deptName) ?></div>
                <div><strong>สิทธิ์ใช้งาน:</strong> <?= htmlspecialchars(strtoupper($userRole)) ?></div>
            </div>

            <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <a href="admin/dashboard.php" class="btn btn-outline" style="width:100%; text-align:center;">
                    💻 เข้าสู่ระบบผู้ดูแลระบบ (Admin Portal)
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="font-size:1.05rem; margin-bottom:12px;">🔒 การรักษาความปลอดภัย</h3>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">
                หากต้องการเปลี่ยนรหัสผ่านส่วนตัว สามารถทำรายการได้ที่หน้าโปรไฟล์ของคุณ
            </p>
            <a href="profile.php" class="btn btn-primary" style="width:100%; text-align:center;">
                ไปยังหน้าโปรไฟล์ & เปลี่ยนรหัสผ่าน
            </a>
        </div>

    </div>

    <!-- Bottom Mobile Navigation Bar -->
    <nav class="bottom-nav">
        <a href="employee_home.php" class="nav-item">
            <span>ลงเวลา</span>
        </a>
        <a href="leave_form.php" class="nav-item">
            <span>ยื่นลางาน</span>
        </a>
        <a href="profile.php" class="nav-item">
            <span>โปรไฟล์</span>
        </a>
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <a href="admin/dashboard.php" class="nav-item">
            <span>จัดการระบบ</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="handleLogout()" class="nav-item">
            <span>ออกระบบ</span>
        </a>
    </nav>

    <!-- Core Scripts -->
    <script src="assets/js/auth.js"></script>
</body>
</html>
