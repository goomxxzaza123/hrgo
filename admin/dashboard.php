<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น manager หรือ admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

$userName = $currentUser['name'];
$userRole = strtoupper($currentUser['role']);
$deptName = $_SESSION['dept_name'] ?? 'ไม่ระบุ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HR Management System</title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Core & Admin CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Theme Manager -->
    <script src="../assets/js/theme.js"></script>
</head>
<body>

    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">HR</div>
                <div class="sidebar-title">HR Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="dashboard.php" class="sidebar-link active">
                        <span>ภาพรวมองค์กร</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="approve_leave.php" class="sidebar-link">
                        <span>อนุมัติใบลา</span>
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
                <li class="sidebar-menu-item">
                    <a href="manage_users.php" class="sidebar-link">
                        <span>จัดการพนักงาน</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_departments.php" class="sidebar-link">
                        <span>จัดการแผนก</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="reports.php" class="sidebar-link">
                        <span>รายงานลงเวลา & CSV</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_holidays.php" class="sidebar-link">
                        <span>วันหยุดบริษัท</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_settings.php" class="sidebar-link">
                        <span>ตั้งค่าพิกัด & รัศมี</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-menu-item" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                    <a href="../employee_home.php" class="sidebar-link">
                        <span>สลับไปหน้าพนักงาน</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="../logout.php" class="btn btn-danger btn-sm" style="width: 100%;">
                    ออกจากระบบ
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1>Dashboard สรุปภาพรวม HR</h1>
                    <p style="color:var(--text-muted);">ยินดีต้อนรับ <?= htmlspecialchars($userName) ?> (<?= $userRole ?> | <?= htmlspecialchars($deptName) ?>)</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <span class="badge badge-info"><?= date('d/m/Y') ?></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">พนักงานทั้งหมด</span>
                        <span class="stat-value" id="statTotalEmployees">0</span>
                    </div>
                    <div class="stat-icon primary">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">เข้างานแล้ววันนี้</span>
                        <span class="stat-value" id="statPresentToday">0</span>
                    </div>
                    <div class="stat-icon success">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">เข้างานสายวันนี้</span>
                        <span class="stat-value" id="statLateToday">0</span>
                    </div>
                    <div class="stat-icon warning">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">คำขอลาที่รออนุมัติ</span>
                        <span class="stat-value" id="statPendingLeaves">0</span>
                    </div>
                    <div class="stat-icon danger">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    </div>
                </div>
            </div>

            <!-- Card: ตารางการลงเวลาของวันนี้ล่าสุด -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">บันทึกเวลาเข้า-ออกงานของพนักงานวันนี้</div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>แผนก</th>
                                <th>กะงาน</th>
                                <th>เวลา เข้า / ออก</th>
                                <th>รูปถ่าย</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="recentAttendanceTable">
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px; color:var(--text-muted);">
                                    กำลังโหลดข้อมูลสถิติ...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
