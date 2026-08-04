/**
 * HR Management System - Leave Request JavaScript Module
 * Handles leave balance cards, date duration calculations, Fetch API leave submission, and history table
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. โหลดข้อมูลโควตาคงเหลือและประวัติการลา
    loadLeaveData();

    // 2. ผูกการคำนวณจำนวนวันลาอัตโนมัติเมื่อเลือกวันที่
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', calculateLeaveDays);
        endDateInput.addEventListener('change', calculateLeaveDays);
    }

    // 3. ผูก Event ฟอร์มยื่นคำขอลางาน
    const leaveForm = document.getElementById('leaveForm');
    if (leaveForm) {
        leaveForm.addEventListener('submit', handleLeaveSubmit);
    }
});

/**
 * โหลดโควตาคงเหลือและประวัติการขอลางานจาก API
 */
async function loadLeaveData() {
    const balancesGrid = document.getElementById('leaveBalancesGrid');
    const historyContainer = document.getElementById('leaveHistoryTable');

    try {
        const response = await fetch('api/get_leaves.php');
        const result = await response.json();

        if (!result.success) {
            if (response.status === 401) window.location.href = 'index.php';
            return;
        }

        const data = result.data;

        // แสดงการ์ดโควตาวันลาคงเหลือ
        renderLeaveBalances(data.balances, balancesGrid);

        // แสดงประวัติการขอลางาน
        renderLeaveHistory(data.requests, historyContainer);

    } catch (error) {
        console.error('Error loading leave data:', error);
    }
}

/**
 * แสดงการ์ดสรุปโควตาวันลาคงเหลือ 3 ประเภท (ป่วย, กิจ, พักร้อน)
 */
function renderLeaveBalances(balances, container) {
    if (!container) return;

    let html = '';
    balances.forEach(b => {
        let cardColorClass = 'border-primary';
        if (b.type === 'sick') cardColorClass = 'quota-sick';
        if (b.type === 'personal') cardColorClass = 'quota-personal';
        if (b.type === 'vacation') cardColorClass = 'quota-vacation';

        html += `
            <div class="quota-card ${cardColorClass}">
                <div class="quota-title">${b.type_label}</div>
                <div class="quota-number">${b.remaining} <small style="font-size:0.8rem; font-weight:normal;">วัน</small></div>
                <div class="quota-sub">ใช้ไปแล้ว ${b.used_days} / ${b.total_quota} วัน</div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * คำนวณจำนวนวันที่ขอลาเมื่อระบุ start_date และ end_date
 */
function calculateLeaveDays() {
    const startDateVal = document.getElementById('start_date').value;
    const endDateVal = document.getElementById('end_date').value;
    const daysCalcEl = document.getElementById('calculatedDaysDisplay');

    if (!startDateVal || !endDateVal) {
        if (daysCalcEl) daysCalcEl.textContent = 'กรุณาเลือกวันที่';
        return;
    }

    const start = new Date(startDateVal);
    const end = new Date(endDateVal);

    if (start > end) {
        if (daysCalcEl) {
            daysCalcEl.innerHTML = '<span style="color:var(--danger-color);">วันที่สิ้นสุดต้องมากกว่าวันที่เริ่ม</span>';
        }
        return;
    }

    // คำนวณความแตกต่างของวัน (รวมวันแรกและวันสุดท้าย)
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

    if (daysCalcEl) {
        daysCalcEl.innerHTML = `จำนวนวันลาทั้งหมด: <strong style="color:var(--primary-color); font-size:1.1rem;">${diffDays}</strong> วัน`;
    }
}

/**
 * ส่งคำขอลางานผ่าน Fetch API
 */
async function handleLeaveSubmit(e) {
    e.preventDefault();

    const leaveType = document.getElementById('leave_type').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reason = document.getElementById('reason').value.trim();
    const submitBtn = document.getElementById('leaveSubmitBtn');

    if (!leaveType || !startDate || !endDate || !reason) {
        Swal.fire({
            icon: 'warning',
            title: 'ข้อมูลไม่ครบถ้วน',
            text: 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง',
            confirmButtonColor: '#4A90E2'
        });
        return;
    }

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>กำลังส่งข้อมูล...</span>';

    try {
        const response = await fetch('api/post_leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
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
                title: 'ยื่นคำขอลางานสำเร็จ!',
                text: result.message,
                timer: 2000,
                showConfirmButton: false
            });

            // ล้างฟอร์ม
            document.getElementById('leaveForm').reset();
            document.getElementById('calculatedDaysDisplay').textContent = 'กรุณาเลือกวันที่';

            // อัปเดตข้อมูลโควตาและตารางประวัติ
            loadLeaveData();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถยื่นลาได้',
                text: result.message || 'เกิดข้อผิดพลาดในการยื่นใบลา',
                confirmButtonColor: '#4A90E2'
            });
        }
    } catch (error) {
        console.error('Leave submit error:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
            confirmButtonColor: '#4A90E2'
        });
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

/**
 * แสดงตารางประวัติคำขอลางาน
 */
function renderLeaveHistory(requests, container) {
    if (!container) return;

    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; color:var(--text-muted); padding: 20px;">
                    ยังไม่มีประวัติการยื่นคำขอลางาน
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

        html += `
            <tr>
                <td><strong>${item.type_label}</strong></td>
                <td>${item.start_date_th} - ${item.end_date_th}<br><small style="color:var(--text-muted);">(${item.days_count} วัน)</small></td>
                <td style="max-width: 160px; word-wrap: break-word;">${escapeHtml(item.reason)}</td>
                <td><span class="badge ${badgeClass}">${item.status_label}</span></td>
                <td><small style="color:var(--text-muted);">${item.approver_name}</small></td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
