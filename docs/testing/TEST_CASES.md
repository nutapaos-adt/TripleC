# Test Cases — Chira Continuity Care (Triple C)

เอกสารนี้รวบรวม Test Case ของทุกโมดูล อ้างอิง Acceptance Criteria จาก [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)
ผ่านคอลัมน์ "Related AC" ดูภาพรวมกลยุทธ์/ขอบเขต/environment setup ใน [TEST_PLAN.md](TEST_PLAN.md)

> **หมายเหตุร่วมทุกโมดูล:** ยังไม่มี PHPUnit/Pest หรือ Laravel scaffolding ในรีโพนี้ (ไม่มี `composer.json`/
> `artisan`/`vendor/` — ดู [CLAUDE.md](../../CLAUDE.md)) เคสทดสอบทั้งหมดด้านล่างเขียนในระดับ manual/spec
> สำหรับดำเนินการด้วยมือ หรือใช้เป็นต้นแบบแปลงเป็น Feature/Unit test (`RefreshDatabase`, `Http::fake()` สำหรับ
> mock Ollama, `actingAs()` สำหรับ role/session) เมื่อ scaffold โปรเจกต์เสร็จตาม [SETUP.md](../../SETUP.md)

## สารบัญโมดูล

1. [INTAKE](#intake--referral-intake--zone-resolution) — 28 test cases
2. [SUMMARY](#summary--ai-draft-summary--nurse-care-plan-confirmation) — 20 test cases
3. [SCHED](#sched--visit-scheduling-engine--case-type--visit-rule-admin) — 28 test cases
4. [RECORD](#record--follow-up-guide--outcome-recording) — 20 test cases
5. [DECISION](#decision--ai-risk-analysis--mandatory-nurse-decision) — 17 test cases
6. [ADMINRBAC](#adminrbac--user--role-administration-access-control-matrix) — 12 test cases
7. [DASHNFR](#dashnfr--dashboard-kpis-ai-resilience--design-system-compliance) — 27 test cases

**รวมทั้งหมด: 152 test cases**

---

## INTAKE — Referral Intake & Zone Resolution

| ID | Title (ไทย) | Preconditions | Role | Steps | Test Data | Expected Result | Type | Priority | Related AC |
|---|---|---|---|---|---|---|---|---|---|
| TC-INTAKE-001 | สร้างใบส่งต่อสำเร็จ — แหล่งที่มา ward | ล็อกอินแล้ว, มี `case_type` active อย่างน้อย 1 รายการ | ward_staff | 1. เปิดหน้า `referrals.create` 2. กรอกฟิลด์บังคับครบ 3. เลือก source_type = ward 4. กด submit | source_type=`ward`, patient_hn=`HN00001`, patient_name=`นายทดสอบ หนึ่ง`, zone=`in_area`, raw_notes=`ทดสอบ` | สร้าง Patient+Referral สำเร็จ, `status=pending_review`, `created_by`=ผู้ใช้ปัจจุบัน, redirect ไป `referrals.show` พร้อมข้อความ "สร้างใบส่งต่อเรียบร้อยแล้ว" | Positive | High | AC-INTAKE-01, AC-INTAKE-02 |
| TC-INTAKE-002 | สร้างใบส่งต่อสำเร็จ — แหล่งที่มา opd | เหมือน TC-001 | home_visit_team | เหมือน TC-001 แต่ source_type=opd | source_type=`opd` | บันทึก `source_type=opd` สำเร็จ | Positive | Medium | AC-INTAKE-02 |
| TC-INTAKE-003 | สร้างใบส่งต่อสำเร็จ — แหล่งที่มา internal_dept | เหมือน TC-001 | admin | เหมือน TC-001 แต่ source_type=internal_dept | source_type=`internal_dept` | บันทึก `source_type=internal_dept` สำเร็จ | Positive | Medium | AC-INTAKE-02 |
| TC-INTAKE-004 | สร้างใบส่งต่อสำเร็จ — แหล่งที่มา external_hospital | เหมือน TC-001 | ward_staff | เหมือน TC-001 แต่ source_type=external_hospital | source_type=`external_hospital` | บันทึก `source_type=external_hospital` สำเร็จ | Positive | Medium | AC-INTAKE-02 |
| TC-INTAKE-005 | ปฏิเสธ source_type ที่ไม่ถูกต้อง | ล็อกอินแล้ว | ward_staff | ส่งฟอร์มด้วย source_type ที่ไม่อยู่ในรายการที่กำหนด | source_type=`emergency_room` | Validation error, ไม่มีการสร้าง Referral | Negative | Medium | AC-INTAKE-02 |
| TC-INTAKE-006 | ส่ง HN ซ้ำ — อัปเดตผู้ป่วยเดิมไม่สร้างใหม่ | มี Patient(hn=`HN00001`) อยู่แล้วจาก TC-001 | ward_staff | 1. สร้างใบส่งต่อใหม่ด้วย hn เดิมแต่ patient_name/ที่อยู่เปลี่ยนไป 2. ตรวจ DB | patient_hn=`HN00001`, patient_name=`นายทดสอบ หนึ่ง (แก้ไข)` | จำนวนแถวใน `patients` ที่ hn=`HN00001` ยังเป็น 1 แถว, ชื่อ/ที่อยู่ถูกอัปเดตเป็นค่าใหม่, มี Referral แถวที่ 2 ผูกกับ patient_id เดิม | Positive | High | AC-INTAKE-03 |
| TC-INTAKE-007 | Zone auto-resolve — sub_district อยู่ใน catchment list, ไม่ override | `config('catchment.in_area_sub_districts')` ตั้งไว้ = `['บางรัก']`, zone_override=false | ward_staff | ส่งฟอร์มโดยเลือก zone=out_area (ผิดๆ) แต่ patient_sub_district=`บางรัก` | patient_sub_district=`บางรัก`, zone (ที่เลือกในฟอร์ม)=`out_area`, zone_override=false | ระบบ auto-resolve ทับค่าที่เลือก → บันทึก zone จริง = `in_area` ทั้งใน Patient และ Referral | Positive | High | AC-INTAKE-04, AC-INTAKE-06 |
| TC-INTAKE-008 | Zone override เปิดใช้ — ค่าที่เลือกไม่ถูกทับ | catchment list ตั้งไว้ = `['บางรัก']`, zone_override=true | ward_staff | ส่งฟอร์มโดยติ๊ก zone_override และเลือก zone=out_area ทั้งที่ sub_district=`บางรัก` (อยู่ใน list) | patient_sub_district=`บางรัก`, zone=`out_area`, zone_override=true | บันทึก zone = `out_area` ตามที่เลือกเอง ไม่ถูกระบบทับ | Positive | High | AC-INTAKE-05 |
| TC-INTAKE-009 | Zone fallback เมื่อ catchment list ว่าง (ค่า default ปัจจุบัน) | `config('catchment.in_area_sub_districts')` = `[]`, zone_override=false | ward_staff | ส่งฟอร์มโดยกรอก patient_sub_district ใดๆ และเลือก zone=in_area เอง | patient_sub_district=`บางรัก`, zone (เลือกเอง)=`in_area`, zone_override=false | resolver คืนค่า `null` เพราะ list ว่าง → ระบบใช้ค่า zone ที่เลือกเอง (`in_area`) เป็นค่าบันทึกจริง | Edge | High | AC-INTAKE-04 |
| TC-INTAKE-010 | Zone fallback เมื่อไม่กรอก sub_district | catchment list ตั้งไว้ = `['บางรัก']`, zone_override=false | ward_staff | ส่งฟอร์มโดยเว้น patient_sub_district ว่าง และเลือก zone=out_area เอง | patient_sub_district=`` (ว่าง), zone=`out_area`, zone_override=false | resolver คืนค่า `null` เพราะ sub_district ว่าง → ใช้ค่า zone ที่เลือกเอง (`out_area`) | Edge | Medium | AC-INTAKE-04 |
| TC-INTAKE-011 | Zone-lookup AJAX — sub_district อยู่ใน catchment list | catchment list = `['บางรัก']` | ward_staff | เรียก `GET /referrals/zone-lookup?sub_district=บางรัก` | sub_district=`บางรัก` | ตอบ JSON `{zone: "in_area", label: "ระบบตรวจพบ: อยู่ในเขตรับผิดชอบ"}` | Positive | High | AC-INTAKE-07 |
| TC-INTAKE-012 | Zone-lookup AJAX — sub_district ไม่อยู่ใน catchment list | catchment list = `['บางรัก']` | ward_staff | เรียก `GET /referrals/zone-lookup?sub_district=สีลม` | sub_district=`สีลม` | ตอบ JSON `{zone: "out_area", label: "ระบบตรวจพบ: อยู่นอกเขตรับผิดชอบ"}` | Positive | High | AC-INTAKE-07 |
| TC-INTAKE-013 | Zone-lookup AJAX — catchment list ว่าง (ค่า default ปัจจุบันของระบบ) | catchment list = `[]` | ward_staff | เรียก `GET /referrals/zone-lookup?sub_district=บางรัก` | sub_district=`บางรัก` | ตอบ JSON `{zone: null, label: "ระบบยังไม่สามารถตรวจจับเขตอัตโนมัติได้ กรุณาเลือกเอง"}` | Edge | High | AC-INTAKE-07 |
| TC-INTAKE-014 | Zone-lookup AJAX — case-insensitive/trim matching | catchment list = `['บางรัก']` | ward_staff | เรียก `GET /referrals/zone-lookup?sub_district= BANGRAK ` (ปรับตามข้อมูลจริงในระบบ) | sub_district มีช่องว่างนำหน้า/ตามหลัง และตัวพิมพ์ต่างจากค่าใน config | resolver ต้อง trim + lower-case ก่อนเทียบ ผลลัพธ์ต้องตรงกับกรณีไม่มีช่องว่าง/ตัวพิมพ์ตรงกัน | Edge | Medium | AC-INTAKE-06 |
| TC-INTAKE-015 | ปฏิเสธไฟล์แนบขนาดเกิน 10MB | ล็อกอินแล้ว | ward_staff | แนบไฟล์ pdf ขนาด 11MB แล้ว submit | attachments[0] = ไฟล์ .pdf ขนาด ~11,000 KB | Validation error บนฟิลด์ attachments.0 (เกิน max:10240), ไม่มีการสร้าง Referral หรือ Patient (rollback) | Negative | High | AC-INTAKE-08 |
| TC-INTAKE-016 | ปฏิเสธไฟล์แนบ mime type ไม่รองรับ | ล็อกอินแล้ว | ward_staff | แนบไฟล์ `.docx` หรือ `.exe` แล้ว submit | attachments[0] = ไฟล์ `.docx` | Validation error (ไม่อยู่ใน pdf,jpg,jpeg,png), ไม่มีการสร้าง Referral | Negative | High | AC-INTAKE-08 |
| TC-INTAKE-017 | แนบไฟล์ได้ถูกต้องหลายไฟล์พร้อมกัน | ล็อกอินแล้ว | ward_staff | แนบไฟล์ 3 ไฟล์ (pdf, jpg, png ขนาดต่างกันแต่ ≤10MB) แล้ว submit | attachments = [ไฟล์.pdf 1MB, ไฟล์.jpg 2MB, ไฟล์.png 500KB] | สร้าง `ReferralAttachment` 3 แถว, ไฟล์ถูกจัดเก็บบน disk `local` ใต้ path `referral-attachments/`, แต่ละแถวมี original_name/mime_type/size ตรงกับไฟล์จริง, uploaded_by=ผู้ใช้ปัจจุบัน | Positive | High | AC-INTAKE-09 |
| TC-INTAKE-018 | ดาวน์โหลดไฟล์แนบข้าม referral — ต้องถูกปฏิเสธ 404 | มี Referral A (id=1) กับไฟล์แนบ attachment_1 (referral_id=1), และ Referral B (id=2) | ward_staff | เรียก `GET /referrals/2/attachments/1` (referral B, แต่ attachment เป็นของ referral A) | referral={2}, attachment={1} | ตอบ HTTP 404, ไม่มีการสตรีมไฟล์กลับมา | Security | High | AC-INTAKE-10 |
| TC-INTAKE-019 | ดาวน์โหลดไฟล์แนบที่ถูกต้อง | มี Referral A (id=1) กับไฟล์แนบ attachment_1 (referral_id=1) | ward_staff | เรียก `GET /referrals/1/attachments/1` | referral={1}, attachment={1} | ตอบกลับไฟล์จริง (stream download) พร้อมชื่อไฟล์ = `original_name` | Positive | Medium | AC-INTAKE-10 |
| TC-INTAKE-020 | เข้าถึงหน้าสร้าง/รายการใบส่งต่อโดยไม่ล็อกอิน | ไม่ได้ล็อกอิน (guest) | guest | เรียก `GET /referrals/create` หรือ `GET /referrals` โดยตรง | - | Redirect ไปหน้า login (`/login`), ไม่แสดงข้อมูลใดๆ | Security | High | AC-INTAKE-11 |
| TC-INTAKE-021 | ทุก role ที่ล็อกอินแล้วเข้าถึงโมดูลได้ | ล็อกอินแล้วด้วยแต่ละ role | ward_staff / home_visit_team / admin | เรียก `GET /referrals`, `GET /referrals/create`, `POST /referrals` ด้วยแต่ละ role | - | ทุก role เข้าถึงและสร้างใบส่งต่อได้สำเร็จ ไม่มี role ใดถูกบล็อกด้วย middleware `role` | Positive | Medium | AC-INTAKE-11 |
| TC-INTAKE-022 | ปล่อยฟิลด์บังคับว่าง — patient_hn | ล็อกอินแล้ว | ward_staff | ส่งฟอร์มโดยเว้น patient_hn ว่าง (ฟิลด์อื่นครบ) | patient_hn=`` | Validation error ข้อความอ้างชื่อฟิลด์ "HN" (ตาม attributes() map), ไม่มีการสร้าง Referral/Patient | Negative | High | AC-INTAKE-12 |
| TC-INTAKE-023 | ปล่อยฟิลด์บังคับว่าง — patient_name / raw_notes / zone | ล็อกอินแล้ว | ward_staff | ทดสอบเว้นว่างแต่ละฟิลด์ทีละฟิลด์: patient_name, raw_notes, zone | เว้นว่างทีละฟิลด์ | Validation error อ้างชื่อ "ชื่อ-สกุลผู้ป่วย" / "ข้อความสรุปอาการ/สถานการณ์" / "เขตพื้นที่" ตามลำดับ | Negative | Medium | AC-INTAKE-12 |
| TC-INTAKE-024 | สร้างใบส่งต่อโดยไม่เลือก case_type_id | ล็อกอินแล้ว | ward_staff | ส่งฟอร์มโดยเว้น case_type_id ว่าง (ฟิลด์บังคับอื่นครบ) | case_type_id=`` (ไม่ระบุ) | สร้าง Referral สำเร็จ, `case_type_id = null`, สามารถเลือกประเภทเคสได้ทีหลังในหน้ายืนยันแผนดูแล | Positive | Medium | AC-INTAKE-13 |
| TC-INTAKE-025 | ระบุ case_type_id ที่ไม่มีอยู่จริง | ล็อกอินแล้ว | ward_staff | ส่งฟอร์มโดยระบุ case_type_id เป็นเลขที่ไม่มีอยู่ในตาราง case_types | case_type_id=`999999` | Validation error (exists:case_types,id), ไม่มีการสร้าง Referral | Negative | Low | AC-INTAKE-13 |
| TC-INTAKE-026 | Pagination บนหน้ารายการใบส่งต่อเมื่อมีมากกว่า 20 รายการ | มี Referral อยู่ในระบบ 25 รายการ | ward_staff | เปิดหน้า `GET /referrals` | - | หน้าแรกแสดง 20 รายการ เรียงจากใหม่ไปเก่า, มีลิงก์/ปุ่มไปหน้า 2 ที่แสดง 5 รายการที่เหลือ, ไม่มี N+1 query | Positive | Medium | AC-INTAKE-14 |
| TC-INTAKE-027 | หน้ารายละเอียดใบส่งต่อโหลดความสัมพันธ์ครบ | มี Referral ที่มี attachments และ followUpPlans อยู่แล้ว | ward_staff | เปิดหน้า `GET /referrals/{id}` | - | หน้าแสดงข้อมูล patient, caseType, creator, attachments (พร้อมชื่อผู้อัปโหลด), followUpPlans พร้อมผลบันทึก (record) ครบถ้วนโดยไม่มี query error | Positive | Low | AC-INTAKE-14 |
| TC-INTAKE-028 | ไม่แนบไฟล์เลย (attachments เป็น optional) | ล็อกอินแล้ว | ward_staff | ส่งฟอร์มโดยไม่แนบไฟล์ใดๆ (ฟิลด์บังคับอื่นครบ) | attachments = (ไม่ส่ง) | สร้าง Referral สำเร็จโดยไม่มีแถว ReferralAttachment ใดๆ ไม่เกิด error | Positive | Low | AC-INTAKE-09 |

---

## SUMMARY — AI Draft Summary & Nurse Care-Plan Confirmation

| ID | Title (Thai) | Preconditions | Role | Steps | Test Data | Expected Result | Type | Priority | Related AC |
|---|---|---|---|---|---|---|---|---|---|
| TC-SUMMARY-001 | สร้างร่าง AI สำเร็จ → ตรวจสอบ → ยืนยันแผน (happy path) | มี referral สถานะ `pending_review`, `ai_summary` เป็น null, มี active `CaseType` อย่างน้อย 1 รายการ, Ollama ตอบ JSON ที่ถูกต้องครบทุก key | ผู้ใช้ล็อกอินแล้ว (role ใดก็ได้) | 1) POST `referrals.ai-summary` 2) GET `referrals.care-plan` ตรวจ label "ร่างจาก AI — ยังไม่ยืนยัน" + กรอบเส้นประ 3) แก้ไขค่าบางฟิลด์ 4) POST `referrals.care-plan.confirm` พร้อมฟิลด์ครบ | `patient_type`="ผู้ป่วยติดเตียง", `main_problem`="แผลกดทับ", `follow_up_need`="เยี่ยมสัปดาห์ละครั้ง", `risk_signals`="เสี่ยงหกล้ม", `initial_pps_score`=60, `case_type_id`=ของ active CaseType | `ai_summary`+`ai_summary_generated_at` ถูกบันทึกหลังขั้น 1; หลังขั้น 4: `confirmed_summary` ตรงกับ input, `confirmed_by`=user id, `confirmed_at` ไม่ null, `status`="plan_confirmed", redirect ไป `referrals.show` พร้อม flash "ยืนยันแผนติดตามเรียบร้อยแล้ว สร้างกำหนดการเยี่ยม/โทรครั้งแรกให้อัตโนมัติ", มี `FollowUpPlan` ถูกสร้าง | Positive | High | AC-SUMMARY-01,06,10,11,12 |
| TC-SUMMARY-002 | Ollama เชื่อมต่อไม่ได้ระหว่างสร้างร่าง | referral มี `ai_summary` เป็น null, จำลอง `Http::post` โยน connection exception | ผู้ใช้ล็อกอินแล้ว | POST `referrals.ai-summary` | — | Redirect ไป `referrals.show` พร้อม flash `error` = "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ AI ได้ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง"; `ai_summary` ยังเป็น null, `ai_summary_generated_at` ยังเป็น null; exception ถูกส่งเข้า error reporter | Negative | High | AC-SUMMARY-02 |
| TC-SUMMARY-003 | Ollama ตอบ HTTP failure (เช่น 500) ระหว่างสร้างร่าง | จำลอง response status >= 400/500 จาก Ollama | ผู้ใช้ล็อกอินแล้ว | POST `referrals.ai-summary` | — | Redirect ไป `referrals.show` พร้อม flash `error` = "เรียกใช้ AI ไม่สำเร็จ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง" (ข้อความต่างจาก TC-002); `ai_summary` ไม่ถูกแก้ไข | Negative | High | AC-SUMMARY-03 |
| TC-SUMMARY-004 | Ollama ตอบข้อความที่ไม่ใช่ JSON ที่ถูกต้อง (parse_error) | จำลอง Ollama คืน `response` เป็น plain text ที่ decode ไม่ได้ | ผู้ใช้ล็อกอินแล้ว | 1) POST `referrals.ai-summary` 2) GET `referrals.care-plan` | — | ขั้น 1: ไม่มี exception, redirect ไปที่ `referrals.care-plan`; `ai_summary` ถูกบันทึกเป็น `{patient_type: null, main_problem: null, follow_up_need: null, risk_signals: [], suggested_case_type_slug: null, parse_error: true, raw_response: "<ข้อความดิบ>"}`; ขั้น 2: หน้าแสดงกล่องเตือนสีเหลือง (amber) ให้กรอกข้อมูลด้วยตนเอง | Edge | High | AC-SUMMARY-04 |
| TC-SUMMARY-005 | ขอให้ AI สรุปใหม่ก่อนยืนยัน (regenerate) ต้อง overwrite ร่างเดิม | referral มี `ai_summary` ชุดที่ 1 อยู่แล้ว, ยังไม่ confirm | ผู้ใช้ล็อกอินแล้ว | 1) จด `ai_summary`/`ai_summary_generated_at` เดิม 2) กดปุ่ม "↻ ขอให้ AI สรุปใหม่" โดย mock ให้ Ollama ตอบเนื้อหาต่างจากครั้งก่อน | ชุดข้อมูล AI ครั้งที่ 2 ต่างจากครั้งที่ 1 | `ai_summary` ถูกแทนที่ทั้งหมดด้วยชุดใหม่ (ไม่ merge กับของเก่า), `ai_summary_generated_at` อัปเดตเป็นเวลาใหม่ที่ใหม่กว่าเดิม | Positive | Medium | AC-SUMMARY-05 |
| TC-SUMMARY-006 | ยืนยันแผนโดยไม่กรอก `risk_signals` (ฟิลด์ optional) | referral มีร่าง AI หรือไม่มีก็ได้, ฟิลด์บังคับอื่นครบ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` เว้น `risk_signals` เป็นค่าว่าง/ไม่ส่งเลย | `case_type_id`=valid, `patient_type`="...", `main_problem`="...", `follow_up_need`="...", `risk_signals`=null/"" | Validation ผ่าน (nullable), `confirmed_summary['risk_signals']` = `[]`, status เปลี่ยนเป็น `plan_confirmed` ตามปกติ | Positive | Medium | AC-SUMMARY-08 |
| TC-SUMMARY-007 | ยืนยันแผนด้วย `risk_signals` หลายบรรทัด ต้องถูกแยก/trim เป็น array ถูกต้อง | ฟิลด์บังคับอื่นครบ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` โดยส่ง `risk_signals` เป็น textarea หลายบรรทัดรวมบรรทัดว่างและ whitespace แปลก ๆ | `risk_signals` = `"  เสี่ยงหกล้ม \n\nแผลติดเชื้อ\n   \nซึมเศร้า  "` | `confirmed_summary['risk_signals']` = `["เสี่ยงหกล้ม", "แผลติดเชื้อ", "ซึมเศร้า"]` — บรรทัดว่าง/whitespace-only ถูกกรองออก ทุกบรรทัดถูก trim | Positive | High | AC-SUMMARY-08 |
| TC-SUMMARY-008 | ยืนยันแผนด้วย `initial_pps_score` เกินช่วง 0–100 ต้องถูก reject | ฟิลด์บังคับอื่นครบ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` โดยส่ง `initial_pps_score` นอกช่วง | ทดสอบ 3 ค่า: -1, 101, "abc" | Validation error บนฟิลด์ `initial_pps_score`, ทั้ง request ล้มเหลว, referral ไม่ถูกแก้ไข | Negative/Edge | High | AC-SUMMARY-09 |
| TC-SUMMARY-009 | ยืนยันแผนสำหรับประเภทเคสที่ยังไม่มี active VisitRule | มี `CaseType` ที่ active แต่ไม่มี `VisitRule` แถวใดผูกอยู่ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` เลือก `case_type_id` ของ CaseType นี้ พร้อมฟิลด์บังคับอื่นครบ | `case_type_id` = CaseType ที่ไม่มี VisitRule active | Referral ถูก update สำเร็จ: `status`="plan_confirmed"; flash message เตือนให้ตั้งค่าที่หน้าแอดมิน; ไม่มี `FollowUpPlan` ใดถูกสร้าง (count = 0) | Edge | High | AC-SUMMARY-13 |
| TC-SUMMARY-010 | ยืนยันแผนด้วย `case_type_id` ที่ไม่มีอยู่จริง | ฟิลด์บังคับอื่นครบ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` โดยส่ง `case_type_id` เป็น ID ที่ไม่มีในตาราง `case_types` | `case_type_id`=999999 | Validation error, referral ไม่ถูกแก้ไขใด ๆ, ไม่มี `FollowUpPlan` ถูกสร้าง | Negative | High | AC-SUMMARY-14 |
| TC-SUMMARY-011 | ยืนยันแผนโดยเว้น `case_type_id` ว่าง | ฟิลด์บังคับอื่นครบ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.care-plan.confirm` เว้น `case_type_id` เป็นค่าว่าง | `case_type_id`="" | Validation error ("required"), referral ไม่ถูกแก้ไข | Negative | High | AC-SUMMARY-07,14 |
| TC-SUMMARY-012 | ยืนยันแผนโดยเว้น `patient_type`/`main_problem`/`follow_up_need` ว่าง แม้มีร่าง AI ที่สมบูรณ์อยู่แล้ว | referral มี `ai_summary` ที่ parse สำเร็จครบทุกฟิลด์ | ผู้ใช้ล็อกอินแล้ว | ลบข้อความในฟิลด์ `patient_type` (หรืออื่น) ให้ว่าง แล้ว POST confirm | `patient_type`="" (ฟิลด์อื่นครบ) | Validation error เฉพาะฟิลด์ที่ว่าง, request ทั้งก้อนล้มเหลว, referral ยังไม่ confirm | Negative | High | AC-SUMMARY-07 |
| TC-SUMMARY-013 | เข้าถึง `POST /referrals/{id}/ai-summary` โดยไม่ได้ล็อกอิน | ไม่มี session/auth cookie | guest | ยิง POST ตรงไปที่ route โดยไม่มี auth session | — | Redirect ไปหน้า login, ไม่มีการเรียก `AiService` หรือแก้ไข referral ใด ๆ | Security | High | AC-SUMMARY-15 |
| TC-SUMMARY-014 | เข้าถึง `GET /referrals/{id}/care-plan` โดยไม่ได้ล็อกอิน | ไม่มี session/auth cookie | guest | ยิง GET ตรงไปที่ route | — | Redirect ไปหน้า login | Security | High | AC-SUMMARY-15 |
| TC-SUMMARY-015 | เข้าถึง `POST /referrals/{id}/care-plan` (confirm) โดยไม่ได้ล็อกอิน | ไม่มี session/auth cookie | guest | ยิง POST พร้อมฟิลด์ครบไปที่ route confirm โดยไม่ auth | ฟิลด์ครบเหมือน happy path | Redirect ไปหน้า login, referral ไม่ถูกแก้ไข, ไม่มี `FollowUpPlan` ถูกสร้าง | Security | High | AC-SUMMARY-15 |
| TC-SUMMARY-016 | ผู้ใช้ role ใดก็ได้ (ไม่จำกัด role) สามารถยืนยันแผนได้ | มี user role = `ward_staff` ล็อกอินอยู่ | ward_staff | POST `referrals.care-plan.confirm` พร้อมฟิลด์ครบ | เหมือน happy path | ยืนยันสำเร็จเหมือน TC-001 ไม่มี 403 | Positive | Medium | AC-SUMMARY-15 |
| TC-SUMMARY-017 | ตรวจสอบว่า `generateAiSummary` ไม่เคยสร้าง `FollowUpPlan` แม้ AI ตอบสมบูรณ์แบบ | referral ใหม่, มี active VisitRule สำหรับ case type ที่ AI แนะนำ | ผู้ใช้ล็อกอินแล้ว | POST `referrals.ai-summary` (สำเร็จ) แล้วนับจำนวน `FollowUpPlan` ทันที (ก่อนเข้าหน้า confirm) | — | จำนวน `FollowUpPlan` = 0 ก่อนที่จะเรียก `confirmCarePlan` | Security/Correctness | High | AC-SUMMARY-12 |
| TC-SUMMARY-018 | ตรวจสอบว่า `confirmed_summary` ไม่ถูกก็อปปีจาก `ai_summary` โดยอัตโนมัติเมื่อ nurse แก้ไขค่าก่อน submit | referral มี `ai_summary` = `{patient_type:"A", main_problem:"B", follow_up_need:"C", risk_signals:["X"]}` | ผู้ใช้ล็อกอินแล้ว | แก้ไขค่าในฟอร์มเป็นค่าใหม่ทั้งหมดก่อน submit POST confirm | `patient_type`="A2", `main_problem`="B2", `follow_up_need`="C2", `risk_signals`="Y" | `confirmed_summary` ที่บันทึกจริงตรงกับค่าที่ submit ทุกตัวอักษร ไม่ใช่ค่าเดิมจาก `ai_summary` | Positive/Correctness | High | AC-SUMMARY-10 |
| TC-SUMMARY-019 | Submit `case_type_id` ของ CaseType ที่ `is_active=false` | มี `CaseType` ที่ `is_active = false` อยู่ในระบบ | ผู้ใช้ล็อกอินแล้ว | ส่ง POST confirm ตรง ๆ (bypass UI) ด้วย `case_type_id` ของ inactive CaseType | `case_type_id` = ID ของ inactive CaseType | ผ่าน validation (rule คือ `exists` เท่านั้น ไม่กรอง `is_active`) และยืนยันสำเร็จ — บันทึกเป็นข้อสังเกต QA | Security/Edge | Low | AC-SUMMARY-07,14 |
| TC-SUMMARY-020 | Submit ฟอร์ม confirm ซ้ำหลังยืนยันไปแล้ว | referral ถูกยืนยันไปแล้วครั้งหนึ่ง (`confirmed_at` ไม่ null) | ผู้ใช้ล็อกอินแล้ว | GET care-plan (เห็นกรอบทึบ) จากนั้นพยายาม POST confirm อีกครั้งด้วยค่าใหม่ | ฟิลด์ครบ ค่าต่างจากครั้งแรก | ระบบยัง overwrite ได้อีก (ไม่มี guard กันการ submit ซ้ำ) — flag เป็นข้อสังเกตให้ทีมตัดสินใจ | Edge/Regression | Medium | AC-SUMMARY-16 |

