<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น manager หรือ admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

$userName = $currentUser['name'];
$userRole = strtoupper($currentUser['role']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติใบลา | HR Management System</title>
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
                    <a href="dashboard.php" class="sidebar-link">
                        <span>ภาพรวมองค์กร</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="approve_leave.php" class="sidebar-link active">
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
                    <h1>ระบบพิจารณาอนุมัติใบลา</h1>
                    <p style="color:var(--text-muted);">จัดการคำขอลางานของพนักงาน (พิจารณาอนุมัติและตัดโควตาอัตโนมัติ)</p>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <label for="statusFilter" style="font-size:0.9rem; font-weight:500;">ตัวกรอง:</label>
                    <select id="statusFilter" class="form-control" style="width: auto; padding: 6px 12px;">
                        <option value="">ทั้งหมด (All)</option>
                        <option value="pending" selected>รออนุมัติ (Pending)</option>
                        <option value="approved">อนุมัติแล้ว (Approved)</option>
                        <option value="rejected">ปฏิเสธ (Rejected)</option>
                    </select>
                </div>
            </div>

            <!-- Card: ตารางคำขอลางาน -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>พนักงาน</th>
                                <th>แผนก</th>
                                <th>ประเภท</th>
                                <th>ช่วงวันที่ขอลา</th>
                                <th>เหตุผลการลา</th>
                                <th>สถานะ</th>
                                <th style="min-width: 150px;">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody id="leaveApprovalTable">
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px; color:var(--text-muted);">
                                    กำลังโหลดข้อมูลคำขอลางาน...
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
