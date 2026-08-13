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
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
                <div class="sidebar-title">HR GO Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="dashboard.php" class="sidebar-link">
                        <i class="fa-solid fa-chart-pie icon"></i>
                        <span>ภาพรวมองค์กร</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="approve_leave.php" class="sidebar-link active">
                        <i class="fa-solid fa-file-circle-check icon"></i>
                        <span>อนุมัติใบลา</span>
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
                <li class="sidebar-menu-item">
                    <a href="manage_users.php" class="sidebar-link">
                        <i class="fa-solid fa-users-gear icon"></i>
                        <span>จัดการพนักงาน</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_departments.php" class="sidebar-link">
                        <i class="fa-solid fa-sitemap icon"></i>
                        <span>จัดการแผนก</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_roster.php" class="sidebar-link">
                        <i class="fa-solid fa-calendar-week icon"></i>
                        <span>จัดตารางกะ (Roster)</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="reports.php" class="sidebar-link">
                        <i class="fa-solid fa-file-csv icon"></i>
                        <span>รายงานลงเวลา & CSV</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_holidays.php" class="sidebar-link">
                        <i class="fa-solid fa-calendar-day icon"></i>
                        <span>วันหยุดบริษัท</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_settings.php" class="sidebar-link">
                        <i class="fa-solid fa-location-crosshairs icon"></i>
                        <span>ตั้งค่าพิกัด & รัศมี</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-menu-item" style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 6px;">
                    <a href="../employee_home.php" class="sidebar-link">
                        <i class="fa-solid fa-user-gear icon"></i>
                        <span>สลับไปหน้าพนักงาน</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="../logout.php" class="btn btn-danger btn-sm" style="width: 100%;">
                    <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
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
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAdminLeaveModal()">
                        <i class="fa-solid fa-user-plus"></i> ยื่นลาแทนพนักงาน
                    </button>
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

    <!-- Modal: ยื่นลาแทนพนักงาน (Admin / Manager On Behalf) -->
    <div class="modal-backdrop" id="adminLeaveModal" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> แบบฟอร์มยื่นลางานแทนพนักงาน</h3>
                <button type="button" onclick="closeAdminLeaveModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="adminLeaveForm" onsubmit="handleAdminLeaveSubmit(event)">
                <div class="form-group">
                    <label class="form-label">เลือกพนักงาน</label>
                    <select id="admin_leave_user_id" class="form-control" required>
                        <option value="">-- กำลังโหลดรายชื่อพนักงาน... --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ประเภทการลา</label>
                    <select id="admin_leave_type" class="form-control" required>
                        <option value="">-- เลือกประเภทการลา --</option>
                        <option value="sick">ลาป่วย (Sick Leave)</option>
                        <option value="personal">ลากิจ (Personal Leave)</option>
                        <option value="vacation">ลาพักร้อน (Vacation Leave)</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มลา</label>
                        <input type="date" id="admin_start_date" class="form-control" required onchange="calculateAdminLeaveDays()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" id="admin_end_date" class="form-control" required onchange="calculateAdminLeaveDays()">
                    </div>
                </div>

                <div id="adminCalculatedDaysDisplay" style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px; text-align:right;">
                    กรุณาเลือกวันที่
                </div>

                <div class="form-group">
                    <label class="form-label">เหตุผลการลา</label>
                    <textarea id="admin_leave_reason" class="form-control" placeholder="ระบุเหตุผลการลาแทนพนักงาน..." required style="height:80px;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                    <button type="button" class="btn btn-outline" onclick="closeAdminLeaveModal()">ยกเลิก</button>
                    <button type="submit" id="adminSubmitLeaveBtn" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> บันทึกและอนุมัติทันที
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: แก้ไขคำขอลางาน (Edit Leave Modal) -->
    <div class="modal-backdrop" id="editLeaveModal" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> แก้ไขคำขอลางาน</h3>
                <button type="button" onclick="closeEditLeaveModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="editLeaveForm" onsubmit="handleEditLeaveSubmit(event)">
                <input type="hidden" id="edit_leave_id">

                <div class="form-group">
                    <label class="form-label">พนักงาน</label>
                    <input type="text" id="edit_emp_name" class="form-control" readonly style="background:var(--surface-soft); font-weight:600;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ประเภทการลา</label>
                    <select id="edit_leave_type" class="form-control" required>
                        <option value="sick">ลาป่วย (Sick Leave)</option>
                        <option value="personal">ลากิจ (Personal Leave)</option>
                        <option value="vacation">ลาพักร้อน (Vacation Leave)</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มลา</label>
                        <input type="date" id="edit_start_date" class="form-control" required onchange="calculateEditLeaveDays()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" id="edit_end_date" class="form-control" required onchange="calculateEditLeaveDays()">
                    </div>
                </div>

                <div id="editCalculatedDaysDisplay" style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px; text-align:right;">
                    คำนวณจำนวนวัน...
                </div>

                <div class="form-group">
                    <label class="form-label">เหตุผลการลา</label>
                    <textarea id="edit_leave_reason" class="form-control" placeholder="ระบุเหตุผลการลา..." required style="height:80px;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                    <button type="button" class="btn btn-outline" onclick="closeEditLeaveModal()">ยกเลิก</button>
                    <button type="submit" id="editSubmitLeaveBtn" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
