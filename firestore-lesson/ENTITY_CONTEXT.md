# บริบทของข้อมูล — ทำไมถึงออกแบบ Firestore แบบนี้

เอกสารนี้อธิบายที่มาของโครงสร้างข้อมูลใน `seed.js` สำหรับผู้ตรวจงานที่ไม่คุ้นเคยกับระบบต้นทาง

## 1. ระบบต้นทาง (บริบท ไม่ใช่ส่วนหนึ่งของงานส่ง)

ข้อมูลตัวอย่างในโฟลเดอร์นี้จำลองมาจาก entity หนึ่งของระบบชื่อ **Chira Continuity Care (Triple C)** —
ระบบติดตามการดูแลผู้ป่วยต่อเนื่อง (continuity-of-care) ของทีมเยี่ยมบ้าน/ติดตามผู้ป่วยในโรงพยาบาลแห่งหนึ่ง
(โครงการนี้เดิมพัฒนาด้วย Laravel + ฐานข้อมูลเชิงสัมพันธ์ — เอกสารนี้ไม่ได้แนบโค้ดต้นฉบับมาด้วย เพราะเป็น
คนละงาน/คนละ stack กับแบบฝึกหัด Firestore ฉบับนี้)

ระบบมีกฎสำคัญข้อหนึ่งที่สะท้อนอยู่ในโครงสร้างข้อมูลด้านล่าง คือ **AI สร้างได้แค่ "ร่าง" เท่านั้น
มนุษย์ (พยาบาล) ต้องเป็นคนกดยืนยันเสมอ ก่อนข้อมูลจะกลายเป็นค่าที่ใช้งานจริง** — นี่คือเหตุผลที่ entity
ด้านล่างมีทั้งฟิลด์ "ร่างจาก AI" และ "ค่าที่ยืนยันแล้ว" แยกกันชัดเจน ไม่ใช่ทับซ้อนกันโดยไม่มีเหตุผล

## 2. Entity ที่เลือกมาทำแบบฝึกหัดนี้: `Referral` (เคส)

`Referral` คือ "เคส" หนึ่งเคสที่เกิดขึ้นเมื่อมีการส่งต่อผู้ป่วยเข้าสู่การติดตามที่บ้าน — เป็น entity หลัก
ของระบบต้นทาง จึงเหมาะสมที่สุดที่จะใช้แสดงแนวคิดของ Firestore (collection/document, ฟิลด์แบบ nested
object, reference ข้ามคอลเลกชัน)

### โครงสร้างเดิม (relational, ก่อนแปลงเป็น Firestore)

| คอลัมน์เดิม | ชนิดข้อมูล | ความหมาย |
|---|---|---|
| `patient_id` | foreign key → `patients` | ผู้ป่วยของเคสนี้ |
| `case_type_id` | foreign key → `case_types` (nullable) | ประเภทเคส กำหนดกฎการนัดติดตาม |
| `source_type` | enum: `ward`/`opd`/`internal_dept`/`external_hospital` | เคสถูกส่งมาจากไหน |
| `created_by` | foreign key → `users` | เจ้าหน้าที่ผู้บันทึกเคส |
| `raw_notes` | ข้อความยาว | บันทึกดิบก่อนให้ AI สรุป |
| `ai_summary` | JSON (nullable) | **ร่าง**จาก AI — ยังไม่ผูกกับการตัดสินใจใดๆ |
| `confirmed_summary` | JSON (nullable) | ค่าที่**พยาบาลตรวจสอบ/แก้ไข/ยืนยันแล้ว** |
| `confirmed_by` / `confirmed_at` | foreign key / timestamp | ใครยืนยัน เมื่อไหร่ |
| `zone` | enum: `in_area`/`out_area` | เขตพื้นที่ของผู้ป่วย |
| `status` | enum: `pending_review`/`plan_confirmed`/`in_progress`/`closed` | สถานะเคส (ดูข้อ 3) |

### การแปลงเป็น Firestore (`seed.js`)

| ฟิลด์เดิม (snake_case) | ฟิลด์ใน Firestore (camelCase) | เปลี่ยนแปลงอย่างไร |
|---|---|---|
| `patient_id` | `patientId` | เก็บเป็น document ID ของ collection `patients` (string) แทน foreign key ของ SQL |
| `case_type_id` | `caseTypeId` | เก็บเป็น document ID ของ collection `caseTypes` |
| `ai_summary` (JSON) | `aiSummary` (map) | แปลง JSON column ตรงๆ เป็น nested object ของ Firestore ได้เลย |
| `confirmed_summary` (JSON) | `confirmedSummary` (map, null จนกว่าจะยืนยัน) | เหมือนกัน |
| `created_at`/`updated_at` (SQL timestamp) | `createdAt` (Firestore `Timestamp`) | ใช้ `admin.firestore.Timestamp` แทน SQL timestamp column |

