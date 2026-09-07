# Firestore lesson/homework — Triple C "Referral" (งานส่งบทเรียนสัปดาห์ที่ 6-7)

**เว็บออนไลน์: https://triplec-a5e75.web.app**

โฟลเดอร์นี้แปลง entity **Referral** ของโปรเจกต์ Triple C (ดู `app/Models/Referral.php` และ
`database/migrations` ในต้นฉบับ) ให้เป็น Firestore collections พร้อมเว็บแอปเพิ่ม/แก้สถานะ/ลบเคสได้จริง
ผ่านการล็อกอิน + สิทธิ์ตามบทบาท — ดู [ACL.md](ACL.md) สำหรับตารางสิทธิ์เต็ม และ [CLAUDE.md](CLAUDE.md)
สำหรับภาพรวมทางเทคนิค

## เข้าสู่ระบบ

**สมัครสมาชิกได้เองที่หน้า [login.html](https://triplec-a5e75.web.app/login.html) → "ลงทะเบียน"** — เลือก
บทบาทได้เฉพาะ `ward_staff` (เจ้าหน้าที่หอผู้ป่วย) หรือ `home_visit_team` (พยาบาลทีมเยี่ยมบ้าน) เท่านั้น

บัญชี **admin** ไม่มีระบบสมัครสมาชิกสาธารณะ (ป้องกันการยกระดับสิทธิ์ตัวเอง) — สร้างได้ทางเดียวคือรัน
`setup-users.js` ด้วยอีเมล/รหัสผ่านของคุณเอง (ดูขั้นตอนด้านล่าง) ไม่มีรหัสผ่านตัวอย่างใด ๆ เผยแพร่ในเอกสารนี้

## ฟีเจอร์ (อัปเดตสัปดาห์ที่ 7)

- **ลงทะเบียน/ล็อกอินด้วย Firebase Authentication (Email/Password)** — ทุกหน้าบังคับล็อกอินก่อนอ่าน/เขียน
  Firestore; สมัครสมาชิกเองได้ที่ `register.html` (จำกัดบทบาทที่เลือกได้ ดูด้านบน)
- **เพิ่มเคสใหม่** (`referral-create.html`) — เขียนลง Firestore จริง สถานะเริ่มต้น `pending_review` เสมอ
- **แก้สถานะเคส** (ยืนยันแผนดูแล / เปลี่ยนสถานะรอบติดตาม) — เขียนกลับ Firestore จริง ปิดเบราว์เซอร์แล้ว
  เปิดใหม่ข้อมูลยังอยู่ (ต่างจากเวอร์ชันสัปดาห์ที่ 6 ที่แค่ mutate ตัวแปรในหน่วยความจำ)
- **ลบเคส** พร้อม dialog ยืนยันก่อนลบเสมอ
- **เมนู/ปุ่มเปลี่ยนตามบทบาทจริง** ของผู้ใช้ที่ล็อกอินอยู่ (อ่านจากเอกสาร `users/{uid}` ของผู้ใช้เองใน Firestore)
- **Firestore Security Rules** (`firestore.rules`) บังคับกฎ ACL จริงฝั่งเซิร์ฟเวอร์ ไม่ใช่แค่ซ่อนปุ่มฝั่ง UI —
  รวมถึงกันไม่ให้ผู้สมัครสมาชิกเองตั้ง role เป็น `admin`

## Collections ที่จะถูกสร้าง

| Collection | ใช้แทน | หมายเหตุ |
|---|---|---|
| `referrals` | เคส (entity หลัก) | เริ่มต้นด้วยข้อมูลตัวอย่าง 5 รายการจาก `seed.js` ครอบคลุม `status` ครบทั้ง 4 ค่า — เพิ่มเติมได้จากหน้าเว็บ |
| `patients` | ผู้ป่วยที่ `referrals.patientId` อ้างอิง | ข้อมูลจำลอง 5 คน + ที่สร้างใหม่จากฟอร์ม |
| `caseTypes` | ประเภทเคสที่ `referrals.caseTypeId` อ้างอิง | 5 ประเภท: Palliative Care / ผู้ป่วยติดเตียง / COPD / โรคหลอดเลือดสมอง / แผลเบาหวาน |
| `users` | เจ้าหน้าที่/พยาบาลที่ `createdBy` / `confirmedBy` อ้างอิง | doc id = Firebase Auth uid สำหรับบัญชีจริง (ดูตารางด้านบน) + ข้อมูลเก่าจาก seed ไว้แสดงผลย้อนหลัง |

## วิธีรันในเครื่อง (local)

1. ติดตั้ง dependency:

   ```bash
   npm install
   ```

2. สร้าง Service Account Key ของโปรเจกต์ Firebase ของคุณเอง (ใช้กับ `seed.js`/`setup-users.js` เท่านั้น):
   - เปิด [Firebase Console](https://console.firebase.google.com/) → เลือกโปรเจกต์ของคุณ
   - Project settings (ไอคอนเฟือง) → **Service accounts** → **Generate new private key**
   - ดาวน์โหลดไฟล์ JSON แล้วเปลี่ยนชื่อเป็น `serviceAccountKey.json` วางไว้ในโฟลเดอร์นี้
   - **ห้าม commit ไฟล์นี้เข้า git** (อยู่ใน `.gitignore` แล้ว) — มีสิทธิ์แอดมินเต็มโปรเจกต์ Firebase ของคุณ

3. เปิด **Authentication → Sign-in method** ใน Firebase Console แล้วเปิดใช้งาน **Email/Password** (ขั้นตอน
   นี้ทำครั้งเดียว ทำผ่าน Console เท่านั้น ไม่มี API ให้ทำอัตโนมัติ)

4. รัน seed ข้อมูลตัวอย่าง:

   ```bash
   npm run seed
   ```

5. สร้างบัญชี **admin** ของคุณเอง (อีเมล/รหัสผ่านกำหนดเองผ่าน environment variable — ไม่ hardcode/commit):

   ```bash
   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=yourpassword ADMIN_NAME="Your Name" npm run setup-users
   ```

   บัญชี `ward_staff`/`home_visit_team` ไม่ต้องใช้สคริปต์นี้ — สมัครเองได้ที่หน้า `register.html`

6. รันเว็บในเครื่อง:

   ```bash
   powershell -File server.ps1   # http://localhost:8080
   ```

7. Deploy ขึ้น Firebase Hosting พร้อม Security Rules **ในรอบเดียว** (ห้าม deploy hosting โดยไม่มี rules):

   ```bash
   firebase deploy --only hosting,firestore:rules
   ```

## ขอบเขตงาน

ดู [SCOPE.md](SCOPE.md) — บอกว่าเลือกทำ entity ไหนของระบบ ทำไมถึงเลือก และอะไรอยู่/ไม่อยู่ในขอบเขต

## บริบท / ที่มาของข้อมูล

ดู [ENTITY_CONTEXT.md](ENTITY_CONTEXT.md) — อธิบายว่า entity นี้มาจากไหน โครงสร้างเดิมก่อนแปลงเป็น
Firestore เป็นอย่างไร และทำไมข้อมูลตัวอย่างถึงออกแบบมาแบบนี้ (เผื่อผู้ตรวจงานไม่คุ้นเคยกับระบบต้นทาง)

## สิทธิ์การเข้าถึง (ACL)

ดู [ACL.md](ACL.md) — ตารางสิทธิ์เต็มของทั้ง 3 บทบาท พร้อมอ้างอิงว่าแต่ละกฎบังคับใช้ที่จุดไหนในโค้ดจริง

## หลักฐานว่ารันจริงบน Firebase (Firestore + Auth + ACL)

ดู [EVIDENCE.md](EVIDENCE.md) — ภาพหน้าจอยืนยันว่า seed/CRUD/login ทำงานกับ Firebase จริง รวมถึงภาพทดสอบ
`permission-denied` เมื่อไม่ได้ล็อกอินหรือพยายามเข้าถึงเคสของคนอื่น