**หมายเหตุ:** mock `Http::fake()` ควรมี fixture แยกกัน 3 แบบ: connection exception, HTTP failed response, และ response ที่ format=json แต่เนื้อหา parse ไม่ได้ (ดู AC-SUMMARY-02/03/04)

---

## SCHED — Visit Scheduling Engine & Case Type / Visit Rule Admin

| ID | Title | Preconditions | Role | Steps | Test Data | Expected Result | Type | Priority | Related AC |
|---|---|---|---|---|---|---|---|---|---|
| TC-SCHED-001 | สร้างแผน fixed_count 3 ครั้งสำหรับ referral ในเขต (in_area) | มีประเภทเคส "หลังคลอด" ที่มี active VisitRule: rule_type=fixed_count, fixed_visit_count=3, fixed_interval_days=7; referral ยังไม่มีแผนใด ๆ; zone = in_area | System (ผ่าน flow ยืนยันแผนดูแล) | 1. ยืนยันแผนดูแลของ referral (trigger `generateInitialPlans`) 2. ตรวจ `follow_up_plans` | zone=in_area, fixed_visit_count=3, fixed_interval_days=7 | ได้แผน 3 แผน: plan_number 1/2/3, due_date = วันนี้+7/+14/+21 วันตามลำดับ, method=home_visit ทุกแผน, status=scheduled ทุกแผน | Positive | High | AC-SCHED-01 |
| TC-SCHED-002 | สร้างแผน fixed_count สำหรับ referral นอกเขต (out_area) ได้ method=phone_call | เหมือน TC-001 แต่ zone = out_area | System | 1. ยืนยันแผนดูแล 2. ตรวจ method ของทุกแผน | zone=out_area | ทุกแผนมี method=phone_call ค่า due_date เหมือน TC-001 | Positive | High | AC-SCHED-01 |
| TC-SCHED-003 | สร้างแผนแรก score_based เมื่อ PPS Score ตรงกับช่วงที่กำหนด | ประเภทเคส "Palliative" มี active rule: score_based, score_rules=[{0-30:3d},{31-60:7d}] | System | 1. ยืนยันแผนดูแลพร้อมระบุ initialPpsScore=20 2. ตรวจแผนที่สร้าง | initialPpsScore=20 (อยู่ในช่วง 0-30) | ได้แผนเดียว plan_number=1, due_date = วันนี้+3 วัน, status=scheduled | Positive | High | AC-SCHED-02 |
| TC-SCHED-004 | สร้างแผนแรก score_based เมื่อ PPS Score ไม่ตรงช่วงใดเลย → fallback 14 วัน | เหมือน TC-003, score_rules ครอบคลุมเฉพาะ 0-60 | System | 1. ยืนยันแผนดูแลพร้อม initialPpsScore=90 2. ตรวจแผนที่สร้าง | initialPpsScore=90 | ได้แผนเดียว plan_number=1, due_date = วันนี้+14 วัน (fallback) | Edge | High | AC-SCHED-02 |
| TC-SCHED-005 | สร้างแผนแรก score_based เมื่อไม่ระบุ PPS Score (null) → fallback 14 วัน | เหมือน TC-003 | System | 1. เรียก `generateInitialPlans($referral, null)` 2. ตรวจแผนที่สร้าง | initialPpsScore=null | ได้แผนเดียว plan_number=1, due_date = วันนี้+14 วัน | Edge | Medium | AC-SCHED-02 |
| TC-SCHED-006 | generateInitialPlans ต้อง no-op ถ้า referral มีแผนอยู่แล้ว | referral มีแผน 1 แผนอยู่แล้ว | System | 1. เรียก `generateInitialPlans()` ซ้ำอีกครั้ง 2. ตรวจจำนวนแผน | referral มีแผนอยู่ก่อนแล้ว 1 แผน | คืนค่า array ว่าง [], จำนวนแผนไม่เพิ่มขึ้น | Edge | High | AC-SCHED-11 |
| TC-SCHED-007 | generateInitialPlans ต้อง no-op ถ้าประเภทเคสไม่มีเกณฑ์ active | ประเภทเคสไม่มี VisitRule ที่ is_active=true | System | 1. เรียก `generateInitialPlans()` 2. ตรวจผลลัพธ์ | ไม่มี active VisitRule | คืนค่า array ว่าง [], ไม่มีแผนถูกสร้าง | Edge | High | AC-SCHED-11 |
| TC-SCHED-008 | generateNextPlan ไม่สร้างแผนซ้ำเมื่อยังมีแผนที่รออยู่ (fixed_count) | referral fixed_count, มีแผน 3 แผนล่วงหน้าแล้ว (plan 1 done, plan 2&3 scheduled) | System (nurse ตัดสินใจ "ติดตามซ้ำ" บน plan 1) | 1. บันทึกผล plan 1 พร้อม decision=repeat 2. เรียก `generateNextPlan($record)` 3. ตรวจจำนวนแผนทั้งหมด | plan_number ปัจจุบัน=1, มี plan 2 (scheduled) อยู่แล้ว | คืนค่า null, ไม่มีแผนใหม่, จำนวนแผนคงเดิมที่ 3 | Positive | High | AC-SCHED-03 |
| TC-SCHED-009 | generateNextPlan สร้างแผน #2 สำหรับ score_based โดยใช้ interval จาก PPS Score รอบใหม่ | referral score_based, มีเพียง plan 1, score_rules=[{0-30:3d},{31-60:7d}] | Nurse (ตัดสินใจ "ติดตามซ้ำ" พร้อมประเมิน PPS Score ใหม่) | 1. บันทึกผล plan 1 พร้อม pps_score=45, decision=repeat 2. เรียก `generateNextPlan($record)` 3. ตรวจแผนใหม่ | pps_score=45 (ช่วง 31-60 → interval 7) | ได้แผนใหม่ plan_number=2, due_date=วันนี้+7 วัน, method เท่ากับ plan 1 เดิม, status=scheduled | Positive | High | AC-SCHED-04 |
| TC-SCHED-010 | generateNextPlan ใช้เกณฑ์ปัจจุบัน (ไม่ใช่เกณฑ์ตอนสร้างแผนแรก) เมื่อ rule_type ถูกเปลี่ยนกลางทาง | referral สร้างแผนแรกตอนยังเป็น score_based; ต่อมา admin เปลี่ยนเป็น fixed_count (interval=10) ก่อนบันทึกผล plan 1 | Nurse + Admin (setup) | 1. Admin เปลี่ยน rule_type เป็น fixed_count, interval=10 2. Nurse บันทึกผล plan 1 พร้อม decision=repeat 3. เรียก `generateNextPlan($record)` | active rule ปัจจุบัน = fixed_count, interval=10 | ได้แผนใหม่ plan_number=2, due_date=วันนี้+10 วัน (ใช้เกณฑ์ปัจจุบัน ไม่ใช่ score_rules เดิม) | Edge | Medium | AC-SCHED-04 |
| TC-SCHED-011 | generateNextPlan fallback 14 วันเมื่อไม่มีเกณฑ์ active เหลืออยู่เลย | referral มี plan 1 บันทึกผลแล้ว; VisitRule ของประเภทเคสถูก deactivate ทั้งหมด | System | 1. Deactivate VisitRule ทั้งหมด 2. เรียก `generateNextPlan($record)` decision=repeat | ไม่มี active VisitRule | ได้แผนใหม่ plan_number=2, due_date=วันนี้+14 วัน (ultimate default) | Edge | Low | AC-SCHED-04 |
| TC-SCHED-012 | cancelRemainingPlans ปิดเคสยกเลิกเฉพาะแผน scheduled | referral มีแผน 4 แผน: plan1=done, plan2=overdue, plan3=cancelled, plan4=scheduled | Nurse (ตัดสินใจ "ปิดเคส") | 1. บันทึกผลพร้อม decision=close 2. เรียก `cancelRemainingPlans($referral)` 3. ตรวจสถานะทุกแผน | สถานะเริ่มต้นตามที่ระบุ | plan4 เปลี่ยนเป็น cancelled; plan1(done), plan2(overdue), plan3(cancelled เดิม) ไม่เปลี่ยน | Positive | High | AC-SCHED-05 |
| TC-SCHED-013 | isOverdue()=true เมื่อ status=scheduled และ due_date ผ่านไปแล้ว | FollowUpPlan status=scheduled, due_date=เมื่อวาน | - | เรียก `$plan->isOverdue()` | due_date < today, status=scheduled | คืนค่า true | Positive | Medium | AC-SCHED-06 |
| TC-SCHED-014 | isOverdue()=false สำหรับแผน done แม้ due_date ผ่านไปแล้ว | status=done, due_date=เมื่อวาน | - | เรียก `$plan->isOverdue()` | due_date < today, status=done | คืนค่า false | Edge | Medium | AC-SCHED-06 |
| TC-SCHED-015 | isOverdue()=false สำหรับแผน cancelled แม้ due_date ผ่านไปแล้ว | status=cancelled, due_date=เมื่อวาน | - | เรียก `$plan->isOverdue()` | due_date < today, status=cancelled | คืนค่า false | Edge | Low | AC-SCHED-06 |
| TC-SCHED-016 | สร้างประเภทเคสใหม่ด้วย slug ที่ซ้ำกับที่มีอยู่แล้ว → ถูกปฏิเสธ | มีประเภทเคส slug="postpartum" อยู่แล้ว | Admin | 1. /admin/case-types/create 2. กรอก slug="postpartum" พร้อมข้อมูลอื่นครบ 3. Submit | name="หลังคลอด 2", slug="postpartum" | บันทึกไม่สำเร็จ, validation error ที่ slug, ไม่มีการสร้าง record ใหม่ | Negative | High | AC-SCHED-07 |
| TC-SCHED-017 | สร้างประเภทเคสใหม่โดยไม่ติ๊ก is_active → บันทึกเป็น active (default true บน store) | ไม่มี slug ซ้ำ | Admin | 1. /admin/case-types/create 2. กรอกฟิลด์จำเป็น ไม่ติ๊ก is_active 3. Submit | slug="new-case-type" | บันทึกสำเร็จ, CaseType ใหม่มี is_active=true | Positive | High | AC-SCHED-10 |
| TC-SCHED-018 | แก้ไขประเภทเคสโดยไม่ติ๊ก is_active → บันทึกเป็น inactive (default false บน update) | ประเภทเคสเดิม is_active=true | Admin | 1. /admin/case-types/{id}/edit 2. ไม่ติ๊ก is_active 3. Submit 4. ตรวจสถานะ | is_active field ไม่ถูกส่งมา | บันทึกสำเร็จ, CaseType.is_active กลายเป็น false ทันที (ต่างจาก create) | Negative/Edge | High | AC-SCHED-10 |
| TC-SCHED-019 | แก้ไขประเภทเคสสลับ rule_type จาก fixed_count เป็น score_based | active rule เดิม: fixed_count, count=3, interval=7 | Admin | 1. /admin/case-types/{id}/edit 2. เปลี่ยน rule_type=score_based, score_rules_text="0,30,3,วิกฤต\n31,60,7,ทั่วไป" 3. Submit 4. ตรวจ VisitRule | rule_type ใหม่=score_based | VisitRule แถว active เดิมถูก**อัปเดต** (ไม่สร้างแถวใหม่): rule_type=score_based, fixed fields=null, score_rules=[{...},{...}]; จำนวนแถว active ยังเป็น 1 | Positive | High | AC-SCHED-08, AC-SCHED-13 |
| TC-SCHED-020 | rule_type=fixed_count แต่ไม่กรอก fixed_visit_count/fixed_interval_days → ปฏิเสธ | - | Admin | 1. กรอก rule_type=fixed_count 2. เว้น fixed_visit_count/fixed_interval_days ว่าง 3. Submit | rule_type=fixed_count, ทั้งสองว่าง | Validation error บนทั้งสอง field (required_if), ไม่มีการบันทึก | Negative | High | AC-SCHED-08 |
| TC-SCHED-021 | rule_type=score_based แต่ไม่กรอก score_rules_text → ปฏิเสธ | - | Admin | 1. กรอก rule_type=score_based 2. เว้น score_rules_text ว่าง 3. Submit | rule_type=score_based, score_rules_text="" | Validation error บน score_rules_text (required_if), ไม่มีการบันทึก | Negative | High | AC-SCHED-08 |
| TC-SCHED-022 | บรรทัดที่พิมพ์ผิดรูปแบบใน score_rules_text ถูกข้ามอย่างเงียบ ๆ โดยไม่มี error | rule_type=score_based | Admin | 1. กรอก score_rules_text ผสมบรรทัดถูก/ผิด: `"0,30,3,วิกฤต\nbadline\n31,60,7"` 2. Submit 3. ตรวจ score_rules ที่บันทึก | บรรทัดกลางมี 0 comma | บันทึกสำเร็จไม่มี error; score_rules มีเพียง 2 รายการ บรรทัด "badline" ถูกข้าม | Negative/Edge | Medium | AC-SCHED-12 |
| TC-SCHED-023 | ค่า min/max/interval_days ที่ไม่ใช่ตัวเลขใน score_rules_text ถูกแปลงเป็น 0 อย่างเงียบ ๆ | rule_type=score_based | Admin | 1. กรอก score_rules_text = `"abc,def,ghi,ป้ายกำกับ"` 2. Submit 3. ตรวจ score_rules ที่บันทึก | 4 ส่วนคั่นด้วย comma ครบ แต่ไม่ใช่ตัวเลข | บันทึกสำเร็จไม่มี error; score_rules = {min:0, max:0, interval_days:0, label:"ป้ายกำกับ"} | Edge | Low | AC-SCHED-12 |
| TC-SCHED-024 | ward_staff ถูกบล็อก 403 จากทุก route ของ admin/case-types | ล็อกอินด้วย role=ward_staff | ward_staff | เข้าถึงทั้ง 5 route (index/create/store/edit/update) | role=ward_staff | ทุก request ได้ HTTP 403, ไม่มีการเปลี่ยนแปลงข้อมูล | Security | High | AC-SCHED-09 |
| TC-SCHED-025 | home_visit_team ถูกบล็อก 403 จากทุก route ของ admin/case-types | ล็อกอินด้วย role=home_visit_team | home_visit_team | เหมือน TC-024 | role=home_visit_team | ทุก request ได้ HTTP 403 | Security | High | AC-SCHED-09 |
| TC-SCHED-026 | ประเภทเคสที่ inactive ถูกซ่อนจากหน้าเปิดเคสใหม่ แต่ activeVisitRule() ยังทำงานได้อิสระจาก CaseType.is_active | CaseType A: is_active=false แต่ VisitRule.is_active=true; referral เดิมผูกกับ A | Ward staff + System | 1. เปิด /referrals/create และ care-plan dropdown 2. ตรวจว่า A ปรากฏหรือไม่ 3. เรียก generateInitialPlans/generateNextPlan บน referral เดิม | CaseType A is_active=false, VisitRule is_active=true | A **ไม่ปรากฏ**ในตัวเลือกหน้าเปิดเคสใหม่; แต่ referral เดิมยังคำนวณ/สร้างแผนได้ปกติ | Edge | Medium | AC-SCHED-10, AC-SCHED-11 |
| TC-SCHED-027 | slug ที่มีอักขระต้องห้าม (เว้นวรรค, สัญลักษณ์) ถูกปฏิเสธโดย alpha_dash | - | Admin | กรอก slug="post partum!" แล้ว Submit | slug="post partum!" | Validation error บน slug (alpha_dash), ไม่มีการบันทึก | Negative | Medium | AC-SCHED-07 |
| TC-SCHED-028 | แก้ไขประเภทเคสด้วย slug เดิมของตัวเอง (ไม่เปลี่ยน) → ต้องผ่าน ไม่ถือว่าซ้ำ | ประเภทเคส B มี slug="palliative" | Admin | 1. /admin/case-types/{B}/edit 2. แก้ไข description คง slug เดิม 3. Submit | slug="palliative" (เท่ากับปัจจุบัน) | บันทึกสำเร็จ ไม่มี validation error เรื่อง slug ซ้ำ (unique rule ignore ตัวเอง) | Positive | Medium | AC-SCHED-07 |

