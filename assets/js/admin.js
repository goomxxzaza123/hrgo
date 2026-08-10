/**
 * HR Management System - Admin & Manager JavaScript Module
 * Handles Dashboard Stats, Leave Approvals, Employee Management & Quota Adjustments
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. โหลดข้อมูล Dashboard หากอยู่ในหน้า dashboard.php
    if (document.getElementById('statTotalEmployees')) {
        loadDashboardStats();
    }

    // 2. โหลดรายการคำขอลางาน หากอยู่ในหน้า approve_leave.php
    if (document.getElementById('leaveApprovalTable')) {
        loadLeaveApprovals();

        // ตัวกรองสถานะ
        const filterSelect = document.getElementById('statusFilter');
        if (filterSelect) {
            filterSelect.addEventListener('change', () => {
                loadLeaveApprovals(filterSelect.value);
            });
        }
    }

    // 3. โหลดรายชื่อพนักงาน หากอยู่ในหน้า manage_users.php
    if (document.getElementById('usersTable')) {
        loadManageUsers();
    }
});

/**
 * โหลดตัวเลขสถิติภาพรวมบน Dashboard
 */
async function loadDashboardStats() {
    try {
        const response = await fetch('../api/get_dashboard_stats.php');
        const result = await response.json();

        if (!result.success) {
            if (response.status === 401) window.location.href = '../index.php';
            return;
        }

        const data = result.data;
        document.getElementById('statTotalEmployees').textContent = data.total_employees;
        document.getElementById('statPresentToday').textContent   = data.present_today;
        document.getElementById('statLateToday').textContent      = data.late_today;
        document.getElementById('statPendingLeaves').textContent   = data.pending_leaves;

        // แสดงตารางประวัติลงเวลาวันนี้ล่าสุด
        renderRecentAttendanceLog(data.recent_log);

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

function renderRecentAttendanceLog(logs) {
    const container = document.getElementById('recentAttendanceTable');
    if (!container) return;

    if (!logs || logs.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; color:var(--text-muted); padding:15px;">
                    ยังไม่มีบันทึกการเข้างานวันนี้
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    logs.forEach(log => {
        const badgeClass = (log.status === 'on_time') ? 'badge-success' : 'badge-warning';
        const shiftBadgeClass = (log.shift_type === 'night') ? 'badge-info' : 'badge-outline';
        const shiftText = log.shift_label || (log.shift_type === 'night' ? '<i class="fa-solid fa-moon"></i> กลางคืน' : '<i class="fa-solid fa-sun"></i> กลางวัน');

        const inPhotoBtn = log.check_in_photo 
            ? `<button class="btn btn-sm btn-outline" style="padding:2px 8px; font-size:0.75rem;" onclick="viewPhoto('${log.check_in_photo}', 'รูปเข้างาน: ${escapeHtml(log.employee_name)}')">รูปเข้างาน</button>` 
            : '<span style="color:var(--text-muted); font-size:0.8rem;">ไม่มีรูป</span>';

        const outPhotoBtn = log.check_out_photo 
            ? `<button class="btn btn-sm btn-outline" style="padding:2px 8px; font-size:0.75rem;" onclick="viewPhoto('${log.check_out_photo}', 'รูปออกงาน: ${escapeHtml(log.employee_name)}')">รูปออกงาน</button>` 
            : '';

        html += `
            <tr>
                <td><strong>${log.emp_code}</strong></td>
                <td>${escapeHtml(log.employee_name)}</td>
                <td>${escapeHtml(log.dept_name)}</td>
                <td><span class="badge ${shiftBadgeClass}" style="font-size:0.78rem;">${shiftText}</span></td>
                <td>${log.check_in_time} / ${log.check_out_time}</td>
                <td><div style="display:flex; gap:4px; flex-wrap:wrap;">${inPhotoBtn}${outPhotoBtn}</div></td>
                <td><span class="badge ${badgeClass}">${log.status_label}</span></td>
            </tr>
        `;
    });
    container.innerHTML = html;
}

function viewPhoto(url, title) {
    if (!url) return;
    const finalUrl = url.startsWith('../') ? url : (url.startsWith('uploads/') ? '../' + url : url);
    Swal.fire({
        title: title,
        imageUrl: finalUrl,
        imageWidth: 340,
        imageHeight: 340,
        imageAlt: title,
        confirmButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: '#2563EB'
    });
}

/**
 * โหลดตารางคำขอลางานสำหรับ Manager/HR กดอนุมัติ
 */
async function loadLeaveApprovals(statusFilter = '') {
    const container = document.getElementById('leaveApprovalTable');
    if (!container) return;

    try {
        const url = statusFilter ? `../api/admin_leave.php?status=${statusFilter}` : '../api/admin_leave.php';
        const response = await fetch(url);
        const result = await response.json();

        if (!result.success) return;

        const requests = result.data;
        window.cachedLeaveRequests = requests;

        if (!requests || requests.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">
                        ไม่พบคำขอลางาน
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        requests.forEach(item => {
            let badgeClass = 'badge-warning';
            if (item.status === 'approved') badgeClass = 'badge-success';
            if (item.status === 'rejected') badgeClass = 'badge-danger';

            let actionButtons = '';
            if (item.status === 'pending') {
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="handleLeaveAction(${item.leave_id}, 'approve')" title="อนุมัติ">
                        <i class="fa-solid fa-check"></i> อนุมัติ
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="handleLeaveAction(${item.leave_id}, 'reject')" title="ปฏิเสธ">
                        <i class="fa-solid fa-xmark"></i> ปฏิเสธ
                    </button>
                `;
            }

            actionButtons += `
                <button class="btn btn-outline btn-sm" onclick="openEditLeaveModal(${item.leave_id})" title="แก้ไขใบลา">
                    <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                </button>
                <button class="btn btn-outline btn-sm" style="color:var(--danger-color); border-color:var(--danger-color);" onclick="deleteLeaveRequest(${item.leave_id})" title="ลบใบลา">
                    <i class="fa-solid fa-trash-can"></i> ลบ
                </button>
            `;

            html += `
                <tr>
                    <td><strong>${item.emp_code}</strong><br><small style="color:var(--text-muted);">${escapeHtml(item.employee_name)}</small></td>
                    <td>${escapeHtml(item.dept_name)}</td>
                    <td><strong>${item.type_label}</strong></td>
                    <td>${item.start_date_th} - ${item.end_date_th}<br><small style="color:var(--text-muted);">(${item.days_count} วัน | สิทธิ์เหลือ ${item.remaining} วัน)</small></td>
                    <td style="max-width:180px; word-wrap:break-word;">${escapeHtml(item.reason)}</td>
                    <td><span class="badge ${badgeClass}">${item.status === 'pending' ? 'รออนุมัติ' : (item.status === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธ')}</span></td>
                    <td><div style="display:flex; gap:6px; flex-wrap:wrap;">${actionButtons}</div></td>
                </tr>
            `;
        });

        container.innerHTML = html;

    } catch (error) {
        console.error('Error loading leave approvals:', error);
    }
}

/**
 * ดำเนินการกด อนุมัติ หรือ ปฏิเสธ ใบลา
 */
async function handleLeaveAction(leaveId, action) {
    const actionText = (action === 'approve') ? 'อนุมัติ' : 'ปฏิเสธ';
    const confirmColor = (action === 'approve') ? '#2ECC71' : '#E74C3C';

    const result = await Swal.fire({
        title: `ยืนยัน${actionText}คำขอลางาน?`,
        text: (action === 'approve') ? "ระบบจะตัดโควตาวันลาของพนักงานโดยอัตโนมัติ" : "ระบบจะเปลี่ยนสถานะใบลาเป็นปฏิเสธ",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#7F8C8D',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('../api/admin_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: leaveId, action: action })
        });

        const resData = await response.json();

        if (response.ok && resData.success) {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: resData.message,
                timer: 1800,
                showConfirmButton: false
            });
            loadLeaveApprovals();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถดำเนินการได้',
                text: resData.message || 'เกิดข้อผิดพลาดในการปรับสถานะ',
                confirmButtonColor: '#4A90E2'
            });
        }
    } catch (error) {
        console.error('Error handling leave action:', error);
    }
}

/**
 * โหลดรายชื่อพนักงานทั้งหมดสำหรับหน้า manage_users.php
 */
async function loadManageUsers() {
    const container = document.getElementById('usersTable');
    if (!container) return;

    try {
        const response = await fetch('../api/admin_users.php');
        const result = await response.json();

        if (!result.success) return;

        const data = result.data;
        window.allDepartments = data.departments; // บันทึกรายชื่อแผนกไว้เปิดใน Select Dropdown

        let html = '';
        data.users.forEach(u => {
            const statusBadge = u.is_active 
                ? '<span class="badge badge-success">ปกติ</span>' 
                : '<span class="badge badge-danger">ระงับ</span>';

            const shiftBadge = (u.shift_type === 'night') 
                ? '<span class="badge" style="background:#F59E0B; color:white;">กลางคืน</span>' 
                : '<span class="badge badge-info">กลางวัน</span>';

            const sickBal     = u.balances.sick ? `${u.balances.sick.remaining}/${u.balances.sick.total_quota}` : '-';
            const personalBal = u.balances.personal ? `${u.balances.personal.remaining}/${u.balances.personal.total_quota}` : '-';
            const vacationBal = u.balances.vacation ? `${u.balances.vacation.remaining}/${u.balances.vacation.total_quota}` : '-';

            const userJson = JSON.stringify(u).replace(/'/g, "&apos;");

            html += `
                <tr>
                    <td><strong>${u.emp_code}</strong></td>
                    <td>
                        <strong>${escapeHtml(u.name)}</strong>
                        ${u.phone ? `<br><small style="color:var(--text-muted);">${escapeHtml(u.phone)}</small>` : ''}
                    </td>
                    <td><span class="badge badge-info">${u.role.toUpperCase()}</span></td>
                    <td>${escapeHtml(u.dept_name)}</td>
                    <td>${u.birth_date_th}<br><small style="color:var(--text-muted);">(${u.age})</small></td>
                    <td>${u.start_work_th}<br><small style="color:var(--text-muted);">(${u.work_tenure})</small></td>
                    <td><small>ป่วย:${sickBal} | กิจ:${personalBal} | พักร้อน:${vacationBal}</small></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-dropdown">
                            <button type="button" class="action-dropdown-btn" onclick="toggleActionDropdown(this, event)">
                                <i class="fa-solid fa-gear"></i> จัดการ <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;"></i>
                            </button>
                            <div class="action-dropdown-menu">
                                <a class="action-dropdown-item" onclick='openEditUserModal(${userJson})'>
                                    <i class="fa-solid fa-pen-to-square" style="color:#2563EB;"></i> แก้ไขข้อมูลส่วนตัว
                                </a>
                                <a class="action-dropdown-item" onclick="openShiftModal(${u.user_id}, '${escapeHtml(u.name)}', '${u.shift_type}')">
                                    <i class="fa-solid fa-clock" style="color:#F59E0B;"></i> ปรับกะทำงาน & OT
                                </a>
                                <a class="action-dropdown-item" onclick="openQuotaModal(${u.user_id}, '${escapeHtml(u.name)}', ${u.balances.sick?.total_quota || 30}, ${u.balances.personal?.total_quota || 6}, ${u.balances.vacation?.total_quota || 10})">
                                    <i class="fa-solid fa-calendar-check" style="color:#10B981;"></i> ตั้งค่าโควตาวันลา
                                </a>
                                <div class="action-dropdown-divider"></div>
                                <a class="action-dropdown-item danger" onclick="deleteUser(${u.user_id}, '${escapeHtml(u.name)}')">
                                    <i class="fa-solid fa-trash-can" style="color:#EF4444;"></i> ลบรายชื่อพนักงาน
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });

        container.innerHTML = html;

    } catch (error) {
        console.error('Error loading manage users:', error);
    }
}

/**
 * เปิด Modal แก้ไขข้อมูลพนักงาน
 */
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value  = user.user_id;
    document.getElementById('edit_emp_code').value = user.emp_code;
    document.getElementById('edit_name').value     = user.name;
    document.getElementById('edit_role').value     = user.role;
    document.getElementById('edit_password').value = '';

    const phoneInput = document.getElementById('edit_phone');
    if (phoneInput) phoneInput.value = user.phone || '';

    const birthInput = document.getElementById('edit_birth_date');
    if (birthInput) birthInput.value = user.birth_date || '';

    const startWorkInput = document.getElementById('edit_start_work_date');
    if (startWorkInput) startWorkInput.value = user.start_work_date || '';

    const avatarFile = document.getElementById('edit_avatar_file');
    if (avatarFile) avatarFile.value = '';

    const imgPreview = document.getElementById('edit_avatar_preview');
    const placeholder = document.getElementById('edit_avatar_placeholder');

    if (user.avatar_url && imgPreview && placeholder) {
        imgPreview.src = '../' + user.avatar_url;
        imgPreview.style.display = 'block';
        placeholder.style.display = 'none';
    } else if (imgPreview && placeholder) {
        imgPreview.style.display = 'none';
        placeholder.style.display = 'flex';
        placeholder.textContent = user.name ? user.name.charAt(0) : 'A';
    }

    // เติมแผนกใน Select Dropdown
    const deptSelect = document.getElementById('edit_dept_id');
    if (deptSelect && window.allDepartments) {
        deptSelect.innerHTML = window.allDepartments.map(d => 
            `<option value="${d.dept_id}" ${d.dept_id == user.dept_id ? 'selected' : ''}>${escapeHtml(d.dept_name)}</option>`
        ).join('');
    }

    document.getElementById('editUserModal').classList.add('active');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.remove('active');
}

async function handleEditUserSubmit(e) {
    e.preventDefault();

    const userId        = document.getElementById('edit_user_id').value;
    const empCode       = document.getElementById('edit_emp_code').value.trim();
    const name          = document.getElementById('edit_name').value.trim();
    const phone         = document.getElementById('edit_phone') ? document.getElementById('edit_phone').value.trim() : '';
    const birthDate     = document.getElementById('edit_birth_date') ? document.getElementById('edit_birth_date').value : '';
    const startWorkDate = document.getElementById('edit_start_work_date') ? document.getElementById('edit_start_work_date').value : '';
    const role          = document.getElementById('edit_role').value;
    const deptId        = document.getElementById('edit_dept_id').value;
    const password      = document.getElementById('edit_password').value.trim();
    const avatarInput   = document.getElementById('edit_avatar_file');

    try {
        // 1. บันทึกข้อมูลข้อความ
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                user_id: userId,
                emp_code: empCode,
                name: name,
                phone: phone,
                birth_date: birthDate,
                start_work_date: startWorkDate,
                role: role,
                dept_id: deptId,
                password: password
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
            return;
        }

        // 2. อัปโหลดรูปโปรไฟล์หากมีไฟล์แนบ
        if (avatarInput && avatarInput.files && avatarInput.files.length > 0) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('avatar_file', avatarInput.files[0]);

            const uploadRes = await fetch('../api/upload_avatar.php', {
                method: 'POST',
                body: formData
            });

            const uploadResult = await uploadRes.json();
            if (!uploadRes.ok || !uploadResult.success) {
                Swal.fire({ icon: 'warning', title: 'บันทึกข้อมูลแล้ว แต่รูปภาพมีปัญหา', text: uploadResult.message });
                closeEditUserModal();
                loadManageUsers();
                return;
            }
        }

        Swal.fire({ icon: 'success', title: 'บันทึกการแก้ไขพนักงานเรียบร้อยแล้ว', timer: 1500, showConfirmButton: false });
        closeEditUserModal();
        loadManageUsers();

    } catch (error) {
        console.error('Edit user error:', error);
    }
}

/**
 * สลับสถานะเปิดใช้งาน / ระงับพนักงาน
 */
async function toggleUserActive(userId) {
    try {
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_active', user_id: userId })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({ icon: 'success', title: result.message, timer: 1200, showConfirmButton: false });
            loadManageUsers();
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถเปลี่ยนสถานะได้', text: result.message });
        }
    } catch (error) {
        console.error('Toggle active error:', error);
    }
}

/**
 * ลบพนักงานออกจากระบบ
 */
async function deleteUser(userId, userName) {
    const confirmRes = await Swal.fire({
        title: `ยืนยันการลบพนักงาน ${userName}?`,
        text: 'การลบพนักงานจะลบประวัติการลงเวลา ใบลา และโควตาคงเหลือทั้งหมดออกจากระบบ!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E74C3C',
        cancelButtonColor: '#95A5A6',
        confirmButtonText: 'ใช่, ลบพนักงาน!',
        cancelButtonText: 'ยกเลิก'
    });

    if (!confirmRes.isConfirmed) return;

    try {
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', user_id: userId })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({ icon: 'success', title: 'ลบพนักงานเรียบร้อยแล้ว', timer: 1500, showConfirmButton: false });
            loadManageUsers();
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถลบพนักงานได้', text: result.message });
        }
    } catch (error) {
        console.error('Delete user error:', error);
    }
}

/**
 * เปิด Modal ปรับกะการทำงานพนักงาน
 */
function openShiftModal(userId, userName, shiftType) {
    document.getElementById('shiftUserId').value = userId;
    document.getElementById('shiftUserName').textContent = `พนักงาน: ${userName}`;
    document.getElementById('shift_type_select').value = shiftType || 'day';

    document.getElementById('shiftModal').classList.add('active');
}

function closeShiftModal() {
    document.getElementById('shiftModal').classList.remove('active');
}

async function handleShiftSubmit(e) {
    e.preventDefault();

    const userId    = document.getElementById('shiftUserId').value;
    const shiftType = document.getElementById('shift_type_select').value;

    try {
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_shift',
                user_id: userId,
                shift_type: shiftType
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({ icon: 'success', title: 'อัปเดตกะงานสำเร็จ', timer: 1500, showConfirmButton: false });
            closeShiftModal();
            loadManageUsers();
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
        }
    } catch (error) {
        console.error('Shift update error:', error);
    }
}

/**
 * เปิด Modal สำหรับปรับตั้งค่าโควตาวันลาของพนักงาน
 */
function openQuotaModal(userId, userName, sick, personal, vacation) {
    document.getElementById('quotaUserId').value = userId;
    document.getElementById('quotaUserName').textContent = userName;
    document.getElementById('sick_quota').value = sick;
    document.getElementById('personal_quota').value = personal;
    document.getElementById('vacation_quota').value = vacation;

    document.getElementById('quotaModal').classList.add('active');
}

function closeQuotaModal() {
    document.getElementById('quotaModal').classList.remove('active');
}

async function handleQuotaSubmit(e) {
    e.preventDefault();

    const userId   = document.getElementById('quotaUserId').value;
    const sick     = document.getElementById('sick_quota').value;
    const personal = document.getElementById('personal_quota').value;
    const vacation = document.getElementById('vacation_quota').value;

    try {
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_quota',
                user_id: userId,
                sick_quota: sick,
                personal_quota: personal,
                vacation_quota: vacation
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({ icon: 'success', title: 'อัปเดตโควตาเรียบร้อย', timer: 1500, showConfirmButton: false });
            closeQuotaModal();
            loadManageUsers();
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
        }
    } catch (error) {
        console.error('Quota update error:', error);
    }
}

