# รายงานผลการทดสอบ — Triple C "Referral" (firestore-lesson)

รันจริงด้วย Playwright (`npx playwright test`) กับเว็บ live ที่ `https://triplec-a5e75.web.app` และ Firebase
project จริง (`triplec-a5e75`) ไม่ใช่ mock — ไฟล์ทดสอบอยู่ที่ [`tests/`](tests/)

**ผลล่าสุด (รอบที่ใช้ยืนยันการส่งงาน): ผ่านครบ 5/5 — รันเมื่อ 2026-09-23 04:15:54 UTC (33.9 วินาที)**

| # | ไฟล์ | ทดสอบอะไร | ผล | เวลารัน (duration) |
|---|---|---|---|---|
| 1 | [`tests/01-primary-workflow.spec.js`](tests/01-primary-workflow.spec.js) | เส้นทางหลัก: ward_staff สมัคร/ล็อกอิน → กรอกฟอร์มเพิ่มเคสใหม่ → เช็คว่าเคสโผล่ในรายการของตัวเอง | ✅ PASS | 5.9s |
| 2 | [`tests/02-confirm-plan-status-change.spec.js`](tests/02-confirm-plan-status-change.spec.js) | ปุ่มเปลี่ยนสถานะ: home_visit_team (คนละบัญชีกับผู้สร้าง) กด "ยืนยันแผนดูแล" → เช็คทั้ง UI badge และอ่าน Firestore ตรงๆ ว่า `status` เป็น `plan_confirmed` จริง พร้อม `confirmedBy`/`confirmedAt` | ✅ PASS | 8.6s |
| 3 | [`tests/03-required-field-rejection.spec.js`](tests/03-required-field-rejection.spec.js) | กรอกไม่ครบต้องไม่บันทึก: (ก) ฟอร์มเว้นช่องบังคับ → เบราว์เซอร์บล็อกการ submit (ข) ยิงเขียนตรงข้าม Firestore SDK แบบไม่ผ่านฟอร์มด้วยฟิลด์ว่างเปล่า → ต้องโดน `permission-denied` | ✅ PASS | 5.4s |
| 4 | [`tests/04-unauthenticated-read-denied.spec.js`](tests/04-unauthenticated-read-denied.spec.js) | **ความปลอดภัย**: ไม่ล็อกอินแล้วยิงอ่าน collection `referrals` ตรงๆ ผ่าน Firestore SDK → ต้องได้ `permission-denied` จริง (ไม่ใช่แค่เว็บ redirect ไปหน้า login) | ✅ PASS | 0.7s |
| 5 | [`tests/05-cross-account-isolation.spec.js`](tests/05-cross-account-isolation.spec.js) | **ความปลอดภัย**: ward_staff บัญชี B (สมัครใหม่) ต้องเห็นรายการเคสว่างเปล่า และอ่านเคสของบัญชี A ตรงๆ ด้วย `getDoc()` ต้องโดน `permission-denied` | ✅ PASS | 12.3s |

## ประวัติการทดสอบ (มีข้อไม่ผ่านระหว่างทาง — เปิดเผยตามจริง)

รันชุดทดสอบนี้ทั้งหมด **3 ครั้ง** ก่อนสรุปผลด้านบน:

1. **รอบที่ 1** (2026-09-23 03:46:51 UTC) — ผ่าน 5/5 แต่ข้อ 3 เจอช่องโหว่ระหว่างทดสอบ: การยิงเขียนตรงข้าม
   Firestore SDK (ข้ามฟอร์มไปเลย) ด้วยฟิลด์ว่างเปล่ากลับ **เขียนผ่านได้** เพราะตอนนั้น `firestore.rules`
   ยังไม่ได้บังคับว่าฟิลด์ต้องไม่ว่าง (เช็คแค่ role/owner/status) — เทสต์ตอนนั้นถูกออกแบบให้ "ผ่าน" ในความหมายว่า
   ยืนยันพฤติกรรมปัจจุบัน (บันทึกช่องโหว่ไว้ ไม่ใช่ตัดสินว่าปลอดภัย)
2. **รอบที่ 2** (2026-09-23 03:47:51 UTC) — ผลเหมือนรอบที่ 1 (ยืนยันซ้ำว่าไม่ใช่ความบังเอิญ)
3. **แก้ไข**: สั่งผู้ช่วย `data-firestore` ไปเพิ่มเงื่อนไข `isFilled()` ในกฎ `create` ของ `patients`/`referrals`
   ใน `firestore.rules` (บังคับ `fullName`/`hn`/`rawNotes` ต้องไม่ว่าง + `sourceType`/`zone` ต้องอยู่ใน enum
   ที่กำหนด) แล้ว deploy ขึ้นเว็บจริง
4. **รอบที่ 3** (2026-09-23 04:15:54 UTC, ผลที่ใช้สรุปด้านบน) — แก้เทสต์ข้อ 3 ให้คาดหวัง `permission-denied`
   แทน แล้วรันซ้ำทั้ง 5 ตัว **ผ่านครบ 5/5 ไม่มี regression** ในข้ออื่น

สรุป: **ไม่มีข้อทดสอบใดที่ยัง "ไม่ผ่าน" ค้างอยู่ในปัจจุบัน** — ปัญหาที่เจอระหว่างทางถูกแก้และยืนยันซ้ำแล้ว
(รายละเอียดกฎที่แก้ไปอยู่ใน `ACL.md` แถว "ฟิลด์บังคับต้องไม่ว่าง ตอนสร้างเคสใหม่")

## Environment

- เว็บที่ทดสอบ: `https://triplec-a5e75.web.app` (deploy จริง ไม่ใช่ local)
- Firebase project: `triplec-a5e75` (Auth + Firestore จริง)
- บัญชีทดสอบ: สมัครใหม่ทุกครั้งผ่าน `register.html` ด้วยอีเมลปลอมที่มี timestamp กันชนกัน (เช่น
  `tester-ward-primary-<timestamp>-<rand>@example.com`) รหัสผ่านทดสอบคงที่ `TestPass123!` — ไม่มีการใช้/แชร์
  บัญชีจริงใดๆ
- **หมายเหตุ:** การรันเทสต์ 3 รอบทิ้งข้อมูลตัวอย่างปลอม (~10 referrals/patients, ~14 บัญชี Auth) ไว้ใน
  Firestore จริง เนื่องจากยังไม่มี admin cleanup script (ต้องใช้ `serviceAccountKey.json` ที่ยังไม่ได้สร้าง)
  — บันทึกไว้ใน `BACKLOG.md`
