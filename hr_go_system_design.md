# เอกสารออกแบบระบบ HR Management System (อ้างอิงต้นแบบ HR GO)
**สภาพแวดล้อมการทำงาน:** Local Server (Intranet)
**เทคโนโลยีหลัก:** HTML, CSS, JavaScript (Frontend) | PHP (Backend API) | MySQL (Database)

---

## 1. แนวคิดการออกแบบหน้าจอ (UI/UX Design Concept)
เพื่อให้ระบบดูเป็นมืออาชีพ ใช้งานง่ายเหมือนแอปพลิเคชันจริง และเน้นความสบายตาในการมองจอนานๆ (ไม่ใช้สีฉูดฉาดหรือการไล่สีที่แสบตา)

*   **สไตล์การออกแบบ:** Flat Design & Clean Minimalist เน้นความโปร่ง มีพื้นที่ว่าง (White Space)
*   **โทนสีหลัก (Color Palette):**
    *   **สีพื้นหลัง (Background):** `#F4F7F6` (สีเทาอมฟ้าอ่อนๆ ช่วยลดแสงสะท้อน สบายตา)
    *   **สีหลัก (Primary Color):** `#4A90E2` (สีฟ้าละมุน สำหรับแถบเมนู หรือปุ่มหลัก)
    *   **สีรอง/ปุ่มสถานะ (Accent):**
        *   *เช็คอินสำเร็จ / อนุมัติ:* `#2ECC71` (สีเขียวพาสเทล)
        *   *เตือน / ลาป่วย:* `#F39C12` (สีเหลืองส้มซอฟต์ๆ)
        *   *ยกเลิก / ปฏิเสธ:* `#E74C3C` (สีแดงที่ไม่สดเกินไป)
    *   **สีตัวอักษร (Text):** `#333333` (สีเทาเข้มแทนสีดำสนิท เพื่อให้อ่านง่ายไม่ล้าสายตา)
*   **โครงสร้าง (Layout):**
    *   **ฝั่งพนักงาน (Mobile-First):** ออกแบบเป็น Web App ที่พอดีกับหน้าจอมือถือ ปุ่ม "เข้างาน-ออกงาน" เป็นปุ่มวงกลมขนาดใหญ่ตรงกลางจอ หรือปุ่มเหลี่ยมมุมโค้ง (Border-radius) กดง่ายด้วยมือเดียว
    *   **ฝั่งแอดมิน (Desktop):** เป็น Dashboard มี Sidebar เมนูทางซ้าย และกราฟสรุป/ตารางข้อมูลอยู่ตรงกลาง (Grid Layout)

---

## 2. โครงสร้างฐานข้อมูล (Database Schema Design)
ออกแบบโดยเน้นความเป็น Relational Database เพื่อให้ Query ข้อมูลสรุปผลได้ง่าย

**1. ตาราง `departments` (ข้อมูลแผนก)**
*   `dept_id` (INT, PK, Auto Increment)
*   `dept_name` (VARCHAR) - ชื่อแผนก

**2. ตาราง `users` (ข้อมูลพนักงานและสิทธิ์)**
*   `user_id` (INT, PK, Auto Increment)
*   `emp_code` (VARCHAR) - รหัสพนักงาน (เช่น EMP001)
*   `name` (VARCHAR) - ชื่อ-นามสกุล
*   `password_hash` (VARCHAR) - รหัสผ่าน (เข้ารหัสแล้ว)
*   `role` (ENUM: 'employee', 'manager', 'admin') - สิทธิ์การใช้งาน
*   `dept_id` (INT, FK) - อ้างอิงแผนก
*   `is_active` (BOOLEAN) - สถานะพนักงาน (ทำงานอยู่/ลาออก)

**3. ตาราง `attendances` (ข้อมูลลงเวลาเข้า-ออก)**
*   `attendance_id` (INT, PK, Auto Increment)
*   `user_id` (INT, FK)
*   `date` (DATE) - วันที่บันทึก
*   `check_in_time` (DATETIME) - เวลาเข้างาน
*   `check_out_time` (DATETIME, Nullable) - เวลาออกงาน
*   `ip_address` (VARCHAR) - เก็บ IP ที่ใช้กด (เพื่อตรวจว่าต่อ Wi-Fi ออฟฟิศจริงไหม)
*   `status` (ENUM: 'on_time', 'late', 'absent') - สถานะการมาทำงาน

