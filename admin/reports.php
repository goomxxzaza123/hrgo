<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น manager หรือ admin เท่านั้น)
$currentUser = requireAuth(['manager', 'admin']);

$userName = $currentUser['name'];
$userRole = strtoupper($currentUser['role']);

$pdo = getDBConnection();
$stmtDepts = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_id ASC");
$departments = $stmtDepts->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุป HR | HR Management System</title>
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
    <style>
        @media print {
            .sidebar, .admin-header button, #filterForm, .modal-backdrop, button { display: none !important; }
            .admin-main { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: #fff !important; }
        }

        /* Calendar Grid Styling */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-header-cell {
            background: #F1F5F9;
            padding: 10px 4px;
            font-weight: 700;
            text-align: center;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #334155;
            border: 1px solid #E2E8F0;
        }
        .calendar-header-cell.sunday {
            background: #FEF08A;
            color: #854D0E;
            border-color: #FDE047;
        }
        .calendar-day-cell {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            min-height: 115px;
            padding: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .calendar-day-cell:hover {
            border-color: #2563EB;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            transform: translateY(-2px);
        }
        .calendar-day-cell.sunday {
            background-color: #FEF9C3;
            border-color: #FDE047;
        }
        .calendar-day-cell.empty {
            background: #F8FAFC;
            border: 1px dashed #E2E8F0;
            cursor: default;
        }
        .calendar-day-cell.empty:hover {
            transform: none;
            box-shadow: none;
        }

        /* Mobile & Tablet Responsive Adjustments for Calendar */
        @media (max-width: 768px) {
            .calendar-grid {
                gap: 4px;
            }
            .calendar-header-cell {
                padding: 6px 2px;
                font-size: 0.75rem;
            }
            .calendar-day-cell {
                min-height: 68px;
                padding: 4px 2px;
                border-radius: 6px;
            }
            .calendar-day-number {
                font-size: 0.78rem;
            }
            .calendar-event-badge {
                font-size: 0.62rem;
                padding: 1px 3px;
                margin-bottom: 2px;
            }
        }
        .calendar-day-number {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1E293B;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2px;
            border-bottom: 1px solid #F1F5F9;
        }
        .calendar-event-badge {
            font-size: 0.72rem;
            padding: 3px 5px;
            border-radius: 4px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            line-height: 1.2;
        }
        .calendar-event-badge.on-time { background: #E6F4EA; color: #137333; border: 1px solid #A8DADC; }
        .calendar-event-badge.late { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .calendar-event-badge.leave { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
        .calendar-event-badge.ot { background: #FFEDD5; color: #C2410C; border: 1px solid #FED7AA; font-weight:bold; }
        .calendar-event-badge.sunday-off { background: #FEF9C3; color: #854D0E; border: 1px solid #FDE047; }
    </style>
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
                    <a href="approve_leave.php" class="sidebar-link">
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
                <?php endif; ?>
                <li class="sidebar-menu-item">
                    <a href="reports.php" class="sidebar-link active">
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
                    <h1>รายงานสรุปการลงเวลาเข้า-ออกงาน</h1>
                    <p style="color:var(--text-muted);">เรียกดู ค้นหา และส่งออกไฟล์รายงาน CSV สำหรับงานฝ่ายบุคคล</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    
                    <!-- Dropdown ปุ่มส่งออกรายงาน Excel -->
                    <div class="dropdown" style="position: relative; display: inline-block;">
                        <button type="button" class="btn btn-success" id="exportDropdownBtn" onclick="toggleExportMenu(event)" style="display:flex; align-items:center; gap:8px; font-weight:600; padding:9px 16px; border-radius:10px;">
                            <i class="fa-solid fa-file-excel"></i> <span>ส่งออกรายงาน Excel (CSV)</span>
                            <span style="font-size:0.75rem; transition: transform 0.2s;" id="exportDropdownArrow">▼</span>
                        </button>
                        <div id="exportDropdownMenu" style="display:none; position:absolute; right:0; top:calc(100% + 6px); background:var(--card-bg, #1E293B); border:1px solid var(--border-color, #334155); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.4); z-index:1000; min-width:260px; padding:6px;">
                            <a href="javascript:void(0)" onclick="exportReportAllCsv()" style="display:flex; align-items:center; gap:10px; padding:10px 14px; color:var(--text-main, #F8FAFC); border-radius:8px; text-decoration:none; font-size:0.88rem; transition:all 0.2s;" onmouseover="this.style.background='rgba(16, 185, 129, 0.15)'" onmouseout="this.style.background='transparent'">
                                <i class="fa-solid fa-users-line" style="font-size:1.2rem; color:#10B981;"></i>
                                <div>
                                    <div style="font-weight:600;">ส่งออกรายงานพนักงานทุกคน</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted, #94A3B8);">รวมข้อมูลพนักงานทุกคนทุกแผนก</div>
                                </div>
                            </a>
                            <div style="height:1px; background:var(--border-color, #334155); margin:4px 0;"></div>
                            <a href="javascript:void(0)" onclick="exportReportIndividualCsv()" style="display:flex; align-items:center; gap:10px; padding:10px 14px; color:var(--text-main, #F8FAFC); border-radius:8px; text-decoration:none; font-size:0.88rem; transition:all 0.2s;" onmouseover="this.style.background='rgba(16, 185, 129, 0.15)'" onmouseout="this.style.background='transparent'">
                                <i class="fa-solid fa-user-check" style="font-size:1.2rem; color:#3B82F6;"></i>
                                <div>
                                    <div style="font-weight:600;">ส่งออกรายงานรายบุคคล (Excel)</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted, #94A3B8);">ส่งออก Excel เฉพาะพนักงานที่ระบุ</div>
                                </div>
                            </a>
                            <div style="height:1px; background:var(--border-color, #334155); margin:4px 0;"></div>
                            <a href="javascript:void(0)" onclick="printIndividualReportPdf()" style="display:flex; align-items:center; gap:10px; padding:10px 14px; color:var(--text-main, #F8FAFC); border-radius:8px; text-decoration:none; font-size:0.88rem; transition:all 0.2s;" onmouseover="this.style.background='rgba(16, 185, 129, 0.15)'" onmouseout="this.style.background='transparent'">
                                <i class="fa-solid fa-print" style="font-size:1.2rem; color:#F59E0B;"></i>
                                <div>
                                    <div style="font-weight:600;">พิมพ์ / บันทึก PDF (รายบุคคล 1 หน้าจบ)</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted, #94A3B8);">แสดงหน้าเอกสารเต็มแบบ 1 หน้า A4 เข้าเล่ม/เซฟ PDF</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: ตัวกรองการค้นหา -->
            <div class="card">
                <form id="filterForm" onsubmit="handleFilterSubmit(event)">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">ตั้งแต่วันที่</label>
                            <input type="date" id="filter_start_date" class="form-control" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">ถึงวันที่</label>
                            <input type="date" id="filter_end_date" class="form-control" value="<?= date('Y-m-t') ?>">
                        </div>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">แผนก</label>
                            <select id="filter_dept_id" class="form-control">
                                <option value="0">ทั้งหมดทุกแผนก</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">เลือกพนักงาน</label>
                            <select id="filter_user_id" class="form-control">
                                <option value="0">พนักงานทุกคน (ทุกแผนก)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">สถานะ</label>
                            <select id="filter_status" class="form-control">
                                <option value="">ทุกสถานะ</option>
                                <option value="on_time">ตรงเวลา (On Time)</option>
                                <option value="late">สาย (Late)</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                <i class="fa-solid fa-magnifying-glass"></i> ค้นหา
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Summary Cards -->
            <div class="stats-grid" style="margin-bottom:16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">รวมบันทึกทั้งหมด (ครั้ง)</span>
                        <span class="stat-value" id="reportTotal">0</span>
                    </div>
                    <div class="stat-icon primary"><i class="fa-solid fa-list-check"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">ตรงเวลา (ครั้ง)</span>
                        <span class="stat-value" id="reportOnTime">0</span>
                    </div>
                    <div class="stat-icon success"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">สาย (ครั้ง)</span>
                        <span class="stat-value" id="reportLate">0</span>
                    </div>
                    <div class="stat-icon warning"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">วันลา (ครั้ง)</span>
                        <span class="stat-value" id="reportLeave">0</span>
                    </div>
                    <div class="stat-icon info"><i class="fa-solid fa-calendar-minus"></i></div>
                </div>
            </div>

            <!-- Card: รายการแสดงผล (สลับมุมมอง ปฏิทิน / ตาราง) -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0; font-size:1.1rem; color:var(--text-color);">การแสดงผลข้อมูล</h3>
                    <div class="report-tab-switcher">
                        <button type="button" id="btnViewCalendar" class="btn btn-sm btn-primary" style="padding:6px 16px; border-radius:6px;" onclick="switchReportView('calendar')">
                            <i class="fa-solid fa-calendar-days"></i> มุมมองปฏิทิน (Calendar View)
                        </button>
                        <button type="button" id="btnViewTable" class="btn btn-sm" style="padding:6px 16px; border-radius:6px; background:transparent; color:var(--text-muted);" onclick="switchReportView('table')">
                            <i class="fa-solid fa-table-list"></i> มุมมองตาราง (Table View)
                        </button>
                    </div>
                </div>

                <!-- 1. มุมมองปฏิทิน (Calendar View Container) -->
                <div id="calendarViewContainer" style="display:block;">
                    <!-- Calendar Controls Header -->
                    <div class="calendar-controls-bar">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="changeCalendarMonth(-1)">◀ เดือนก่อนหน้า</button>
                            <h2 id="calendarMonthTitle" class="calendar-month-title" style="margin:0 12px;">กรกฎาคม 2569</h2>
                            <button type="button" class="btn btn-sm btn-outline" onclick="changeCalendarMonth(1)">เดือนถัดไป ▶</button>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <label class="form-label calendar-filter-label" style="margin:0;">กรองปฏิทินรายบุคคล:</label>
                            <select id="calendarEmployeeSelect" class="form-control" style="width:auto; padding:4px 10px; font-size:0.9rem;" onchange="renderCalendarView()">
                                <option value="0">พนักงานทุกคน (ทุกแผนก)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Calendar Legend Bar -->
                    <div style="display:flex; gap:15px; margin-bottom:14px; flex-wrap:wrap; font-size:0.85rem; padding:0 4px;">
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; border-radius:3px; background:#10B981; display:inline-block;"></span> ตรงเวลา</span>
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; border-radius:3px; background:#F59E0B; display:inline-block;"></span> สาย</span>
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; border-radius:3px; background:#0EA5E9; display:inline-block;"></span> วันลาอนุมัติ</span>
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; border-radius:3px; background:#D35400; display:inline-block;"></span> ทำ OT</span>
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; border-radius:3px; background:#FEF9C3; border:1px solid #FDE047; display:inline-block;"></span> วันอาทิตย์ (วันหยุด)</span>
                    </div>

                    <!-- Calendar Grid -->
                    <div id="calendarGrid" class="calendar-grid">
                        <!-- Dynamic Calendar Content -->
                    </div>
                </div>

                <!-- 2. มุมมองตาราง (Table View Container) -->
                <div id="tableViewContainer" style="display:none;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>รหัส</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>แผนก</th>
                                    <th>เข้างาน</th>
                                    <th>ออกงาน</th>
                                    <th>ชั่วโมงทำงาน</th>
                                    <th>เวลา OT</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="reportTable">
                                <tr>
                                    <td colspan="10" style="text-align:center; padding: 20px; color:var(--text-muted);">
                                        กำลังโหลดรายงานการลงเวลา...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Modal: แก้ไขเวลาเข้า-ออกงาน -->
    <div class="modal-backdrop" id="editAttendanceModal" style="z-index: 2000;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>แก้ไขเวลาลงเวลาเข้า-ออกงาน</h3>
                <button type="button" onclick="closeEditAttendanceModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="editAttendanceForm" onsubmit="handleEditAttendanceSubmit(event)">
                <input type="hidden" id="edit_attendance_id">
                
                <div style="background:var(--bg-color); padding:12px; border-radius:var(--radius-sm); margin-bottom:14px; border:1px solid var(--border-color);">
                    <div style="font-weight:600; font-size:1rem; color:var(--primary-color);" id="edit_att_employee_name">สมชาย ใจดี</div>
                    <div style="font-size:0.85rem; color:var(--text-muted);" id="edit_att_date_info">ประจำวันที่: 31/07/2026</div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">เวลาเข้างาน (Check-In)</label>
                        <input type="time" id="edit_check_in_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">เวลาออกงาน (Check-Out)</label>
                        <input type="time" id="edit_check_out_time" class="form-control">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeEditAttendanceModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไขเวลา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: รายละเอียดการลงเวลาประจำวัน (Calendar Day Detail Modal) -->
    <div class="modal-backdrop" id="calendarDayModal">
        <div class="modal-content" style="max-width: 760px; width: 90%;">
            <div class="modal-header">
                <h3 id="calendarDayModalTitle">รายละเอียดการลงเวลาประจำวันที่ --/--/----</h3>
                <button type="button" onclick="closeCalendarDayModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <div style="max-height: 480px; overflow-y: auto; padding: 4px;">
                <div class="table-responsive">
                    <table class="table" style="font-size:0.88rem;">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>แผนก</th>
                                <th>เข้างาน</th>
                                <th>ออกงาน</th>
                                <th>ชั่วโมง</th>
                                <th>OT</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="calendarDayModalBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeCalendarDayModal()">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        let globalReportsData = [];
        let globalEmployeesData = [];
        let currentViewMode = 'calendar';
        let calendarYear = 2026;
        let calendarMonth = 7; // July (1-indexed)

        document.addEventListener('DOMContentLoaded', () => {
            // อ่านค่าเดือนและปีจาก filter_start_date
            const sDate = document.getElementById('filter_start_date').value;
            if (sDate) {
                const dt = new Date(sDate);
                calendarYear = dt.getFullYear();
                calendarMonth = dt.getMonth() + 1;
            }
            loadAttendanceReports();
        });

        function switchReportView(mode) {
            currentViewMode = mode;
            const calContainer = document.getElementById('calendarViewContainer');
            const tableContainer = document.getElementById('tableViewContainer');
            const btnCal = document.getElementById('btnViewCalendar');
            const btnTab = document.getElementById('btnViewTable');

            if (mode === 'calendar') {
                calContainer.style.display = 'block';
                tableContainer.style.display = 'none';
                btnCal.className = 'btn btn-sm btn-primary';
                btnCal.style.background = 'var(--primary-color)';
                btnCal.style.color = '#fff';

                btnTab.className = 'btn btn-sm';
                btnTab.style.background = 'transparent';
                btnTab.style.color = 'var(--text-muted)';
                renderCalendarView();
            } else {
                calContainer.style.display = 'none';
                tableContainer.style.display = 'block';
                btnTab.className = 'btn btn-sm btn-primary';
                btnTab.style.background = 'var(--primary-color)';
                btnTab.style.color = '#fff';

                btnCal.className = 'btn btn-sm';
                btnCal.style.background = 'transparent';
                btnCal.style.color = 'var(--text-muted)';
            }
        }

        async function loadAttendanceReports() {
            const startDate = document.getElementById('filter_start_date').value;
            const endDate   = document.getElementById('filter_end_date').value;
            const deptId    = document.getElementById('filter_dept_id') ? document.getElementById('filter_dept_id').value : 0;
            const userId    = document.getElementById('filter_user_id') ? document.getElementById('filter_user_id').value : 0;
            const status    = document.getElementById('filter_status').value;

            if (startDate) {
                const parts = startDate.split('-');
                if (parts.length === 3) {
                    calendarYear = parseInt(parts[0]);
                    calendarMonth = parseInt(parts[1]);
                }
            }

            const query = new URLSearchParams({
                start_date: startDate,
                end_date: endDate,
                dept_id: deptId,
                user_id: userId,
                status: status
            });

            try {
                const response = await fetch(`../api/admin_reports.php?${query.toString()}`);
                const result = await response.json();

                if (!result.success) return;

                const data = result.data;
                globalReportsData = data.reports || [];
                globalEmployeesData = data.employees || [];

                document.getElementById('reportTotal').textContent  = data.summary.total || 0;
                document.getElementById('reportOnTime').textContent = data.summary.on_time || 0;
                document.getElementById('reportLate').textContent   = data.summary.late || 0;
                if (document.getElementById('reportLeave')) {
                    document.getElementById('reportLeave').textContent = data.summary.leave || 0;
                }

                populateCalendarEmployeeDropdown(globalEmployeesData);
                renderReportTable(globalReportsData);
                renderCalendarView();

            } catch (error) {
                console.error('Error loading reports:', error);
            }
        }

        function populateCalendarEmployeeDropdown(employees) {
            const filterSel = document.getElementById('filter_user_id');
            const calSel    = document.getElementById('calendarEmployeeSelect');

            let html = '<option value="0">พนักงานทุกคน (ทุกแผนก)</option>';
            if (employees && employees.length > 0) {
                employees.forEach(e => {
                    html += `<option value="${e.user_id}">${e.emp_code} - ${escapeHtml(e.name)}</option>`;
                });
            }

            if (filterSel) {
                const curVal = filterSel.value;
                filterSel.innerHTML = html;
                filterSel.value = curVal || "0";
            }

            if (calSel) {
                const calVal = calSel.value;
                calSel.innerHTML = html;
                calSel.value = calVal || "0";
            }
        }

        function changeCalendarMonth(delta) {
            calendarMonth += delta;
            if (calendarMonth > 12) {
                calendarMonth = 1;
                calendarYear++;
            } else if (calendarMonth < 1) {
                calendarMonth = 12;
                calendarYear--;
            }

            const monthPad = String(calendarMonth).padStart(2, '0');
            const startStr = `${calendarYear}-${monthPad}-01`;
            const lastDay = new Date(calendarYear, calendarMonth, 0).getDate();
            const endStr = `${calendarYear}-${monthPad}-${String(lastDay).padStart(2, '0')}`;

            document.getElementById('filter_start_date').value = startStr;
            document.getElementById('filter_end_date').value = endStr;

            loadAttendanceReports();
        }

        function renderCalendarView() {
            const grid = document.getElementById('calendarGrid');
            if (!grid) return;

            const thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            document.getElementById('calendarMonthTitle').textContent = `${thaiMonths[calendarMonth]} ${calendarYear + 543} (${calendarYear})`;

            const selectedUserId = parseInt(document.getElementById('calendarEmployeeSelect').value || 0);
            let filteredReports = globalReportsData;
            if (selectedUserId > 0) {
                filteredReports = globalReportsData.filter(r => parseInt(r.user_id) === selectedUserId);
            }

            const dateMap = {};
            filteredReports.forEach(r => {
                if (!dateMap[r.work_date]) dateMap[r.work_date] = [];
                dateMap[r.work_date].push(r);
            });

            const dayHeaders = [
                { name: 'อาทิตย์', isSun: true },
                { name: 'จันทร์', isSun: false },
                { name: 'อังคาร', isSun: false },
                { name: 'พุธ', isSun: false },
                { name: 'พฤหัสบดี', isSun: false },
                { name: 'ศุกร์', isSun: false },
                { name: 'เสาร์', isSun: false }
            ];

            let html = '';
            dayHeaders.forEach(h => {
                const cls = h.isSun ? 'calendar-header-cell sunday' : 'calendar-header-cell';
                html += `<div class="${cls}">${h.name}</div>`;
            });

            const firstDayIndex = new Date(calendarYear, calendarMonth - 1, 1).getDay(); // 0 = Sun
            const totalDaysInMonth = new Date(calendarYear, calendarMonth, 0).getDate();

            for (let i = 0; i < firstDayIndex; i++) {
                html += `<div class="calendar-day-cell empty"></div>`;
            }

            for (let day = 1; day <= totalDaysInMonth; day++) {
                const dayPad = String(day).padStart(2, '0');
                const monthPad = String(calendarMonth).padStart(2, '0');
                const dStr = `${calendarYear}-${monthPad}-${dayPad}`;
                const curDateObj = new Date(calendarYear, calendarMonth - 1, day);
                const isSun = (curDateObj.getDay() === 0);

                const dayRecords = dateMap[dStr] || [];
                const cellClass = isSun ? 'calendar-day-cell sunday' : 'calendar-day-cell';

                // กรองเฉพาะรายการที่มีการลงเวลาจริง, OT หรือ วันลาอนุมัติ เท่านั้น (ไม่แสดงวันหยุด/ขาดงานที่ไม่มีลงเวลา)
                const activeRecords = dayRecords.filter(r => {
                    const hasCheckIn = (r.check_in_time && r.check_in_time !== '-');
                    const isLeave = (r.status === 'leave');
                    const hasOt = (r.ot_hours > 0);
                    return hasCheckIn || isLeave || hasOt;
                });

                let badgeHtml = '';
                let count = 0;
                const maxBadges = 3;

                activeRecords.forEach(r => {
                    if (count < maxBadges) {
                        let bCls = 'on-time';
                        let label = '';

                        if (r.status === 'leave') {
                            bCls = 'leave';
                            label = `${r.emp_code}: ${r.status_label}`;
                        } else if (r.ot_hours > 0) {
                            bCls = 'ot';
                            label = `${r.emp_code}: OT ${r.ot_hours}ชม.`;
                        } else if (r.status === 'late') {
                            bCls = 'late';
                            label = `${r.emp_code}: สาย (${r.late_minutes}น.)`;
                        } else {
                            bCls = 'on-time';
                            label = `${r.emp_code}: ตรงเวลา`;
                        }

                        badgeHtml += `<span class="calendar-event-badge ${bCls}">${escapeHtml(label)}</span>`;
                    }
                    count++;
                });

                if (count > maxBadges) {
                    badgeHtml += `<span style="font-size:0.7rem; color:var(--text-muted); text-align:center;">+${count - maxBadges} รายการเพิ่มเติม</span>`;
                }

                html += `
                    <div class="${cellClass}" onclick="openDayDetailModal('${dStr}')">
                        <div class="calendar-day-number">
                            <span>${day}</span>
                            ${activeRecords.length > 0 ? `<span class="badge badge-outline" style="font-size:0.65rem; padding:1px 4px;">${activeRecords.length}</span>` : ''}
                        </div>
                        <div>${badgeHtml}</div>
                    </div>
                `;
            }

            grid.innerHTML = html;
        }

        function openDayDetailModal(dateStr) {
            const selectedUserId = parseInt(document.getElementById('calendarEmployeeSelect').value || 0);
            let dayRecords = globalReportsData.filter(r => r.work_date === dateStr);
            if (selectedUserId > 0) {
                dayRecords = dayRecords.filter(r => parseInt(r.user_id) === selectedUserId);
            }

            const dt = new Date(dateStr);
            const dateTh = `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}/${dt.getFullYear()}`;
            document.getElementById('calendarDayModalTitle').textContent = `รายละเอียดการลงเวลาประจำวันที่ ${dateTh}`;

            const tbody = document.getElementById('calendarDayModalBody');
            if (dayRecords.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:var(--text-muted);">ไม่มีรายการลงเวลาในวันนี้</td></tr>`;
            } else {
                let html = '';
                dayRecords.forEach(r => {
                    const isSunday = (r.is_sunday || r.status === 'sunday');
                    const isLeave = (r.status === 'leave');
                    const isOnTime = (r.status === 'on_time' || r.status === 'normal');
                    const badgeClass = isLeave ? 'badge-info' : (isSunday ? 'badge-warning' : (isOnTime ? 'badge-success' : 'badge-warning'));

                    const inPhotoBtn = r.check_in_photo 
                        ? ` <button class="btn btn-sm btn-outline" style="padding:1px 5px; font-size:0.7rem;" onclick="viewAdminPhoto('../${r.check_in_photo}', 'รูปถ่ายเข้างาน - ${escapeHtml(r.employee_name)}')">รูปถ่าย</button>` 
                        : '';

                    const outPhotoBtn = r.check_out_photo 
                        ? ` <button class="btn btn-sm btn-outline" style="padding:1px 5px; font-size:0.7rem;" onclick="viewAdminPhoto('../${r.check_out_photo}', 'รูปถ่ายออกงาน - ${escapeHtml(r.employee_name)}')">รูปถ่าย</button>` 
                        : '';

                    const recordJson = JSON.stringify(r).replace(/'/g, "&apos;");

                    html += `
                        <tr>
                            <td><b>${r.emp_code}</b></td>
                            <td>${escapeHtml(r.employee_name)}</td>
                            <td>${escapeHtml(r.dept_name)}</td>
                            <td>${r.check_in_time}${inPhotoBtn}</td>
                            <td>${r.check_out_time}${outPhotoBtn}</td>
                            <td>${r.work_hours} ชม.</td>
                            <td>${r.ot_hours > 0 ? `<b style="color:#D35400;">${r.ot_hours} ชม.</b>` : '-'}</td>
                            <td><span class="badge ${badgeClass}">${r.status_label}</span></td>
                            <td>
                                <button class="btn btn-primary btn-sm" style="padding:1px 6px; font-size:0.72rem;" onclick='openEditAttendanceModal(${recordJson})'>แก้ไข</button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }

            document.getElementById('calendarDayModal').classList.add('active');
        }

        function closeCalendarDayModal() {
            document.getElementById('calendarDayModal').classList.remove('active');
        }

        function handleFilterSubmit(e) {
            e.preventDefault();
            loadAttendanceReports();
        }

        function renderReportTable(reports) {
            const tbody = document.getElementById('reportTable');
            if (!tbody) return;

            if (!reports || reports.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" style="text-align:center; padding:20px; color:var(--text-muted);">
                            ไม่พบข้อมูลตามเงื่อนไขที่เลือก
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            reports.forEach(r => {
                const isCompanyHoliday = (r.status === 'company_holiday');
                const isSunday = (r.is_sunday || r.status === 'sunday');
                const isLeave = (r.status === 'leave');
                const isOnTime = (r.status === 'on_time' || r.status === 'normal');
                const badgeClass = isLeave ? 'badge-info' : (isCompanyHoliday ? 'badge-info' : (isSunday ? 'badge-warning' : (isOnTime ? 'badge-success' : 'badge-warning')));
                const rowStyle = isCompanyHoliday ? 'style="background-color:#F3E8FF;"' : (isSunday ? 'style="background-color:#FEF9C3;"' : '');

                const inPhotoBtn = r.check_in_photo 
                    ? ` <button class="btn btn-sm btn-outline" style="padding:2px 6px; font-size:0.75rem;" onclick="viewAdminPhoto('../${r.check_in_photo}', 'รูปถ่ายเข้างาน - ${escapeHtml(r.employee_name)}')">รูปถ่าย</button>` 
                    : '';

                const outPhotoBtn = r.check_out_photo 
                    ? ` <button class="btn btn-sm btn-outline" style="padding:2px 6px; font-size:0.75rem;" onclick="viewAdminPhoto('../${r.check_out_photo}', 'รูปถ่ายออกงาน - ${escapeHtml(r.employee_name)}')">รูปถ่าย</button>` 
                    : '';

                const otBadge = (r.ot_hours > 0) 
                    ? `<span style="color:#D35400; font-weight:bold;">${r.ot_hours} ชม.</span>` 
                    : '<span style="color:var(--text-muted);">-</span>';

                const recordJson = JSON.stringify(r).replace(/'/g, "&apos;");

                html += `
                    <tr ${rowStyle}>
                        <td><strong>${r.work_date_th}</strong></td>
                        <td>${r.emp_code}</td>
                        <td>${escapeHtml(r.employee_name)}</td>
                        <td>${escapeHtml(r.dept_name)}</td>
                        <td>${r.check_in_time}${inPhotoBtn}</td>
                        <td>${r.check_out_time}${outPhotoBtn}</td>
                        <td><strong>${r.work_hours} ชม.</strong></td>
                        <td>${otBadge}</td>
                        <td><span class="badge ${badgeClass}">${r.status_label}</span></td>
                        <td>
                            <div style="display:flex; gap:4px;">
                                <button class="btn btn-success btn-sm" style="padding:2px 6px; font-size:0.75rem;" onclick="exportSingleUserCsv(${r.user_id})" title="ดาวน์โหลด Excel เฉพาะพนักงานคนนี้">Excel</button>
                                <button class="btn btn-primary btn-sm" style="padding:2px 6px; font-size:0.75rem;" onclick='openEditAttendanceModal(${recordJson})'>แก้ไข</button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function openEditAttendanceModal(att) {
            closeCalendarDayModal();

            document.getElementById('edit_attendance_id').value = att.attendance_id;
            document.getElementById('edit_att_employee_name').textContent = att.employee_name + ' (' + att.emp_code + ')';
            document.getElementById('edit_att_date_info').textContent = 'ประจำวันที่: ' + att.work_date_th;

            // เติมเวลาเข้าและเวลาออก (ตัดวินาทีออก)
            const inTime  = (att.check_in_time && att.check_in_time !== '-') ? att.check_in_time.substring(0, 5) : '';
            const outTime = (att.check_out_time && att.check_out_time !== '-') ? att.check_out_time.substring(0, 5) : '';

            document.getElementById('edit_check_in_time').value = inTime;
            document.getElementById('edit_check_out_time').value = outTime;

            document.getElementById('editAttendanceModal').classList.add('active');
        }

        function closeEditAttendanceModal() {
            document.getElementById('editAttendanceModal').classList.remove('active');
        }

        async function handleEditAttendanceSubmit(e) {
            e.preventDefault();

            const attId   = document.getElementById('edit_attendance_id').value;
            const inTime  = document.getElementById('edit_check_in_time').value;
            const outTime = document.getElementById('edit_check_out_time').value;

            try {
                const response = await fetch('../api/admin_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_attendance',
                        attendance_id: attId,
                        check_in_time: inTime,
                        check_out_time: outTime
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'แก้ไขเวลาลงงานสำเร็จ!', timer: 1500, showConfirmButton: false });
                    closeEditAttendanceModal();
                    loadAttendanceReports();
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
                }
            } catch (err) {
                console.error('Edit attendance error:', err);
            }
        }

        function viewAdminPhoto(url, title) {
            Swal.fire({
                title: title,
                imageUrl: url,
                imageWidth: 320,
                imageHeight: 320,
                imageAlt: title,
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#4A90E2'
            });
        }

        function printReport() {
            window.print();
        }

        function exportSingleUserCsv(userId) {
            const startDate = document.getElementById('filter_start_date').value;
            const endDate = document.getElementById('filter_end_date').value;
            const status = document.getElementById('filter_status').value;

            const query = new URLSearchParams({
                start_date: startDate,
                end_date: endDate,
                user_id: userId,
                status: status,
                export: 'csv'
            });

            window.location.href = `../api/admin_reports.php?${query.toString()}`;
        }

        function toggleExportMenu(e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById('exportDropdownMenu');
            const arrow = document.getElementById('exportDropdownArrow');
            if (!menu) return;
            const isVisible = (menu.style.display === 'block');
            menu.style.display = isVisible ? 'none' : 'block';
            if (arrow) arrow.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
        }

        document.addEventListener('click', function(e) {
            const menu = document.getElementById('exportDropdownMenu');
            const btn  = document.getElementById('exportDropdownBtn');
            const arrow = document.getElementById('exportDropdownArrow');
            if (menu && menu.style.display === 'block') {
                if (btn && btn.contains(e.target)) return;
                menu.style.display = 'none';
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            }
        });

        function exportReportAllCsv() {
            toggleExportMenu();
            const startDate = document.getElementById('filter_start_date').value;
            const endDate   = document.getElementById('filter_end_date').value;
            const deptId    = document.getElementById('filter_dept_id') ? document.getElementById('filter_dept_id').value : 0;
            const status    = document.getElementById('filter_status').value;

            const query = new URLSearchParams({
                start_date: startDate,
                end_date: endDate,
                dept_id: deptId,
                user_id: 0,
                status: status,
                export: 'csv'
            });

            window.location.href = `../api/admin_reports.php?${query.toString()}`;
        }

        function exportReportIndividualCsv() {
            toggleExportMenu();
            const startDate = document.getElementById('filter_start_date').value;
            const endDate   = document.getElementById('filter_end_date').value;
            const filterUserIdEl = document.getElementById('filter_user_id');
            const selectedUserId = filterUserIdEl ? parseInt(filterUserIdEl.value || 0) : 0;
            const status    = document.getElementById('filter_status').value;

            if (selectedUserId > 0) {
                const query = new URLSearchParams({
                    start_date: startDate,
                    end_date: endDate,
                    user_id: selectedUserId,
                    status: status,
                    export: 'csv'
                });
                window.location.href = `../api/admin_reports.php?${query.toString()}`;
            } else {
                let optionsHtml = '';
                if (globalEmployeesData && globalEmployeesData.length > 0) {
                    globalEmployeesData.forEach(emp => {
                        optionsHtml += `<option value="${emp.user_id}">${emp.emp_code} - ${escapeHtml(emp.name)} (${escapeHtml(emp.dept_name || '')})</option>`;
                    });
                }

                Swal.fire({
                    title: '👤 เลือกพนักงานที่ต้องการส่งออกรายงาน',
                    html: `
                        <div style="text-align:left; margin-top:10px;">
                            <label class="form-label" style="font-weight:bold; font-size:0.9rem;">รายชื่อพนักงาน:</label>
                            <select id="swalSelectUserId" class="form-control" style="width:100%; padding:10px; margin-top:5px;">
                                ${optionsHtml}
                            </select>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '📥 ดาวน์โหลด Excel',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#10B981',
                    focusConfirm: false,
                    preConfirm: () => {
                        const uId = document.getElementById('swalSelectUserId').value;
                        if (!uId || uId === '0') {
                            Swal.showValidationMessage('กรุณาเลือกพนักงาน');
                            return false;
                        }
                        return uId;
                    }
                }).then((res) => {
                    if (res.isConfirmed && res.value) {
                        if (filterUserIdEl) filterUserIdEl.value = res.value;

                        const query = new URLSearchParams({
                            start_date: startDate,
                            end_date: endDate,
                            user_id: res.value,
                            status: status,
                            export: 'csv'
                        });
                        window.location.href = `../api/admin_reports.php?${query.toString()}`;
                    }
                });
            }
        }

        function printIndividualReportPdf() {
            toggleExportMenu();
            const startDate = document.getElementById('filter_start_date').value;
            const endDate   = document.getElementById('filter_end_date').value;
            const filterUserIdEl = document.getElementById('filter_user_id');
            const selectedUserId = filterUserIdEl ? parseInt(filterUserIdEl.value || 0) : 0;
            const status    = document.getElementById('filter_status').value;

            if (selectedUserId > 0) {
                const query = new URLSearchParams({
                    start_date: startDate,
                    end_date: endDate,
                    user_id: selectedUserId,
                    status: status,
                    print: 1
                });
                window.open(`../api/admin_reports.php?${query.toString()}`, '_blank');
            } else {
                let optionsHtml = '';
                if (globalEmployeesData && globalEmployeesData.length > 0) {
                    globalEmployeesData.forEach(emp => {
                        optionsHtml += `<option value="${emp.user_id}">${emp.emp_code} - ${escapeHtml(emp.name)} (${escapeHtml(emp.dept_name || '')})</option>`;
                    });
                }

                Swal.fire({
                    title: '🖨️ เลือกพนักงานที่ต้องการพิมพ์ / เซฟ PDF',
                    html: `
                        <div style="text-align:left; margin-top:10px;">
                            <label class="form-label" style="font-weight:bold; font-size:0.9rem;">รายชื่อพนักงาน:</label>
                            <select id="swalSelectUserIdPrint" class="form-control" style="width:100%; padding:10px; margin-top:5px;">
                                ${optionsHtml}
                            </select>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '🖨️ พิมพ์ / บันทึก PDF',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#10B981',
                    focusConfirm: false,
                    preConfirm: () => {
                        const uId = document.getElementById('swalSelectUserIdPrint').value;
                        if (!uId || uId === '0') {
                            Swal.showValidationMessage('กรุณาเลือกพนักงาน');
                            return false;
                        }
                        return uId;
                    }
                }).then((res) => {
                    if (res.isConfirmed && res.value) {
                        if (filterUserIdEl) filterUserIdEl.value = res.value;

                        const query = new URLSearchParams({
                            start_date: startDate,
                            end_date: endDate,
                            user_id: res.value,
                            status: status,
                            print: 1
                        });
                        window.open(`../api/admin_reports.php?${query.toString()}`, '_blank');
                    }
                });
            }
        }

        function exportReportCsv() {
            exportReportAllCsv();
        }
    </script>
</body>
</html>
