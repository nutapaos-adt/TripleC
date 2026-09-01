# Firestore seed — Triple C "Referral" (งานส่งบทเรียน)

โฟลเดอร์นี้แปลง entity **Referral** ของโปรเจกต์ Triple C (ดู `app/Models/Referral.php` และ
`database/migrations` ในต้นฉบับ) ให้เป็น Firestore collections พร้อมข้อมูลตัวอย่าง สำหรับส่งงานบทเรียน

## Collections ที่จะถูกสร้าง

| Collection | ใช้แทน | หมายเหตุ |
|---|---|---|
| `referrals` | เคส (entity หลัก) | 5 ตัวอย่าง ครอบคลุม `status` ครบทั้ง 4 ค่า: `pending_review` → `plan_confirmed` → `in_progress` → `closed` |
| `patients` | ผู้ป่วยที่ `referrals.patientId` อ้างอิง | ข้อมูลจำลอง 5 คน |
| `caseTypes` | ประเภทเคสที่ `referrals.caseTypeId` อ้างอิง | Palliative Care / ผู้ป่วยติดเตียง / COPD ติดตามหลังจำหน่าย |
| `users` | เจ้าหน้าที่/พยาบาลที่ `createdBy` / `confirmedBy` อ้างอิง | 1 ward staff, 1 nurse, 1 admin |

ค่า `status` เริ่มต้นคือ `pending_review` (ยังไม่ผ่านพยาบาล) และเปลี่ยนได้เฉพาะพยาบาลเป็นคนกดยืนยัน —
ตรงกับกฎ human-in-the-loop ของระบบจริง (AI สร้างได้แค่ `aiSummary`/`aiSummary...`, ต้องพยาบาลกดยืนยันก่อน
ค่าจะไหลเข้า `confirmedSummary`/`confirmedBy`/`status`)

## วิธีรัน

1. ติดตั้ง dependency:

   ```bash
   npm install
   ```

2. สร้าง Service Account Key ของโปรเจกต์ Firebase ของคุณเอง:
   - เปิด [Firebase Console](https://console.firebase.google.com/) → เลือกโปรเจกต์ของคุณ
   - Project settings (ไอคอนเฟือง) → **Service accounts** → **Generate new private key**
   - ดาวน์โหลดไฟล์ JSON แล้วเปลี่ยนชื่อเป็น `serviceAccountKey.json` วางไว้ในโฟลเดอร์นี้
   - **ห้าม commit ไฟล์นี้เข้า git** — มีสิทธิ์แอดมินเต็มโปรเจกต์ Firebase ของคุณ

3. รัน seed:

   ```bash
   npm run seed
   ```

4. ตรวจสอบผลลัพธ์ใน Firebase Console → **Firestore Database** → ควรเห็น 4 collections ตามตารางด้านบน
   (collection `referrals` ควรมี 5 documents)

## ขอบเขตงาน

ดู [SCOPE.md](SCOPE.md) — บอกว่าเลือกทำ entity ไหนของระบบ ทำไมถึงเลือก และอะไรอยู่/ไม่อยู่ในขอบเขต

## บริบท / ที่มาของข้อมูล

ดู [ENTITY_CONTEXT.md](ENTITY_CONTEXT.md) — อธิบายว่า entity นี้มาจากไหน โครงสร้างเดิมก่อนแปลงเป็น
Firestore เป็นอย่างไร และทำไมข้อมูลตัวอย่างถึงออกแบบมาแบบนี้ (เผื่อผู้ตรวจงานไม่คุ้นเคยกับระบบต้นทาง)
