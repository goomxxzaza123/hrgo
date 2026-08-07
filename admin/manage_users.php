<?php
require_once __DIR__ . '/../api/config.php';

// ตรวจสอบสิทธิ์ (ต้องเป็น manager หรือ admin เท่านั้น)
$currentUser = requireAuth(['admin', 'manager']);

$userName = $currentUser['name'];
$userRole = strtoupper($currentUser['role']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการพนักงาน | HR Management System</title>
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
                <li class="sidebar-menu-item">
                    <a href="manage_users.php" class="sidebar-link active">
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
                    <h1>ระบบจัดการข้อมูลพนักงาน</h1>
                    <p style="color:var(--text-muted);">เพิ่มพนักงานใหม่ กำหนดสิทธิ์ แผนก และตั้งค่าโควตาวันลาคงเหลือ</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                    <button class="btn btn-primary" onclick="openAddUserModal()">
                        + เพิ่มพนักงานใหม่
                    </button>
                </div>
            </div>

            <!-- Card: ตารางรายชื่อพนักงาน -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>สิทธิ์ (Role)</th>
                                <th>แผนก</th>
                                <th>กะการทำงาน (Shift)</th>
                                <th>โควตาคงเหลือ</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="usersTable">
                            <tr>
                                <td colspan="8" style="text-align:center; padding: 20px; color:var(--text-muted);">
                                    กำลังโหลดรายชื่อพนักงาน...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Admin Page Footer -->
            <footer class="admin-footer">
                <p>© <?= date('Y') ?> HR GO Management System. All rights reserved.</p>
                <p class="admin-footer-sub">ระบบบริหารจัดการทรัพยากรบุคคลและลงเวลาทำงาน (Intranet System)</p>
            </footer>

        </main>
    </div>

    <!-- Modal: เพิ่มพนักงานใหม่ -->
    <div class="modal-backdrop" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0;">เพิ่มพนักงานใหม่</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeAddUserModal()" style="width:auto; padding:2px 8px;">✕</button>
            </div>
            <form id="addUserForm" onsubmit="handleAddUserSubmit(event)">
                <div class="form-group">
                    <label class="form-label">รหัสพนักงาน (Employee Code)</label>
                    <input type="text" id="add_emp_code" class="form-control" placeholder="เช่น EMP005" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" id="add_name" class="form-control" placeholder="เช่น สมศักดิ์ มีสุข" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์</label>
                        <input type="tel" id="add_phone" class="form-control" placeholder="เช่น 081-234-5678">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">รหัสผ่านเริ่มต้น</label>
                    <input type="password" id="add_password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">สิทธิ์การใช้งาน (Role)</label>
                        <select id="add_role" class="form-control">
                            <option value="employee">Employee (พนักงาน)</option>
                            <option value="manager">Manager (หัวหน้าแผนก)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">แผนก</label>
                        <select id="add_dept_id" class="form-control">
                            <option value="1">IT</option>
                            <option value="2">HR</option>
                            <option value="3">Marketing</option>
                            <option value="4">Operations</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="text-align:center; background:var(--bg-color); padding:12px; border-radius:var(--radius-sm); border:1px dashed var(--border-color);">
                    <div style="margin-bottom:8px;">
                        <img id="add_avatar_preview" src="" alt="Avatar" style="width:72px; height:72px; border-radius:50%; object-fit:cover; display:none; border:2px solid var(--primary-color); margin:0 auto; box-shadow:var(--shadow-sm);">
                        <div id="add_avatar_placeholder" class="user-avatar" style="width:64px; height:64px; font-size:1.4rem; margin:0 auto; display:flex;">HR</div>
                    </div>
                    <label class="form-label" style="font-weight:600; margin-bottom:6px;">อัปโหลดรูปโปรไฟล์พนักงาน</label>
                    <input type="file" id="add_avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกพนักงานใหม่</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: แก้ไขข้อมูลพนักงาน -->
    <div class="modal-backdrop" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0;">แก้ไขข้อมูลพนักงาน</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeEditUserModal()" style="width:auto; padding:2px 8px;">✕</button>
            </div>
            <form id="editUserForm" onsubmit="handleEditUserSubmit(event)">
                <input type="hidden" id="edit_user_id">
                <div class="form-group">
                    <label class="form-label">รหัสพนักงาน (Employee Code)</label>
                    <input type="text" id="edit_emp_code" class="form-control" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์</label>
                        <input type="tel" id="edit_phone" class="form-control" placeholder="เช่น 081-234-5678">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">สิทธิ์การใช้งาน (Role)</label>
                        <select id="edit_role" class="form-control">
                            <option value="employee">Employee (พนักงาน)</option>
                            <option value="manager">Manager (หัวหน้าแผนก)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">แผนก (Department)</label>
                        <select id="edit_dept_id" class="form-control">
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                </div>
                <div class="form-group upload-box" style="text-align:center;">
                    <div style="margin-bottom:8px;">
                        <img id="edit_avatar_preview" src="" alt="Avatar" style="width:72px; height:72px; border-radius:50%; object-fit:cover; display:none; border:2px solid var(--primary-color); margin:0 auto; box-shadow:var(--shadow-sm);">
                        <div id="edit_avatar_placeholder" class="user-avatar" style="width:64px; height:64px; font-size:1.4rem; margin:0 auto; display:flex;">HR</div>
                    </div>
                    <label class="form-label upload-box-title" style="margin-bottom:6px;">อัปโหลดรูปโปรไฟล์พนักงาน <small style="color:var(--primary-color);">(เฉพาะ Admin/Manager)</small></label>
                    <input type="file" id="edit_avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="form-group">
                    <label class="form-label">รีเซ็ตรหัสผ่านใหม่ <small style="color:var(--text-muted);">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</small></label>
                    <input type="password" id="edit_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่เมื่อต้องการเปลี่ยน">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: ปรับตั้งค่าโควตาวันลา -->
    <div class="modal-backdrop" id="quotaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0;">ตั้งค่าโควตาวันลาประจำปี</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeQuotaModal()" style="width:auto; padding:2px 8px;">✕</button>
            </div>
            <form id="quotaForm" onsubmit="handleQuotaSubmit(event)">
                <input type="hidden" id="quotaUserId">
                <p style="margin-bottom:14px; font-weight:600;" id="quotaUserName">พนักงาน: -</p>
                
                <div class="form-group">
                    <label class="form-label">สิทธิ์ลาป่วยทั้งหมดต่อปี (วัน)</label>
                    <input type="number" id="sick_quota" class="form-control" min="0" max="365" required>
                </div>
                <div class="form-group">
                    <label class="form-label">สิทธิ์ลากิจทั้งหมดต่อปี (วัน)</label>
                    <input type="number" id="personal_quota" class="form-control" min="0" max="365" required>
                </div>
                <div class="form-group">
                    <label class="form-label">สิทธิ์ลาพักร้อนทั้งหมดต่อปี (วัน)</label>
                    <input type="number" id="vacation_quota" class="form-control" min="0" max="365" required>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeQuotaModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกโควตา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: กำหนดกะการทำงานพนักงาน -->
    <div class="modal-backdrop" id="shiftModal">
        <div class="modal-content" style="max-width: 440px;">
            <div class="modal-header">
                <h3 style="margin:0;">กำหนดกะการทำงานพนักงาน</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeShiftModal()" style="width:auto; padding:2px 8px;">✕</button>
            </div>
            <form id="shiftForm" onsubmit="handleShiftSubmit(event)">
                <input type="hidden" id="shiftUserId">
                
                <div style="margin-bottom: 16px;">
                    <p style="font-weight:600; color:var(--text-main); margin-bottom:4px;" id="shiftUserName">ชื่อพนักงาน: -</p>
                    <p style="font-size:0.83rem; color:var(--text-muted);">เลือกกะการทำงานเพื่อใช้นับชั่วโมงทำงานและคำนวณ OT อัตโนมัติ</p>
                </div>

                <div class="form-group">
                    <label class="form-label">กะการทำงาน (Shift Type)</label>
                    <select id="shift_type_select" class="form-control">
                        <option value="day">กะกลางวัน (08:00 - 17:00 น. | OT สูงสุด 20:00 น.)</option>
                        <option value="night">กะกลางคืน (20:00 - 05:00 น. | OT สูงสุด 08:00 น.)</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeShiftModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกกะการทำงาน</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
