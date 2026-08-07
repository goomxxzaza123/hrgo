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
    <title>กำหนดวันหยุดบริษัท | HR Management System</title>
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
                <div class="sidebar-title">HR GO Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="dashboard.php" class="sidebar-link">
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
                    <a href="manage_holidays.php" class="sidebar-link active">
                        <span>วันหยุดบริษัท</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_settings.php" class="sidebar-link">
                        <span>ตั้งค่าพิกัด & รัศมี</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-menu-item" style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 6px;">
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
                    <h1>🌴 กำหนดวันหยุดบริษัท & วันหยุดนักขัตฤกษ์</h1>
                    <p style="color:var(--text-muted);">กำหนดวันหยุดประจำปี วันหยุดพิเศษของบริษัท หรือวันหยุดนักขัตฤกษ์ เพื่อไม่ให้นับเป็นวันขาดงาน</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px; align-items:start;">
                
                <!-- Card: ฟอร์มเพิ่มวันหยุด -->
                <div class="card">
                    <div class="card-header" style="margin-bottom:16px;">
                        <h3 class="card-title">➕ เพิ่มวันหยุดใหม่</h3>
                    </div>
                    <form id="addHolidayForm" onsubmit="handleAddHolidaySubmit(event)">
                        <div class="form-group">
                            <label class="form-label">วันที่หยุด</label>
                            <input type="date" id="holiday_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ชื่อวันหยุด / รายละเอียด</label>
                            <input type="text" id="holiday_name" class="form-control" placeholder="เช่น วันสงกรานต์, วันหยุดพิเศษบริษัท" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ประเภทวันหยุด</label>
                            <select id="holiday_type" class="form-control">
                                <option value="company">🌴 วันหยุดพิเศษบริษัท (Company Holiday)</option>
                                <option value="public">🇹🇭 วันหยุดนักขัตฤกษ์ (Public Holiday)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">
                            บันทึกเพิ่มวันหยุด
                        </button>
                    </form>
                </div>

                <!-- Card: ตารางแสดงรายการวันหยุด -->
                <div class="card">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 class="card-title">📅 รายการวันหยุดในระบบ</h3>
                        <span id="holidayCountBadge" class="badge badge-info">0 รายการ</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>ชื่อวันหยุด</th>
                                    <th>ประเภท</th>
                                    <th style="width:70px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="holidaysTableBody">
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">
                                        กำลังโหลดรายการวันหยุด...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadHolidays();
        });

        async function loadHolidays() {
            try {
                const response = await fetch('../api/admin_holidays.php');
                const result = await response.json();

                if (!result.success) return;

                const data = result.data || [];
                const tbody = document.getElementById('holidaysTableBody');
                const badge = document.getElementById('holidayCountBadge');

                if (badge) badge.textContent = `${data.length} รายการ`;

                if (data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">ยังไม่มีวันหยุดในระบบ (สามารถเพิ่มใหม่จากฟอร์มด้านข้าง)</td></tr>`;
                    return;
                }

                let html = '';
                data.forEach(h => {
                    const badgeClass = h.holiday_type === 'public' ? 'badge-warning' : 'badge-info';
                    html += `
                        <tr>
                            <td><strong>${h.holiday_date_th}</strong></td>
                            <td>${escapeHtml(h.holiday_name)}</td>
                            <td><span class="badge ${badgeClass}">${h.type_label}</span></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="deleteHoliday(${h.holiday_id}, '${escapeHtml(h.holiday_name)}')">
                                    🗑️ ลบ
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;

            } catch (err) {
                console.error('Error loading holidays:', err);
            }
        }

        async function handleAddHolidaySubmit(e) {
            e.preventDefault();
            const date = document.getElementById('holiday_date').value;
            const name = document.getElementById('holiday_name').value.trim();
            const type = document.getElementById('holiday_type').value;

            if (!date || !name) {
                Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณากรอกวันที่และชื่อวันหยุด' });
                return;
            }

            try {
                const response = await fetch('../api/admin_holidays.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_holiday',
                        holiday_date: date,
                        holiday_name: name,
                        holiday_type: type
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'บันทึกวันหยุดสำเร็จ!', timer: 1500, showConfirmButton: false });
                    document.getElementById('holiday_name').value = '';
                    loadHolidays();
                } else {
                    Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: result.message });
                }
            } catch (err) {
                console.error('Error adding holiday:', err);
            }
        }

        function deleteHoliday(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบวันหยุด?',
                text: `คุณต้องการลบวันหยุด "${name}" ออกจากระบบใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'ลบวันหยุด',
                cancelButtonText: 'ยกเลิก'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('../api/admin_holidays.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'delete_holiday',
                                holiday_id: id
                            })
                        });

                        const res = await response.json();

                        if (response.ok && res.success) {
                            Swal.fire({ icon: 'success', title: 'ลบวันหยุดเรียบร้อย', timer: 1500, showConfirmButton: false });
                            loadHolidays();
                        } else {
                            Swal.fire({ icon: 'error', title: 'ลบไม่สำเร็จ', text: res.message });
                        }
                    } catch (err) {
                        console.error('Error deleting holiday:', err);
                    }
                }
            });
        }
    </script>
</body>
</html>
