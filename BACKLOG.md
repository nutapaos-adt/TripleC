# Backlog — ส่งต่อ Module 3

รายการสิ่งที่ยังไม่เสร็จ/ยังไม่ทำในงาน Module 2 (`firestore-lesson/`) เก็บไว้ให้ Module 3 ต่อยอด

## คีย์ AI — ย้ายไปไว้ฝั่งที่ผู้ใช้แตะไม่ได้

ตอนนี้คีย์ OpenRouter อยู่ใน `firestore-lesson/js/ai-config.js` ที่ **ไม่ commit เข้า git** แล้ว (กันคนอื่นเห็น
ใน repo ได้) แต่ตัวเว็บเองยังเป็น static site ล้วนๆ ไม่มีเซิร์ฟเวอร์อยู่ตรงกลาง — คีย์จึงยังถูกส่งลงเบราว์เซอร์
ของผู้ใช้ทุกคนที่เปิดเว็บ (เปิด devtools → Network/Sources ก็เห็นคีย์ได้) นี่คือข้อจำกัดของสถาปัตยกรรม client-only
ของ Module 2 (ไม่มี Cloud Functions อยู่ในขอบเขต — ดู `firestore-lesson/spec.md` §5) ไม่ใช่บั๊ก

**สำหรับ Module 3:** ย้ายการเรียก AI ไปอยู่หลัง Cloud Function/เซิร์ฟเวอร์ตัวกลาง แล้วให้ client เรียก endpoint
ของตัวเองแทนที่จะเรียก OpenRouter ตรง คีย์จะได้ไม่อยู่ในโค้ดฝั่งเบราว์เซอร์เลย

## สิ่งที่เทสต์จับได้แต่ยังไม่ได้แก้

**ไม่มีรายการค้าง** ณ ตอนส่งงานนี้ — ระหว่างทดสอบ (การบ้านที่ 4 ส่วน B) เจอ 1 จุดจริง: กฎ `create` ของ
`referrals`/`patients` ใน `firestore.rules` ไม่เคยบังคับว่าฟิลด์บังคับ (`rawNotes`, `fullName`, `hn`) ต้องไม่
ว่างเปล่าในระดับเซิร์ฟเวอร์ (บังคับแค่ฝั่งฟอร์มด้วย `required`) ทำให้เขียนข้อมูลว่างเปล่าผ่าน Firestore SDK
ตรงๆ ได้ — **แก้แล้วและยืนยันด้วยการรันเทสต์ซ้ำจนผ่านครบ** รายละเอียดเต็มอยู่ใน
[`firestore-lesson/test-results.md`](firestore-lesson/test-results.md)

## ฟีเจอร์จาก Module 1 ที่ยังไม่ได้ทำ

- `VisitRule`, `FollowUpPlan`, `FollowUpRecord`, `ReferralAttachment` ยังไม่ได้แปลงเป็น Firestore
  collection — "รอบติดตาม" ในหน้ารายละเอียดเคสยังเป็นแค่ UI ประกอบ ไม่มี document จริงรองรับ
- ไม่มี Cloud Functions / business logic ฝั่งเซิร์ฟเวอร์ — ทุกอย่างเป็น client-side JS ที่เขียน Firestore
  ตรง โดยมี `firestore.rules` เป็นเซิร์ฟเวอร์ฝั่งเดียวที่บังคับใช้จริง
- ไม่มีหน้าเว็บจัดการผู้ใช้สำหรับ admin, ไม่มีการอัปโหลดไฟล์แนบ, ไม่มีฟอร์มแก้ไขเคสย้อนหลัง, ไม่มีระบบแจ้งเตือน,
  ไม่มีหน้าแก้ไขโปรไฟล์ผู้ใช้ (ดู `firestore-lesson/spec.md` §5 สำหรับรายการเต็ม)

## รายการเสริมอื่นๆ (ไม่กระทบเกณฑ์ผ่าน Module 2 — เป็นทางเลือกเสริมความรัดกุม)

- **ล็อกชุดฟิลด์ตอนสร้างเคส** — กฎ `create` ของ `referrals`/`patients` ยังไม่จำกัดชุดคีย์ที่เขียนได้แบบ
  `hasOnly([...])` เหมือนกฎ `create` ของ `users` (ตอนนี้บังคับแค่ว่าฟิลด์บังคับต้องไม่ว่าง ไม่ได้ห้ามฟิลด์แถม)
- **`referral-create.js` ลืมใส่ `aiSummaryGeneratedAt: null`** ตอนสร้างเคสใหม่ (มีแค่ `aiSummary: null`)
  ไม่กระทบการทำงาน (โค้ดอ่านทนต่อ `undefined`) แต่ควรเติมให้ครบตามสคีมาใน `spec.md` §2
- **ยังไม่ได้รัน `node seed.js` ซ้ำ** หลังแก้ 4 จุด (users มี `department` เกิน/ขาด `email`, `referrals`
  ขาด `createdByName`, ฟิลด์วันที่เป็น string ไม่ใช่ Timestamp, มี `createdAt` เกินใน 3 collections) —
  `seed.js` ไฟล์แก้ถูกต้องแล้ว แต่ข้อมูลตัวอย่างที่อยู่บน Firestore จริงตอนนี้ยังเป็นเวอร์ชันเก่า ต้องมี
  `serviceAccountKey.json` (ดาวน์โหลดจาก Firebase Console เอง) ก่อนจึงจะรันได้
- **ข้อมูลทดสอบหลงเหลือใน Firestore จริง** จากการรัน Playwright 3 รอบ (~10 referrals/patients ปลอม, ~14
  บัญชี Auth ปลอม) — ไม่มี admin cleanup script ในตอนนี้ (ต้องใช้ `serviceAccountKey.json` เช่นกัน)
