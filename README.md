ผู้จัดทำ : กัลยาณี หนูตะเภา

# Chira Continuity Care (Triple C)

ระบบดูแลต่อเนื่อง (continuity of care) สำหรับทีมเยี่ยมบ้าน/ติดตามอาการของโรงพยาบาล ใช้ทดแทนระบบ
"Thai COC" ระดับประเทศที่ยกเลิกไปแล้ว โฟลว์หลักคือ รับเคส → AI สรุปข้อมูล (ร่างเท่านั้น) →
พยาบาลตรวจสอบ/ยืนยันแผนดูแล → สร้างกำหนดการติดตามอัตโนมัติ → เยี่ยมบ้าน/โทรติดตาม → บันทึกผล →
AI วิเคราะห์ความเสี่ยง → **พยาบาลยืนยันการตัดสินใจเสมอ 100%** → สร้างกำหนดการถัดไปหรือปิดเคส

> **สำหรับอาจารย์ผู้ตรวจการบ้าน Firestore:** งานส่งบทเรียนอยู่ในโฟลเดอร์ [`firestore-lesson/`](firestore-lesson/)
> เปิดโฟลเดอร์นั้นแล้วเริ่มอ่านจาก [`firestore-lesson/README.md`](firestore-lesson/README.md) ได้เลยค่ะ

## บทบาทผู้ใช้งาน (Roles)

ผู้ใช้ทุกคนคือ record เดียวกันใน `users` (คอลัมน์ `role`, ค่าเริ่มต้นตอนสมัครคือ `ward_staff`) แบ่งเป็น 3
บทบาทตาม `App\Models\User::ROLES`:

| Role (ค่าในคอลัมน์) | ชื่อเรียก | หน้าที่หลักในระบบ | สิทธิ์เข้าถึงที่บังคับด้วยโค้ด |
|---|---|---|---|
| `ward_staff` (ค่าเริ่มต้น) | พยาบาล/เจ้าหน้าที่หอผู้ป่วย | **รับเคส (intake)** — กรอกข้อมูลผู้ป่วย สร้าง `Referral` ใหม่ (`referrals.store`), แนบไฟล์ประกอบ, กดให้ AI สรุปข้อมูลเบื้องต้น | เข้าหน้า `referrals.*` และ `follow-up-plans.*` ได้เหมือนผู้ใช้ทุกคนที่ login แล้ว (ระบบยังไม่ล็อกฟีเจอร์เหล่านี้ตาม role ในโค้ดปัจจุบัน — ดูหมายเหตุด้านล่าง) |
| `home_visit_team` | ทีมเยี่ยมบ้าน (พยาบาลติดตาม) | **ตรวจสอบ/ยืนยันแผนดูแล** จากร่างของ AI (`referrals.care-plan.confirm`), ขอคู่มือเยี่ยมบ้านจาก AI, บันทึกผลเยี่ยม/โทรติดตาม (`follow-up-plans.record.store`), ให้ AI วิเคราะห์ความเสี่ยง แล้ว **ยืนยันการตัดสินใจเอง 100%** (`follow-up-plans.decision`) | เช่นเดียวกับข้างต้น |
| `admin` | แอดมิน/หัวหน้าแผนก | ตั้งค่าประเภทเคสและเกณฑ์จำนวนครั้งเยี่ยม (`admin.case-types.*`), จัดการบัญชีผู้ใช้และกำหนด role (`admin.users.*`) | **บังคับด้วย middleware จริง** — เฉพาะ `role:admin` เท่านั้นที่เข้า `/admin/*` ได้ (`App\Http\Middleware\EnsureUserHasRole`) |

> **หมายเหตุสำคัญ:** ตอนนี้มีแค่กลุ่ม `/admin/*` เท่านั้นที่บังคับสิทธิ์ตาม role ในโค้ดจริง
> (`routes/web.php` ใช้ `->middleware('role:admin')`) ส่วนขั้นตอนอย่าง "ยืนยันแผนดูแล" หรือ
> "ยืนยันการตัดสินใจ" ที่ควรทำโดยพยาบาล/ทีมเยี่ยมบ้านเท่านั้น เป็นการออกแบบเชิงกระบวนการทำงาน
> (business flow) ที่ยังไม่ได้ล็อกด้วยสิทธิ์ระดับ route/controller — ทุก role ที่ login แล้วเรียกได้ในทางเทคนิค

