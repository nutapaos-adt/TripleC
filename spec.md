# Spec — Chira Continuity Care (Triple C)

ใบสั่งงานสรุปภาพรวมของระบบจริง (Laravel + MySQL) ที่ deploy อยู่ที่ `triplec.chiraprawat.com` — เขียนไว้ให้
ผู้ช่วย/agent ตัวใหม่ใช้ปูพื้นความเข้าใจก่อนเริ่มงาน แทนที่จะต้องไล่อ่านโค้ดทั้งหมดเอง ดู [CLAUDE.md](CLAUDE.md)
สำหรับรายละเอียดเชิงลึกกว่านี้ และ [DESIGN.md](DESIGN.md) สำหรับ design tokens/component pattern

**หมายเหตุ:** โฟลเดอร์ `firestore-lesson/` เป็นการบ้านคอร์สแยกต่างหาก ไม่เกี่ยวกับระบบนี้ (ดู spec.md ของ
ตัวเองในโฟลเดอร์นั้น)

## 1. หน้าจอ (Screens / Routes)

| Route | Controller@method | หน้าที่ | เข้าถึงได้โดย |
|---|---|---|---|
| `GET /` | — | redirect ไป `/dashboard` (ถ้าล็อกอิน) หรือ `/login` | ทุกคน |
| `GET /login`, `/register` | Breeze (`Auth\*`) | ล็อกอิน/สมัครสมาชิก (role เริ่มต้นเสมอคือ `ward_staff`, เลือกหน่วยงานจากตาราง `wards` ตอนสมัคร) | ทุกคน (ยังไม่ล็อกอิน) |
| `GET /dashboard` | `DashboardController@index` | แดชบอร์ดหลัก — เนื้อหาต่างกันตาม role (ward_staff เห็นภาพรวมหอตัวเอง, home_visit_team/admin เห็น KPI งานเยี่ยมบ้านทั้งหมด) | ทุก role (login) |
| `GET/POST /referrals`, `/referrals/create` | `ReferralController@index/create/store` | รายการเคส + ฟอร์มรับเคสใหม่ (ข้อมูลผู้ป่วย, ประวัติ, severity group, PPS score ถ้าเป็น Palliative) | ทุก role |
| `GET /referrals/{id}`, `edit`, `update` | `ReferralController@show/edit/update` | รายละเอียดเคส 1 ใบ (แก้ไขได้เฉพาะตอนยัง `pending_review`) | ทุก role |
| `GET /referrals/{id}/attachments/{a}` | `ReferralController@downloadAttachment` | ดาวน์โหลดเอกสารแนบ (private disk) | ทุก role |
| `POST /referrals/{id}/ai-summary` | `ReferralController@generateAiSummary` | สั่ง AI สรุปข้อมูลเคส (เขียนแค่ `ai_summary`) | ทุก role |
| `GET/POST /referrals/{id}/care-plan`, `/print` | `ReferralController@showCarePlan/confirmCarePlan/printCarePlan` | ตรวจ/แก้ไข/ยืนยันร่างจาก AI → `confirmed_summary` + สร้างกำหนดการติดตามชุดแรก | ทุก role (ยืนยันจริงต้องเป็นคนตรวจ) |
| `GET /care-plan/pending` | `CarePlanController@pending` | รายการเคสที่ยืนยันแผนแล้วรอ... (รอ AI ประมวลผล/รอตรวจ) | `home_visit_team`, `admin` |
| `GET /follow-up-plans` | `FollowUpController@index` | รายการกำหนดการติดตามทั้งหมด (กรองตามวันที่/สถานะ) | ทุก role |
| `GET/POST /follow-up-plans/{plan}/record` | `FollowUpController@createRecord/storeRecord` | ฟอร์มบันทึกผลเยี่ยมบ้าน/โทรติดตาม (vitals, ADL, TKA/UKA section ถ้ามีแท็กในประวัติผ่าตัด, รูปถ่าย) | ทุก role |
| `GET /follow-up-plans/{plan}/review` | `FollowUpController@review` | ตรวจร่างวิเคราะห์ AI + กล่องตัดสินใจของพยาบาล (repeat/refer/close) | ทุก role |
| `POST /follow-up-plans/{plan}/analyze` | `FollowUpController@analyzeRecord` | สั่ง AI วิเคราะห์ความเสี่ยงจากผลบันทึก | ทุก role |
| `POST /follow-up-plans/{plan}/decision` | `FollowUpController@confirmDecision` | ยืนยันการตัดสินใจ → สร้างกำหนดการถัดไปอัตโนมัติ หรือปิดเคส | ทุก role |
| `GET /ward/visit-results` | `WardController@visitResults` | เคสที่มีผลเยี่ยมอย่างน้อย 1 ครั้ง — `ward_staff` เห็นเฉพาะหอตัวเอง (กรองด้วย `ward_id`), role อื่นเห็นทุกหอ | ทุก role |
| `GET /reports/visit-summary` | `VisitSummaryController@show` | สรุปงานเยี่ยมบ้านตามช่วงเวลา (เดือน/ไตรมาส/ปี) | ทุก role |
| `GET /reports/monthly`, `POST/DELETE …/photos` | `MonthlyReportController@show/storePhoto/…` | รายงานประจำเดือนแบบเต็ม (KPI, ตาราง timeliness, เทรนด์ความพึงพอใจ, DM/COPD AI scan, อัลบั้มภาพ, case log) | ทุก role |
| `GET/POST /satisfaction-surveys*` | `SatisfactionSurveyController` | สร้าง/ดูแบบประเมินความพึงพอใจ (โหมด staff กรอกแทน หรือโหมด self ผ่าน token) | `home_visit_team`, `admin` |
| `GET/POST /s/{token}` | `SatisfactionSurveyController@showByToken/submitByToken` | หน้าตอบแบบประเมินด้วยตนเอง (สแกน QR) — **ไม่ต้อง login**, ใช้ opaque token แทนการส่ง HN ผ่าน URL | ผู้ป่วย/ญาติ (ไม่ล็อกอิน) |
| `GET/POST /admin/case-types*` | `Admin\CaseTypeController` | จัดการประเภทเคส + กติกาการนัด (VisitRule: fixed_count/score_based) | `admin` เท่านั้น |
| `GET/PUT /admin/users*` | `Admin\UserController` | จัดการผู้ใช้/role/ward | `admin` เท่านั้น |
| `GET/PATCH/DELETE /profile` | `ProfileController` | แก้ไขโปรไฟล์ตัวเอง (Breeze default) | ทุก role |

