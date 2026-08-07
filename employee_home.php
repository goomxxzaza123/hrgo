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
    <title>ลงเวลาเข้างาน | HR Management System</title>
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

        <!-- Card: นาฬิกา และ ปุ่ม One-Tap Check-in -->
        <div class="card clock-card">
            <div id="userIpDisplay" style="font-size:0.78rem; color:var(--text-muted); margin-bottom:4px;">IP: --.--.--.--</div>
            <div class="live-date" id="liveDate">วัน-- ที่ -- Month 202X</div>
            <div class="live-time" id="liveTime">00:00:00</div>
            
            <div style="margin-top: 8px; display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
                <span id="attendanceStatusBadge" class="badge badge-info">กำลังโหลดข้อมูล...</span>
                <span id="userShiftBadge" class="badge badge-info">
                    กะกลางวัน (08:00 - 17:00 น.)
                </span>
            </div>

            <!-- Live Work Stopwatch Timer (นับเวลาทำงานจริงนับตั้งแต่กดเข้างาน) -->
            <div id="liveWorkTimerBox" style="display:none; margin-top:14px; background:var(--bg-color); padding:12px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                <div style="font-size:0.8rem; color:var(--text-muted); font-weight:500;">ระยะเวลาทำงานสะสมวันนี้</div>
                <div id="workTimerDisplay" style="font-size:1.4rem; font-weight:700; color:var(--primary-color); margin:4px 0;">00 ชม. 00 นาที 00 วินาที</div>
                <div id="otTimerDisplay" style="font-size:0.82rem; color:#D97706; font-weight:600; display:none;">รวมเวลา OT: 00 ชม. 00 นาที (สูงสุด 20:00 น.)</div>
            </div>

            <!-- ปุ่มลงเวลาเข้า-ออกงานแบบ 2 ปุ่ม (Check In & Check Out) -->
            <div class="check-in-grid">
                <button type="button" id="checkInBtn" class="btn btn-checkin">
                    <div class="btn-checkin-icon"><i class="fa-solid fa-sun"></i></div>
                    <div>
                        <div class="btn-checkin-title">บันทึกเวลาเข้างาน</div>
                        <div class="btn-checkin-sub">Check In</div>
                    </div>
                </button>

                <button type="button" id="checkOutBtn" class="btn btn-checkout" disabled>
                    <div class="btn-checkin-icon"><i class="fa-solid fa-moon"></i></div>
                    <div>
                        <div class="btn-checkin-title">บันทึกเวลาออกงาน</div>
                        <div class="btn-checkin-sub">Check Out</div>
                    </div>
                </button>
            </div>

            <!-- สรุปเวลาเข้า-ออกงานวันนี้ -->
            <div class="time-summary-grid">
                <div class="time-box">
                    <div class="time-box-label">เวลาเข้างาน (Check In)</div>
                    <div class="time-box-value" id="checkInTimeDisplay">--:--</div>
                    <div id="checkInPhotoBtnContainer" style="margin-top:6px;"></div>
                </div>
                <div class="time-box">
                    <div class="time-box-label">เวลาออกงาน (Check Out)</div>
                    <div class="time-box-value" id="checkOutTimeDisplay">--:--</div>
                    <div id="checkOutPhotoBtnContainer" style="margin-top:6px;"></div>
                </div>
            </div>
        </div>

        <!-- Card: สถานะการเข้างานของเพื่อนร่วมทีมวันนี้ (รูปมีสีเมื่อเข้างานแล้ว รูปสีเทาเมื่อยังไม่เข้างาน) -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">สถานะการเข้างานของเพื่อนร่วมทีมวันนี้</div>
            </div>
            <div id="teamColleaguesContainer" class="team-list">
                <div style="text-align:center; padding:15px; color:var(--text-muted); grid-column: 1 / -1;">
                    กำลังโหลดสถานะเพื่อนร่วมงาน...
                </div>
            </div>
        </div>

        <!-- Card: ประวัติการลงเวลางานและชั่วโมง OT สะสมของฉัน -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">ประวัติการลงเวลาและชั่วโมง OT ของฉัน</div>
            </div>

            <!-- ตัวกรองช่วงวันที่สำหรับพนักงาน -->
            <form id="personalHistoryFilterForm" onsubmit="handlePersonalHistoryFilter(event)" style="margin-bottom: 16px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:8px; align-items:end;">
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">ตั้งแต่วันที่</label>
                        <input type="date" id="personal_start_date" class="form-control" style="font-size:0.82rem; padding:6px 8px;" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.78rem;">ถึงวันที่</label>
                        <input type="date" id="personal_end_date" class="form-control" style="font-size:0.82rem; padding:6px 8px;" value="<?= date('Y-m-t') ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm" style="padding:7px 12px;">ค้นหา</button>
                    </div>
                </div>
            </form>

            <!-- สรุปตัวเลขชั่วโมงทำงาน & OT สะสม -->
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; margin-bottom:16px; text-align:center;">
                <div class="summary-box work">
                    <div class="summary-title" style="font-size:0.72rem; color:var(--text-muted);">ชั่วโมงทำงาน</div>
                    <div id="summaryWorkHours" class="summary-value" style="font-size:1rem; font-weight:700; color:var(--primary-color);">0.00 ชม.</div>
                </div>
                <div class="summary-box ot">
                    <div class="summary-title" style="font-size:0.72rem; color:#D97706;">เวลา OT สะสม</div>
                    <div id="summaryOtHours" class="summary-value" style="font-size:1rem; font-weight:700; color:#D97706;">0.00 ชม.</div>
                </div>
                <div class="summary-box ontime">
                    <div class="summary-title" style="font-size:0.72rem; color:#059669;">ตรงเวลา</div>
                    <div id="summaryOnTimeCount" class="summary-value" style="font-size:1rem; font-weight:700; color:#059669;">0 วัน</div>
                </div>
                <div class="summary-box late">
                    <div class="summary-title" style="font-size:0.72rem; color:#DC2626;">สาย</div>
                    <div id="summaryLateCount" class="summary-value" style="font-size:1rem; font-weight:700; color:#DC2626;">0 วัน</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>เข้างาน</th>
                            <th>ออกงาน</th>
                            <th>ชม.ทำงาน</th>
                            <th>เวลา OT</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceHistoryTable">
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--text-muted); padding: 15px;">
                                กำลังโหลดประวัติการลงเวลา...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal: ถ่ายรูปบันทึกเวลาเข้า-ออกงาน (Biometric Face ID Style) -->
    <div class="modal-backdrop" id="cameraModal">
        <div class="modal-content" style="max-width: 420px; text-align: center; border-radius: 20px; padding: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 14px;">
                <h3 id="cameraModalTitle" style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-main);"><i class="fa-solid fa-camera"></i> สแกนใบหน้าลงเวลา</h3>
                <button type="button" onclick="closeCameraModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>

            <div class="camera-badge">
                <span style="width: 8px; height: 8px; background-color: #10B981; border-radius: 50%; display: inline-block;"></span>
                ระบบสแกนใบหน้าพร้อมลงเวลา
            </div>
            
            <!-- กรอบเลนส์กล้อง Face ID Biometric Scan -->
            <div id="cameraPreviewContainer" class="camera-scan-frame">
                <div class="camera-scan-line"></div>
                <video id="cameraVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                <img id="mobilePhotoPreview" src="" alt="Selfie Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                <canvas id="cameraCanvas" width="480" height="480" style="display: none;"></canvas>
            </div>

            <!-- Mobile Native Camera Fallback Input -->
            <input type="file" id="mobileCameraInput" accept="image/*" capture="user" style="display: none;" onchange="handleMobilePhotoSelect(event)">

            <p id="cameraHelpText" style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 18px; line-height: 1.4;">
                มองตรงที่กล้องให้อยู่ในกรอบเพื่อบันทึกเวลาเข้า-ออกงาน
            </p>

            <div id="cameraFallbackBtnContainer" style="display: none; margin-bottom: 16px;">
                <button type="button" class="btn btn-outline" style="width: 100%; border-color: var(--primary-color); color: var(--primary-color); font-weight: 600; padding: 10px;" onclick="triggerMobileCamera()">
                    <i class="fa-solid fa-camera"></i> แตะเพื่อถ่ายรูปด้วยกล้องมือถือ
                </button>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" class="btn btn-outline" onclick="closeCameraModal()" style="width: 38%; font-weight: 600;">ยกเลิก</button>
                <button type="button" id="captureSubmitBtn" class="btn camera-btn-capture" style="width: 58%;">
                    <i class="fa-solid fa-camera-retro"></i> สแกน & บันทึกเวลา
                </button>
            </div>
        </div>
    </div>

    <!-- Bottom Mobile Navigation Bar -->
    <nav class="bottom-nav">
        <a href="employee_home.php" class="nav-item active">
            <i class="fa-solid fa-clock"></i>
            <span>ลงเวลา</span>
        </a>
        <a href="leave_form.php" class="nav-item">
            <i class="fa-solid fa-calendar-plus"></i>
            <span>ยื่นลางาน</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fa-solid fa-user"></i>
            <span>โปรไฟล์</span>
        </a>
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <a href="admin/dashboard.php" class="nav-item">
            <i class="fa-solid fa-user-shield"></i>
            <span>จัดการระบบ</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="handleLogout()" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>ออกระบบ</span>
        </a>
    </nav>

    <!-- Core Scripts -->
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/checkin.js"></script>
</body>
</html>