## User flow แบบละเอียด (วงจรชีวิตของหนึ่งเคส)

แต่ละเคส (`Referral`) ไหลผ่านสถานะ (`status`) 4 ค่าตามลำดับ: `pending_review` → `plan_confirmed` →
`in_progress` → `closed` ขั้นตอนทั้งหมดอ้างอิงจาก route จริงใน [`routes/web.php`](routes/web.php):

1. **รับเคส (Intake)** — `ward_staff` กรอกฟอร์ม `referrals.create` → submit `referrals.store`
   (`ReferralController::store`) ระบบสร้าง/อัปเดต `Patient` พร้อมคำนวณโซนอัตโนมัติผ่าน `ZoneResolver`
   (เทียบกับ `config/catchment.php`) แล้วสร้าง `Referral` สถานะเริ่มต้น `pending_review`
2. **AI สรุปข้อมูล (ร่างเท่านั้น)** — กดปุ่มที่หน้า `referrals.show` เรียก `referrals.ai-summary`
   (`ReferralController::generateAiSummary`) ให้ `AiService::summarizeReferral()` ส่ง prompt ไปยัง
   Ollama แล้วบันทึกผลลง `ai_summary` — **ยังไม่ใช่ค่าที่ระบบเชื่อถือ** เป็นแค่ร่างรอตรวจ
3. **พยาบาลตรวจสอบ/ยืนยันแผนดูแล** — เปิดหน้า `referrals.care-plan` อ่านร่างจาก AI แก้ไขได้ก่อน
   แล้ว submit `referrals.care-plan.confirm` (`ReferralController::confirmCarePlan`) ค่าที่แก้ไข/ยืนยัน
   จะถูกเขียนลง `confirmed_summary` พร้อม `confirmed_by` / `confirmed_at` และเปลี่ยนสถานะเป็น
   `plan_confirmed` — **นี่คือจุดที่กฎ human-in-the-loop บังคับใช้จริง**: ค่าจาก AI จะไม่มีวันไหลเข้า
   สถานะเคสเองโดยไม่ผ่านขั้นตอนนี้
4. **สร้างกำหนดการติดตามอัตโนมัติ** — ทันทีที่ยืนยันแผน `VisitPlanService::generateInitialPlans()`
   จะสร้าง `FollowUpPlan` ตามกติกาของ `VisitRule` ที่ผูกกับ `CaseType`:
   - `fixed_count` (เยี่ยม N ครั้งทุกช่วงเวลาคงที่) → สร้างครบทุกครั้งตั้งแต่ต้น
   - `score_based` (ช่วงเวลาแปรผันตาม PPS Score สำหรับ Palliative Care) → สร้างแค่ครั้งที่ 1
     ก่อน เพราะยังไม่มีคะแนนสำหรับคำนวณครั้งถัดไป
5. **เยี่ยมบ้าน/โทรติดตาม พร้อมคู่มือจาก AI** — ทีมเยี่ยมบ้านเปิด `follow-up-plans.guide` ขอคู่มือจาก
   AI ผ่าน `follow-up-plans.guide.generate` (`AiService::suggestFollowUpGuide()`) ก่อนลงพื้นที่
6. **บันทึกผล** — หลังเยี่ยม/โทร กรอกแบบฟอร์มที่ `follow-up-plans.record.create` แล้ว submit
   `follow-up-plans.record.store` (`FollowUpController::storeRecord`) บันทึก `FollowUpRecord`
   (PPS Score, บันทึกดิบ) และปิดสถานะแผนนี้เป็น `done`
7. **AI วิเคราะห์ความเสี่ยง** — ที่หน้า `follow-up-plans.review` กดให้ AI วิเคราะห์ผ่าน
   `follow-up-plans.analyze` (`AiService::analyzeFollowUpRecord()`) บันทึกลง `ai_analysis` — ยังเป็น
   แค่ข้อเสนอแนะ ไม่ใช่การตัดสินใจ
8. **พยาบาลยืนยันการตัดสินใจเสมอ 100%** — submit `follow-up-plans.decision`
   (`FollowUpController::confirmDecision`) เลือกหนึ่งใน 3 ทาง: **ติดตามซ้ำ** / **ส่งต่อ** / **ปิดเคส**
   บันทึกลง `nurse_decision` พร้อมผู้ยืนยันและเวลา