function openAddUserModal() {
    const avatarInput = document.getElementById('add_avatar_file');
    if (avatarInput) avatarInput.value = '';
    const imgPreview = document.getElementById('add_avatar_preview');
    const placeholder = document.getElementById('add_avatar_placeholder');
    if (imgPreview && placeholder) {
        imgPreview.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    const deptSelect = document.getElementById('add_dept_id');
    if (deptSelect && window.allDepartments) {
        deptSelect.innerHTML = window.allDepartments.map(d => 
            `<option value="${d.dept_id}">${escapeHtml(d.dept_name)}</option>`
        ).join('');
    }

    document.getElementById('addUserModal').classList.add('active');
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.remove('active');
}

async function handleAddUserSubmit(e) {
    e.preventDefault();

    const emp_code      = document.getElementById('add_emp_code').value.trim();
    const name          = document.getElementById('add_name').value.trim();
    const phone         = document.getElementById('add_phone') ? document.getElementById('add_phone').value.trim() : '';
    const birth_date    = document.getElementById('add_birth_date') ? document.getElementById('add_birth_date').value : '';
    const start_work_date = document.getElementById('add_start_work_date') ? document.getElementById('add_start_work_date').value : '';
    const password      = document.getElementById('add_password').value.trim();
    const role          = document.getElementById('add_role').value;
    const dept_id       = document.getElementById('add_dept_id').value;
    const avatarInput   = document.getElementById('add_avatar_file');

    try {
        // 1. สร้างพนักงานใหม่
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                emp_code, name, phone, birth_date, start_work_date, password, role, dept_id
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถเพิ่มพนักงานได้', text: result.message });
            return;
        }

        const newUserId = result.data ? result.data.user_id : 0;

        // 2. อัปโหลดรูปโปรไฟล์หากมีไฟล์แนบ
        if (newUserId && avatarInput && avatarInput.files && avatarInput.files.length > 0) {
            const formData = new FormData();
            formData.append('user_id', newUserId);
            formData.append('avatar_file', avatarInput.files[0]);

            const uploadRes = await fetch('../api/upload_avatar.php', {
                method: 'POST',
                body: formData
            });

            const uploadResult = await uploadRes.json();
            if (!uploadRes.ok || !uploadResult.success) {
                Swal.fire({ icon: 'warning', title: 'เพิ่มพนักงานแล้ว แต่รูปภาพมีปัญหา', text: uploadResult.message });
                closeAddUserModal();
                document.getElementById('addUserForm').reset();
                loadManageUsers();
                return;
            }
        }

        Swal.fire({ icon: 'success', title: 'เพิ่มพนักงานใหม่พร้อมรูปโปรไฟล์เรียบร้อยแล้ว', timer: 1500, showConfirmButton: false });
        closeAddUserModal();
        document.getElementById('addUserForm').reset();
        loadManageUsers();

    } catch (error) {
        console.error('Add user error:', error);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

/**
 * -------------------------------------------------------------
 * Admin / Manager ยื่นขอลางานแทนพนักงาน (Leave On Behalf)
 * -------------------------------------------------------------
 */
async function openAdminLeaveModal() {
    const modal = document.getElementById('adminLeaveModal');
    const selectUser = document.getElementById('admin_leave_user_id');
    if (!modal || !selectUser) return;

    selectUser.innerHTML = '<option value="">-- กำลังโหลดรายชื่อพนักงาน... --</option>';
    modal.classList.add('active');

    try {
        const response = await fetch('../api/admin_users.php');
        const result = await response.json();

        if (result.success && result.data && result.data.users) {
            let options = '<option value="">-- เลือกพนักงาน --</option>';
            result.data.users.forEach(u => {
                options += `<option value="${u.user_id}">${u.emp_code} - ${escapeHtml(u.name)} (${escapeHtml(u.dept_name)})</option>`;
            });
            selectUser.innerHTML = options;
        } else {
            selectUser.innerHTML = '<option value="">-- ไม่พบรายชื่อพนักงาน --</option>';
        }
    } catch (err) {
        console.error('Error loading employees for leave modal:', err);
        selectUser.innerHTML = '<option value="">-- เกิดข้อผิดพลาดในการโหลดพนักงาน --</option>';
    }
}

function closeAdminLeaveModal() {
    const modal = document.getElementById('adminLeaveModal');
    if (modal) modal.classList.remove('active');
    const form = document.getElementById('adminLeaveForm');
    if (form) form.reset();
    const daysDisplay = document.getElementById('adminCalculatedDaysDisplay');
    if (daysDisplay) daysDisplay.textContent = 'กรุณาเลือกวันที่';
}

function calculateAdminLeaveDays() {
    const startVal = document.getElementById('admin_start_date').value;
    const endVal   = document.getElementById('admin_end_date').value;
    const display  = document.getElementById('adminCalculatedDaysDisplay');

    if (!startVal || !endVal || !display) return;

    const start = new Date(startVal);
    const end   = new Date(endVal);

    if (start > end) {
        display.style.color = '#E74C3C';
        display.textContent = '⚠️ วันที่เริ่มต้น ต้องไม่มากกว่า วันที่สิ้นสุด';
        return;
    }

    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

    display.style.color = 'var(--primary-color)';
    display.innerHTML = `รวมจำนวนวันลา: <strong>${diffDays} วัน</strong>`;
}

async function handleAdminLeaveSubmit(e) {
    e.preventDefault();

    const userId   = document.getElementById('admin_leave_user_id').value;
    const leaveType = document.getElementById('admin_leave_type').value;
    const startDate = document.getElementById('admin_start_date').value;
    const endDate   = document.getElementById('admin_end_date').value;
    const reason    = document.getElementById('admin_leave_reason').value.trim();
    const btn       = document.getElementById('adminSubmitLeaveBtn');

    if (!userId || !leaveType || !startDate || !endDate || !reason) {
        Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณากรอกข้อมูลให้ครบทุกช่อง' });
        return;
    }

    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';
        }

        const response = await fetch('../api/admin_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_on_behalf',
                user_id: userId,
                leave_type: leaveType,
                start_date: startDate,
                end_date: endDate,
                reason: reason
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({
                icon: 'success',
                title: 'ยื่นลางานแทนพนักงานสำเร็จ!',
                text: result.message,
                timer: 2000,
                showConfirmButton: false
            });
            closeAdminLeaveModal();
            loadLeaveApprovals();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถยื่นลาแทนได้',
                text: result.message || 'เกิดข้อผิดพลาดในการบันทึก'
            });
        }
    } catch (err) {
        console.error('Error submitting admin leave:', err);
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> บันทึกและอนุมัติทันที';
        }
    }
}

