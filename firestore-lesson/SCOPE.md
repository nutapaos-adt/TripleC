# ขอบเขตงาน (Scope)

## ส่วนที่เลือกทำ: `Referral` (เคส)

ระบบต้นทาง (Chira Continuity Care / Triple C) มี entity หลักอยู่ 8 ตัว: `User`, `Patient`, `CaseType`,
`VisitRule`, `Referral`, `FollowUpPlan`, `FollowUpRecord`, `ReferralAttachment` — งานชิ้นนี้เลือกทำ
เฉพาะ **`Referral`** เป็นตัวอย่างหลักในการออกแบบ Firestore เพียง entity เดียว ไม่ได้ทำครบทั้งระบบ

## ทำไมถึงเลือก `Referral`

- เป็น entity ที่เป็น "แกนกลาง" ของระบบ — ทุก entity อื่นเชื่อมโยงเข้ามาที่นี่ (`Patient`, `CaseType`
  ถูกอ้างอิงจาก `Referral`; `FollowUpPlan`/`FollowUpRecord` ก็แตกยอดออกมาจากเคสหนึ่งใบ)
- มีฟิลด์ที่ครบตัวอย่างของ Firestore ให้เห็นชัด: reference ข้ามคอลเลกชัน (`patientId`, `caseTypeId`,
  `createdBy`), nested object/map (`aiSummary`, `confirmedSummary`), enum-like status field ที่เปลี่ยน
  ตามลำดับ (`status`)
- สะท้อนกฎสำคัญของระบบต้นทาง (human-in-the-loop — ดู [ENTITY_CONTEXT.md](ENTITY_CONTEXT.md) ข้อ 1)
  ผ่านโครงสร้างข้อมูลได้ตรงที่สุด เพราะมีทั้งฟิลด์ "ร่างจาก AI" และ "ค่าที่พยาบาลยืนยันแล้ว" แยกกันชัดเจน

## อยู่ในขอบเขต

- Collection `referrals` (entity หลักที่ทำ) พร้อมข้อมูลตัวอย่าง 5 รายการ ครอบคลุมสถานะครบ 4 ค่า
- Collection สนับสนุนเท่าที่จำเป็นให้ `referrals` มี reference ใช้งานได้จริง: `patients`, `caseTypes`,
  `users` (ใส่ข้อมูลจำลองแบบย่อ ไม่ครบทุกฟิลด์เท่าระบบต้นทาง เพราะไม่ใช่ entity หลักที่ต้องการสาธิต)

## ไม่อยู่ในขอบเขต (ไม่ได้ทำ)

- `VisitRule`, `FollowUpPlan`, `FollowUpRecord`, `ReferralAttachment` — ยังไม่ได้แปลงเป็น Firestore
  collection ในงานชิ้นนี้
- Business logic ฝั่งเซิร์ฟเวอร์ (เช่น การสร้าง `FollowUpPlan` อัตโนมัติจาก `VisitRule`, การเรียก AI
  จริง) — งานนี้มีแค่ข้อมูลตัวอย่างนิ่ง ๆ (static seed data) ไม่มีโค้ด business logic ประกอบ
- Authentication/Authorization จริง (Firebase Auth, security rules ตาม role) — ฐานข้อมูลตอนนี้เปิดแบบ
  test mode เพื่อจุดประสงค์ส่งงานเท่านั้น
- ส่วนหน้าเว็บ (`index.html`, `referral-detail.html`) เป็นแค่ตัวอย่างประกอบการสาธิตว่าเชื่อมต่อ Firebase
  ได้จริง ไม่ใช่ requirement หลักของโจทย์
