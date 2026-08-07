<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้งาน (เฉพาะ Admin และ Manager เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);
$userName    = $_SESSION['name'] ?? 'ผู้ดูแลระบบ';
$userRole    = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแผนก | HR Management System</title>
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
            <div class="sidebar-header" style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="sidebar-logo">HR</div>
                    <div class="sidebar-title">HR GO Admin</div>
                </div>
                <a href="../logout.php" class="btn btn-danger btn-sm" style="padding:4px 10px; font-size:0.75rem; font-weight:600; flex-shrink:0;">🚪 ออกจากระบบ</a>
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
                <li class="sidebar-menu-item">
                    <a href="manage_users.php" class="sidebar-link">
                        <span>จัดการพนักงาน</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_departments.php" class="sidebar-link active">
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
                    <h1>จัดการแผนกองค์กร</h1>
                    <p style="color:var(--text-muted);">สร้าง แก้ไข และลบแผนกภายในบริษัทเพื่อจัดหมวดหมู่พนักงาน</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="../logout.php" class="btn btn-danger btn-sm mobile-toggle-btn" style="padding:6px 12px; font-size:0.8rem; font-weight:600;">🚪 ออกจากระบบ</a>
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <button class="btn btn-primary" onclick="openAddDeptModal()">
                        + เพิ่มแผนกใหม่
                    </button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid" style="margin-bottom:16px;">
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">แผนกทั้งหมด</span>
                        <span class="stat-value" id="statDeptCount">0</span>
                    </div>
                    <div class="stat-icon primary">🏢</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">พนักงานสังกัดแผนก</span>
                        <span class="stat-value" id="statEmpCount">0</span>
                    </div>
                    <div class="stat-icon success">👥</div>
                </div>
            </div>

            <!-- Card: ตารางแผนก -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>รหัสแผนก</th>
                                <th>ชื่อแผนก</th>
                                <th>จำนวนพนักงานสังกัด</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="departmentTable">
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 20px; color:var(--text-muted);">
                                    กำลังโหลดข้อมูลแผนก...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Modal: เพิ่มแผนกใหม่ -->
    <div class="modal-backdrop" id="addDeptModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h3>🏢 เพิ่มแผนกใหม่</h3>
                <button type="button" onclick="closeAddDeptModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="addDeptForm" onsubmit="handleAddDeptSubmit(event)">
                <div class="form-group">
                    <label class="form-label">ชื่อแผนก</label>
                    <input type="text" id="add_dept_name" class="form-control" placeholder="เช่น ฝ่ายบัญชีและการเงิน" required>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeAddDeptModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">💾 บันทึกแผนกใหม่</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: แก้ไขชื่อแผนก -->
    <div class="modal-backdrop" id="editDeptModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h3>✏️ แก้ไขชื่อแผนก</h3>
                <button type="button" onclick="closeEditDeptModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <form id="editDeptForm" onsubmit="handleEditDeptSubmit(event)">
                <input type="hidden" id="edit_dept_id">
                <div class="form-group">
                    <label class="form-label">ชื่อแผนก</label>
                    <input type="text" id="edit_dept_name" class="form-control" required>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeEditDeptModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">💾 บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadManageDepartments();
        });

        async function loadManageDepartments() {
            try {
                const response = await fetch('../api/admin_departments.php');
                const result = await response.json();

                if (!result.success) return;

                const depts = result.data;
                document.getElementById('statDeptCount').textContent = depts.length;

                let totalEmp = 0;
                depts.forEach(d => totalEmp += parseInt(d.employee_count || 0));
                document.getElementById('statEmpCount').textContent = totalEmp;

                renderDeptTable(depts);

            } catch (err) {
                console.error('Load depts error:', err);
            }
        }

        function renderDeptTable(depts) {
            const tbody = document.getElementById('departmentTable');
            if (!tbody) return;

            if (!depts || depts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">ยังไม่มีแผนกในระบบ</td></tr>`;
                return;
            }

            let html = '';
            depts.forEach(d => {
                const deptJson = JSON.stringify(d).replace(/'/g, "&apos;");
                html += `
                    <tr>
                        <td><strong>DEPT-${d.dept_id}</strong></td>
                        <td><strong>${escapeHtml(d.dept_name)}</strong></td>
                        <td><span class="badge badge-info">${d.employee_count} คน</span></td>
                        <td>
                            <div class="action-dropdown">
                                <button type="button" class="action-dropdown-btn" onclick="toggleActionDropdown(this, event)">
                                    ⚙️ จัดการ ▾
                                </button>
                                <div class="action-dropdown-menu">
                                    <a class="action-dropdown-item" onclick='openEditDeptModal(${deptJson})'>
                                        <span>✏️</span> แก้ไขชื่อแผนก
                                    </a>
                                    <div class="action-dropdown-divider"></div>
                                    <a class="action-dropdown-item danger" onclick="deleteDept(${d.dept_id}, '${escapeHtml(d.dept_name)}', ${d.employee_count})">
                                        <span>🗑️</span> ลบแผนกนี้
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function openAddDeptModal() {
            document.getElementById('add_dept_name').value = '';
            document.getElementById('addDeptModal').classList.add('active');
        }

        function closeAddDeptModal() {
            document.getElementById('addDeptModal').classList.remove('active');
        }

        async function handleAddDeptSubmit(e) {
            e.preventDefault();
            const deptName = document.getElementById('add_dept_name').value.trim();

            try {
                const response = await fetch('../api/admin_departments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', dept_name: deptName })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: result.message, timer: 1500, showConfirmButton: false });
                    closeAddDeptModal();
                    loadManageDepartments();
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
                }
            } catch (err) {
                console.error('Add dept error:', err);
            }
        }

        function openEditDeptModal(dept) {
            document.getElementById('edit_dept_id').value = dept.dept_id;
            document.getElementById('edit_dept_name').value = dept.dept_name;
            document.getElementById('editDeptModal').classList.add('active');
        }

        function closeEditDeptModal() {
            document.getElementById('editDeptModal').classList.remove('active');
        }

        async function handleEditDeptSubmit(e) {
            e.preventDefault();
            const deptId   = document.getElementById('edit_dept_id').value;
            const deptName = document.getElementById('edit_dept_name').value.trim();

            try {
                const response = await fetch('../api/admin_departments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', dept_id: deptId, dept_name: deptName })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: result.message, timer: 1500, showConfirmButton: false });
                    closeEditDeptModal();
                    loadManageDepartments();
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
                }
            } catch (err) {
                console.error('Edit dept error:', err);
            }
        }

        async function deleteDept(deptId, deptName, empCount) {
            const warningText = empCount > 0 
                ? `แผนกนี้มีพนักงานสังกัดอยู่ ${empCount} คน การลบแผนกจะปรับแผนกของพนักงานกลุ่มนี้เป็น 'ไม่ระบุ'!`
                : 'คุณต้องการลบแผนกนี้ใช่หรือไม่?';

            const confirmRes = await Swal.fire({
                title: `ยืนยันการลบแผนก "${deptName}"?`,
                text: warningText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E74C3C',
                cancelButtonColor: '#95A5A6',
                confirmButtonText: 'ใช่, ลบแผนก!',
                cancelButtonText: 'ยกเลิก'
            });

            if (!confirmRes.isConfirmed) return;

            try {
                const response = await fetch('../api/admin_departments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', dept_id: deptId })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: result.message, timer: 1500, showConfirmButton: false });
                    loadManageDepartments();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถลบแผนกได้', text: result.message });
                }
            } catch (err) {
                console.error('Delete dept error:', err);
            }
        }
    </script>
</body>
</html>