## 2. โครงสร้างข้อมูล (Database — MySQL, 19 ตาราง)

| ตาราง / Model | ฟิลด์สำคัญ | ความสัมพันธ์ |
|---|---|---|
| `users` | `role` (`ward_staff`\|`home_visit_team`\|`admin`, default `ward_staff`), `department` (free text เดิม), `ward_id` (FK, nullable) | `belongsTo Ward` |
| `wards` | `name`, `is_active` — seed 7 หน่วยงาน (หอผู้ป่วยอายุรกรรม/ชาย/หนัก/หญิง, ห้องตรวจ OPD, ห้องฉุกเฉิน, แผนกส่งเสริมสุขภาพฯ) | `hasMany User`, `hasMany Referral` |
| `patients` | `hn`, `national_id`, `name`, `dob`, `phone`, `address`/`sub_district`/`district`/`province`, `zone` (`in_area`\|`out_area`, resolve จาก `config/catchment.php` ผ่าน `ZoneResolver`) | `hasMany Referral` |
| `case_types` | `name`, `slug`, `description`, `is_active` — 8 ค่า seed (อายุรกรรม, ศัลยกรรม, กระดูกและข้อ, กุมารเวชกรรม, Palliative Care, หลังคลอด, หลังผ่าตัด, อื่นๆ) | `hasMany VisitRule`, `hasMany Referral` |
| `visit_rules` | `case_type_id`, `rule_type` (`fixed_count`\|`score_based`), `fixed_visit_count`, `fixed_interval_days`, `score_rules` (JSON: `[{min,max,interval_days,label}]` — ใช้กับ PPS Score), `is_active` | หนึ่ง case_type มีได้หลาย rule แต่ active ได้ทีละ 1 |
| `referrals` | ดูรายละเอียดครบใน `Referral.php` — ฟิลด์หลัก: `patient_id`, `case_type_id`, `ward_id`, `created_by`, `source_type`/`source_detail`, `raw_notes`, ข้อมูล intake (`caregiver_*`, `patient_status`, `military_unit`, `coverage_type`, `diagnosis`, `underlying_disease`, `surgery_history`, `equipment[]`, `clinical_tracers[]`, `admit_date`/`discharge_date`/`opd_followup_date`, `attending_physician`), `severity_group` (`green`\|`yellow`\|`red`\|`palliative`), `initial_pps_score`, **AI/ยืนยัน**: `ai_summary`/`ai_summary_generated_at` (ร่าง) vs `confirmed_summary`/`confirmed_by`/`confirmed_at` (ค่าจริง), `zone`, `status` (`pending_review`→`plan_confirmed`→`in_progress`→`closed`), `closed_at` | `belongsTo Patient/CaseType/Ward/User(creator)`, `hasMany FollowUpPlan/ReferralAttachment` |
| `follow_up_plans` | `referral_id`, `plan_number`, `method` (`home_visit`\|`phone_call`), `due_date`, `status` (`scheduled`\|`done`\|`overdue`\|`cancelled`) | `belongsTo Referral`, `hasOne FollowUpRecord` |
| `follow_up_records` | `follow_up_plan_id`, `performed_by`, `visited_at`, `pps_score`, `raw_notes`, `general_appearance`, `vital_signs`(JSON: bp/pr/rr/temp/spo2), `weight_kg`/`height_cm`, `tka_assessment`(JSON — เฉพาะเคสที่มีแท็ก TKA/UKA ในประวัติผ่าตัด), `adl_scores`(JSON array 4 ข้อ), `photo_paths`(JSON), **AI/ยืนยัน**: `ai_analysis`/`ai_analysis_generated_at` (ร่าง) vs `risk_flag`/`nurse_decision`(`repeat`\|`refer`\|`close`)/`decision_notes`/`confirmed_by`/`confirmed_at` (ค่าจริง), `next_follow_up_plan_id` (self-link ไปแผนถัดไปที่สร้างจากการตัดสินใจนี้) | `belongsTo FollowUpPlan` |
| `referral_attachments` | `referral_id`, `uploaded_by`, `original_name`, `file_path`(private disk เท่านั้น), `mime_type`, `size` | `belongsTo Referral` |
| `satisfaction_surveys` | `referral_id`, `follow_up_plan_id`(nullable), `token`(uuid, unique — ใช้แทนการส่ง HN ผ่าน query string), `mode`(`staff`\|`self`), demographic 6 ข้อ, `q1..q11`(มีแค่ q1-q10 ใน UI จริง), `suggestion`, `submitted_by`/`submitted_at` | `belongsTo Referral/FollowUpPlan` |
| `monthly_report_photos` | `report_month`(date), `caption`, `file_path`, `uploaded_by` | `belongsTo User(uploader)` |
| ที่เหลือ (Laravel core) | `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` | มาตรฐาน Laravel |

