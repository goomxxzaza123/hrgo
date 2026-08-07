# Modern SaaS Dashboard CSS Design

## Goal

ปรับหน้าตาของระบบ HR GO ทุกหน้าให้เป็น Modern SaaS Clean Dashboard ตามภาพอ้างอิง โดยแก้เฉพาะ CSS และรักษา HTML, PHP, JavaScript, API, class names และพฤติกรรมเดิมทั้งหมด

## Scope

- แก้ไขเฉพาะ `assets/css/style.css` และ `assets/css/admin.css`
- ครอบคลุมหน้า Login, หน้าพนักงาน และหน้าแอดมินทั้งหมด
- รองรับ Light Mode และ Dark Mode ผ่านระบบ theme เดิม
- ไม่ใช้ Tailwind CSS และไม่เพิ่ม dependency ใหม่
- ไม่เพิ่ม override stylesheet หรือกอง selector ชุดใหม่ไว้ท้ายไฟล์ แต่ refactor กฎเดิมให้ใช้ design tokens ร่วมกัน

## Visual System

### Colors

- Light background: `#F8FAFC` พร้อมพื้นผิวการ์ดสีขาว
- Dark background: slate/charcoal พร้อมการ์ดที่สว่างกว่าพื้นหลังเล็กน้อย
- Primary action: ดำเกือบสนิทใน Light Mode และขาวหรือเทาอ่อนใน Dark Mode
- Semantic accents: ม่วงอ่อน เขียวอ่อน ส้มอ่อน และแดงอ่อน ใช้กับไอคอน สถิติ badge และสถานะ
- เส้นขอบใช้สีโปร่งบาง โดยรักษา contrast ให้อ่านได้ทั้งสองธีม

### Shape and Elevation

- การ์ดหลักใช้รัศมีประมาณ 18–24px
- ปุ่ม ช่องกรอก และองค์ประกอบย่อยใช้รัศมีที่สัมพันธ์กัน
- Badge และ navigation active ใช้รูปทรงแคปซูล
- เงาการ์ดเป็นเงาฟุ้งหลายชั้นที่เบาใน Light Mode และลดความทึบใน Dark Mode

### Typography and Spacing

- คงฟอนต์ Prompt ซึ่งเป็น sans-serif ภาษาไทยที่ระบบใช้อยู่
- เพิ่มลำดับชั้นของหัวข้อ คำอธิบาย ตัวเลขสถิติ และ metadata ให้ชัดเจน
- ใช้ spacing scale สม่ำเสมอ และรักษาความหนาแน่นที่เหมาะกับตารางข้อมูล

## Component Treatment

- Sidebar: พื้นผิวสะอาด เมนู active แบบแคปซูล และ hover ที่นุ่มนวล
- Header/navigation: ลดเส้นแบ่งที่แข็ง ใช้พื้นโปร่งหรือพื้นผิวการ์ดพร้อมเงาบาง
- Cards/stat cards: มุมโค้งมาก เงาฟุ้ง และไอคอนในวงกลมสีพาสเทล
- Buttons: ปุ่มหลักโทนดำ/ขาวตามธีม ปุ่มรองสีเทาอ่อน และปุ่มสถานะคง semantic color
- Forms: ช่องกรอกพื้นอ่อน เส้นขอบบาง focus ring ที่ชัด และ disabled state ที่อ่านได้
- Tables: หัวตารางพื้นอ่อน แถว hover อ่อนโยน และรองรับ horizontal scrolling เดิม
- Badges: รูปทรงแคปซูล สีพื้นอ่อน และข้อความเข้มในโทนเดียวกัน
- Modals, login card, employee cards และ mobile bottom navigation ใช้ visual system เดียวกัน

## Responsive Behavior

- รักษา breakpoint และโครงสร้าง responsive เดิม
- Desktop admin ยังคง sidebar และ content grid เดิม
- Mobile admin ยังคง sidebar drawer, backdrop และปุ่มเปิดเมนูเดิม
- Employee pages ยังคง mobile-first และพื้นที่กดของปุ่มต้องไม่น้อยลง
- ตารางและ modal ต้องไม่ล้น viewport

## Compatibility and Data Flow

งานนี้ไม่มีการเปลี่ยน data flow หรือ behavior การเปลี่ยนธีม ตัวแปร CSS ที่ JavaScript และ inline styles อ้างอิงอยู่ เช่น `--text-muted`, `--primary-color`, `--border-color` และ `--shadow-md` ต้องคงชื่อเดิมไว้ หรือมีค่าเทียบเท่าที่เข้ากันได้

## Verification

- ตรวจว่ามีการเปลี่ยนเฉพาะสองไฟล์ CSS ที่อนุญาตในงาน implementation
- ตรวจสมดุลวงเล็บและ syntax พื้นฐานของ CSS
- รัน PHP syntax checks เพื่อยืนยันว่าไม่มีไฟล์ PHP ถูกกระทบ
- เปิดหน้า Login, Employee Home และ Admin Dashboard ใน Light/Dark Mode
- ตรวจ viewport desktop, tablet และ mobile เพื่อหา overflow, overlap และข้อความอ่านยาก
- เปรียบเทียบองค์ประกอบสำคัญกับภาพอ้างอิง: พื้นหลัง การ์ด เงา รัศมี ปุ่ม badge sidebar และสีพาสเทล

## Acceptance Criteria

- ทุกหน้ามีภาษาภาพ Modern SaaS Clean ที่สม่ำเสมอ
- Light/Dark Mode อ่านง่ายและมีสถานะ hover, focus, disabled ที่มองเห็นได้
- ไม่มีการแก้ HTML, PHP, JavaScript, API หรือฐานข้อมูล
- ไม่มี Tailwind หรือ dependency ใหม่
- ระบบ responsive และ interaction เดิมยังทำงาน
