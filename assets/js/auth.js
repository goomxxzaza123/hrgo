/**
 * HR Management System - Auth JavaScript Module
 * Handles login submission, logout, session check, and UI interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }
});

/**
 * จัดการการส่งฟอร์มเข้าสู่ระบบด้วย Fetch API (ไม่รีเฟรชหน้าเว็บ)
 * @param {Event} e 
 */
async function handleLoginSubmit(e) {
    e.preventDefault();

    const empCodeInput = document.getElementById('emp_code');
    const passwordInput = document.getElementById('password');
    const submitBtn = document.getElementById('submitBtn');

    const emp_code = empCodeInput.value.trim();
    const password = passwordInput.value.trim();

    if (!emp_code || !password) {
        showToast('warning', 'กรุณากรอกรหัสพนักงานและรหัสผ่าน');
        return;
    }

    // แสดงสถานะ Loading บนปุ่ม
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>กำลังเข้าสู่ระบบ...</span>';

    try {
        const response = await fetch('api/auth_login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ emp_code, password })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ!',
                text: `ยินดีต้อนรับ ${data.data.user.name}`,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = data.data.redirect_url;
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เข้าสู่ระบบไม่สำเร็จ',
                text: data.message || 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง',
                confirmButtonColor: '#4A90E2'
            });
        }
    } catch (error) {
        console.error('Login Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับระบบได้ กรุณาลองใหม่อีกครั้ง',
            confirmButtonColor: '#4A90E2'
        });
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    }
}

/**
 * ฟังก์ชันสำหรับออกจากระบบ
 */
async function handleLogout() {
    const result = await Swal.fire({
        title: 'ยืนยันการออกจากระบบ?',
        text: "คุณต้องการออกจากระบบใช่หรือไม่",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#E74C3C',
        cancelButtonColor: '#7F8C8D',
        confirmButtonText: 'ออกจากระบบ',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('api/auth_logout.php', { method: 'POST' });
            const data = await response.json();

            if (data.success) {
                window.location.href = 'index.php';
            } else {
                window.location.href = 'index.php';
            }
        } catch (error) {
            console.error('Logout error:', error);
            window.location.href = 'index.php';
        }
    }
}

/**
 * แสดง Toast แจ้งเตือนแบบสั้น
 */
function showToast(icon, title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: 3000
        });
    } else {
        alert(title);
    }
}

/**
 * ฟังก์ชันสำหรับกรอกข้อมูลทดสอบด่วน (Quick Fill Demo)
 */
function fillDemoAccount(empCode) {
    const empInput = document.getElementById('emp_code');
    const passInput = document.getElementById('password');
    if (empInput && passInput) {
        empInput.value = empCode;
        passInput.value = '123456';
    }
}