ไม่มีการเพิ่ม/ตัดฟิลด์ทางธุรกิจ — เป็นการแปลง "ภาษา" ของโครงสร้างข้อมูลจาก relational ไป NoSQL เท่านั้น

## 3. ทำไมข้อมูลตัวอย่างมี 5 รายการ ครอบคลุมสถานะครบ 4 ค่า

ข้อมูลตัวอย่าง 5 รายการใน `referrals` ถูกเลือกให้ครอบคลุมสถานะครบทุกค่าโดยตั้งใจ (มี `pending_review`
2 รายการเพื่อให้เห็นว่าเคสใหม่หลายเคสพร้อมกันได้) เพื่อให้ผู้ตรวจเห็นวงจรชีวิตทั้งหมดของเคสในชุดข้อมูล
ตัวอย่างเดียว:

1. `referral_001` — `pending_review`: เพิ่งสร้างเคส มี `aiSummary` แต่ `confirmedSummary` ยังเป็น `null`
2. `referral_002` — `plan_confirmed`: พยาบาลยืนยันแผนดูแลแล้ว (`confirmedBy`/`confirmedAt` มีค่า)
3. `referral_003` — `in_progress`: อยู่ระหว่างรอบติดตาม
4. `referral_004` — `closed`: ปิดเคสแล้ว (`closedAt` มีค่า)
5. `referral_005` — `pending_review`: เคสนอกเขตพื้นที่ (`zone: out_area`) เพิ่งสร้าง ยังไม่ผ่านการยืนยัน

**กฎสำคัญ:** ฟิลด์ `status` เปลี่ยนได้เฉพาะเมื่อพยาบาลกดยืนยันเท่านั้น ไม่มีทางที่ `aiSummary` จะไหลเข้า
`status`/`confirmedSummary` โดยอัตโนมัติ — ตรงกับกฎ human-in-the-loop ที่กล่าวถึงในข้อ 1

## 4. คำตอบตามเทมเพลตคำถามของโจทย์

> ระบบของฉันเก็บ **เคส (Referral)** ที่ **เจ้าหน้าที่หอผู้ป่วย/OPD/แผนกภายใน/โรงพยาบาลต้นทาง** (role
> `ward_staff`, บันทึกใน `created_by`) สร้างขึ้น แต่ละเคสเลือก **ประเภทเคส (CaseType** — เช่น
> Palliative Care, ผู้ป่วยติดเตียง ฯลฯ ผ่าน `case_type_id`**)** ได้ และมีสถานะ `pending_review`
> (รอตรวจสอบ) → `plan_confirmed` → `in_progress` → `closed` โดย **พยาบาล** (ผ่านการยืนยันแผนดูแล/
> ตัดสินใจ — `confirmed_by`, `nurse_decision`) เป็นคนกดเปลี่ยน

รายละเอียดที่มาของแต่ละช่อง (อ้างอิงโค้ดจริงในระบบต้นทาง — ดูข้อ 1 ว่าทำไมโค้ดต้นฉบับไม่ได้แนบมาด้วย):

| ช่องในเทมเพลต | ค่าจริงในระบบ | อ้างอิง |
|---|---|---|
| รายการอะไร | Referral (เคส/การส่งต่อ) | `Referral.php` |
| ใครสร้าง | เจ้าหน้าที่ (`created_by` → `users.id`, ปกติ role `ward_staff`) | คอลัมน์ `created_by` ใน `referrals` |
| เลือกประเภทอะไร | CaseType ผ่าน `case_type_id` (nullable) — กำหนด VisitRule การนัดตามมา | `CaseType.php` |
| สถานะเริ่มต้น | `pending_review` (default ของ enum `status`) | migration: `->default('pending_review')` |
| สถานะที่เปลี่ยนไป | `plan_confirmed` → `in_progress` → `closed` | `Referral::STATUS_*` constants |
| ใครกดเปลี่ยน | พยาบาลเท่านั้น — ยืนยัน `confirmed_summary`/`confirmed_by` (→ `plan_confirmed`), และยืนยัน `nurse_decision` ในแต่ละรอบติดตาม (→ `in_progress`/`closed`) ตามกฎ human-in-the-loop 100% | `CLAUDE.md` §"The one rule…", `AI_DRAFT_NURSE_CONFIRM_DESIGN.md` |