**Config สำคัญ:** `config/catchment.php` (`in_area_sub_districts[]` — ถ้าว่าง ระบบให้เลือกเขตเอง),
`config/ai.php` (`OLLAMA_URL`/`OLLAMA_MODEL`/`OLLAMA_TIMEOUT` — URL ต้องเป็น intranet เท่านั้น),
`config/medical_glossary.php` (อภิธานศัพท์การแพทย์ ~200 คำ ใช้แนบใน prompt ของ AI เท่านั้น)

## 3. บทบาทผู้ใช้ (Roles)

3 role เก็บใน `users.role` (ตรวจสอบผ่าน middleware `role:...` — ดู `EnsureUserHasRole`):

- **`ward_staff`** (ค่าเริ่มต้นตอนสมัคร, ผูกกับ `ward_id`) — รับเคส/ส่งข้อมูลเยี่ยมบ้าน, แก้ไขเคสตัวเองได้ตอนยัง
  `pending_review`, ดูผลเยี่ยมของหอตัวเองที่ `/ward/visit-results` (กรองด้วย `ward_id` — **ถ้าบัญชีไม่มี `ward_id`
  ผูกไว้ เคสที่สร้างจะไม่ผูก ward_id ให้อัตโนมัติ ทำให้หน้านี้กรองไม่เจอ** ต้องให้ admin ผูก ward ผ่าน `/admin/users` ก่อน)