---

## RECORD — Follow-Up Guide & Outcome Recording

### TC-RECORD-001 — สร้างคู่มือติดตามสำเร็จ แสดงหัวข้อที่ AI แนะนำ
- **Preconditions:** ผู้ใช้ล็อกอินแล้ว; `FollowUpPlan.ai_guide = null`; referral มี `confirmed_summary` สมบูรณ์; Ollama ตอบ JSON ถูกต้องตาม schema `{"topics":[...]}`
- **Role:** home_visit_team
- **Steps:** 1) เปิด `GET .../guide` 2) ยืนยันข้อความ "ยังไม่มีคู่มือติดตามสำหรับครั้งนี้" 3) กด generate (POST) 4) สังเกตหน้าที่ redirect กลับมา
- **Test Data:** Mock Ollama ตอบ `{"topics":[{"title":"ประเมินแผลกดทับ","note":"ผู้ป่วยติดเตียง"},{"title":"ถามอาการปวด","note":null}]}`
- **Expected Result:** Redirect กลับ guide; `ai_guide` = `{topics:[...2 รายการ...], parse_error:false}`; หน้าแสดง box "หัวข้อที่ AI แนะนำ" พร้อม 2 รายการ, ปุ่มเปลี่ยนเป็น "↻ ขอให้ AI แนะนำใหม่"
- **Type:** Positive | **Priority:** High | **Related AC:** AC-RECORD-01

