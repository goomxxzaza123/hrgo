# 🏢 HR GO - Enterprise HR Management & Time Attendance System
> ระบบบริหารจัดการทรัพยากรบุคคล ลงเวลาเข้า-ออกงานด้วย GPS จัดตารางกะรายเดือน และอนุมัติใบลาออนไลน์

---

## 🌟 ฟีเจอร์หลักของระบบ (Key Features)

### 📍 1. ระบบลงเวลาเข้า-ออกงาน (GPS & Selfie Attendance)
- **ถ่ายรูปเซลฟี่ (Selfie Check-in/Out):** บันทึกรูปถ่ายใบหน้าพนักงานขณะลงเวลาเข้างานและออกงาน
- **ตรวจสอบพิกัด GPS & รัศมีโรงงาน (Haversine Formula):** ยืนยันตำแหน่งพนักงานว่าอยู่ในรัศมีที่อนุญาต (เช่น 10-300 เมตร) ป้องกันการลงเวลานอกสถานที่
- **ประมวลผลสาย & OT อัตโนมัติ:**
  - คำนวณนาทีสายอัตโนมัติ (เช่น *สาย 2 นาที*, *สาย 1 ชม. 15 นาที*)
  - คำนวณชั่วโมงทำงาน และชั่วโมง OT อัตโนมัติ (วันธรรมดา 3 ชม. เมื่อออกหลัง 20:00 น. / วันอาทิตย์และวันหยุดนับชั่วโมงทำงานปกติเป็น OT เต็มวัน 8 ชม.)

### 📅 2. ระบบจัดตารางกะการทำงานรายเดือน (Monthly Shift Roster & Scheduling)
- **Matrix Table Interactive:** Admin/Manager สามารถจัดตารางเวรกะงาน (☀️ กะเช้า `08:00 - 17:00`, 🌙 กะดึก `20:00 - 05:00`, 🏝️ วันหยุด `Off`) ของพนักงานทุกคนรายเดือนผ่านหน้าเว็บ
- **จัดกะอัตโนมัติทั้งเดือน (Batch Auto-Assign):** เครื่องมือช่วยจัดกะตามรูปแบบสำเร็จรูป (เช่น ทำงานจันทร์-ศุกร์ กะเช้า | เสาร์-อาทิตย์ หยุด) ทั้งแผนกในคลิกเดียว
- **ประมวลผลตามตารางกะจริง:** ระบบนำกะงานในตารางไปคำนวณเวลาเริ่มงาน สาย และ OT ของพนักงานแบบ Real-time

### 📝 3. ระบบจัดการและพิจารณาอนุมัติใบลา (Leave Management)
- **ยื่นใบลาออนไลน์:** พนักงานยื่นลาป่วย ลากิจ ลาพักร้อน พร้อมระบบคำนวณวันและตรวจสอบโควตาสิทธิ์คงเหลือ
- **อนุมัติ & ตัดโควตาอัตโนมัติ:** Admin/Manager พิจารณาอนุมัติ/ปฏิเสธใบลาพร้อมตัดโควตาอัตโนมัติ
- **ยื่นลาแทนพนักงาน (Leave On Behalf):** ฝากหัวหน้ายื่นลาแทนพนักงานพร้อมอนุมัติทันที
- **แก้ไขและลบใบลา (Edit & Delete Leave):** รองรับการแก้ไขข้อมูลใบลาหรือยกเลิกใบลา พร้อมระบบ **คืนโควตาวันลากลับเข้าสู่ระบบอัตโนมัติ**

### 📊 4. ระบบรายงาน & ออกเอกสาร (Reports & Export)
- **รายงานภาพรวม & ตัวกรอง:** ค้นหาประวัติลงเวลาตามช่วงวันที่ แผนก พนักงาน และสถานะ
- **ส่งออกไฟล์ Excel (CSV/XLS):** ไฟล์รายงานภาษาไทยตัวหนังสือใหญ่ คมชัด อ่านง่าย (12pt Segoe UI / Prompt)
- **พิมพ์เอกสาร / เซฟ PDF (1 หน้าจบ A4):** แสดงหน้าเอกสารรายงานสรุปรายบุคคลแบบเต็มหน้า A4 พร้อมปุ่มพิมพ์/บันทึก PDF ทันที

### 👥 5. ระบบจัดการพนักงาน & แผนก (Administration)
- **จัดการรายชื่อพนักงาน:** เพิ่ม แก้ไข ระงับการใช้งาน กำหนดสิทธิ์ (`employee`, `manager`, `admin`) และกำหนดโควตาวันลา
- **จัดการแผนกองค์กร & วันหยุดบริษัท:** กำหนดวันหยุดนักขัตฤกษ์ประจำปี
- **ตั้งค่าพิกัดโรงงาน:** ปรับตั้งค่า ละติจูด ลองจิจูด และรัศมีเมตรที่อนุญาตให้ลงเวลา

---

## 🛠️ เทคโนโลยีที่ใช้ (Tech Stack)

- **Backend:** PHP 8.x (PDO Object-Oriented, Session Authentication)
- **Database:** MySQL / MariaDB (UTF-8MB4, InnoDB Engine)
- **Frontend:** HTML5, Vanilla CSS3 (Modern SaaS Design System, Dark/Light Mode with Glassmorphic UI), JavaScript (ES6+ Async/Await Fetch API)
- **Icons & Fonts:** Font Awesome 6 Pro, Google Fonts (Prompt)
- **Libraries:** SweetAlert2 (Interactive Modals)