- **`home_visit_team`** — เข้าถึงทุกเมนูงานเยี่ยมบ้าน (วิเคราะห์แผน, บันทึก/ตรวจผลเยี่ยม, รายงาน, แบบประเมินความพึงพอใจ)
  เห็นทุกเคสไม่จำกัดหอ — เป็นคนกดยืนยันแผนดูแล/ตัดสินใจ (`confirmed_by`/`nurse_decision`) ตามกฎ human-in-the-loop
- **`admin`** — เข้าถึงทุกอย่างของ `home_visit_team` บวกเมนู `/admin/*` (จัดการประเภทเคส+กติกานัด, จัดการผู้ใช้/role/ward)

**ข้อสังเกตจากข้อมูลจริง (28/9/2026):** production มีผู้ใช้จริง 3 คน — 1 admin (ไม่มี ward), 1
home_visit_team (ward "ผสวป." = แผนกส่งเสริมสุขภาพและเวชกรรมป้องกัน), และ 1 บัญชี `ward_staff` จริงตัวแรก
("ตะเภา ลองใช้ระบบ") ซึ่งผูก `ward_id` ไปที่ "หอผู้ป่วยชาย" แล้ว (ตั้งค่าผ่าน `/admin/users/3/edit`) —
เคสเก่า 8 ใบที่สร้างก่อนหน้านี้ (ตอนยังไม่มีบัญชี ward_staff) ยังมี `referrals.ward_id = NULL` อยู่ (ผูกกับ
บัญชี admin/home_visit_team ที่สร้างตอนนั้น ไม่ใช่บั๊กโค้ด) แต่เคสใหม่ที่บัญชีนี้สร้างจากนี้ไปจะผูก ward_id
อัตโนมัติและแสดงถูกต้องที่ `/ward/visit-results`

## 4. กฎสำคัญ

1. **Human-in-the-loop 100% (ไม่มีข้อยกเว้น)** — `AiService` ผลิตได้แค่ "ร่าง" (`ai_summary`, `ai_analysis`)
   ไม่เคยเขียนตรงเข้าฟิลด์ที่มีผลต่อสถานะ/กำหนดการ (`confirmed_summary`, `nurse_decision` ฯลฯ) พยาบาลต้อง
   ตรวจ/แก้ไข/กดยืนยันเองเสมอ (ดู `docs/design/AI_DRAFT_NURSE_CONFIRM_DESIGN.md`)
2. **กติกาการนัดติดตาม** (`VisitPlanService`) — `fixed_count`: สร้างครบทุกครั้งตั้งแต่แรก; `score_based`
   (ใช้กับ Palliative Care ตาม PPS Score): สร้างแค่ครั้งที่ 1 ก่อน ครั้งถัดไปคำนวณจากคะแนนที่ยังไม่มี;
   severity กลุ่ม 3 (แดง) ที่ไม่มี VisitRule เฉพาะ → นัดต่อเนื่องรายเดือนอัตโนมัติ; เพดานวันนัดครั้งแรกตาม
   severity (เขียว≤30วัน/เหลือง≤14วัน/แดง≤5วัน)
3. **AI ต้องอยู่ในเครือข่าย รพ. เท่านั้น** — `OLLAMA_URL` ห้ามชี้ไป public/cloud endpoint เด็ดขาด (ข้อมูลผู้ป่วย/PHI)
   — ปัจจุบันรันบนเครื่องแผนก เชื่อมผ่าน Cloudflare Tunnel (โดเมนแยกต่างหาก ไม่ใช่โดเมนหลักของเว็บ) เพราะ
   production hosting เป็น shared hosting ภายนอก ไม่ได้อยู่ในเครือข่าย รพ. จริง