### TC-RECORD-002 — สร้างคู่มือเมื่อ referral ยังไม่ยืนยัน confirmed_summary (fallback ไปใช้ ai_summary)
- **Preconditions:** `confirmed_summary = null`, `ai_summary` มีค่า main_problem/follow_up_need/risk_signals
- **Role:** home_visit_team
- **Steps:** 1) ตรวจ referral ว่า confirmed_summary=null 2) กดขอคู่มือ AI
- **Test Data:** `ai_summary = {main_problem: "แผลกดทับระยะ 2", follow_up_need: "ทำแผลสัปดาห์ละครั้ง", risk_signals: ["ภาวะทุพโภชนาการ"]}`
- **Expected Result:** Prompt ใช้ค่าจาก `ai_summary` แทน ไม่ error; คู่มือถูกบันทึกตามปกติ
- **Type:** Positive/Edge | **Priority:** Medium | **Related AC:** AC-RECORD-01

### TC-RECORD-003 — สร้างคู่มือเมื่อทั้ง confirmed_summary และ ai_summary เป็น null
- **Preconditions:** `confirmed_summary = null` และ `ai_summary = null`
- **Role:** ward_staff
- **Steps:** 1) เปิดหน้า guide 2) กดขอคู่มือ AI
- **Test Data:** ทั้งสองเป็น null
- **Expected Result:** Prompt ใช้ placeholder `-` แทน ไม่ throw exception; เรียก AI และประมวลผลต่อได้ตามปกติ ไม่ 500
- **Type:** Edge | **Priority:** Medium | **Related AC:** AC-RECORD-01

