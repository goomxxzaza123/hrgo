<?php
require_once __DIR__ . '/api/config.php';

// หากมีการส่ง parameter ?logout=1 มา ให้เคลียร์ session ทันที
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: index.php');
    exit;
}

// หากมีการเข้าสู่ระบบอยู่แล้ว ให้เปลี่ยนหน้าไปยัง Dashboard ตาม Role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'employee') {
        header('Location: employee_home.php');
    } else {
        header('Location: admin/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | HR Management System</title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Theme Manager -->
    <script src="assets/js/theme.js"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-color);
            padding: 16px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            position: relative;
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 16px auto;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .demo-box {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
        }
        .demo-title {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-align: center;
            font-weight: 600;
        }
        .demo-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .demo-btn {
            padding: 8px 4px;
            font-size: 0.78rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: #FAFAFA;
            color: var(--text-main);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }
        .demo-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div style="position:absolute; top:16px; right:16px;">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
        </div>
        <div class="login-header">
            <div class="login-logo"><i class="fa-solid fa-building-user"></i></div>
            <h1 class="login-title">HR GO System</h1>
            <p class="login-subtitle">ระบบลงเวลาและบริหารจัดการทรัพยากรบุคคล</p>
        </div>

        <form id="loginForm">
            <div class="form-group">
                <label for="emp_code" class="form-label">รหัสพนักงาน (Employee ID)</label>
                <input 
                    type="text" 
                    id="emp_code" 
                    name="emp_code" 
                    class="form-control" 
                    placeholder="เช่น EMP001, EMP003" 
                    required 
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">รหัสผ่าน (Password)</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="กรอกรหัสผ่าน" 
                    required 
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" id="submitBtn" class="btn btn-primary" style="margin-top: 10px; width: 100%;">
                <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ (Sign In)
            </button>
        </form>

        <!-- Quick Fill Helper สำหรับการทดสอบระบบ -->
        <div class="demo-box">
            <div class="demo-title" style="font-weight:600; color:var(--text-main); margin-bottom:10px;"><i class="fa-solid fa-key" style="color:var(--primary-color);"></i> เลือกบัญชีเพื่อทดสอบเข้าสู่ระบบ (รหัสผ่าน: 123456)</div>
            <div class="demo-buttons" style="display:grid; grid-template-columns: repeat(2, 1fr); gap:8px;">
                <button type="button" class="demo-btn" onclick="fillDemoAccount('EMP001')" style="grid-column: span 2; background:rgba(37, 99, 235, 0.1); border-color:var(--primary-color);">
                    <strong>EMP001 - สมชาย วงศ์สวัสดิ์</strong><br><small style="color:var(--primary-color); font-weight:600;">(Admin | แผนก HR)</small>
                </button>
                <button type="button" class="demo-btn" onclick="fillDemoAccount('EMP002')">
                    <strong>EMP002 - กิตติพงษ์</strong><br><small style="color:var(--text-muted);">(Manager | หัวหน้า IT)</small>
                </button>
                <button type="button" class="demo-btn" onclick="fillDemoAccount('EMP003')">
                    <strong>EMP003 - ธนกฤต</strong><br><small style="color:var(--text-muted);">(Employee | พนักงาน IT)</small>
                </button>
                <button type="button" class="demo-btn" onclick="fillDemoAccount('EMP004')">
                    <strong>EMP004 - พิมพ์ชนก</strong><br><small style="color:var(--text-muted);">(Employee | พนักงาน HR)</small>
                </button>
                <button type="button" class="demo-btn" onclick="fillDemoAccount('EMP005')">
                    <strong>EMP005 - ศุภโชค</strong><br><small style="color:var(--text-muted);">(Employee | กะดึก)</small>
                </button>
            </div>
        </div>
    </div>

    <!-- Core Auth JS -->
    <script src="assets/js/auth.js"></script>
</body>
</html>