**4. ตาราง `leave_requests` (การขอลางาน)**
*   `leave_id` (INT, PK, Auto Increment)
*   `user_id` (INT, FK)
*   `leave_type` (ENUM: 'sick', 'personal', 'vacation') - ประเภทการลา
*   `start_date` (DATE) - วันที่เริ่มลา
*   `end_date` (DATE) - วันที่สิ้นสุด
*   `reason` (TEXT) - เหตุผลการลา
*   `status` (ENUM: 'pending', 'approved', 'rejected') - สถานะอนุมัติ
*   `approved_by` (INT, FK, Nullable) - ไอดีของหัวหน้าที่อนุมัติ

**5. ตาราง `leave_balances` (โควตาวันลาคงเหลือ)**
*   `user_id` (INT, FK)
*   `leave_type` (ENUM: 'sick', 'personal', 'vacation')
*   `total_quota` (INT) - สิทธิ์ลาทั้งหมดต่อปี
*   `used_days` (INT) - จำนวนวันที่ใช้ไปแล้ว

---

## 3. แผนการทดสอบและแก้ไขข้อผิดพลาดก่อนส่งงาน (Testing & Bug Fixing Phase)
ก่อนที่จะนำระบบขึ้น Local Server จริง และให้พนักงานเริ่มใช้ ต้องผ่านกระบวนการทดสอบตามขั้นตอนดังนี้:

### เฟส 1: การทดสอบระดับโมดูล (Unit Testing โดยโปรแกรมเมอร์)
1.  **API Testing:** ใช้ Postman หรือ Thunder Client ยิง API สมมติข้อมูล (เช่น จำลองการ Check-in สองรอบซ้ำกัน) เพื่อดูว่า PHP แจ้ง Error กลับมาถูกต้องหรือไม่
2.  **Validation Check:** ทดสอบการกรอกข้อมูลผิดพลาด เช่น กรอกวันลาติดลบ, ลาเกินโควตา ระบบต้องแจ้งเตือน (Alert) และป้องกันการ Insert ข้อมูลลง Database
3.  **UI Responsiveness:** ย่อ/ขยายหน้าต่างเบราว์เซอร์ และใช้ Inspect (F12) กดโหมดมือถือ เพื่อเช็คว่า CSS ไม่แตก ปุ่มไม่ล้นจอ

### เฟส 2: การทดสอบสภาพแวดล้อมจริง (Environment & Network Testing)
1.  **IP Restriction Test:** 
    *   *ทดสอบผ่าน:* นำมือถือเชื่อมต่อ Wi-Fi/LAN ของออฟฟิศ แล้วกด Check-in -> ต้องสำเร็จ
    *   *ทดสอบตก:* ปิด Wi-Fi ใช้เน็ตมือถือ (4G/5G) แล้วเข้า IP Local ของเซิร์ฟเวอร์ -> ระบบต้องบล็อกการ Check-in หรือแจ้งเตือนว่าไม่ได้อยู่ในพื้นที่
2.  **Concurrent Users:** จำลองการเข้าสู่ระบบและกด Check-in พร้อมกัน 5-10 เครื่อง (เพื่อนร่วมทีมช่วยกันกด) เพื่อดูว่า Database มีปัญหาคอขวด หรือลงเวลาชนกันหรือไม่

### เฟส 3: การทดสอบฝั่งผู้ใช้งาน (UAT - User Acceptance Testing)
1.  ให้ตัวแทนพนักงาน (ที่ไม่ใช่ทีมไอที) ลองกดใช้งานจริง โดยไม่สอนวิธีใช้ เพื่อดูว่า UX เข้าใจง่ายจริงไหม (เช่น หาปุ่มลางานเจอไหม ดูโควตาลาตัวเองรู้เรื่องหรือไม่)
2.  นำ Feedback เรื่องสี ตำแหน่งปุ่ม หรือความเร็วในการตอบสนอง มาปรับแก้ CSS และ JavaScript

### เฟส 4: แก้ไขข้อผิดพลาดและจัดทำเอกสาร (Fixing & Documentation)
1.  รวบรวม Bug ที่เจอทั้งหมดลงในตาราง (Issue Tracker) และแก้ไขให้เสร็จ
2.  เคลียร์ข้อมูลขยะ (Mock Data) ออกจาก Database ก่อนส่งมอบ
3.  เขียนคู่มือสั้นๆ (User Manual) หรือคู่มือติดตั้งระบบ (Deployment Guide) สำหรับการเซ็ต IP และ Start Server ในอนาคต