/**
 * เปิด Modal แก้ไขคำขอลางาน
 */
function openEditLeaveModal(leaveId) {
    const list = window.cachedLeaveRequests || [];
    const item = list.find(l => l.leave_id == leaveId);
    if (!item) return;

    document.getElementById('edit_leave_id').value = item.leave_id;
    document.getElementById('edit_emp_name').value = `${item.emp_code} - ${item.employee_name} (${item.dept_name})`;
    document.getElementById('edit_leave_type').value = item.leave_type;
    document.getElementById('edit_start_date').value = item.start_date;
    document.getElementById('edit_end_date').value = item.end_date;
    document.getElementById('edit_leave_reason').value = item.reason;

    calculateEditLeaveDays();
    document.getElementById('editLeaveModal').classList.add('active');
}

function closeEditLeaveModal() {
    document.getElementById('editLeaveModal').classList.remove('active');
}

function calculateEditLeaveDays() {
    const sDate = document.getElementById('edit_start_date').value;
    const eDate = document.getElementById('edit_end_date').value;
    const display = document.getElementById('editCalculatedDaysDisplay');

    if (!sDate || !eDate) {
        display.textContent = 'กรุณาเลือกวันที่';
        return;
    }

    const start = new Date(sDate);
    const end = new Date(eDate);

    if (start > end) {
        display.innerHTML = '<span style="color:var(--danger-color);">วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด</span>';
        return;
    }

    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    display.innerHTML = `รวมจำนวน <strong>${diffDays}</strong> วัน`;
}