### TC-RECORD-004 — สร้างคู่มือล้มเหลวเพราะ Ollama ไม่ตอบสนอง
- **Preconditions:** แผนมี `ai_guide` เดิม (ค่า A) หรือ null; จำลอง Ollama connection ล้มเหลว
- **Role:** home_visit_team
- **Steps:** 1) บันทึกค่า ai_guide เดิม 2) กดขอคู่มือ AI ขณะ Ollama ไม่ตอบสนอง
- **Test Data:** จำลอง `Http::post` throw connection exception
- **Expected Result:** Redirect กลับ guide พร้อม flash error ("ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ AI ได้..."); `ai_guide` ไม่เปลี่ยนแปลง; exception ถูก report()
- **Type:** Negative | **Priority:** High | **Related AC:** AC-RECORD-02

### TC-RECORD-005 — ขอคู่มือใหม่ซ้ำ (regenerate) แทนที่ค่าเดิมทั้งหมด
- **Preconditions:** `ai_guide` เดิม = `{topics:[{title:"A", note:"x"}], parse_error:false}`
- **Role:** home_visit_team
- **Steps:** 1) เปิดหน้า guide ยืนยันเห็น "A" 2) กด regenerate โดยจำลอง AI ตอบชุดใหม่ไม่มี "A"
- **Test Data:** ตอบใหม่ `{"topics":[{"title":"B","note":"y"},{"title":"C","note":null}]}`
- **Expected Result:** `ai_guide` ถูกแทนที่ทั้งหมด (มีเฉพาะ B, C); หน้าแสดงเฉพาะ B, C
- **Type:** Positive | **Priority:** Medium | **Related AC:** AC-RECORD-03

### TC-RECORD-006 — AI ตอบไม่เป็น JSON ที่ถูกต้อง → แสดง parse_error fallback
- **Preconditions:** จำลอง Ollama ตอบ `response` เป็นข้อความที่ไม่ใช่ JSON ที่ถูกต้อง
- **Role:** home_visit_team
- **Steps:** 1) กดขอคู่มือ AI 2) สังเกตหน้าที่แสดงผลกลับมา
- **Test Data:** raw response: `"ขออภัย ไม่สามารถประมวลผลได้"`
- **Expected Result:** `ai_guide` = `{topics:[], parse_error:true, raw_response:"..."}`; หน้าแสดงกล่องเตือนสีเหลืองแทนรายการหัวข้อ; ไม่มี error 500; ปุ่มบันทึกผลติดตามยังใช้งานได้
- **Type:** Edge | **Priority:** Medium | **Related AC:** AC-RECORD-05

### TC-RECORD-007 — บันทึกผลติดตาม (happy path) พร้อมทุกฟิลด์
- **Preconditions:** แผนยังไม่มี `FollowUpRecord`
- **Role:** home_visit_team
- **Steps:** 1) เปิดหน้าบันทึกผล 2) กรอก visited_at/pps_score/raw_notes 3) POST
- **Test Data:** `visited_at=2026-08-20 14:30`, `pps_score=60`, `raw_notes="ผู้ป่วยรู้สึกตัวดี แผลเริ่มตกสะเก็ด"`
- **Expected Result:** สร้าง `FollowUpRecord` ใหม่ (ai_analysis/nurse_decision/confirmed_* เป็น null); `FollowUpPlan.status = done`; redirect ไป review พร้อม flash ชี้ขั้นตอนถัดไป
- **Type:** Positive | **Priority:** High | **Related AC:** AC-RECORD-08,09,10,11,12

### TC-RECORD-008 — บันทึกผลติดตามโดยไม่กรอก pps_score
- **Preconditions:** แผนยังไม่มี record
- **Role:** ward_staff
- **Steps:** 1) เว้น PPS Score ว่าง, กรอก raw_notes 2) บันทึก
- **Test Data:** `pps_score = ""`
- **Expected Result:** บันทึกสำเร็จ; `pps_score = null`; `status = done` (ยืนยันว่าเว้น pps_score ไม่บล็อก)
- **Type:** Positive/Edge | **Priority:** High | **Related AC:** AC-RECORD-08,09

### TC-RECORD-009 — บันทึกผลติดตามด้วย pps_score = 101 (เกินขอบเขตบน) ถูกปฏิเสธ
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** กรอก `pps_score=101`, `raw_notes="ทดสอบ"` แล้วบันทึก
- **Test Data:** `pps_score = 101`
- **Expected Result:** Validation ล้มเหลว (max:100); ไม่มี record ถูกสร้าง; status ไม่เปลี่ยน
- **Type:** Negative | **Priority:** High | **Related AC:** AC-RECORD-09

### TC-RECORD-010 — บันทึกผลติดตามด้วย pps_score = -1 ถูกปฏิเสธ
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** กรอก `pps_score=-1`, `raw_notes="ทดสอบ"` แล้วบันทึก
- **Test Data:** `pps_score = -1`
- **Expected Result:** Validation ล้มเหลว (min:0); ไม่มี record ถูกสร้าง; status ไม่เปลี่ยน
- **Type:** Negative | **Priority:** High | **Related AC:** AC-RECORD-09

### TC-RECORD-011 — บันทึกผลติดตามโดยเว้น raw_notes ว่าง ถูกปฏิเสธ
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** เว้น raw_notes ว่าง, กรอกฟิลด์อื่นตามปกติ แล้วบันทึก
- **Test Data:** `raw_notes = ""`
- **Expected Result:** Validation ล้มเหลว (required), error อ้าง "อาการ/ปัญหาที่พบ"; ไม่มี record ถูกสร้าง
- **Type:** Negative | **Priority:** High | **Related AC:** AC-RECORD-10

### TC-RECORD-012 — เข้าหน้าสร้างบันทึกผล (GET) ซ้ำหลังจากมี record แล้ว → 403
- **Preconditions:** แผนมี `FollowUpRecord` อยู่แล้ว
- **Role:** home_visit_team
- **Steps:** เข้า `GET .../record` โดยตรง
- **Test Data:** แผนที่มี record 1 แถว
- **Expected Result:** HTTP 403 "บันทึกผลติดตามครั้งนี้ไปแล้ว" — ไม่แสดงฟอร์มแม้แบบอ่านอย่างเดียว
- **Type:** Negative | **Priority:** High | **Related AC:** AC-RECORD-06

### TC-RECORD-013 — ส่งฟอร์มบันทึกผล (POST) ซ้ำสำหรับแผนที่มี record แล้ว (race condition) → 403
- **Preconditions:** 2 sessions เปิดหน้า record ของแผนเดียวกันพร้อมกันตอนยังไม่มี record
- **Role:** home_visit_team (ทั้งสอง)
- **Steps:** 1) Session A submit ก่อน (สำเร็จ) 2) Session B submit ตามมาทันที
- **Test Data:** ฟอร์มต่างกันเล็กน้อยระหว่าง A/B
- **Expected Result:** A สำเร็จ; B ได้ 403; มี record เพียง 1 แถว (ของ A)
- **Type:** Negative/Security | **Priority:** High | **Related AC:** AC-RECORD-07

### TC-RECORD-014 — ไม่กรอก visited_at → ใช้เวลาปัจจุบันเป็นค่าตั้งต้น
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** ส่ง POST โดยไม่ส่ง key `visited_at` เลย
- **Test Data:** `visited_at` ไม่อยู่ใน payload
- **Expected Result:** บันทึกสำเร็จ; `visited_at` = เวลา ณ ขณะประมวลผล request
- **Type:** Edge | **Priority:** Medium | **Related AC:** AC-RECORD-11

### TC-RECORD-015 — บันทึกผลติดตามกับแผนที่ถูกยกเลิกไปแล้ว (cancelled) — ปัจจุบันไม่มีการบล็อก (known gap)
- **Preconditions:** แผน `status = cancelled`, ยังไม่มี record
- **Role:** home_visit_team
- **Steps:** เข้า `GET .../record` ของแผน cancelled โดยตรง แล้วบันทึก
- **Test Data:** raw_notes ใดๆ ที่ผ่าน validation
- **Expected Result (as-is):** ระบบอนุญาตให้บันทึกได้ตามปกติ และเปลี่ยน status จาก cancelled เป็น done ทับค่าเดิม — **flag เป็น known gap** ในรายงาน QA
- **Type:** Edge (documented gap) | **Priority:** Medium | **Related AC:** AC-RECORD-14

### TC-RECORD-016 — ผู้ใช้ที่ไม่ได้ล็อกอินเข้าถึง route ใดๆ ของโมดูลนี้ → redirect ไปหน้า login
- **Preconditions:** guest
- **Role:** guest
- **Steps:** เรียกทั้ง 4 route โดยตรง แบบไม่ล็อกอิน
- **Test Data:** plan ID ใดๆ ที่มีอยู่จริง
- **Expected Result:** ทุก route redirect ไปหน้า login; ไม่มีการเปลี่ยนแปลงข้อมูลใน DB
- **Type:** Security | **Priority:** High | **Related AC:** AC-RECORD-15

### TC-RECORD-017 — ward_staff เข้าถึงและใช้งานฟีเจอร์ guide/record ได้เต็มสิทธิ์ (ไม่มี role gate — known gap)
- **Preconditions:** ผู้ใช้ role ward_staff
- **Role:** ward_staff
- **Steps:** ขอคู่มือ AI + บันทึกผลติดตามครบ flow
- **Test Data:** ข้อมูลที่ผ่าน validation ปกติ
- **Expected Result (as-is):** ทำงานสำเร็จทุกขั้นตอนเหมือน home_visit_team — **flag เป็น known gap** เชิง authorization
- **Type:** Security (documented gap) | **Priority:** Medium | **Related AC:** AC-RECORD-13

### TC-RECORD-018 — สถานะแผนเปลี่ยนเป็น done แม้เนื้อหา raw_notes สั้น/ไม่มีสาระ และ pps_score ว่าง
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** กรอก `raw_notes="-"`, เว้น pps_score แล้วบันทึก
- **Test Data:** `raw_notes="-"`, `pps_score=null`
- **Expected Result:** บันทึกสำเร็จ; status เปลี่ยนเป็น done ทันที (ไม่มีการตรวจสอบคุณภาพเนื้อหา)
- **Type:** Edge | **Priority:** Medium | **Related AC:** AC-RECORD-08

### TC-RECORD-019 — คู่มือ AI ไม่ถูกเขียนลง Referral/Patient และไม่กระทบกำหนดการแผนอื่น
- **Preconditions:** referral มีแผน 3 แผน (fixed_count); แผนที่ 2 ยังไม่มี ai_guide
- **Role:** home_visit_team
- **Steps:** 1) บันทึกค่าเดิมของ referral และแผน 1/3 2) ขอคู่มือ AI ให้แผน 2 3) ตรวจซ้ำ
- **Test Data:** referral fixed_count
- **Expected Result:** referral/patient ไม่เปลี่ยน; แผน 1, 3 เหมือนก่อนหน้าทุกประการ; มีแต่ ai_guide ของแผน 2 ที่เปลี่ยน
- **Type:** Positive (isolation check) | **Priority:** Medium | **Related AC:** AC-RECORD-04

### TC-RECORD-020 — บันทึกผลติดตามสำเร็จ แล้ว redirect ไปหน้า review พร้อมข้อความชี้ขั้นตอนถัดไป
- **Preconditions:** แผนยังไม่มี record
- **Role:** home_visit_team
- **Steps:** บันทึกผลติดตามให้สำเร็จ แล้วสังเกต redirect/flash
- **Test Data:** ข้อมูลถูกต้องตาม happy path
- **Expected Result:** Redirect ไป `follow-up-plans.review` พร้อม flash ชี้ไปโมดูล DECISION; โมดูล RECORD เองไม่เรียก analyze/เปลี่ยน nurse_decision ใดๆ
- **Type:** Positive | **Priority:** Medium | **Related AC:** AC-RECORD-08

