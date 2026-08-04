# เอกสารออกแบบระบบและสถาปัตยกรรม (Architecture & System Design)
**ชื่อโปรเจกต์:** HR Management System (อ้างอิงฟีเจอร์จาก HR GO)
**สภาพแวดล้อมการทำงาน:** Local Server (Intranet) / WAMP, XAMPP, หรือ Docker
**เทคโนโลยีหลัก:** HTML5, CSS3, Vanilla JavaScript, PHP 8+ (PDO), MySQL/MariaDB

---

## 1. ภาพรวมระบบ (System Overview)
ระบบนี้คือ Web Application สำหรับบริหารจัดการทรัพยากรบุคคลภายในองค์กร ออกแบบมาเพื่อทดแทนการใช้เอกสารกระดาษ (Paperless) และแก้ปัญหาการลงเวลาเข้างานที่ไม่ยืดหยุ่น โดยระบบถูกออกแบบสถาปัตยกรรมแบบแยกส่วน (Decoupled Architecture) ระหว่าง Frontend (UI) และ Backend (API) เพื่อให้รองรับการพัฒนาต่อยอดเป็น Mobile App ในอนาคต

**กลุ่มผู้ใช้งานเป้าหมายแบ่งเป็น 3 ระดับ (Roles):**
1.  **Employee (พนักงานทั่วไป):** ใช้งานผ่านมือถือ (Mobile-First UI) เพื่อลงเวลาเข้า-ออกงาน, ดูโควตาและยื่นขอลางาน, ดูประวัติการเข้างาน
2.  **Manager (หัวหน้าแผนก):** มีสิทธิ์ของ Employee ทั้งหมด และเพิ่มสิทธิ์ในการดูคำขอลางานของลูกน้องในแผนกเดียวกันเพื่อกด อนุมัติ/ปฏิเสธ
3.  **HR / Admin (ผู้ดูแลระบบ):** ใช้งานผ่าน Desktop (Dashboard UI) เพื่อจัดการข้อมูลพนักงาน, เพิ่ม/แก้ไขแผนก, ตั้งค่าโควตาวันลา และดูรายงานสรุป (Report) ระดับองค์กร

---

## 2. โมดูลหลักของระบบ (Core Modules)

### 2.1 โมดูลการลงเวลาเข้า-ออกงาน (Time Attendance Module)
*   **แนวคิด:** พนักงานกดเข้างานด้วยปุ่มเดียว (One-Tap Check-in) ผ่านสมาร์ทโฟน
*   **เงื่อนไขสำคัญ (Business Logic):**
    *   ป้องกันการเช็คอินนอกสถานที่: ระบบจะต้องตรวจสอบ `IP Address` ของผู้ใช้ หากไม่ตรงกับวง LAN/Wi-Fi ของบริษัท (เช่น `192.168.1.x`) จะไม่อนุญาตให้ลงเวลา
    *   ป้องกันการลงเวลาซ้ำซ้อน: ต้องเช็คว่าในวันที่ปัจจุบัน (`work_date`) พนักงานคนนี้มีบันทึก Check-in ไปแล้วหรือยัง
*   **เทคนิคที่ใช้:** JavaScript `fetch()` ยิง API ไปหา PHP และใช้ SQL เช็คเงื่อนไขต่างๆ ก่อน `INSERT` หรือ `UPDATE` ข้อมูลลงตาราง `attendances`

### 2.2 โมดูลระบบลางาน (Leave Management Module)
*   **แนวคิด:** แบบฟอร์มออนไลน์ที่ช่วยให้พนักงานเห็นโควตาคงเหลือก่อนยื่นลา และมีการส่งเรื่องให้หัวหน้าอนุมัติตามลำดับขั้น
*   **เงื่อนไขสำคัญ (Business Logic):**
    *   ก่อนบันทึกคำขอลา (`leave_requests`) ต้อง Query ข้อมูลจาก `leave_balances` เพื่อตรวจสอบว่า จำนวนวันที่ขอลา ต้องไม่เกิน โควตาที่เหลืออยู่ (`total_quota` - `used_days`)
    *   เมื่อหัวหน้า (Manager) กด Approve สถานะใบลาจะเปลี่ยน และระบบต้องตัดโควตาวันลาในตาราง `leave_balances` โดยอัตโนมัติ

