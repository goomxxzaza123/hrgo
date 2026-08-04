<?php
require_once __DIR__ . '/api/config.php';

// ตรวจสอบสิทธิ์ (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userName = $_SESSION['name'] ?? 'พนักงาน';
$userRole = $_SESSION['role'] ?? 'employee';
$deptName = $_SESSION['dept_name'] ?? 'ไม่ระบุ';
$empCode  = $_SESSION['emp_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ส่วนตัว | HR Management System</title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Theme Manager -->
    <script src="assets/js/theme.js"></script>
</head>
<body style="padding-bottom: 70px;">

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <a href="employee_home.php" class="brand">
            <div class="brand-icon">HR</div>
            <span>HR GO</span>
        </a>
        <div style="display:flex; align-items:center; gap:12px;">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
            <div class="user-badge">
                <a href="profile.php" style="text-decoration:none; display:flex; align-items:center; gap:8px; color:inherit;">
                    <div class="user-avatar"><?= mb_substr($userName, 0, 1, 'UTF-8') ?></div>
                    <div>
                        <strong><?= htmlspecialchars($userName) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($empCode) ?></div>
                    </div>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- Profile Header Card -->
        <div class="card" style="text-align: center; padding: 24px 16px;">
            <div id="profileAvatarContainer" style="margin: 0 auto 12px auto;">
                <div class="user-avatar" style="width: 76px; height: 76px; font-size: 2.2rem; margin: 0 auto; box-shadow: var(--shadow-md);" id="avatarPlaceholder">
                    <?= mb_substr($userName, 0, 1, 'UTF-8') ?>
                </div>
                <img id="avatarImage" src="" alt="Avatar" style="width:76px; height:76px; border-radius:50%; object-fit:cover; display:none; margin:0 auto; box-shadow:var(--shadow-md); border:2px solid var(--primary-color);">
            </div>

            <h2 id="profileNameDisplay" style="font-size: 1.3rem; margin-bottom: 4px;"><?= htmlspecialchars($userName) ?></h2>
            <p style="font-size: 0.88rem; color: var(--text-muted);">
                รหัสพนักงาน: <strong><?= htmlspecialchars($empCode) ?></strong> | แผนก: <?= htmlspecialchars($deptName) ?>
            </p>
            <div style="margin-top: 8px;">
                <span class="badge badge-info"><?= strtoupper($userRole) ?></span>
            </div>

            <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
            <!-- อัปโหลดรูปโปรไฟล์เฉพาะ Admin และ Manager -->
            <div class="upload-box" style="margin-top: 16px; max-width:360px; margin-left:auto; margin-right:auto;">
                <label class="upload-box-title">
                    อัปโหลดรูปโปรไฟล์ส่วนตัว (Admin/Manager Only)
                </label>
                <input type="file" id="self_avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="font-size:0.8rem;" onchange="handleSelfAvatarUpload(this)">
            </div>
            <?php else: ?>
            <div class="upload-box" style="margin-top: 12px; font-size:0.78rem; color:var(--text-muted); display:inline-block; padding:8px 12px;">
                รูปโปรไฟล์ถูกจัดการและอัปโหลดโดยฝ่ายบุคคล (Admin / Manager) เท่านั้น
            </div>
            <?php endif; ?>
        </div>

        <!-- Card 1: ข้อมูลโปรไฟล์ -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">แก้ไขข้อมูลส่วนตัว</div>
            </div>
            <form id="profileNameForm" onsubmit="handleUpdateName(event)">
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" id="edit_name" class="form-control" value="<?= htmlspecialchars($userName) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">เบอร์โทรศัพท์ติดต่อ</label>
                    <input type="tel" id="edit_phone" class="form-control" placeholder="เช่น 081-234-5678">
                </div>
                <button type="submit" id="saveNameBtn" class="btn btn-primary">
                    บันทึกข้อมูลส่วนตัว
                </button>
            </form>
        </div>

        <!-- Card 2: เปลี่ยนรหัสผ่าน -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">เปลี่ยนรหัสผ่าน</div>
            </div>
            <form id="changePasswordForm" onsubmit="handleChangePassword(event)">
                <div class="form-group">
                    <label class="form-label">รหัสผ่านปัจจุบัน</label>
                    <input type="password" id="current_password" class="form-control" placeholder="กรอกรหัสผ่านปัจจุบัน" required>
                </div>
                <div class="form-group">
                    <label class="form-label">รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                    <input type="password" id="new_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required minlength="6">
                </div>
                <button type="submit" id="savePassBtn" class="btn btn-primary">
                    เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </div>

    </div>

    <!-- Bottom Mobile Navigation Bar -->
    <nav class="bottom-nav">
        <a href="employee_home.php" class="nav-item">
            <span>ลงเวลา</span>
        </a>
        <a href="leave_form.php" class="nav-item">
            <span>ยื่นลางาน</span>
        </a>
        <a href="profile.php" class="nav-item active">
            <span>โปรไฟล์</span>
        </a>
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <a href="admin/dashboard.php" class="nav-item">
            <span>จัดการระบบ</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="handleLogout()" class="nav-item">
            <span>ออกระบบ</span>
        </a>
    </nav>

    <!-- Core Scripts -->
    <script src="assets/js/auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadMyProfile();
        });

        let currentUserId = <?= (int)$_SESSION['user_id'] ?>;

        async function loadMyProfile() {
            try {
                const response = await fetch('api/auth_me.php');
                const result = await response.json();

                if (!result.success) return;

                const u = result.data;
                currentUserId = u.user_id;

                const placeholder = document.getElementById('avatarPlaceholder');
                const img = document.getElementById('avatarImage');
                const phoneInput = document.getElementById('edit_phone');

                if (phoneInput && u.phone) {
                    phoneInput.value = u.phone;
                }

                if (u.avatar_url && placeholder && img) {
                    img.src = u.avatar_url;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                }
            } catch (err) {
                console.error('Error loading profile:', err);
            }
        }

        async function handleSelfAvatarUpload(input) {
            if (!input.files || input.files.length === 0) return;

            const formData = new FormData();
            formData.append('user_id', currentUserId);
            formData.append('avatar_file', input.files[0]);

            Swal.fire({ title: 'กำลังอัปโหลดรูปโปรไฟล์...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            try {
                const response = await fetch('api/upload_avatar.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'อัปโหลดรูปโปรไฟล์สำเร็จ!', timer: 1500, showConfirmButton: false });
                    loadMyProfile();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถอัปโหลดได้', text: result.message });
                }
            } catch (err) {
                console.error('Self avatar upload error:', err);
            }
        }

        async function handleUpdateName(e) {
            e.preventDefault();
            const name  = document.getElementById('edit_name').value.trim();
            const phone = document.getElementById('edit_phone') ? document.getElementById('edit_phone').value.trim() : '';
            const saveBtn = document.getElementById('saveNameBtn');

            if (!name) return;

            saveBtn.disabled = true;
            try {
                const response = await fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_profile', name: name, phone: phone })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'บันทึกข้อมูลส่วนตัวสำเร็จ', timer: 1500, showConfirmButton: false });
                    document.getElementById('profileNameDisplay').textContent = name;
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
                }
            } catch (err) {
                console.error('Update name error:', err);
            } finally {
                saveBtn.disabled = false;
            }
        }

        async function handleChangePassword(e) {
            e.preventDefault();
            const current_password = document.getElementById('current_password').value;
            const new_password = document.getElementById('new_password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const savePassBtn = document.getElementById('savePassBtn');

            if (new_password !== confirm_password) {
                Swal.fire({ icon: 'warning', title: 'รหัสผ่านไม่ตรงกัน', text: 'รหัสผ่านใหม่และรหัสผ่านยืนยันไม่ตรงกัน' });
                return;
            }

            savePassBtn.disabled = true;
            try {
                const response = await fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'change_password',
                        current_password: current_password,
                        new_password: new_password
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'เปลี่ยนรหัสผ่านสำเร็จ!', text: result.message });
                    document.getElementById('changePasswordForm').reset();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถเปลี่ยนรหัสผ่านได้', text: result.message });
                }
            } catch (err) {
                console.error('Change pass error:', err);
            } finally {
                savePassBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
