# Spec — Triple C "Referral" (firestore-lesson)

รวม `SCOPE.md` + โค้ดจริงในโฟลเดอร์นี้ (การบ้านที่ 1–3 / สัปดาห์ 6–8) เป็นใบสั่งงานเดียว สำหรับใช้สั่ง AI
สร้าง/ตรวจระบบในการบ้านที่ 4 ระบบนี้เป็น static site (HTML/CSS/vanilla JS) + Firebase (Auth + Firestore +
Hosting) แยกอิสระจากแอป Laravel หลักที่ root ของ repo — ดู [CLAUDE.md](CLAUDE.md) และ [ENTITY_CONTEXT.md](ENTITY_CONTEXT.md)
สำหรับที่มา

## 1. หน้าจอ (Screens)

| ไฟล์ | หน้าที่ | เข้าถึงได้โดย |
|---|---|---|
| `login.html` | เข้าสู่ระบบด้วยอีเมล/รหัสผ่าน (Firebase Auth) — ล็อกอินอยู่แล้วข้ามไป `index.html` (หรือ `?next=`) อัตโนมัติ | ทุกคน (ยังไม่ล็อกอิน) |
| `register.html` | สมัครสมาชิกเอง เลือกได้เฉพาะ role `ward_staff`/`home_visit_team` (เลือก `admin` ไม่ได้) | ทุกคน (ยังไม่ล็อกอิน) |
| `index.html` | รายการเคสทั้งหมด — `ward_staff` เห็นเฉพาะเคสที่ตัวเองสร้าง, `home_visit_team`/`admin` เห็นทุกเคส มีปุ่ม "+ เพิ่มเคสใหม่" สำหรับ `ward_staff`/`admin` | ทุก role (ต้องล็อกอิน) |
| `referral-create.html` | ฟอร์มเพิ่มเคสใหม่ (ผู้ป่วย + ประเภทเคส + บันทึกดิบ) พร้อมปุ่ม **AI ระดับ 1** "ให้ AI ช่วยแนะนำประเภทเคส" จากบันทึกดิบ | `ward_staff`, `admin` |
| `referral-detail.html` | รายละเอียดเคส 1 ใบ — สถานะ/stepper, บันทึกดิบ, ร่างสรุปจาก AI, ฟอร์มยืนยันแผนดูแล, ปุ่ม **AI ระดับ 2 (agentic)** "ให้ AI ช่วยสรุปเคส", "รอบติดตาม" (ภาพประกอบ ไม่บันทึกจริง — ดูข้อ 4), ปุ่มลบเคส | ทุก role ที่มีสิทธิ์เห็นเคสนั้น (ดู ACL.md) |

ทุกหน้า (ยกเว้น login/register) โหลด nav บนสุดผ่าน `session.js` → `renderNav()` ซึ่งเมนู/ปุ่มจะเปลี่ยนตาม
role จริงของผู้ใช้ที่ล็อกอินอยู่

## 2. โครงสร้างข้อมูล (Firestore collections)

| Collection | Doc ID | ฟิลด์ | หมายเหตุ |
|---|---|---|---|
| `users` | Firebase Auth uid | `name`, `role` (`ward_staff`\|`home_visit_team`\|`admin`), `email` | เอกสารนี้คือ source of truth ของ role (ไม่ใช่ custom claim) |
| `patients` | auto-id | `fullName`, `hn`, `zone` (`in_area`\|`out_area`) | สร้างพร้อมกับ referral ใหม่จากฟอร์ม |
| `caseTypes` | auto-id | `name`, `slug`, `isActive` | อ่านอย่างเดียวจากฝั่งผู้ใช้ทั่วไป เขียนได้เฉพาะ admin |
| `referrals` | auto-id | `patientId`, `caseTypeId`, `sourceType` (`ward`\|`opd`\|`internal_dept`\|`external_hospital`), `sourceDetail`, `createdBy` (uid), `createdByName`, `rawNotes`, `aiSummary` (map: `patientType`, `keyIssues[]`, `riskSignals[]`, `reasoning` — ร่างจาก AI ระดับ 2, null จนกว่าจะกดสร้าง), `aiSummaryGeneratedAt`, `confirmedSummary` (map, null จนกว่าพยาบาลยืนยัน), `confirmedBy`, `confirmedAt`, `zone`, `status` (`pending_review`→`plan_confirmed`→`in_progress`→`closed`), `closedAt`, `createdAt` | entity หลักของงานนี้ |
| `referrals/{id}/aiLogs` | auto-id | `triggeredBy` (uid), `triggeredByName`, `model`, `generatedAt`, `input`, `output` | บันทึกทุกครั้งที่กดปุ่ม AI ระดับ 2 — แก้ไข/ลบไม่ได้ (immutable log) |

**ฟิลด์บังคับตอนสร้าง (บังคับทั้ง 2 ชั้น):** ฟอร์ม `referral-create.html` ใส่ `required` ไว้ที่ชื่อผู้ป่วย, HN,
zone, ประเภทเคส, แหล่งที่มา และบันทึกดิบ — `firestore.rules` บังคับซ้ำฝั่งเซิร์ฟเวอร์ด้วย: `patients` ต้องมี
`fullName`/`hn` เป็น string ที่ไม่ว่าง, `referrals` ต้องมี `rawNotes` เป็น string ที่ไม่ว่าง และ `sourceType`/
`zone` ต้องเป็นค่าใน enum ข้างบนเท่านั้น (เขียนตรงผ่าน SDK ข้าม `required` ของเบราว์เซอร์แล้วยัดค่าว่างไม่ได้)
ยกเว้น `sourceDetail` ที่เป็นฟิลด์ไม่บังคับ — ปล่อยเป็น string ว่างได้