/**
 * บันทึกการแก้ไขใบลา
 */
async function handleEditLeaveSubmit(e) {
    e.preventDefault();
    const leaveId = document.getElementById('edit_leave_id').value;
    const leaveType = document.getElementById('edit_leave_type').value;
    const startDate = document.getElementById('edit_start_date').value;
    const endDate = document.getElementById('edit_end_date').value;
    const reason = document.getElementById('edit_leave_reason').value;
    const btn = document.getElementById('editSubmitLeaveBtn');

    if (!leaveId || !startDate || !endDate || !reason) {
        Swal.fire({ icon: 'warning', title: 'กรุณากรอกข้อมูลให้ครบถ้วน' });
        return;
    }

    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';
        }

        const response = await fetch('../api/admin_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'edit',
                leave_id: leaveId,
                leave_type: leaveType,
                start_date: startDate,
                end_date: endDate,
                reason: reason
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({
                icon: 'success',
                title: 'แก้ไขคำขอลางานสำเร็จ!',
                text: result.message,
                timer: 1800,
                showConfirmButton: false
            });
            closeEditLeaveModal();
            loadLeaveApprovals();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถแก้ไขได้',
                text: result.message || 'เกิดข้อผิดพลาดในการบันทึก'
            });
        }
    } catch (err) {
        console.error('Error editing leave:', err);
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> บันทึกการแก้ไข';
        }
    }
}

/**
 * ลบ/ยกเลิกคำขอลางาน
 */
async function deleteLeaveRequest(leaveId) {
    const confirm = await Swal.fire({
        title: 'ยืนยันลบคำขอลางาน?',
        text: 'หากเป็นใบลาที่อนุมัติแล้ว ระบบจะคืนโควตาวันลากลับเข้าสู่บัญชีพนักงานโดยอัตโนมัติ',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'ลบคำขอลางาน',
        cancelButtonText: 'ยกเลิก'
    });

    if (!confirm.isConfirmed) return;

    try {
        const response = await fetch('../api/admin_leave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                leave_id: leaveId
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            Swal.fire({
                icon: 'success',
                title: 'ลบคำขอลางานสำเร็จ!',
                text: result.message,
                timer: 1800,
                showConfirmButton: false
            });
            loadLeaveApprovals();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: result.message
            });
        }
    } catch (err) {
        console.error('Error deleting leave:', err);
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
    }
}