4. **AI ต้องไม่เดา/หลอน** — พรอมต์ของ `AiService` ห้ามให้โมเดลเดาความหมายคำย่อทางการแพทย์ที่ไม่ชัดเจน (ต้อง
   ใช้ `MedicalGlossary` ที่แนบมาเป็นความหมายอ้างอิง หรือบอกว่า "ไม่แน่ใจ") และห้ามคัดลอกข้อความต้นฉบับมาวาง
   ตรงๆ โดยไม่สรุป — เกิดจากบั๊กจริงที่เจอ (เดา "OD" เป็นดวงตาทั้งที่ไม่ได้เขียนไว้)
5. **PHI ห้ามผ่าน URL query string เด็ดขาด** — แบบประเมินความพึงพอใจโหมด self ใช้ opaque `token` (uuid) แทน
   การส่ง HN/ชื่อผ่าน query string
6. **เอกสารแนบเป็น private disk เท่านั้น** — ดาวน์โหลดต้องผ่าน `ReferralController::downloadAttachment` เท่านั้น
   ไม่มี URL สาธารณะตรงถึงไฟล์
7. **Badge/สถานะต้องมีทั้งสีและข้อความเสมอ** — ห้ามสื่อความหมายด้วยสีอย่างเดียว (DESIGN.md §3.2)

## 5. Deployment (บริบทที่ต่างจากทั่วไป)

- Hosting เป็น shared hosting แบบ **FTP + phpMyAdmin เท่านั้น ไม่มี SSH/command line** — deploy ทำผ่าน
  WinSCP/net2ftp อัปโหลดไฟล์ทีละไฟล์/เป็น zip, และรัน SQL ผ่าน phpMyAdmin โดยตรง (ดู `deploy/DEPLOY.md`)
- `.env` ของ production ต้องแก้ผ่าน FTP text editor ด้วยมือ — ไม่มี `php artisan config:cache` ให้เคลียร์
  ผ่าน CLI ถ้าจำเป็นต้องเคลียร์ config cache ต้องลบ `bootstrap/cache/config.php` ผ่าน FTP
- AI (Ollama) รันอยู่ที่เครื่องแผนกภายใน รพ. เชื่อมต่อกับ hosting ภายนอกผ่าน Cloudflare Tunnel + โดเมนแยก —
  ถ้าเครื่องแผนกปิดหรือไม่ได้ล็อกอิน Windows ฟีเจอร์ AI จะ error ชั่วคราว (ระบบอื่นไม่กระทบ)

## 6. สิ่งที่ยังไม่ได้ทำ / นอกขอบเขตปัจจุบัน (Known gaps, ไม่ใช่บั๊ก)

- **ไม่มีหน้าแอดมินจัดการ `wards`** — เพิ่ม/แก้ชื่อหอทำผ่าน phpMyAdmin ตรงๆ เท่านั้น
- **ไม่มีระบบแจ้งเตือน** (อีเมล/LINE/push) เมื่อมีเคสใหม่ ครบกำหนดนัด หรือพบความเสี่ยง — ต้องเข้ามาเช็คในระบบเอง
- **บัญชี `ward_staff` จริงเพิ่งมีตัวแรกและเพิ่งผูก ward** (ดูข้อ 3) — เคสเก่า 8 ใบก่อนหน้านี้ยังผูกกับ
  ward_id ของ admin/home_visit_team เดิม (NULL), ยังไม่มีการ backfill ย้อนหลัง
- **การอัปเดตโครงสร้างฐานข้อมูลในอนาคต** ต้องแปลง migration เป็น SQL เองแล้ว import ผ่าน phpMyAdmin (ไม่มี
  `php artisan migrate` บน production)
- **ไม่มี automated test suite รันบน production** — ตรวจสอบผ่านการทดสอบจริงในเบราว์เซอร์เท่านั้น
- **`docs/` (architecture, API/DB spec, test plan)** ที่มีอยู่บางไฟล์อาจไม่ทันการเปลี่ยนแปลงล่าสุด (เขียนไว้
  ช่วงต้นเดือนกันยายน ก่อนงานแก้ไขจำนวนมากในสัปดาห์หลัง) — ควรตรวจกับโค้ดจริง/spec.md นี้ก่อนเชื่อ 100%
