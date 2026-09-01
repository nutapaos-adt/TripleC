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

## ตารางขอบเขต (9 ข้อ ตามคู่มือ RAISE Module 2)

ตามรูปแบบจาก [คู่มือเลือกขอบเขตงาน](https://cnacha-mfu.github.io/raise2-module2/materials/shared/homework-scope-guide.html)
(ตัวอย่างอ้างอิง LeaveEasy) — คอลัมน์ขวาสุดคือคำตอบของ Triple C (`Referral`):

| | 🔧 ตัวอย่าง (LeaveEasy) | 👤 ของฉัน (Triple C — Referral) |
|---|---|---|
| 📁 โฟลเดอร์หลัก | `leaveRequests` | `referrals` |
| 📁 โฟลเดอร์ประเภท | `leaveTypes` | `caseTypes` |
| 📁 โฟลเดอร์ย่อย | `approvals` | `followUpPlans` (subcollection ใต้ `referrals/{id}` — กำหนดการติดตามที่เกิดจากเคสนั้น) |
| ✏️ ช่องบอกว่าเป็นของใคร | `requesterId` · `requesterName` | `createdBy` (uid อ้างอิง `users`) · `createdByName` (ชื่อ denormalize ไว้แสดงผล) |
| 🔀 สถานะทั้งหมด | รอพิจารณา · อนุมัติ · ไม่อนุมัติ | `pending_review` · `plan_confirmed` · `in_progress` · `closed` |
| 👤 คนที่สร้างรายการ | พนักงาน | เจ้าหน้าที่หอผู้ป่วย/OPD/แผนกภายใน (role `ward_staff`) |
| 👤 คนที่เปลี่ยนสถานะ | หัวหน้า | พยาบาล (role `home_visit_team`, ผ่าน `confirmed_by` / `nurse_decision`) |
| 📝 ช่องข้อความยาวที่ AI จะอ่าน | `reason` | `rawNotes` (บันทึกดิบที่เจ้าหน้าที่พิมพ์ตอนรับเคส) |
| 🤖 งานที่ AI ช่วย (สัปดาห์ที่ 8) | จัดประเภทการลาให้อัตโนมัติ | สรุปข้อมูลเคส/สัญญาณเสี่ยงเป็นร่าง (`aiSummary`) ให้พยาบาลตรวจสอบก่อนยืนยัน |

## อยู่ในขอบเขต

- Collection `referrals` (entity หลักที่ทำ) พร้อมข้อมูลตัวอย่าง 5 รายการ ครอบคลุมสถานะครบ 4 ค่า
- Collection สนับสนุนเท่าที่จำเป็นให้ `referrals` มี reference ใช้งานได้จริง: `patients`, `caseTypes`,
  `users` (ใส่ข้อมูลจำลองแบบย่อ ไม่ครบทุกฟิลด์เท่าระบบต้นทาง เพราะไม่ใช่ entity หลักที่ต้องการสาธิต)

## คำตอบตามเทมเพลตคำถามของโจทย์

โจทย์นี้ถามถึง `Referral` (เคส) ซึ่งเป็น entity หลักของ Triple C (ดู `Referral.php` และ migration
`create_referrals_table`) คำตอบตามรูปแบบประโยคที่โจทย์ให้มาคือ:

> ระบบของฉันเก็บ **เคส (Referral)** ที่ **เจ้าหน้าที่หอผู้ป่วย/OPD/แผนกภายใน/โรงพยาบาลต้นทาง** (role
> `ward_staff`, บันทึกใน `created_by`) สร้างขึ้น แต่ละเคสเลือก **ประเภทเคส (CaseType** — เช่น
> Palliative Care, ผู้ป่วยติดเตียง ฯลฯ ผ่าน `case_type_id`**)** ได้ และมีสถานะ `pending_review`
> (รอตรวจสอบ) → `plan_confirmed` → `in_progress` → `closed` โดย **พยาบาล** (ผ่านการยืนยันแผนดูแล/
> ตัดสินใจ — `confirmed_by`, `nurse_decision`) เป็นคนกดเปลี่ยน

รายละเอียดที่มาของแต่ละช่อง (อ้างอิงโค้ดจริง):

| ช่องในเทมเพลต | ค่าจริงในระบบ | อ้างอิง |
|---|---|---|
| รายการอะไร | Referral (เคส/การส่งต่อ) | `Referral.php` |
| ใครสร้าง | เจ้าหน้าที่ (`created_by` → `users.id`, ปกติ role `ward_staff`) | คอลัมน์ `created_by` ใน `referrals` |
| เลือกประเภทอะไร | CaseType ผ่าน `case_type_id` (nullable) — กำหนด VisitRule การนัดตามมา | `CaseType.php` |
| สถานะเริ่มต้น | `pending_review` (default ของ enum `status`) | migration: `->default('pending_review')` |
| สถานะที่เปลี่ยนไป | `plan_confirmed` → `in_progress` → `closed` | `Referral::STATUS_*` constants |
| ใครกดเปลี่ยน | พยาบาลเท่านั้น — ยืนยัน `confirmed_summary`/`confirmed_by` (→ `plan_confirmed`), และยืนยัน `nurse_decision` ในแต่ละรอบติดตาม (→ `in_progress`/`closed`) ตามกฎ human-in-the-loop 100% | `CLAUDE.md` §"The one rule…", `AI_DRAFT_NURSE_CONFIRM_DESIGN.md` |

## ไม่อยู่ในขอบเขต (ไม่ได้ทำ)

- `VisitRule`, `FollowUpPlan`, `FollowUpRecord`, `ReferralAttachment` — ยังไม่ได้แปลงเป็น Firestore
  collection ในงานชิ้นนี้
- Business logic ฝั่งเซิร์ฟเวอร์ (เช่น การสร้าง `FollowUpPlan` อัตโนมัติจาก `VisitRule`, การเรียก AI
  จริง) — งานนี้มีแค่ข้อมูลตัวอย่างนิ่ง ๆ (static seed data) ไม่มีโค้ด business logic ประกอบ
- Authentication/Authorization จริง (Firebase Auth, security rules ตาม role) — ฐานข้อมูลตอนนี้เปิดแบบ
  test mode เพื่อจุดประสงค์ส่งงานเท่านั้น
- ส่วนหน้าเว็บ (`index.html`, `referral-detail.html`) เป็นแค่ตัวอย่างประกอบการสาธิตว่าเชื่อมต่อ Firebase
  ได้จริง ไม่ใช่ requirement หลักของโจทย์