## 3. บทบาทผู้ใช้ (Roles) และสิทธิ์

3 role เก็บใน `users/{uid}.role` (ดูตารางสิทธิ์เต็มใน [ACL.md](ACL.md)):

- **`ward_staff`** (ค่าเริ่มต้นตอนสมัคร) — สร้างเคสใหม่ได้ ดู/ลบได้เฉพาะเคสตัวเอง (ลบได้เฉพาะตอนยัง
  `pending_review`) ห้ามยืนยันแผนดูแลของตัวเอง
- **`home_visit_team`** — ดูได้ทุกเคส ยืนยันแผนดูแล/สั่ง AI สรุปเคสได้ **เฉพาะเคสที่ตัวเองไม่ได้สร้าง**
  (กฎห้ามอนุมัติของตัวเอง บังคับด้วย `firestore.rules` ไม่ใช่แค่ซ่อนปุ่ม) สร้าง/ลบเคสไม่ได้
- **`admin`** — ดู/สร้าง/ลบได้ทุกเคส ยืนยันแผนดูแลของเคสที่ผู้อื่นสร้างได้ (แต่ห้ามอนุมัติเคสที่ตัวเองสร้างเช่นกัน
  ไม่มีข้อยกเว้น) สร้างได้ทางเดียวคือรัน `setup-users.js` ด้วย Admin SDK — สมัครเองผ่านหน้าเว็บไม่ได้

กฎที่บังคับใช้ทั้งฝั่ง UI และ `firestore.rules` (สองชั้นเสมอ): ต้องล็อกอินก่อนอ่าน/เขียนทุกครั้ง,
ward_staff เห็นเฉพาะเคสตัวเอง, ห้ามอนุมัติของตัวเอง (self-approve ban), แก้ `referrals` ได้เฉพาะฟิลด์ที่
กำหนดไว้ตายตัว (`status`, `confirmedSummary`, `confirmedBy`, `confirmedAt`, `closedAt`, `aiSummary`,
`aiSummaryGeneratedAt`), สมัครสมาชิกเองเป็น `admin` ไม่ได้

## 4. กฎสำคัญ: Human-in-the-loop (AI ร่างเท่านั้น)

AI ไม่เคยเขียนผลลงฟิลด์ที่มีผลต่อสถานะเคสโดยตรง:

- **AI ระดับ 1** (`referral-create.html`) แนะนำประเภทเคสจากบันทึกดิบ — เป็นค่าชั่วคราวในฟอร์มเท่านั้น
  ผู้ใช้ต้องกด "ใช้คำแนะนำนี้" เองก่อนจึงจะตั้งค่าลง dropdown แล้วยังต้องกด "บันทึกเคส" เองอีกที ไม่มีการ
  เขียน Firestore อัตโนมัติ
- **AI ระดับ 2** (`referral-detail.html`) อ่านข้อมูลจากหลายที่ (บันทึกดิบ + ผู้ป่วย + ประเภทเคส) แล้วเขียน
  ได้แค่ **ร่าง** ลง `aiSummary`/`aiSummaryGeneratedAt` เท่านั้น — พยาบาลต้องตรวจ/แก้ไข แล้วกด "ยืนยันแผนดูแล"
  เองเสมอ ค่าจึงจะไหลเข้า `confirmedSummary`/`status`

## 5. สิ่งที่ไม่ทำใน Module นี้ (Out of scope)

- **Entity อื่นของระบบต้นทาง** — `VisitRule`, `FollowUpPlan`, `FollowUpRecord`, `ReferralAttachment`
  ยังไม่ได้แปลงเป็น Firestore collection ("รอบติดตาม" ที่เห็นในหน้ารายละเอียดเคสเป็นแค่ UI ประกอบ ปุ่ม
  "พยาบาลยืนยันรอบนี้" เขียนแค่ `status`/`closedAt` ของ referral เท่านั้น ไม่มี document ของรอบนั้นจริง)
- **Business logic ฝั่งเซิร์ฟเวอร์** — ไม่มี Cloud Functions ทั้งหมดเป็น client-side JS ที่เขียน Firestore
  ตรงจากเบราว์เซอร์ (rules เป็นเซิร์ฟเวอร์ฝั่งเดียวที่บังคับใช้จริง)
- **การจัดการผู้ใช้แบบแอดมิน** — ไม่มีหน้าเว็บสำหรับ admin จัดการ/แก้ไข role ผู้ใช้คนอื่น (ทำผ่าน Firestore
  Console หรือสคริปต์เท่านั้น)
- **การอัปโหลดไฟล์แนบ** — ไม่มีฟีเจอร์แนบไฟล์ (ระบบต้นทางมี `ReferralAttachment` แต่ไม่อยู่ในขอบเขตนี้)
- **การแก้ไขข้อมูลเคสหลังสร้าง** (นอกเหนือจากฟิลด์สถานะ/AI ที่ระบุไว้ข้างบน) — ไม่มีฟอร์ม "แก้ไขเคส"
- **การแจ้งเตือน/อีเมล** — ไม่มีระบบแจ้งเตือนเมื่อมีเคสใหม่หรือสถานะเปลี่ยน
- **หน้าแก้ไขโปรไฟล์ผู้ใช้** — สมัครแล้วแก้ `name`/`role` เองไม่ได้ (ต้องให้ admin แก้)
