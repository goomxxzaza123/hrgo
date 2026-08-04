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
        const shiftText = log.shift_label || (log.shift_type === 'night' ? '🌙 กลางคืน' : '☀️ กลางวัน');

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

            let actionButtons = '-';
            if (item.status === 'pending') {
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="handleLeaveAction(${item.leave_id}, 'approve')">
                        อนุมัติ
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="handleLeaveAction(${item.leave_id}, 'reject')">
                        ปฏิเสธ
                    </button>
                `;
            }

            html += `
                <tr>
                    <td><strong>${item.emp_code}</strong><br><small style="color:var(--text-muted);">${escapeHtml(item.employee_name)}</small></td>
                    <td>${escapeHtml(item.dept_name)}</td>
                    <td><strong>${item.type_label}</strong></td>
                    <td>${item.start_date_th} - ${item.end_date_th}<br><small style="color:var(--text-muted);">(${item.days_count} วัน | สิทธิ์เหลือ ${item.remaining} วัน)</small></td>
                    <td style="max-width:180px; word-wrap:break-word;">${escapeHtml(item.reason)}</td>
                    <td><span class="badge ${badgeClass}">${item.status === 'pending' ? 'รออนุมัติ' : (item.status === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธ')}</span></td>
                    <td><div style="display:flex; gap:6px;">${actionButtons}</div></td>
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
                    <td>${shiftBadge}</td>
                    <td><small>ป่วย:${sickBal} | กิจ:${personalBal} | พักร้อน:${vacationBal}</small></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-dropdown">
                            <button type="button" class="action-dropdown-btn" onclick="toggleActionDropdown(this, event)">
                                ⚙️ จัดการ ▾
                            </button>
                            <div class="action-dropdown-menu">
                                <a class="action-dropdown-item" onclick='openEditUserModal(${userJson})'>
                                    <span>✏️</span> แก้ไขข้อมูลส่วนตัว
                                </a>
                                <a class="action-dropdown-item" onclick="openShiftModal(${u.user_id}, '${escapeHtml(u.name)}', '${u.shift_type}')">
                                    <span>⏰</span> ปรับกะทำงาน & OT
                                </a>
                                <a class="action-dropdown-item" onclick="openQuotaModal(${u.user_id}, '${escapeHtml(u.name)}', ${u.balances.sick?.total_quota || 30}, ${u.balances.personal?.total_quota || 6}, ${u.balances.vacation?.total_quota || 10})">
                                    <span>📅</span> ตั้งค่าโควตาวันลา
                                </a>
                                <div class="action-dropdown-divider"></div>
                                <a class="action-dropdown-item danger" onclick="deleteUser(${u.user_id}, '${escapeHtml(u.name)}')">
                                    <span>🗑️</span> ลบรายชื่อพนักงาน
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

    const userId     = document.getElementById('edit_user_id').value;
    const empCode    = document.getElementById('edit_emp_code').value.trim();
    const name       = document.getElementById('edit_name').value.trim();
    const phone      = document.getElementById('edit_phone') ? document.getElementById('edit_phone').value.trim() : '';
    const role       = document.getElementById('edit_role').value;
    const deptId     = document.getElementById('edit_dept_id').value;
    const password   = document.getElementById('edit_password').value.trim();
    const avatarInput = document.getElementById('edit_avatar_file');

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

    const emp_code = document.getElementById('add_emp_code').value.trim();
    const name     = document.getElementById('add_name').value.trim();
    const phone    = document.getElementById('add_phone') ? document.getElementById('add_phone').value.trim() : '';
    const password = document.getElementById('add_password').value.trim();
    const role     = document.getElementById('add_role').value;
    const dept_id  = document.getElementById('add_dept_id').value;
    const avatarInput = document.getElementById('add_avatar_file');

    try {
        // 1. สร้างพนักงานใหม่
        const response = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                emp_code, name, phone, password, role, dept_id
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
