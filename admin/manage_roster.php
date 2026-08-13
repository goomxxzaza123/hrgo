<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น manager หรือ admin เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$userName = $currentUser['name'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดตารางกะการทำงาน (Roster) | HR Management System</title>
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
        .roster-container {
            overflow-x: auto;
            position: relative;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            max-height: calc(100vh - 230px);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .roster-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
        }
        .roster-table th, .roster-table td {
            padding: 8px 6px;
            text-align: center;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .roster-table th {
            position: sticky;
            top: 0;
            background: var(--surface-soft);
            color: var(--text-main);
            z-index: 10;
            font-weight: 600;
        }
        .roster-table th.emp-col, .roster-table td.emp-col {
            position: sticky;
            left: 0;
            background: var(--card-bg);
            z-index: 20;
            text-align: left;
            min-width: 170px;
            max-width: 200px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.04);
        }
        .roster-table th.emp-col {
            z-index: 30;
            background: var(--surface-soft);
        }
        .roster-table tr.sunday-col, .roster-table th.sunday-col {
            background-color: rgba(254, 240, 138, 0.25) !important;
        }
        .roster-cell {
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        }
        .roster-cell:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .shift-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            min-width: 44px;
        }
        .shift-day {
            background: #E0E7FF;
            color: #3730A3;
        }
        .shift-night {
            background: #F3E8FF;
            color: #6B21A8;
        }
        .shift-off {
            background: #F1F5F9;
            color: #64748B;
        }
        [data-theme="dark"] .shift-day {
            background: #312E81;
            color: #E0E7FF;
        }
        [data-theme="dark"] .shift-night {
            background: #581C87;
            color: #F3E8FF;
        }
        [data-theme="dark"] .shift-off {
            background: #334155;
            color: #94A3B8;
        }

        /* Drag & Drop Employee Selection Styles */
        .drag-drop-box {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 10px;
            background: var(--surface-soft);
            transition: all 0.2s ease;
            max-height: 220px;
            overflow-y: auto;
        }
        .drag-drop-box.target {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }
        .drag-drop-box.drag-over {
            border-color: #10B981 !important;
            background: rgba(16, 185, 129, 0.12) !important;
            transform: scale(1.01);
        }
        .drag-drop-header {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--border-color);
        }
        .drag-drop-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-height: 120px;
        }
        .emp-drag-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            border-radius: 8px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: grab;
            user-select: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.15s ease;
        }
        .emp-drag-card:hover {
            transform: translateY(-1px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .emp-drag-card:active {
            cursor: grabbing;
        }
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
                <li class="sidebar-menu-item">
                    <a href="manage_roster.php" class="sidebar-link active">
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
                    <h1>ระบบจัดตารางกะการทำงานรายเดือน</h1>
                    <p style="color:var(--text-muted);">จัดการกะงานรายวัน (กะเช้า / กะดึก / วันหยุด) และกำหนดตารางเวรพนักงานรายเดือน</p>
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openBatchRosterModal()">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> จัดกะอัตโนมัติทั้งเดือน
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="confirmClearMonthRoster()" style="color:var(--danger-color); border-color:var(--danger-color);">
                        <i class="fa-solid fa-trash-can"></i> ล้างกะเดือนนี้
                    </button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card" style="padding: 14px 18px; margin-bottom: 16px; max-width: 100%; box-sizing: border-box;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <label for="rosterMonth" style="font-weight:600; font-size:0.9rem;">ประจำเดือน:</label>
                            <input type="month" id="rosterMonth" class="form-control" style="width:auto; padding:6px 12px;" onchange="loadRosterTable()">
                        </div>

                        <div style="display:flex; align-items:center; gap:8px;">
                            <label for="rosterDept" style="font-weight:600; font-size:0.9rem;">แผนก:</label>
                            <select id="rosterDept" class="form-control" style="width:auto; padding:6px 12px;" onchange="loadRosterTable()">
                                <option value="">ทั้งหมดทุกแผนก</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px; font-size:0.8rem; flex-wrap:wrap;">
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-day"><i class="fa-solid fa-sun"></i> เช้า</span> = 08:00 - 17:00
                        </span>
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-night"><i class="fa-solid fa-moon"></i> ดึก</span> = 20:00 - 05:00
                        </span>
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-off"><i class="fa-solid fa-umbrella-beach"></i> หยุด</span> = วันหยุดประจำสัปดาห์ (Off)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Matrix Table Container -->
            <div class="roster-container" id="rosterTableContainer">
                <div style="text-align:center; padding:40px; color:var(--text-muted);">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>กำลังโหลดตารางกะรายเดือน...
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: จัดกะอัตโนมัติประจำเดือน (Batch Save Roster) -->
    <div class="modal-backdrop" id="batchRosterModal" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 540px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-wand-magic-sparkles"></i> จัดกะอัตโนมัติประจำเดือน</h3>
                <button type="button" onclick="closeBatchRosterModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="batchRosterForm" onsubmit="handleBatchRosterSubmit(event)">
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-calendar-range"></i> ขอบเขตช่วงเวลาการจัดกะ</label>
                    <div style="display:flex; gap:16px; margin-bottom:10px;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:0.88rem;">
                            <input type="radio" name="batch_date_mode" value="month" checked onchange="toggleBatchDateMode('month')">
                            จัดกะเต็มเดือน (Full Month)
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:0.88rem;">
                            <input type="radio" name="batch_date_mode" value="custom" onchange="toggleBatchDateMode('custom')">
                            ระบุช่วงวันที่ / หมุนเวียน 2 สัปดาห์
                        </label>
                    </div>
                </div>

                <div class="form-group" id="monthInputGroup">
                    <label class="form-label">เดือนที่ต้องการจัดกะ</label>
                    <input type="month" id="batch_month" class="form-control" required onchange="handleBatchMonthChange()">
                </div>

                <!-- Custom Date Range Controls for 2-Week Rotation -->
                <div id="customDateRangeContainer" style="display:none; background:var(--surface-soft); padding:12px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:16px;">
                    <div style="font-size:0.82rem; font-weight:600; margin-bottom:8px; color:var(--text-main);">
                        <i class="fa-solid fa-clock-rotate-left"></i> ระบุช่วงวันที่สำหรับกะนี้ (เช่น 2 สัปดาห์ / 14 วัน):
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div>
                            <label class="form-label" style="font-size:0.78rem;">วันที่เริ่มต้น</label>
                            <input type="date" id="batch_start_date" class="form-control" style="font-size:0.85rem; padding:6px 10px;">
                        </div>
                        <div>
                            <label class="form-label" style="font-size:0.78rem;">วันที่สิ้นสุด</label>
                            <input type="date" id="batch_end_date" class="form-control" style="font-size:0.85rem; padding:6px 10px;">
                        </div>
                    </div>

                    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">ปุ่มเลือกช่วงเวลาด่วน (Quick Presets):</div>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px;" onclick="applyBatchDatePreset('week2_1')">⚡ 2 สัปดาห์แรก (วันที่ 1 - 14)</button>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px;" onclick="applyBatchDatePreset('week2_2')">⚡ 2 สัปดาห์หลัง (วันที่ 15 - 28)</button>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px;" onclick="applyBatchDatePreset('half1')">🗓️ ครึ่งแรก (วันที่ 1 - 15)</button>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px;" onclick="applyBatchDatePreset('half2')">🗓️ ครึ่งหลัง (วันที่ 16 - สิ้นเดือน)</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">รูปแบบตารางเวร (Shift Pattern)</label>
                    <select id="batch_pattern" class="form-control" required>
                        <option value="default_6day_night">ทำงาน 6 วัน (จันทร์-เสาร์ กะดึก | อาทิตย์ หยุด)</option>
                        <option value="default_6day">ทำงาน 6 วัน (จันทร์-เสาร์ กะเช้า | อาทิตย์ หยุด)</option>
                        <option value="all_day">กะเช้าทุกวัน (จันทร์-อาทิตย์)</option>
                        <option value="all_night">กะดึกทุกวัน (จันทร์-อาทิตย์)</option>
                        <option value="all_off">ตั้งเป็นวันหยุดทั้งหมด</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-users"></i> ขอบเขตพนักงานเป้าหมาย</label>
                    <div style="display:flex; gap:16px; margin-bottom:10px;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:0.88rem;">
                            <input type="radio" name="batch_target_mode" value="all" checked onchange="toggleBatchTargetMode('all')">
                            ทั้งแผนก / พนักงานทุกคน
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:0.88rem;">
                            <input type="radio" name="batch_target_mode" value="individual" onchange="toggleBatchTargetMode('individual')">
                            ลาก / เลือกรายชื่อพนักงานเฉพาะคน
                        </label>
                    </div>
                </div>

                <div class="form-group" id="batchDeptGroup">
                    <label class="form-label">แผนกเป้าหมาย</label>
                    <select id="batch_dept_id" class="form-control" onchange="refreshBatchEmpLists()">
                        <option value="">ทุกแผนก (All Departments)</option>
                    </select>
                </div>

                <!-- Drag & Drop Individual Selection Container -->
                <div id="individualDragDropContainer" style="display:none; margin-bottom:16px;">
                    <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:8px;">
                        <i class="fa-solid fa-hand-pointer"></i> ลากรายชื่อพนักงานจากซ้ายไปวางฝั่งขวา (หรือคลิกที่ชื่อ) เพื่อเลือกผู้ที่จะใส่กะนี้
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <!-- Left Box: พนักงานทั้งหมด -->
                        <div class="drag-drop-box" id="poolEmpBox" ondragover="handleEmpDragOver(event)" ondragleave="handleEmpDragLeave(event)" ondrop="handleEmpDrop(event, 'unassigned')">
                            <div class="drag-drop-header"><i class="fa-solid fa-users"></i> รายชื่อพนักงาน</div>
                            <div id="poolEmpList" class="drag-drop-list"></div>
                        </div>
                        <!-- Right Box: พนักงานที่ถูกเลือก -->
                        <div class="drag-drop-box target" id="targetEmpBox" ondragover="handleEmpDragOver(event)" ondragleave="handleEmpDragLeave(event)" ondrop="handleEmpDrop(event, 'selected')">
                            <div class="drag-drop-header"><i class="fa-solid fa-user-check" style="color:#10B981;"></i> เลือกแล้ว (<span id="targetEmpCount">0</span> คน)</div>
                            <div id="targetEmpList" class="drag-drop-list"></div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeBatchRosterModal()">ยกเลิก</button>
                    <button type="submit" id="batchSubmitBtn" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> ยืนยันการจัดกะ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Core & Roster Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            const curMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('rosterMonth').value = curMonth;
            document.getElementById('batch_month').value = curMonth;

            loadDepartmentsFilter();
            loadRosterTable();
        });

        let currentRosterData = null;

        async function loadDepartmentsFilter() {
            try {
                const res = await fetch('../api/admin_departments.php');
                const data = await res.json();
                if (data.success && data.data) {
                    const select = document.getElementById('rosterDept');
                    const batchSelect = document.getElementById('batch_dept_id');
                    let opts = '<option value="">ทั้งหมดทุกแผนก</option>';
                    let batchOpts = '<option value="">ทุกแผนก (All Departments)</option>';
                    data.data.forEach(d => {
                        opts += `<option value="${d.dept_id}">${escapeHtml(d.dept_name)}</option>`;
                        batchOpts += `<option value="${d.dept_id}">${escapeHtml(d.dept_name)}</option>`;
                    });
                    select.innerHTML = opts;
                    batchSelect.innerHTML = batchOpts;
                }
            } catch (e) {
                console.error('Error loading depts:', e);
            }
        }

        async function loadRosterTable() {
            const container = document.getElementById('rosterTableContainer');
            const month = document.getElementById('rosterMonth').value;
            const deptId = document.getElementById('rosterDept').value;

            if (!month) return;

            container.innerHTML = `
                <div style="text-align:center; padding:40px; color:var(--text-muted);">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>กำลังดึงข้อมูลตารางกะ...
                </div>
            `;

            try {
                const res = await fetch(`../api/admin_roster.php?month=${month}&dept_id=${deptId}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    container.innerHTML = `<div style="text-align:center; padding:30px; color:var(--danger-color);">${escapeHtml(result.message)}</div>`;
                    return;
                }

                currentRosterData = result.data;
                renderRosterMatrix(currentRosterData);

            } catch (err) {
                console.error('Error loading roster:', err);
                container.innerHTML = `<div style="text-align:center; padding:30px; color:var(--danger-color);">ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</div>`;
            }
        }

        function renderRosterMatrix(data) {
            const container = document.getElementById('rosterTableContainer');
            const users = data.users || [];
            const daysInMonth = data.days_in_month;
            const monthStr = data.month;
            const rosterMap = data.roster_map || {};
            const holidayMap = data.holiday_map || {};

            if (users.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding:40px; color:var(--text-muted);">ไม่พบรายชื่อพนักงานในแผนกที่เลือก</div>`;
                return;
            }

            const thaiDays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

            let html = '<table class="roster-table">';
            
            // Header Row 1: วันที่และชื่อวันในสัปดาห์
            html += '<thead><tr>';
            html += '<th class="emp-col">รายชื่อพนักงาน</th>';

            for (let d = 1; d <= daysInMonth; d++) {
                const dStr = monthStr + '-' + String(d).padStart(2, '0');
                const dateObj = new Date(dStr);
                const dayNum = dateObj.getDay();
                const dayName = thaiDays[dayNum];
                const isSun = (dayNum === 0);
                const isHoliday = !!holidayMap[dStr];

                let cellStyle = isSun ? 'background:rgba(254, 240, 138, 0.3);' : (isHoliday ? 'background:rgba(243, 232, 255, 0.5);' : '');
                let titleAttr = isHoliday ? `title="${escapeHtml(holidayMap[dStr])}"` : '';

                html += `<th style="${cellStyle}" ${titleAttr}><div>${dayName}</div><div style="font-size:0.9rem; font-weight:700;">${d}</div></th>`;
            }
            html += '</tr></thead><tbody>';

            // Data Rows: พนักงานแต่ละคน
            users.forEach(u => {
                const uId = u.user_id;
                html += '<tr>';
                html += `<td class="emp-col">
                            <div style="font-weight:600;">${escapeHtml(u.name)}</div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">${u.emp_code} | ${escapeHtml(u.dept_name || 'ไม่ระบุ')}</div>
                         </td>`;

                for (let d = 1; d <= daysInMonth; d++) {
                    const dStr = monthStr + '-' + String(d).padStart(2, '0');
                    const dateObj = new Date(dStr);
                    const dayNum = dateObj.getDay();
                    const isSun = (dayNum === 0);

                    const userRosters = rosterMap[uId] || {};
                    const r = userRosters[dStr];

                    let shiftType = r ? r.shift_type : u.default_shift;
                    if (!r && isSun) {
                        shiftType = 'off'; // ค่าเริ่มต้นวันอาทิตย์เป็นวันหยุด
                    }

                    let badgeClass = 'shift-day';
                    let label = 'เช้า';
                    if (shiftType === 'night') {
                        badgeClass = 'shift-night';
                        label = 'ดึก';
                    } else if (shiftType === 'off') {
                        badgeClass = 'shift-off';
                        label = 'หยุด';
                    }

                    html += `<td class="roster-cell" onclick="toggleUserShift(${uId}, '${dStr}', '${shiftType}')" title="คลิกเพื่อเปลี่ยนกะ">
                                <span class="shift-badge ${badgeClass}">${label}</span>
                             </td>`;
                }
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        async function toggleUserShift(userId, dateStr, currentShift) {
            const nextShiftMap = {
                'day': 'night',
                'night': 'off',
                'off': 'day'
            };
            const nextShift = nextShiftMap[currentShift] || 'day';

            try {
                const response = await fetch('../api/admin_roster.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_single',
                        user_id: userId,
                        date: dateStr,
                        shift_type: nextShift
                    })
                });

                const result = await response.json();
                if (response.ok && result.success) {
                    loadRosterTable();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถบันทึกกะได้', text: result.message });
                }
            } catch (e) {
                console.error('Error toggling shift:', e);
            }
        }

        let batchTargetMode = 'all'; // 'all' or 'individual'
        let batchDateMode = 'month'; // 'month' or 'custom'
        let selectedTargetUserIds = [];
        let draggedUserId = null;

        function toggleBatchDateMode(mode) {
            batchDateMode = mode;
            const rangeBox = document.getElementById('customDateRangeContainer');
            if (mode === 'custom') {
                rangeBox.style.display = 'block';
                applyBatchDatePreset('week2_1');
            } else {
                rangeBox.style.display = 'none';
            }
        }

        function handleBatchMonthChange() {
            if (batchDateMode === 'custom') {
                applyBatchDatePreset('week2_1');
            }
        }

        function applyBatchDatePreset(presetType) {
            const monthVal = document.getElementById('batch_month').value;
            if (!monthVal) return;
            const [yr, mo] = monthVal.split('-');
            const year = parseInt(yr, 10);
            const month = parseInt(mo, 10);
            
            const lastDayNum = new Date(year, month, 0).getDate();
            const padM = String(month).padStart(2, '0');

            if (presetType === 'half1') {
                document.getElementById('batch_start_date').value = `${year}-${padM}-01`;
                document.getElementById('batch_end_date').value = `${year}-${padM}-15`;
            } else if (presetType === 'half2') {
                document.getElementById('batch_start_date').value = `${year}-${padM}-16`;
                document.getElementById('batch_end_date').value = `${year}-${padM}-${String(lastDayNum).padStart(2, '0')}`;
            } else if (presetType === 'week2_1') {
                document.getElementById('batch_start_date').value = `${year}-${padM}-01`;
                document.getElementById('batch_end_date').value = `${year}-${padM}-14`;
            } else if (presetType === 'week2_2') {
                document.getElementById('batch_start_date').value = `${year}-${padM}-15`;
                document.getElementById('batch_end_date').value = `${year}-${padM}-28`;
            }
        }

        function toggleBatchTargetMode(mode) {
            batchTargetMode = mode;
            const dragContainer = document.getElementById('individualDragDropContainer');
            if (mode === 'individual') {
                dragContainer.style.display = 'block';
                refreshBatchEmpLists();
            } else {
                dragContainer.style.display = 'none';
            }
        }

        function refreshBatchEmpLists() {
            if (!currentRosterData || !currentRosterData.users) return;
            const deptId = document.getElementById('batch_dept_id').value;
            
            // กรองพนักงานตามแผนกที่เลือก
            let users = currentRosterData.users;
            if (deptId) {
                users = users.filter(u => String(u.dept_id) === String(deptId));
            }

            const poolList = document.getElementById('poolEmpList');
            const targetList = document.getElementById('targetEmpList');
            const targetCount = document.getElementById('targetEmpCount');

            if (!poolList || !targetList) return;

            let poolHtml = '';
            let targetHtml = '';
            let count = 0;

            users.forEach(u => {
                const uId = u.user_id;
                const isSelected = selectedTargetUserIds.includes(uId);

                const cardHtml = `
                    <div class="emp-drag-card" draggable="true" 
                         ondragstart="handleEmpDragStart(event, ${uId})" 
                         onclick="toggleEmpSelection(${uId})"
                         title="ลาก หรือ คลิกเพื่อเลือก">
                        <span><strong>${escapeHtml(u.name)}</strong> (${u.emp_code})</span>
                        <i class="fa-solid ${isSelected ? 'fa-minus' : 'fa-plus'}" style="color:${isSelected ? '#EF4444' : '#10B981'}; font-size:0.75rem;"></i>
                    </div>
                `;

                if (isSelected) {
                    targetHtml += cardHtml;
                    count++;
                } else {
                    poolHtml += cardHtml;
                }
            });

            poolList.innerHTML = poolHtml || '<div style="font-size:0.75rem; color:var(--text-muted); padding:8px; text-align:center;">ไม่มีพนักงาน</div>';
            targetList.innerHTML = targetHtml || '<div style="font-size:0.75rem; color:var(--text-muted); padding:8px; text-align:center;">ยังไม่ได้เลือกพนักงาน<br>(ลากหรือคลิกที่ชื่อเพื่อเลือก)</div>';
            targetCount.textContent = count;
        }

        function toggleEmpSelection(userId) {
            if (selectedTargetUserIds.includes(userId)) {
                selectedTargetUserIds = selectedTargetUserIds.filter(id => id !== userId);
            } else {
                selectedTargetUserIds.push(userId);
            }
            refreshBatchEmpLists();
        }

        function handleEmpDragStart(e, userId) {
            draggedUserId = userId;
            e.dataTransfer.setData('text/plain', String(userId));
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleEmpDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
        }

        function handleEmpDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleEmpDrop(e, targetType) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            const uId = parseInt(draggedUserId || e.dataTransfer.getData('text/plain'));
            if (!uId) return;

            if (targetType === 'selected') {
                if (!selectedTargetUserIds.includes(uId)) {
                    selectedTargetUserIds.push(uId);
                }
            } else if (targetType === 'unassigned') {
                selectedTargetUserIds = selectedTargetUserIds.filter(id => id !== uId);
            }
            refreshBatchEmpLists();
        }

        function openBatchRosterModal() {
            selectedTargetUserIds = [];
            
            const dateModes = document.querySelectorAll('input[name="batch_date_mode"]');
            if (dateModes.length > 0) dateModes[0].checked = true;
            toggleBatchDateMode('month');

            const targetModes = document.querySelectorAll('input[name="batch_target_mode"]');
            if (targetModes.length > 0) targetModes[0].checked = true;
            toggleBatchTargetMode('all');

            document.getElementById('batchRosterModal').classList.add('active');
        }

        function closeBatchRosterModal() {
            document.getElementById('batchRosterModal').classList.remove('active');
        }

        async function handleBatchRosterSubmit(e) {
            e.preventDefault();
            const month = document.getElementById('batch_month').value;
            const deptId = document.getElementById('batch_dept_id').value;
            const pattern = document.getElementById('batch_pattern').value;
            const btn = document.getElementById('batchSubmitBtn');

            if (batchTargetMode === 'individual' && selectedTargetUserIds.length === 0) {
                Swal.fire({ icon: 'warning', title: 'กรุณาเลือกพนักงาน', text: 'กรุณาลากหรือเลือกพนักงานอย่างน้อย 1 คนสำหรับจัดกะนี้' });
                return;
            }

            const startDate = (batchDateMode === 'custom') ? document.getElementById('batch_start_date').value : '';
            const endDate   = (batchDateMode === 'custom') ? document.getElementById('batch_end_date').value : '';

            if (batchDateMode === 'custom' && (!startDate || !endDate)) {
                Swal.fire({ icon: 'warning', title: 'กรุณาระบุช่วงวันที่', text: 'กรุณาใส่วันที่เริ่มต้นและวันที่สิ้นสุด' });
                return;
            }

            try {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';

                const payload = {
                    action: 'batch_save',
                    month,
                    pattern
                };

                if (batchDateMode === 'custom') {
                    payload.start_date = startDate;
                    payload.end_date = endDate;
                }

                if (batchTargetMode === 'individual') {
                    payload.target_user_ids = selectedTargetUserIds;
                } else {
                    payload.dept_id = deptId;
                }

                const res = await fetch('../api/admin_roster.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();
                if (res.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'จัดกะเรียบร้อยแล้ว!', text: result.message, timer: 1800, showConfirmButton: false });
                    closeBatchRosterModal();
                    loadRosterTable();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถจัดกะได้', text: result.message });
                }
            } catch (e) {
                console.error('Error batch roster:', e);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ยืนยันการจัดกะ';
            }
        }

        async function confirmClearMonthRoster() {
            const month = document.getElementById('rosterMonth').value;
            const deptId = document.getElementById('rosterDept').value;

            const confirm = await Swal.fire({
                title: 'ล้างตารางกะเดือนนี้?',
                text: `คุณต้องการล้างข้อมูลตารางกะทั้งหมดของเดือน ${month} ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                confirmButtonText: 'ล้างตารางกะ',
                cancelButtonText: 'ยกเลิก'
            });

            if (!confirm.isConfirmed) return;

            try {
                const res = await fetch('../api/admin_roster.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'clear_month',
                        month,
                        dept_id: deptId
                    })
                });
                const result = await res.json();
                if (res.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'ล้างตารางกะเรียบร้อยแล้ว', timer: 1500, showConfirmButton: false });
                    loadRosterTable();
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
                }
            } catch (e) {
                console.error('Error clear month:', e);
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