9. **สร้างกำหนดการถัดไปอัตโนมัติ หรือปิดเคส** — ตาม decision ข้อ 8:
   - ปิดเคส → `VisitPlanService::cancelRemainingPlans()` ยกเลิกทุกแผนที่ยังไม่ถึงกำหนด แล้วตั้ง
     `Referral.status = closed` พร้อม `closed_at`
   - ติดตามซ้ำ/ส่งต่อ → `VisitPlanService::generateNextPlan()` สร้าง `FollowUpPlan` รอบถัดไป (no-op
     ถ้าเป็นเคส `fixed_count` ที่สร้างครบไว้แล้วตั้งแต่ข้อ 4) แล้ววนกลับไปข้อ 5

## โครงสร้างโปรเจกต์

หมายเหตุ: repo นี้ยังไม่ใช่ Laravel application ที่รันได้สมบูรณ์ — เป็นเฉพาะส่วนโค้ดที่เขียนเองของแอป
(migrations, models, controllers, services, views, config) ยังไม่ได้ผ่านการ scaffold ด้วย
`composer create-project laravel/laravel` (ดูขั้นตอนใน [SETUP.md](SETUP.md))

| โฟลเดอร์/ไฟล์ | เนื้อหา |
|---|---|
| [`app/`](app/) | โค้ด Laravel ของแอป — Models, Controllers, Services, Middleware |
| [`config/`](config/) | ไฟล์ config เช่น `catchment.php` (โซนพื้นที่รับผิดชอบ), `ai.php` (Ollama) |
| [`database/`](database/) | Migrations และ Seeders |
| [`resources/`](resources/) | Blade views |
| [`routes/`](routes/) | `web.php` — แผนผัง route ทั้งหมด |
| [`docs/`](docs/) | เอกสารออกแบบระดับเทคนิค: `architecture/`, `api/`, `database/`, `design/`, `testing/` |
| [`prototypes/`](prototypes/) | ต้นแบบ HTML/CSS/JS แบบคลิกได้สำหรับรีวิวกับผู้ใช้งาน (คนละส่วนกับแอปจริง) |
| [`firestore-lesson/`](firestore-lesson/) | **งานส่งบทเรียน Firestore** — แปลง entity `Referral` ของ Triple C เป็น Firestore collections พร้อม seed script, หน้าเว็บสาธิตที่อ่านข้อมูลจาก Firestore จริง, และหลักฐานการรัน |
| [`DESIGN.md`](DESIGN.md) | ระบบดีไซน์ (สี/ตัวอักษร/สเปซซิ่ง) ที่ทุกหน้าจอต้องยึดตาม |
| [`SETUP.md`](SETUP.md) | ขั้นตอน scaffold โปรเจกต์ Laravel และตั้งค่าเริ่มต้น |
| [`CLAUDE.md`](CLAUDE.md) | คู่มือสำหรับ AI assistant ที่ทำงานในโปรเจกต์นี้ — สรุปสถาปัตยกรรม กฎ human-in-the-loop และ conventions |

## งานส่งบทเรียน Firestore (`firestore-lesson/`)

โฟลเดอร์นี้แยกอิสระจากแอป Laravel หลัก ใช้สาธิตการออกแบบและใช้งาน Firestore จริงตาม entity
`Referral` ของ Triple C ประกอบด้วย:

- [`README.md`](firestore-lesson/README.md) — วิธีรัน seed script และภาพรวม collections
- [`SCOPE.md`](firestore-lesson/SCOPE.md) — ขอบเขตงานที่เลือกทำ
- [`ENTITY_CONTEXT.md`](firestore-lesson/ENTITY_CONTEXT.md) — ที่มาของ entity และโครงสร้างข้อมูล
- [`EVIDENCE.md`](firestore-lesson/EVIDENCE.md) — ภาพหลักฐานว่ารัน Firestore จริง รวมถึงพิสูจน์ว่าเว็บ
  หน้า `referral-detail.html` อ่านข้อมูลจาก Firestore จริง (แก้ข้อมูลใน Console แล้วกด F5 เห็นผลทันที)
- `index.html` / `referral-detail.html` — หน้าเว็บสาธิตต่อกับ Firestore project `triplec-a5e75`
- `seed.js` — สคริปต์ seed ข้อมูลตัวอย่างเข้า Firestore ผ่าน `firebase-admin`