---

## DECISION — AI Risk Analysis & Mandatory Nurse Decision

### TC-DECISION-001 — Happy path: วิเคราะห์ด้วย AI แล้วยืนยัน "ติดตามซ้ำ" สำหรับเคส score_based เกิดแผนถัดไปและลิงก์สำเร็จ
- **Preconditions:** Referral score_based, มี plan #1 (scheduled) + record ที่บันทึกผลแล้ว, ไม่มี plan #2, `ai_analysis=null`, Ollama ปกติ
- **Role:** home_visit_team
- **Steps:** 1) เปิด review 2) POST analyze 3) ตรวจกล่อง AI-Draft 4) เลือก radio "ติดตามซ้ำ" + decision_notes 5) POST decision
- **Test Data:** `raw_notes`="ผู้ป่วยอาการทรงตัว", `pps_score`=60, `nurse_decision`=repeat, `decision_notes`="ติดตามต่อตามแผน"
- **Expected Result:** analyze บันทึก ai_analysis ครบ + timestamp; decision: redirect ไป referrals.show พร้อม flash สำเร็จ; nurse_decision=repeat, confirmed_by/at ถูกเซ็ต; plan #2 ถูกสร้าง (scheduled); next_follow_up_plan_id ชี้ไป plan ใหม่; referral.status=in_progress
- **Type:** Positive | **Priority:** High | **Related AC:** AC-DECISION-02,05,09,10

### TC-DECISION-002 — ยืนยันการตัดสินใจได้โดยไม่ต้องเรียก AI วิเคราะห์เลย (analysis เป็น optional)
- **Preconditions:** มี plan/record ปกติ, `ai_analysis=null`, ไม่เคยกด analyze
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** เปิด review (ข้าม analyze) แล้ว submit decision ทันที
- **Test Data:** `nurse_decision`=close, `decision_notes`=ว่าง, `risk_flag`=ไม่ติ๊ก
- **Expected Result:** สำเร็จ (ไม่ 422/500); `ai_analysis` ยังเป็น null; nurse_decision/confirmed_* บันทึกปกติ; referral ปิดเคสสำเร็จ
- **Type:** Positive/Edge | **Priority:** High | **Related AC:** AC-DECISION-05

### TC-DECISION-003 — Ollama ล้มเหลวขณะวิเคราะห์: ai_analysis เดิมไม่ถูกแก้ไข และพยาบาลยืนยันด้วยตนเองได้ตามปกติ
- **Preconditions:** `ai_analysis=null` หรือมีค่าเดิม, จำลอง Ollama ล่ม/timeout
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** 1) กด analyze ขณะ Ollama ล่ม 2) สังเกต flash error 3) ตรวจ ai_analysis ไม่เปลี่ยน 4) ยืนยันตัดสินใจเองต่อ
- **Test Data:** จำลอง `\RuntimeException` จาก AiService
- **Expected Result:** ไม่ 500, flash error, ai_analysis คงค่าเดิม; การยืนยันด้วยตนเองสำเร็จตามปกติ
- **Type:** Negative/Edge | **Priority:** High | **Related AC:** AC-DECISION-06,05

### TC-DECISION-004 — พยาบาลติ๊ก risk_flag=true ทั้งที่ AI ตอบ risk_detected=false — บันทึกตามที่พยาบาลเลือก
- **Preconditions:** `ai_analysis.risk_detected=false`
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** ติ๊ก risk_flag เองด้วยดุลยพินิจ แล้ว submit
- **Test Data:** submit `risk_flag=1`, `nurse_decision=repeat`
- **Expected Result:** สำเร็จ, `risk_flag=true` บันทึกจริง แม้ risk_detected=false — ไม่มี validation ปฏิเสธ
- **Type:** Edge | **Priority:** High | **Related AC:** AC-DECISION-04

### TC-DECISION-005 — พยาบาลไม่ติ๊ก risk_flag ทั้งที่ AI ตอบ risk_detected=true — บันทึกตามที่พยาบาลเลือก (ไม่ผูกกับ AI)
- **Preconditions:** `ai_analysis.risk_detected=true`
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** uncheck risk_flag แล้ว submit โดยไม่ส่ง key `risk_flag`
- **Test Data:** ไม่มี key `risk_flag`, `nurse_decision=refer`
- **Expected Result:** สำเร็จ, `risk_flag=false` บันทึก (default จาก `boolean()`) แม้ AI ระบุความเสี่ยง — ไม่มีการบล็อก
- **Type:** Edge/Security | **Priority:** High | **Related AC:** AC-DECISION-04

### TC-DECISION-006 — `suggested_decision` ของ AI ไม่ auto-fill: พยาบาลเลือกค่าอื่นที่ไม่ตรงกับ AI ต้องถูกบันทึกตามที่พยาบาลเลือก
- **Preconditions:** `ai_analysis.suggested_decision="refer"` (radio เลือกไว้ล่วงหน้า)
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** เปลี่ยนไปเลือก "ติดตามซ้ำ" เอง แล้ว submit
- **Test Data:** submit `nurse_decision=repeat`
- **Expected Result:** `nurse_decision='repeat'` (ตามที่เลือกจริง) ไม่ใช่ 'refer'
- **Type:** Positive | **Priority:** High | **Related AC:** AC-DECISION-03

### TC-DECISION-007 — ยืนยัน "ปิดเคส" ยกเลิกแผนที่ยังไม่เสร็จทั้งหมดของ referral แต่ไม่แก้แผนที่เสร็จแล้ว
- **Preconditions:** plan#1=done (record นี้), plan#2=scheduled, plan#3=scheduled, (บาง plan cancelled ก่อนแล้ว)
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** ยืนยัน `nurse_decision=close` บน plan#1 แล้วตรวจสถานะทุกแผน+referral
- **Test Data:** `nurse_decision=close`
- **Expected Result:** plan#2,#3 → cancelled; plan#1 คงเป็น done; plan cancelled เดิมไม่เปลี่ยน; referral.status=closed, closed_at ถูกเซ็ต; next_follow_up_plan_id ยังเป็น null
- **Type:** Positive | **Priority:** High | **Related AC:** AC-DECISION-07

### TC-DECISION-008 — ยืนยัน "ติดตามซ้ำ" สำหรับ fixed_count ที่แผนถัดไปมีอยู่ก่อนแล้ว: next_follow_up_plan_id ยังเป็น null แต่แผนถัดไปยังเข้าถึงได้
- **Preconditions:** referral fixed_count มี plan#1 (กำลังดำเนินการ), plan#2 scheduled (มีอยู่ล่วงหน้า), plan#3 scheduled
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** ยืนยัน `nurse_decision=repeat` บน plan#1 แล้วตรวจ next_follow_up_plan_id และ followUpPlans ทั้งหมด
- **Test Data:** `nurse_decision=repeat`
- **Expected Result:** generateNextPlan คืน null (มี plan#2 อยู่แล้ว); จำนวนแผนยังเป็น 3 (ไม่ใช่ 4); next_follow_up_plan_id=null; plan#2 ยัง scheduled และเข้าถึงได้ผ่าน followUpPlans(); referral.status=in_progress
- **Type:** Edge | **Priority:** High | **Related AC:** AC-DECISION-09

### TC-DECISION-009 — ยืนยัน "ส่งต่อแพทย์" (refer) ให้ผลลัพธ์การจัดกำหนดการเหมือนกับ "ติดตามซ้ำ" (repeat) ทุกประการ
- **Preconditions:** สอง referral score_based ที่ข้อมูลเริ่มต้นเหมือนกันทุกประการ (A จะ repeat, B จะ refer)
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** ยืนยัน A ด้วย repeat, B ด้วย refer แล้วเทียบผลลัพธ์การจัดกำหนดการ
- **Test Data:** A: `nurse_decision=repeat`; B: `nurse_decision=refer`; อื่นเหมือนกัน
- **Expected Result:** ผลลัพธ์ scheduling เหมือนกันทุกประการ ต่างเฉพาะค่า nurse_decision ที่บันทึก — ยืนยันพฤติกรรมปัจจุบันตาม AC-DECISION-08
- **Type:** Positive/Edge (documentation of parity) | **Priority:** Medium | **Related AC:** AC-DECISION-08

### TC-DECISION-010 — ส่งค่า nurse_decision ที่ไม่ถูกต้อง (ไม่อยู่ใน repeat/refer/close) ถูกปฏิเสธ
- **Preconditions:** มี plan/record พร้อมยืนยัน
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** POST decision ด้วยค่าที่ไม่อยู่ใน enum
- **Test Data:** `nurse_decision="cancel"` (หรือ "" หรือไม่ส่ง)
- **Expected Result:** Validation error (attribute "การตัดสินใจ"), ไม่มีการเขียนค่าใดๆ ลง DB
- **Type:** Negative | **Priority:** High | **Related AC:** AC-DECISION-03

### TC-DECISION-011 — เข้าถึง review/analyze/decision ของแผนที่ยังไม่มี FollowUpRecord ต้องได้ 404
- **Preconditions:** plan ที่ scheduled และยังไม่มี record
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** เรียกทั้ง 3 route
- **Test Data:** plan ไม่มี record
- **Expected Result:** ทั้ง 3 คำขอตอบ HTTP 404, ไม่มีการสร้าง/แก้ไขข้อมูลใดๆ
- **Type:** Negative | **Priority:** High | **Related AC:** AC-DECISION-01

### TC-DECISION-012 — ผู้ใช้ที่ยังไม่ล็อกอินถูก redirect ไปหน้า login เมื่อพยายามเข้าถึงเส้นทางใดในโมดูลนี้
- **Preconditions:** guest, มี plan/record ที่ถูกต้อง
- **Role:** guest
- **Steps:** เรียกทั้ง 3 route โดยไม่มี auth session
- **Test Data:** —
- **Expected Result:** ทั้ง 3 คำขอ redirect ไปหน้า login; ไม่มีข้อมูลใดถูกสร้าง/แก้ไข
- **Type:** Security/Negative | **Priority:** High | **Related AC:** AC-DECISION-13

### TC-DECISION-013 — ผู้ใช้ role ใดก็ได้ที่ล็อกอินแล้วสามารถยืนยันการตัดสินใจได้ (ไม่มีการจำกัด role)
- **Preconditions:** ผู้ใช้ทดสอบ 3 role พร้อม plan/record แยกกัน
- **Role:** ward_staff, home_visit_team, admin (ทดสอบทีละ role)
- **Steps:** ยืนยันการตัดสินใจด้วยผู้ใช้แต่ละ role
- **Test Data:** `nurse_decision=repeat` (หรือค่าอื่นที่ไม่ใช่ close)
- **Expected Result:** ทั้งสาม role ยืนยันสำเร็จ (ไม่มี 403); confirmed_by=Auth::id() ของผู้ทำจริง
- **Type:** Positive/Security | **Priority:** Medium | **Related AC:** AC-DECISION-13

### TC-DECISION-014 — ยืนยันการตัดสินใจโดยไม่กรอก decision_notes (optional field)
- **Preconditions:** มี plan/record พร้อมยืนยัน
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** Submit โดยไม่ส่ง decision_notes
- **Test Data:** `nurse_decision=repeat`, ไม่มี key `decision_notes`
- **Expected Result:** ผ่าน validation; `decision_notes=null`; กระบวนการดำเนินต่อตามปกติ
- **Type:** Positive/Edge | **Priority:** Low | **Related AC:** AC-DECISION-11

### TC-DECISION-015 — วิเคราะห์ AI ที่ parse_error=true ไม่บล็อกการยืนยันการตัดสินใจด้วยตนเอง
- **Preconditions:** analyze แล้ว Ollama ตอบ HTTP 200 แต่ไม่ใช่ JSON ที่ถูกต้อง
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** 1) กด analyze (จำลอง non-JSON) 2) ตรวจหน้า review แสดงเตือน parse error, radio ไม่มีค่า pre-select 3) เลือกและ submit เอง
- **Test Data:** raw response ที่ไม่ใช่ JSON
- **Expected Result:** `ai_analysis.parse_error=true`, `ai_analysis_generated_at` ถูกเซ็ต; การยืนยันด้วยตนเองยังสำเร็จตามปกติ
- **Type:** Edge | **Priority:** Medium | **Related AC:** AC-DECISION-05,06

