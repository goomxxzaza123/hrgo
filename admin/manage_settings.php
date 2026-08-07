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
    <title>ตั้งค่าพิกัด & รัศมี | HR Management System</title>
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
    <style>
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .preset-btn {
            padding: 8px;
            font-size: 0.85rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .preset-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .summary-box {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }
    </style>
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
                    <a href="manage_holidays.php" class="sidebar-link">
                        <span>วันหยุดบริษัท</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="manage_settings.php" class="sidebar-link active">
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
                    <h1>ตั้งค่าพิกัดออฟฟิศ & รัศมีอนุญาตเข้างาน</h1>
                    <p style="color:var(--text-muted);">ปรับแต่งรัศมีพื้นที่บริษัทและตั้งค่าพิกัด GPS สำหรับตรวจสอบการลงเวลาเข้างาน</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button type="button" class="mobile-toggle-btn btn btn-outline btn-sm" onclick="toggleMobileSidebar()">☰ เมนู</button>
                    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()"></button>
                </div>
            </div>

            <!-- Card: แบบฟอร์มตั้งค่า -->
            <div class="card" style="max-width: 650px;">
                <form id="settingsForm" onsubmit="handleSettingsSubmit(event)">
                    
                    <!-- 1. ตั้งค่ารัศมีอนุญาต -->
                    <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size:1.1rem; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                            📏 รัศมีอนุญาตให้ลงเวลาเข้างาน (เมตร)
                        </h3>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">
                            พนักงานต้องอยู่ในระยะไม่เกินจำนวนเมตรที่กำหนด จึงจะกดลงเวลาได้
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label">ระยะรัศมี (เมตร)</label>
                            <input type="number" id="max_distance_meters" class="form-control" min="10" max="100000" required>
                        </div>

                        <label class="form-label" style="font-size:0.8rem; color:var(--text-muted);">ปุ่มเลือกระยะด่วน (Presets):</label>
                        <div class="preset-grid">
                            <button type="button" class="preset-btn" onclick="setRadiusPreset(100)">100 เมตร</button>
                            <button type="button" class="preset-btn" onclick="setRadiusPreset(300)">300 เมตร</button>
                            <button type="button" class="preset-btn" onclick="setRadiusPreset(500)">500 เมตร</button>
                            <button type="button" class="preset-btn" onclick="setRadiusPreset(1000)">1,000 เมตร (1km)</button>
                            <button type="button" class="preset-btn" onclick="setRadiusPreset(50000)" style="background:#EBF3FA; border-color:#4A90E2; color:#4A90E2;">50 km (ทดสอบ)</button>
                        </div>
                    </div>

                    <!-- 2. ตั้งค่าพิกัดออฟฟิศ -->
                    <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <h3 style="font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                                📍 พิกัดออฟฟิศบริษัท (Company HQ)
                            </h3>
                            <button type="button" class="btn btn-outline btn-sm" onclick="useMyCurrentLocation()">
                                🎯 ใช้พิกัดปัจจุบันของฉัน
                            </button>
                        </div>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">
                            พิกัดละติจูด (Latitude) และ ลองจิจูด (Longitude) ของออฟฟิศ
                        </p>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label class="form-label">Latitude (ละติจูด)</label>
                                <input type="text" id="company_lat" class="form-control" placeholder="เช่น 13.756330" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude (ลองจิจูด)</label>
                                <input type="text" id="company_lng" class="form-control" placeholder="เช่น 100.501815" required>
                            </div>
                        </div>
                    </div>

                    <!-- 3. สวิตช์เปิด/ปิดระบบตรวจความปลอดภัย -->
                    <div style="margin-bottom: 24px;">
                        <h3 style="font-size:1.1rem; margin-bottom:12px;">🔒 ตัวเลือกเปิด/ปิดการเช็คความปลอดภัย</h3>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" id="enable_location_check" style="width:18px; height:18px;">
                                <span>เปิดการตรวจสอบพิกัด GPS ออฟฟิศ (GPS Area Guard)</span>
                            </label>
                        </div>

                        <div>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" id="enable_ip_check" style="width:18px; height:18px;">
                                <span>เปิดการจำกัดวง IP Wi-Fi/LAN ของบริษัท</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" id="saveBtn" class="btn btn-primary">
                        💾 บันทึกการตั้งค่า
                    </button>
                </form>
            </div>

            <!-- Card 2: บริหารจัดการพื้นที่รูปถ่ายเซลฟี่ (Photo Storage Cleanup) -->
            <div class="card" style="max-width: 650px; margin-top: 24px;">
                <h3 style="font-size:1.1rem; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                    🗑️ บริหารจัดการพื้นที่จัดเก็บรูปถ่าย (Photo Storage Cleanup)
                </h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
                    สรุปพื้นที่จัดเก็บรูปถ่ายและเครื่องมือลบรูปเก่าตามอายุ retention เพื่อประหยัดดิสก์
                </p>

                <!-- Metrics -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div class="summary-box" style="padding:12px; text-align:center;">
                        <div style="font-size:0.8rem; color:var(--text-muted);">จำนวนรูปถ่ายในระบบ</div>
                        <div style="font-size:1.4rem; font-weight:bold; color:var(--primary-color);" id="photoCount">กำลังโหลด...</div>
                    </div>
                    <div class="summary-box" style="padding:12px; text-align:center;">
                        <div style="font-size:0.8rem; color:var(--text-muted);">ขนาดพื้นที่ที่ใช้อยู่</div>
                        <div style="font-size:1.4rem; font-weight:bold; color:#D35400;" id="photoSizeMb">กำลังโหลด...</div>
                    </div>
                </div>

                <!-- Form Cleanup -->
                <form id="cleanPhotoForm" onsubmit="handleCleanPhotos(event)">
                    <div class="form-group">
                        <label class="form-label">เลือกเงื่อนไขการล้างรูปเก่า</label>
                        <select id="older_than_days" class="form-control">
                            <option value="30">ลบรูปที่เก่ากว่า 30 วัน (1 เดือน)</option>
                            <option value="60">ลบรูปที่เก่ากว่า 60 วัน (2 เดือน)</option>
                            <option value="90" selected>ลบรูปที่เก่ากว่า 90 วัน (3 เดือน - แนะนำ)</option>
                            <option value="180">ลบรูปที่เก่ากว่า 180 วัน (6 เดือน)</option>
                        </select>
                    </div>
                    <button type="submit" id="cleanBtn" class="btn btn-outline" style="border-color:#E74C3C; color:#E74C3C;">
                        🗑️ เคลียร์ไฟล์รูปภาพเก่า
                    </button>
                </form>
            </div>

            <!-- Admin Page Footer -->
            <footer class="admin-footer">
                <p>© <?= date('Y') ?> HR GO Management System. All rights reserved.</p>
                <p class="admin-footer-sub">ระบบบริหารจัดการทรัพยากรบุคคลและลงเวลาทำงาน (Intranet System)</p>
            </footer>

        </main>
    </div>

    <!-- Core Scripts -->
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadSettings();
            loadPhotoStorageMetrics();
        });

        async function loadPhotoStorageMetrics() {
            try {
                const response = await fetch('../api/admin_clean_photos.php');
                const result = await response.json();
                if (response.ok && result.success) {
                    document.getElementById('photoCount').textContent  = `${result.data.file_count} รูป`;
                    document.getElementById('photoSizeMb').textContent = `${result.data.size_mb} MB`;
                }
            } catch (err) {
                console.error('Error loading photo storage metrics:', err);
            }
        }

        async function handleCleanPhotos(e) {
            e.preventDefault();
            const days = document.getElementById('older_than_days').value;

            const confirmRes = await Swal.fire({
                title: 'ยืนยันการลบไฟล์รูปภาพเก่า?',
                text: `รูปถ่ายเข้า-ออกงานที่เก่ากว่า ${days} วัน จะถูกลบออกจากดิสก์อย่างถาวร!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E74C3C',
                cancelButtonColor: '#95A5A6',
                confirmButtonText: 'ใช่, ลบรูปเก่าเลย!',
                cancelButtonText: 'ยกเลิก'
            });

            if (!confirmRes.isConfirmed) return;

            const cleanBtn = document.getElementById('cleanBtn');
            cleanBtn.disabled = true;
            cleanBtn.textContent = 'กำลังลบรูปภาพ...';

            try {
                const response = await fetch('../api/admin_clean_photos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ older_than_days: days })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'ล้างรูปภาพเรียบร้อย!', text: result.message });
                    loadPhotoStorageMetrics();
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถลบได้', text: result.message });
                }
            } catch (err) {
                console.error('Clean photo error:', err);
            } finally {
                cleanBtn.disabled = false;
                cleanBtn.textContent = '🗑️ เคลียร์ไฟล์รูปภาพเก่า';
            }
        }

        async function loadSettings() {
            try {
                const response = await fetch('../api/admin_settings.php');
                const result = await response.json();

                if (!result.success) return;

                const data = result.data;
                document.getElementById('max_distance_meters').value = data.max_distance_meters;
                document.getElementById('company_lat').value          = data.company_lat;
                document.getElementById('company_lng').value          = data.company_lng;
                document.getElementById('enable_location_check').checked = data.enable_location_check;
                document.getElementById('enable_ip_check').checked       = data.enable_ip_check;

            } catch (error) {
                console.error('Error loading settings:', error);
            }
        }

        function setRadiusPreset(meters) {
            document.getElementById('max_distance_meters').value = meters;
        }

        function useMyCurrentLocation() {
            if (!navigator.geolocation) {
                Swal.fire({ icon: 'warning', title: 'ไม่รองรับ GPS', text: 'เบราว์เซอร์ของคุณไม่รองรับการดึงพิกัด GPS' });
                return;
            }

            Swal.fire({ title: 'กำลังดึงพิกัดปัจจุบัน...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    document.getElementById('company_lat').value = pos.coords.latitude.toFixed(6);
                    document.getElementById('company_lng').value = pos.coords.longitude.toFixed(6);
                    Swal.fire({
                        icon: 'success',
                        title: 'ดึงพิกัดปัจจุบันสำเร็จ!',
                        text: `Lat: ${pos.coords.latitude.toFixed(6)}, Lng: ${pos.coords.longitude.toFixed(6)}`,
                        timer: 1800,
                        showConfirmButton: false
                    });
                },
                (err) => {
                    console.error('GPS error:', err);
                    Swal.fire({ icon: 'error', title: 'ดึงพิกัดไม่สำเร็จ', text: 'กรุณาอนุญาตการเข้าถึงตำแหน่งบนเบราว์เซอร์' });
                },
                { enableHighAccuracy: true }
            );
        }

        async function handleSettingsSubmit(e) {
            e.preventDefault();

            const max_distance_meters   = document.getElementById('max_distance_meters').value;
            const company_lat           = document.getElementById('company_lat').value;
            const company_lng           = document.getElementById('company_lng').value;
            const enable_location_check = document.getElementById('enable_location_check').checked;
            const enable_ip_check       = document.getElementById('enable_ip_check').checked;

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'กำลังบันทึก...';

            try {
                const response = await fetch('../api/admin_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        max_distance_meters,
                        company_lat,
                        company_lng,
                        enable_location_check,
                        enable_ip_check
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกการตั้งค่าสำเร็จ!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถบันทึกได้', text: result.message });
                }
            } catch (error) {
                console.error('Settings submit error:', error);
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '💾 บันทึกการตั้งค่า';
            }
        }
    </script>
</body>
</html>