---

## 3. โครงสร้างโฟลเดอร์ (Directory Structure)
ระบบแบ่งโครงสร้างชัดเจนระหว่างไฟล์แสดงผล (Views) และไฟล์จัดการข้อมูล (API)

```text
/hr_local_system
│
├── /assets                # ไฟล์ Resource ฝั่งหน้าบ้าน
│   ├── /css               # ไฟล์สไตล์ (เช่น style.css, admin.css)
│   ├── /js                # ไฟล์สคริปต์ (เช่น auth.js, checkin.js)
│   └── /images            # รูปภาพประกอบ
│
├── /api                   # Backend API (รับ-ส่งข้อมูลรูปแบบ JSON เท่านั้น)
│   ├── config.php         # ไฟล์เชื่อมต่อ Database (PDO)
│   ├── auth_login.php     # ตรวจสอบการ Login และสร้าง Session
│   ├── post_checkin.php   # บันทึกเวลาเข้า-ออกงาน
│   ├── get_leaves.php     # ดึงประวัติและโควตาวันลา
│   └── post_leave.php     # บันทึกคำขอลางาน
│
├── /admin                 # หน้าจอสำหรับ HR / Manager (Desktop View)
│   ├── dashboard.php      # หน้าแรกแอดมิน
│   ├── manage_users.php   # จัดการข้อมูลพนักงาน
│   └── approve_leave.php  # หน้าจัดการอนุมัติใบลา
│
├── index.php              # หน้า Login หลักของระบบ
├── employee_home.php      # หน้าหลักพนักงาน (ปุ่ม Check-in)
└── leave_form.php         # หน้าฟอร์มยื่นลางาน
```

---

## 4. มาตรฐานการพัฒนา (Coding Standards & Security)
เพื่อให้ระบบมีความปลอดภัยและดูแลรักษาง่าย (Maintainable) ให้ยึดหลักการดังนี้:

1.  **Security (ความปลอดภัย):**
    *   **SQL Injection:** ห้ามนำตัวแปรไปต่อ String ในคำสั่ง SQL โดยตรงเด็ดขาด ต้องใช้ `Prepared Statements` ของ PDO เท่านั้น
    *   **Password Hashing:** รหัสผ่านต้องถูกเข้ารหัสด้วยฟังก์ชัน `password_hash()` ของ PHP เสมอ
    *   **API Protection:** ทุก API Endpoint ต้องมีการตรวจสอบ Session/Token ก่อนเสมอ หากไม่มีสิทธิ์ต้องตอบกลับเป็น `HTTP 401 Unauthorized`
2.  **API Design (การออกแบบ API):**
    *   API ทุกตัวต้องตอบกลับในรูปแบบ JSON Format เท่านั้น (เช่น `json_encode(['status' => 'success', 'message' => '...'])`)
    *   จัดการ Error อย่างเป็นระบบ (Try-Catch block)
3.  **UI/UX Design:**
    *   ใช้หลักการ Mobile-First สำหรับหน้า Employee และ Responsive Grid สำหรับหน้า Admin
    *   ยึดตาม Color Palette หลัก: Background (`#F4F7F6`), Primary (`#4A90E2`), Success (`#2ECC71`), Error (`#E74C3C`), Text (`#333333`)
    *   การโต้ตอบกับผู้ใช้ (User Interaction) ต้องไม่มีการ Refresh หน้าเว็บ โดยให้ใช้ JavaScript (AJAX/Fetch) ควบคู่กับ SweetAlert2 หรือ Toast Notification ในการแสดงผลลัพธ์