### TC-DECISION-016 — วิเคราะห์ซ้ำก่อนยืนยัน: ผลวิเคราะห์ครั้งใหม่เขียนทับครั้งก่อนหน้า
- **Preconditions:** `ai_analysis` มีค่าจากครั้งแรก, `isConfirmed()=false`
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** กด "↻ วิเคราะห์ใหม่" (จำลองผลต่างจากครั้งแรก) แล้วตรวจค่า
- **Test Data:** ผลครั้งแรก vs ครั้งที่สองต่างกัน (เช่น risk_detected false→true)
- **Expected Result:** `ai_analysis` ถูกแทนที่ทั้งหมด (ไม่ merge); `ai_analysis_generated_at` เปลี่ยนเป็นเวลาล่าสุด
- **Type:** Positive/Edge | **Priority:** Low | **Related AC:** AC-DECISION-06

### TC-DECISION-017 — พยายามวิเคราะห์หรือยืนยันการตัดสินใจซ้ำหลังจากยืนยันไปแล้ว (record confirmed) — gap observation
- **Preconditions:** `FollowUpRecord.isConfirmed()=true` จากการยืนยันครั้งก่อน
- **Role:** ผู้ใช้ที่ล็อกอินแล้ว
- **Steps:** 1) เปิด review — ฟอร์ม analyze/decision ถูกซ่อนใน UI 2) ยิง POST analyze/decision ตรงๆ (เช่นผ่าน API client)
- **Test Data:** `nurse_decision` ใหม่ที่ต่างจากที่ยืนยันไปแล้ว
- **Expected Result (ต้องตรวจยืนยันกับพฤติกรรมจริง):** ปัจจุบัน controller ไม่มี guard กันการเรียกซ้ำหลัง confirm แล้ว (เช็คแค่ `abort_unless($plan->record, 404)`) — คำขอจะ "สำเร็จ" ทางเทคนิคและเขียนทับค่าที่ยืนยันแล้วได้อีก แม้ UI ซ่อนฟอร์มไว้ — บันทึกเป็นข้อสังเกตให้ทีมพัฒนาพิจารณาเพิ่ม guard ฝั่ง backend
- **Type:** Security/Edge (gap observation) | **Priority:** Medium | **Related AC:** AC-DECISION-02,12

---

## ADMINRBAC — User & Role Administration, Access Control Matrix

### TC-ADMINRBAC-001 — แอดมินเลื่อนสิทธิ์ ward_staff เป็น home_visit_team และตรวจสอบสิทธิ์เข้าถึงที่เปลี่ยนไป
- **Preconditions:** ผู้ใช้ A (admin) ล็อกอินอยู่, ผู้ใช้ B (ward_staff)
- **Role:** admin (ผู้กระทำ)
- **Steps:** 1) A แก้ role ของ B เป็น home_visit_team 2) ตรวจ DB 3) ล็อกอินเป็น B ทดสอบสิทธิ์
- **Test Data:** role=home_visit_team
- **Expected Result:** redirect + flash สำเร็จ; users.role ของ B = home_visit_team; B ยังเข้า admin.* ไม่ได้ (403)
- **Type:** Positive | **Priority:** High | **Related AC:** AC-ADMINRBAC-03,08

### TC-ADMINRBAC-002 — แอดมินเลื่อนสิทธิ์ผู้ใช้เป็น admin และตรวจสอบว่าเข้า admin/* ได้
- **Preconditions:** ผู้ใช้ A (admin), ผู้ใช้ C (ward_staff/home_visit_team)
- **Role:** admin
- **Steps:** 1) A เปลี่ยน role ของ C เป็น admin 2) ล็อกอินเป็น C 3) เข้าทั้ง 8 route ของ admin.*
- **Test Data:** role=admin
- **Expected Result:** อัปเดตสำเร็จ; C เข้าได้ทุกเส้นทางไม่ถูก 403
- **Type:** Positive | **Priority:** High | **Related AC:** AC-ADMINRBAC-03,10

### TC-ADMINRBAC-003 — ward_staff พยายามเข้าทุกเส้นทาง admin/* โดยตรงผ่าน URL
- **Preconditions:** ผู้ใช้ D (ward_staff)
- **Role:** ward_staff
- **Steps:** เข้าทั้ง 8 route โดยตรง (case-types index/create/store/edit/update, users index/edit/update)
- **Test Data:** id ที่มีอยู่จริง
- **Expected Result:** ทั้ง 8 คำขอได้ 403; ไม่มีการเปลี่ยนแปลงข้อมูล
- **Type:** Security/Negative | **Priority:** High | **Related AC:** AC-ADMINRBAC-07

### TC-ADMINRBAC-004 — home_visit_team พยายามเข้าทุกเส้นทาง admin/* โดยตรงผ่าน URL
- **Preconditions:** ผู้ใช้ E (home_visit_team)
- **Role:** home_visit_team
- **Steps:** เหมือน TC-003
- **Test Data:** เดียวกันกับ TC-003
- **Expected Result:** ทั้ง 8 คำขอได้ 403
- **Type:** Security/Negative | **Priority:** High | **Related AC:** AC-ADMINRBAC-08

### TC-ADMINRBAC-005 — ผู้ใช้ที่ไม่ได้ล็อกอินเข้า /admin/users → redirect ไป login ไม่ใช่ 403
- **Preconditions:** guest
- **Role:** guest
- **Steps:** เรียก `/admin/users` และเส้นทาง admin อื่นๆ โดยตรง
- **Test Data:** —
- **Expected Result:** HTTP 302 redirect ไป login ไม่ใช่ 403
- **Type:** Negative/Security | **Priority:** High | **Related AC:** AC-ADMINRBAC-09

### TC-ADMINRBAC-006 — แอดมินลดสิทธิ์ตัวเองเป็น ward_staff (self-demotion, current gap)
- **Preconditions:** ผู้ใช้ F (admin)
- **Role:** admin (กระทำต่อตนเอง)
- **Steps:** 1) F แก้ role ตัวเองเป็น ward_staff 2) ตรวจ redirect/flash 3) request ถัดไปของ F เข้า admin.*
- **Test Data:** role=ward_staff สำหรับ user id ของตนเอง
- **Expected Result (as-is/known gap):** เปลี่ยนสำเร็จไม่มี error; request ถัดไปของ F ได้ 403 ทันที — ไม่มี self-demotion safeguard
- **Type:** Edge/Security (risk documentation) | **Priority:** High | **Related AC:** AC-ADMINRBAC-12

### TC-ADMINRBAC-007 — PUT /admin/users/{user} ด้วย role="superadmin" (ค่าที่ไม่อยู่ใน ROLES) → validation error
- **Preconditions:** ผู้ใช้ A (admin), ผู้ใช้เป้าหมาย G
- **Role:** admin
- **Steps:** ส่ง role ผิดหลายรูปแบบ: "superadmin", "", ขาด field, "Admin"
- **Test Data:** role="superadmin"; ""; (ขาด); "Admin"
- **Expected Result:** ทุก sub-case ถูก reject; users.role ของ G ไม่เปลี่ยน
- **Type:** Negative | **Priority:** High | **Related AC:** AC-ADMINRBAC-06

### TC-ADMINRBAC-008 — อัปเดต department โดยไม่เปลี่ยน role (independent update)
- **Preconditions:** ผู้ใช้ H (home_visit_team, department="แผนกเดิม")
- **Role:** admin
- **Steps:** 1) เปลี่ยน department คง role เดิม 2) ตรวจ DB 3) department="" 4) department >255 ตัวอักษร
- **Test Data:** department="แผนกใหม่"; ""; 256 ตัวอักษร
- **Expected Result:** sub-case 1,3 สำเร็จ role ไม่เปลี่ยน; sub-case 4 ถูก reject
- **Type:** Positive/Edge | **Priority:** Medium | **Related AC:** AC-ADMINRBAC-04,05

### TC-ADMINRBAC-009 — ผู้ใช้ที่ลงทะเบียนใหม่ผ่าน Breeze ได้ role เริ่มต้น ward_staff และเข้า admin/* ไม่ได้
- **Preconditions:** Breeze scaffolding ติดตั้งแล้ว (ดูหมายเหตุปิดท้าย); email ใหม่
- **Role:** guest → ward_staff
- **Steps:** 1) สมัครผ่าน /register 2) ตรวจ role ใน DB 3) เข้า admin/users, admin/case-types 4) เข้า dashboard/referrals
- **Test Data:** ข้อมูลสมัครใหม่
- **Expected Result:** role=ward_staff เสมอ; step 3 ได้ 403 ทั้งคู่; step 4 เข้าได้ปกติ
- **Type:** Positive/Security | **Priority:** Medium | **Related AC:** AC-ADMINRBAC-13

### TC-ADMINRBAC-010 — non-admin routes เข้าได้เท่าเทียมกันทุก role (ยืนยันข้อเท็จจริง ไม่ใช่ช่องโหว่)
- **Preconditions:** 3 ผู้ใช้ 1 คนต่อ role พร้อม record ที่จำเป็น
- **Role:** ward_staff, home_visit_team, admin (ทดสอบทั้ง 3)
- **Steps:** เข้าเส้นทาง non-admin ทั้งหมด (dashboard, profile, referrals.*, follow-up-plans.*) ด้วยแต่ละ role
- **Test Data:** referral/plan id ที่มีอยู่จริง
- **Expected Result:** ทุก role เข้าได้เหมือนกัน ไม่มี 403 จาก RBAC
- **Type:** Positive (regression) | **Priority:** Medium | **Related AC:** AC-ADMINRBAC-11

### TC-ADMINRBAC-011 — admin index list แสดงผู้ใช้ครบทุก role เรียงตามชื่อ
- **Preconditions:** ผู้ใช้อย่างน้อย 3 คน คนละ role
- **Role:** admin
- **Steps:** เปิด `/admin/users` ตรวจลำดับรายชื่อ
- **Test Data:** ชื่อผู้ใช้หลากหลาย
- **Expected Result:** แสดงครบทุกคน เรียงตาม name ASC
- **Type:** Positive | **Priority:** Low | **Related AC:** AC-ADMINRBAC-01

### TC-ADMINRBAC-012 — ไม่มี seeder สร้าง admin คนแรก (setup gap)
- **Preconditions:** DB ใหม่หลัง migrate ไม่มีผู้ใช้เลย
- **Role:** N/A (setup/documentation check)
- **Steps:** ตรวจ DatabaseSeeder และ seeder อื่นๆ
- **Test Data:** —
- **Expected Result:** ไม่พบ seeder ที่สร้าง admin เริ่มต้น (มีแต่ CaseTypeSeeder) — บันทึกเป็น deployment gap
- **Type:** Edge (documentation/setup gap) | **Priority:** Medium | **Related AC:** AC-ADMINRBAC-14

**หมายเหตุ:** TC-ADMINRBAC-009 สมมติว่าขั้นตอนติดตั้ง Breeze (SETUP.md ขั้นที่ 2) เสร็จสิ้นแล้ว — ถ้ายังไม่ได้ติดตั้ง ให้ block/skip เคสนี้ ไม่ใช่ถือว่า fail

---

## DASHNFR — Dashboard KPIs, AI Resilience & Design-System Compliance

### กลุ่ม A — KPI ของแดชบอร์ด

**TC-DASHNFR-001 — นับ totalPatients โดยไม่กรองใดๆ**
Preconditions: seed ผู้ป่วย 7 ราย (zone/referral status ผสมกัน) | Role: ผู้ใช้ verified | Steps: เปิด `/dashboard` | Expected: การ์ด "ผู้ป่วยทั้งหมด" = 7 ไม่กรองด้วย zone/สถานะ | Type: Positive | Priority: Medium | AC: AC-DASHNFR-01

**TC-DASHNFR-002 — dueTodayCount นับเฉพาะวันนี้เป๊ะๆ**
Preconditions: แผน A (due=วันนี้), B (due=เมื่อวาน), C (due=พรุ่งนี้) ทั้งหมด scheduled | Role: verified | Steps: เปิด `/dashboard` | Expected: การ์ด "วันนี้ต้องติดตาม" = 1 (เฉพาะ A) | Type: Edge | Priority: High | AC: AC-DASHNFR-02

