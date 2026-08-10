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
            max-height: calc(100vh - 220px);
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
                    <button type="button" class="btn btn-primary btn-sm" onclick="openBatchRosterModal()">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> จัดกะอัตโนมัติทั้งเดือน
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="confirmClearMonthRoster()" style="color:var(--danger-color); border-color:var(--danger-color);">
                        <i class="fa-solid fa-trash-can"></i> ล้างกะเดือนนี้
                    </button>
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card" style="padding: 14px 18px; margin-bottom: 16px;">
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

                    <div style="margin-left:auto; display:flex; align-items:center; gap:14px; font-size:0.8rem;">
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-day">เช้า</span> = 08:00 - 17:00
                        </span>
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-night">ดึก</span> = 20:00 - 05:00
                        </span>
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="shift-badge shift-off">หยุด</span> = วันหยุดประจำสัปดาห์ (Off)
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

    <!-- Modal: จัดกะอัตโนมัติทั้งเดือน (Batch Save Roster) -->
    <div class="modal-backdrop" id="batchRosterModal" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-wand-magic-sparkles"></i> จัดกะอัตโนมัติประจำเดือน</h3>
                <button type="button" onclick="closeBatchRosterModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="batchRosterForm" onsubmit="handleBatchRosterSubmit(event)">
                <div class="form-group">
                    <label class="form-label">เดือนที่ต้องการจัดกะ</label>
                    <input type="month" id="batch_month" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">แผนกเป้าหมาย</label>
                    <select id="batch_dept_id" class="form-control">
                        <option value="">ทุกแผนก (All Departments)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">รูปแบบตารางเวร (Shift Pattern)</label>
                    <select id="batch_pattern" class="form-control" required>
                        <option value="default_5day">ทำงาน 5 วัน (จันทร์-ศุกร์ กะเช้า | เสาร์-อาทิตย์ หยุด)</option>
                        <option value="default_6day">ทำงาน 6 วัน (จันทร์-เสาร์ กะเช้า | อาทิตย์ หยุด)</option>
                        <option value="all_day">กะเช้าทุกวัน (จันทร์-อาทิตย์)</option>
                        <option value="all_night">กะดึกทุกวัน (จันทร์-อาทิตย์)</option>
                        <option value="all_off">ตั้งเป็นวันหยุดทั้งหมด</option>
                    </select>
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

        function openBatchRosterModal() {
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

            try {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';

                const res = await fetch('../api/admin_roster.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'batch_save',
                        month,
                        dept_id: deptId,
                        pattern
                    })
                });

                const result = await res.json();
                if (res.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'จัดกะอัตโนมัติสำเร็จ!', text: result.message, timer: 1800, showConfirmButton: false });
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