---

## 🚀 ขั้นตอนการติดตั้งและการใช้งาน (Installation Guide)

### 1. ความต้องการของระบบ (Requirements)
- Web Server (XAMPP / Apache / Nginx) รองรับ **PHP 8.0+**
- Database Server **MySQL 5.7+ / MariaDB 10.4+**

### 2. ขั้นตอนการตั้งค่า
1. คัดลอกโฟลเดอร์โปรเจกต์ไปไว้ที่ Web Server (เช่น `c:\xampp\htdocs\hrgo`)
2. เปิด **phpMyAdmin** หรือ MySQL Client แล้วสร้างฐานข้อมูลใหม่ชื่อ:
   ```sql
   CREATE DATABASE hrgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. นำเข้าไฟล์ฐานข้อมูล `schema.sql` เข้าไปยังฐานข้อมูล `hrgo`
4. ตรวจสอบการตั้งค่าฐานข้อมูลในไฟล์ [`api/config.php`](file:///c:/xampp/htdocs/hrgo/api/config.php):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'hrgo');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
5. เปิดเบราว์เซอร์แล้วเข้าใช้งานผ่าน URL:
   `http://localhost/hrgo/`

---

## 🔑 บัญชีผู้ใช้เริ่มต้นสำหรับทดสอบ (Default Credentials)

| รหัสพนักงาน (EMP CODE) | รหัสผ่าน (PASSWORD) | สิทธิ์การใช้งาน (ROLE) | ชื่อ-นามสกุล |
| :--- | :--- | :--- | :--- |
| **EMP001** | `admin123` | Admin | สมชาย วงศ์สวัสดิ์ |
| **EMP002** | `emp123` | Manager | กิตติพงษ์ สุขเจริญ |
| **EMP003** | `emp123` | Employee | ธนกฤต ชัยชนะ |
| **EMP004** | `emp123` | Employee | พิมพ์ชนก สุวรรณ |
| **EMP005** | `emp123` | Employee | ศุภโชค นิมิตมงคล |

---

## 📂 โครงสร้างโฟลเดอร์โปรเจกต์ (Project Structure)

```text
hrgo/
├── admin/                     # หน้าการทำงานของผู้ดูแลระบบ (Admin/Manager Dashboard)
│   ├── dashboard.php          # ภาพรวมสถิติองค์กร & บันทึกลงเวลาล่าสุด
│   ├── approve_leave.php      # ระบบอนุมัติ ยื่นลาแทน แก้ไข และลบใบลา
│   ├── manage_users.php        # จัดการข้อมูลพนักงาน & โควตาวันลา
│   ├── manage_departments.php  # จัดการแผนกองค์กร
│   ├── manage_roster.php       # ระบบจัดตารางกะการทำงานรายเดือน (Matrix Roster)
│   ├── reports.php            # รายงานลงเวลา & พิมพ์ PDF/ส่งออก Excel
│   ├── manage_holidays.php   # จัดการวันหยุดบริษัท
│   └── manage_settings.php   # ตั้งค่าพิกัด GPS ออฟฟิศ & รัศมี
├── api/                       # RESTful API Endpoints (JSON/HTTP)
│   ├── config.php             # ตั้งค่า DB & ฟังก์ชันคำนวณ (Distance, OT, Shift)
│   ├── post_checkin.php       # API ลงเวลาเข้า-ออกงานพร้อมตรวจ GPS & รูปถ่าย
│   ├── admin_leave.php        # API จัดการใบลา (อนุมัติ, ยื่นแทน, แก้ไข, ลบ)
│   ├── admin_roster.php       # API ตารางกะรายเดือน (Single & Batch Save)
│   ├── admin_reports.php      # API รายงาน & สร้างไฟล์ Excel/PDF
│   └── ...                    # API ย่อยอื่นๆ
├── assets/                    # ไฟล์ CSS, JavaScript และสื่อการออกแบบ
│   ├── css/style.css          # Core Design System (CSS Variables, Responsive)
│   ├── css/admin.css          # Admin Layout & Dashboard Styles
│   ├── js/admin.js            # JavaScript ควบคุมหน้า Admin
│   ├── js/checkin.js          # JavaScript ควบคุมการถ่ายรูปเซลฟี่ & ดึง GPS
│   └── js/theme.js            # ระบบจัดการ Dark / Light Theme
├── employee_home.php          # หน้าแรกสำหรับพนักงาน (ลงเวลา & ดูประวัติ)
├── leave_form.php             # หน้าแบบฟอร์มยื่นใบลาสำหรับพนักงาน
├── profile.php                # หน้าโปรไฟล์ส่วนตัว & เปลี่ยนรหัสผ่าน
├── schema.sql                 # ไฟล์ SQL Database Schema & Initial Data
└── README.md                  # เอกสารกำกับโปรเจกต์ (Project Documentation)
```

---

## 📄 ใบอนุญาตและการใช้งาน (License)

โปรเจกต์พัฒนาเพื่อใช้ในงานบริหารจัดการทรัพยากรบุคคล (HR GO System) สงวนลิขสิทธิ์ © 2026 HR GO Team