**TC-DASHNFR-003 — overdueCount ไม่รวมวันนี้ (boundary กับ TC-002)**
Preconditions: ใช้ชุดข้อมูล TC-002 | Role: verified | Steps: อ่านการ์ด "เกินกำหนดติดตาม" | Expected: = 1 (เฉพาะ B) ไม่รวม A/C | Type: Edge | Priority: High | AC: AC-DASHNFR-03

**TC-DASHNFR-004 — แผนที่ due_date เป็นอนาคตต้องไม่ปรากฏในตาราง "upcomingPlans"**
Preconditions: ใช้ชุดข้อมูล TC-002/003 | Role: verified | Steps: ตรวจตาราง "รายการที่ต้องติดตาม" | Expected: แสดง A,B เรียง B ก่อน A; C ไม่แสดง | Type: Edge | Priority: High | AC: AC-DASHNFR-05

**TC-DASHNFR-005 — upcomingPlans จำกัดที่ 20 แถวและเรียงลำดับถูกต้อง**
Preconditions: seed แผน scheduled 25 รายการ due<=วันนี้ | Role: verified | Steps: เปิด `/dashboard` | Expected: แสดงพอดี 20 แถว เรียงเก่าสุดก่อน, 5 รายการที่ใกล้วันนี้ที่สุดหลุด limit | Type: Edge | Priority: Medium | AC: AC-DASHNFR-05

**TC-DASHNFR-006 — riskCount นับบันทึกเสี่ยงของเคสที่ยังเปิดอยู่**
Preconditions: referral R1 active + record risk_flag=true | Role: verified | Steps: เปิด `/dashboard` | Expected: การ์ด "กลุ่มเสี่ยง" = 1 | Type: Positive | Priority: High | AC: AC-DASHNFR-04

**TC-DASHNFR-007 — riskCount ไม่นับบันทึกเสี่ยงของเคสที่ปิดแล้ว**
Preconditions: referral R2 closed + record risk_flag=true (ต่อจาก/แยกจาก TC-006) | Role: verified | Steps: เปิด `/dashboard` รันทั้ง R1+R2 พร้อมกัน | Expected: การ์ดแสดง 1 (นับ R1 เท่านั้น) ไม่ใช่ 2 | Type: Negative | Priority: High | AC: AC-DASHNFR-04

**TC-DASHNFR-008 — recentRiskRecords แสดงบันทึกของเคสปิดแล้วได้ (ตัดกันกับ TC-007)**
Preconditions: ชุดข้อมูล TC-007 | Role: verified | Steps: ตรวจ "สัญญาณเสี่ยงล่าสุด" | Expected: บันทึกของ R2 (ปิดแล้ว) ปรากฏในรายการนี้ — สาธิตความไม่สอดคล้องที่ตั้งใจไว้ | Type: Edge | Priority: High | AC: AC-DASHNFR-06

**TC-DASHNFR-009 — recentRiskRecords จำกัด 5 รายการ เรียงตาม confirmed_at ล่าสุด**
Preconditions: seed บันทึกเสี่ยง 6 รายการ confirmed_at ต่างกัน | Role: verified | Steps: เปิด `/dashboard` | Expected: แสดง 5 รายการล่าสุด (T6→T2), T1 ไม่ปรากฏ | Type: Edge | Priority: Medium | AC: AC-DASHNFR-06

**TC-DASHNFR-010 — แบนเนอร์ pendingReviewCount แสดง/หายตามค่า**
Preconditions: ไม่มี pending_review ตอนแรก | Role: verified | Steps: 1) เปิด dashboard (ไม่มีแบนเนอร์) 2) seed 3 referral pending_review 3) รีเฟรช | Expected: รอบแรกไม่มีแบนเนอร์, รอบสองมีแบนเนอร์ระบุ 3 รายการ | Type: Positive | Priority: Medium | AC: AC-DASHNFR-07

**TC-DASHNFR-011 — ค่า KPI เป็นศูนย์เมื่อไม่มีข้อมูล (zero-state)**
Preconditions: DB ว่าง | Role: verified | Steps: เปิด `/dashboard` | Expected: ทุกการ์ด=0, ข้อความ zero-state, ไม่มี exception | Type: Edge | Priority: Medium | AC: AC-DASHNFR-01,02,03,04

### กลุ่ม B — Access Control ของ /dashboard

**TC-DASHNFR-012 — ผู้ใช้ล็อกอินแล้วแต่ยังไม่ verified ถูกกันจาก /dashboard**
Preconditions: user email_verified_at=null | Role: unverified | Steps: เข้า `/dashboard` | Expected: redirect ไปหน้ายืนยันอีเมล | Type: Security | Priority: High | AC: AC-DASHNFR-08

**TC-DASHNFR-013 — ผู้ใช้เดียวกัน (ยังไม่ verified) ยังเข้าเส้นทางอื่นที่ใช้แค่ auth ได้**
Preconditions: ต่อจาก TC-012 | Role: unverified | Steps: เข้า `/referrals`, `/profile` | Expected: เข้าได้ปกติ ไม่ redirect verification | Type: Positive | Priority: High | AC: AC-DASHNFR-08

**TC-DASHNFR-014 — ผู้ใช้ verified แล้วเข้า /dashboard ได้ตามปกติ (positive control)**
Preconditions: email_verified_at ไม่ null | Role: verified | Steps: เข้า `/dashboard` | Expected: HTTP 200 | Type: Positive | Priority: High | AC: AC-DASHNFR-08

**TC-DASHNFR-015 — ผู้ใช้ที่ไม่ได้ล็อกอินเลยถูกกันจาก /dashboard**
Preconditions: guest | Role: guest | Steps: เข้า `/dashboard` | Expected: redirect ไป login | Type: Security | Priority: High | AC: AC-DASHNFR-08

### กลุ่ม C — ความทนทานของ AI/Ollama

**TC-DASHNFR-016 — ai-summary: จำลอง Ollama timeout/เชื่อมต่อไม่ได้**
Preconditions: referral พร้อมสรุป, OLLAMA_URL ไม่ตอบสนอง | Role: ward staff | Steps: POST ai-summary ขณะ Ollama ไม่ตอบสนอง | Expected: ไม่ 500, flash error, ai_summary/confirmed_summary ไม่เปลี่ยน, log "Ollama connection failed" | Type: Negative | Priority: High | AC: AC-DASHNFR-09,12

**TC-DASHNFR-017 — follow-up-plans.guide.generate: จำลอง Ollama timeout**
Preconditions: plan ไม่มี guide, Ollama ไม่ตอบสนอง | Role: home_visit_team | Steps: POST guide.generate | Expected: ไม่ 500, flash error, ai_guide ไม่ถูกเขียน | Type: Negative | Priority: High | AC: AC-DASHNFR-10,12

**TC-DASHNFR-018 — follow-up-plans.analyze: จำลอง Ollama ตอบ non-2xx**
Preconditions: record ยังไม่วิเคราะห์, Ollama ตอบ non-2xx | Role: พยาบาล | Steps: POST analyze | Expected: ไม่ 500, flash error, ai_analysis/nurse_decision ไม่ถูกเขียน, log "Ollama request failed" (ต่างจาก TC-016) | Type: Negative | Priority: High | AC: AC-DASHNFR-11,12

**TC-DASHNFR-019 — Log message แยกกันระหว่าง connection-failure และ HTTP-failure (service-level)**
Preconditions: log จาก TC-016/018 | Role: — | Steps: เปรียบเทียบข้อความ log | Expected: ข้อความต่างกันชัดเจนตามสาเหตุจริง | Type: Config-review | Priority: Low | AC: AC-DASHNFR-12

**TC-DASHNFR-020 — parseJsonResponse fallback เมื่อ AI ตอบไม่ใช่ JSON ที่ถูกต้อง**
Preconditions: mock raw response ที่ parse ไม่ได้ | Role: — (service-level) | Steps: เรียก 1 ใน 3 เมธอดของ AiService เป็นตัวแทน | Expected: `parse_error:true`, `raw_response` เก็บครบ, `Log::warning` ถูกเรียก | Type: Edge | Priority: Medium | AC: AC-DASHNFR-13

### กลุ่ม D — Config-review

**TC-DASHNFR-021 — ตรวจสอบว่า OLLAMA_URL ไม่ใช่ endpoint สาธารณะ**
Preconditions: `.env` ของสภาพแวดล้อมที่กำลังตรวจ | Role: ผู้ดูแลระบบ | Steps: ตรวจ host ของ `OLLAMA_URL` ว่าเป็น private/intranet | Expected: ผ่านเฉพาะเมื่อเป็น intranet address; block deploy ถ้าเป็น public/cloud | Type: Config-review | Priority: High | AC: AC-DASHNFR-14

### กลุ่ม E — Visual/Manual QA

**TC-DASHNFR-022 — Badge สี+ข้อความคู่กันเสมอ** *(Manual/Visual)*
Preconditions: ข้อมูลตัวอย่างครบทุกสถานะ/zone | Role: Visual QA | Steps: ตรวจ badge ทุกจุดในแดชบอร์ด/รายการ | Expected: ทุก badge มีสี+ข้อความคู่กันเสมอ | Type: Visual | Priority: Medium | AC: AC-DASHNFR-15

**TC-DASHNFR-023 — AI-Draft box สลับสถานะ ร่าง → ยืนยันแล้ว** *(Manual/Visual)*
Preconditions: 1 เคสยังไม่ยืนยัน, 1 เคสยืนยันแล้ว | Role: Visual QA | Steps: เปิดทั้งสองเคสตรวจกรอบ/ป้าย | Expected: เส้นประ+ป้ายร่าง ↔ เส้นทึบ+ป้ายยืนยันแล้ว ตรงตาม isConfirmed() | Type: Visual | Priority: High | AC: AC-DASHNFR-16

**TC-DASHNFR-024 — Nurse-Decision แสดงเป็น radio-card ไม่ใช่ dropdown** *(Manual/Visual)*
Preconditions: มี record พร้อมตัดสินใจ | Role: Visual QA | Steps: เปิดหน้าตัดสินใจ ตรวจ UI | Expected: radio-card เห็นทั้งหมดพร้อมกัน ไม่มี `<select>` | Type: Visual | Priority: High | AC: AC-DASHNFR-17

**TC-DASHNFR-025 — KPI tile ใช้สี semantic เฉพาะเมื่อค่าผิดปกติ** *(Manual/Visual)*
Preconditions: overdueCount=0 กับ >0 สองรอบ | Role: Visual QA | Steps: เปรียบเทียบสีตัวเลขทั้งสองรอบ | Expected: =0 สี neutral, >0 สี risk | Type: Visual | Priority: Low | AC: AC-DASHNFR-18

**TC-DASHNFR-026 — บันทึกช่องว่าง sidebar-vs-topnav เป็น known gap ไม่ใช่ defect ใหม่** *(Manual checklist)*
Preconditions: build ปัจจุบันของ Blade views | Role: Visual QA / compliance reviewer | Steps: เปิดทุกหน้าหลัก สังเกต nav | Expected: ยืนยันยังใช้ top-nav; บันทึกเป็น "known/accepted deviation" ไม่ mark เป็น FAIL ใหม่ | Type: Visual | Priority: Low | AC: AC-DASHNFR-19

**TC-DASHNFR-027 — ai-summary error ไม่ทำให้ข้อมูลเดิมของใบส่งต่อเสียหาย (regression กับ AC-09)**
Preconditions: referral มี raw_notes กรอกไว้แล้ว | Role: ward staff | Steps: 1) บันทึกใบส่งต่อ 2) ทำ Ollama ล่ม (ตาม TC-016) แล้วกด ai-summary 3) ตรวจฟิลด์เดิม | Expected: ฟิลด์เดิมไม่ถูกแก้ไข/ลบ, มีแค่ flash error ชั่วคราว | Type: Negative | Priority: Medium | AC: AC-DASHNFR-09

**หมายเหตุ:** TC กลุ่ม E (Visual) ทั้งหมดเป็น manual QA ล้วน ไม่ automate — ทำ checklist ซ้ำทุกครั้งที่แก้ Blade view ที่เกี่ยวข้อง TC-DASHNFR-021 (config-review) อยู่ใน deployment checklist แยกจาก regression ปกติ เพราะเป็นเรื่อง compliance ด้าน PHI
