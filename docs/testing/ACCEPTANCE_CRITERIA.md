# Acceptance Criteria — Chira Continuity Care (Triple C)

เอกสารนี้รวบรวม Acceptance Criteria (AC) ของทุกโมดูลในระบบ แต่ละ AC มี ID เฉพาะ (`AC-<MODULE>-##`) ที่ใช้
อ้างอิงจาก [TEST_CASES.md](TEST_CASES.md) ในคอลัมน์ "Related AC" — ดู [TEST_PLAN.md](TEST_PLAN.md) สำหรับ
ภาพรวมขอบเขต กลยุทธ์การทดสอบ และรายการ known-gap/product-decision ที่ AC หลายข้อด้านล่างอ้างถึง

## สารบัญโมดูล

1. [INTAKE — Referral Intake & Zone Resolution](#intake--referral-intake--zone-resolution)
2. [SUMMARY — AI Draft Summary & Nurse Care-Plan Confirmation](#summary--ai-draft-summary--nurse-care-plan-confirmation)
3. [SCHED — Visit Scheduling Engine & Case Type / Visit Rule Admin](#sched--visit-scheduling-engine--case-type--visit-rule-admin)
4. [RECORD — Follow-Up Guide & Outcome Recording](#record--follow-up-guide--outcome-recording)
5. [DECISION — AI Risk Analysis & Mandatory Nurse Decision](#decision--ai-risk-analysis--mandatory-nurse-decision)
6. [ADMINRBAC — User & Role Administration, Access Control Matrix](#adminrbac--user--role-administration-access-control-matrix)
7. [DASHNFR — Dashboard KPIs, AI Resilience & Design-System Compliance](#dashnfr--dashboard-kpis-ai-resilience--design-system-compliance)

---

## INTAKE — Referral Intake & Zone Resolution

**AC-INTAKE-01** — เมื่อผู้ใช้ที่ล็อกอินแล้ว (ไม่ว่า role ใดก็ตาม: `ward_staff` / `home_visit_team` / `admin`) กรอกแบบฟอร์มสร้างใบส่งต่อครบตามฟิลด์ที่บังคับ (`source_type`, `patient_hn`, `patient_name`, `zone`, `raw_notes`) และกดบันทึก ระบบต้องสร้าง `Patient` และ `Referral` ในทรานแซกชันเดียว (`DB::transaction`) โดย `Referral.status` ต้องถูกตั้งเป็น `Referral::STATUS_PENDING_REVIEW` และ `Referral.created_by` ต้องถูกตั้งเป็น id ของผู้ใช้ที่ล็อกอินอยู่

**AC-INTAKE-02** — เมื่อ `source_type` ที่ส่งมาเป็นหนึ่งใน `Referral::SOURCE_WARD` (`ward`), `Referral::SOURCE_OPD` (`opd`), `Referral::SOURCE_INTERNAL_DEPT` (`internal_dept`), หรือ `Referral::SOURCE_EXTERNAL_HOSPITAL` (`external_hospital`) ระบบต้องยอมรับค่านั้นและบันทึกลง `Referral.source_type` ตรงตามที่ส่งมา; ค่าอื่นที่ไม่อยู่ในสี่ค่านี้ต้องถูกปฏิเสธด้วย validation error

**AC-INTAKE-03** — เมื่อผู้ใช้ส่งใบส่งต่อใหม่ด้วย `patient_hn` ที่ตรงกับผู้ป่วยที่มีอยู่แล้วในระบบ ระบบต้อง **อัปเดต** ข้อมูลผู้ป่วยแถวเดิม (ผ่าน `Patient::updateOrCreate(['hn' => ...], [...])`) ด้วยข้อมูลชุดใหม่ (ชื่อ, national_id, dob, phone, address, sub_district, district, province, zone) ไม่สร้างผู้ป่วยแถวใหม่ซ้ำ — จำนวนแถวใน `patients` ที่มี `hn` เดียวกันต้องยังคงเป็น 1 แถวเสมอ และ `Referral` แถวใหม่ต้องถูกสร้างและเชื่อมกับ `patient_id` เดิม

**AC-INTAKE-04** — เมื่อ `zone_override` ไม่ถูกส่งมาหรือส่งมาเป็นค่า falsy (ไม่ติ๊ก) ระบบต้องเรียก `ZoneResolver->resolve($data['patient_sub_district'])` และใช้ผลลัพธ์นั้นแทนค่า `zone` ที่ผู้ใช้เลือกไว้ในฟอร์ม เฉพาะเมื่อผลลัพธ์ไม่เป็น `null`; ถ้า resolver คืนค่า `null` (เพราะ `patient_sub_district` ว่าง หรือ `config('catchment.in_area_sub_districts')` ว่าง) ระบบต้องใช้ค่า `zone` ที่ผู้ใช้เลือกเองในฟอร์มแทน

**AC-INTAKE-05** — เมื่อ `zone_override` ถูกส่งมาเป็นค่า truthy (ติ๊กแล้ว) ระบบต้อง **ไม่** เรียก logic การตรวจจับอัตโนมัติมาทับค่า — ต้องใช้ค่า `zone` ที่ผู้ใช้เลือกในฟอร์มตรงตามที่ส่งมาเสมอ ไม่ว่า `patient_sub_district` หรือ `catchment.in_area_sub_districts` จะเป็นอย่างไร

**AC-INTAKE-06** — `ZoneResolver->resolve()` ต้องเทียบ `patient_sub_district` แบบไม่สนตัวพิมพ์เล็ก/ใหญ่ (case-insensitive) และตัดช่องว่างหน้า-หลัง (trim) ก่อนเทียบกับรายการใน `config('catchment.in_area_sub_districts')`; ถ้าพบว่าตรงกับรายการต้องคืนค่า `Patient::ZONE_IN_AREA`, ถ้าไม่ตรง (แต่ค่า sub_district ไม่ว่างและรายการ config ไม่ว่าง) ต้องคืนค่า `Patient::ZONE_OUT_AREA`

**AC-INTAKE-07** — เมื่อเรียก `GET /referrals/zone-lookup?sub_district=...` ระบบต้องตอบกลับเป็น JSON `{zone, label}` โดย `label` ต้องเป็นหนึ่งในสามข้อความไทยตามผลลัพธ์ของ resolver: "ระบบตรวจพบ: อยู่ในเขตรับผิดชอบ" เมื่อ zone เป็น `in_area`, "ระบบตรวจพบ: อยู่นอกเขตรับผิดชอบ" เมื่อ zone เป็น `out_area`, และ "ระบบยังไม่สามารถตรวจจับเขตอัตโนมัติได้ กรุณาเลือกเอง" เมื่อ zone เป็น `null`

**AC-INTAKE-08** — เมื่อไฟล์แนบที่อัปโหลดในฟิลด์ `attachments.*` มีขนาดเกิน 10240 KB (10MB) หรือมี MIME type ที่ไม่ใช่ `pdf`, `jpg`, `jpeg`, หรือ `png` ระบบต้องปฏิเสธการบันทึกทั้งฟอร์มด้วย validation error และต้อง **ไม่** สร้างทั้ง `Referral` และไฟล์แนบใด ๆ (rollback ทั้งทรานแซกชัน)

**AC-INTAKE-09** — เมื่อไฟล์แนบผ่าน validation ระบบต้องจัดเก็บไฟล์บน disk `local` (private) ภายใต้ path `referral-attachments/` และสร้างแถว `ReferralAttachment` หนึ่งแถวต่อไฟล์ โดยบันทึก `original_name`, `mime_type`, `size` ตามค่าจริงของไฟล์ ณ เวลาอัปโหลด และ `uploaded_by` เป็น id ของผู้ใช้ที่ล็อกอินอยู่ — ไฟล์แนบต้อง **ไม่** สามารถเข้าถึงได้ผ่าน public URL โดยตรง

**AC-INTAKE-10** — เมื่อเรียก `GET /referrals/{referral}/attachments/{attachment}` และ `attachment.referral_id` **ไม่ตรง** กับ `referral.id` ที่ระบุใน URL ระบบต้องตอบกลับ HTTP 404 และต้อง **ไม่** สตรีมไฟล์นั้นออกมา แม้ว่า `attachment` id ที่ระบุจะมีอยู่จริงในระบบก็ตาม

**AC-INTAKE-11** — ทุก route ในโมดูลนี้ (`referrals.*`) ต้องถูกป้องกันด้วย middleware `auth`; ผู้ใช้ที่ยังไม่ล็อกอินต้องถูก redirect ไปหน้า login เมื่อพยายามเข้าถึง route ใด ๆ ในกลุ่มนี้ และผู้ใช้ที่ล็อกอินแล้วไม่ว่า role ใดต้องสามารถเข้าถึงได้โดยไม่ถูกจำกัดด้วย role (ต่างจากกลุ่ม `admin.*` ที่ใช้ middleware `role:admin`)

**AC-INTAKE-12** — เมื่อฟิลด์บังคับ (`source_type`, `patient_hn`, `patient_name`, `zone`, `raw_notes`) ฟิลด์ใดฟิลด์หนึ่งถูกปล่อยว่าง ระบบต้องปฏิเสธการบันทึกด้วย validation error ที่อ้างชื่อฟิลด์ตาม `attributes()` map ของ `StoreReferralRequest` (`patient_hn` → "HN", `patient_name` → "ชื่อ-สกุลผู้ป่วย", `raw_notes` → "ข้อความสรุปอาการ/สถานการณ์", `zone` → "เขตพื้นที่") แทนชื่อฟิลด์ดิบในภาษาอังกฤษ

**AC-INTAKE-13** — เมื่อสร้างใบส่งต่อโดยไม่ระบุ `case_type_id` (ปล่อยว่าง) ระบบต้องยอมรับและบันทึก `Referral.case_type_id` เป็น `null` ได้สำเร็จ (ฟิลด์นี้เป็น nullable) — การเลือกประเภทเคสสามารถทำได้ทีหลังในขั้นตอนยืนยันแผนดูแล (`confirmCarePlan`); หากมีการระบุ `case_type_id` มาต้องตรวจสอบว่ามีอยู่จริงในตาราง `case_types` มิฉะนั้นต้องปฏิเสธด้วย validation error

**AC-INTAKE-14** — เมื่อเข้าหน้ารายการใบส่งต่อ (`referrals.index`) ระบบต้องแสดงผลแบบแบ่งหน้า (pagination) หน้าละ 20 รายการ เรียงจากใหม่ไปเก่า (`latest()`) พร้อม eager-load ความสัมพันธ์ `patient`, `caseType`, `creator` โดยไม่เกิด N+1 query; เมื่อเข้าหน้ารายละเอียด (`referrals.show`) ระบบต้อง eager-load `patient`, `caseType`, `creator`, `attachments.uploader`, `followUpPlans.record`

---

## SUMMARY — AI Draft Summary & Nurse Care-Plan Confirmation

โมดูลนี้ครอบคลุมสามหน้าจอ/เอนด์พอยต์ใน `App\Http\Controllers\ReferralController`: `generateAiSummary`
(`POST /referrals/{referral}/ai-summary`), `showCarePlan` (`GET /referrals/{referral}/care-plan`),
`confirmCarePlan` (`POST /referrals/{referral}/care-plan`). ทั้งสามอยู่ใต้ `auth` เท่านั้น ไม่มี role gate เฉพาะ
กฎที่ต้องพิสูจน์ในทุก AC: **`ai_summary` เป็นได้แค่ร่าง** — ไม่มีทางใดที่ค่าจาก AI จะไหลเข้า `confirmed_summary`,
`status`, หรือกระตุ้น `VisitPlanService` โดยตรง จนกว่าจะผ่าน `confirmCarePlan` ที่มนุษย์กดยืนยันเอง

### กลุ่ม: การสร้างร่างจาก AI (`generateAiSummary`)

**AC-SUMMARY-01 — สร้างร่างสำเร็จ**
เมื่อ `AiService::summarizeReferral()` คืนค่าได้โดยไม่มี exception ระบบต้อง `update()` ที่ referral ด้วย `ai_summary` (array, จาก cast `'ai_summary' => 'array'`) และ `ai_summary_generated_at = now()` จากนั้น redirect ไปที่ route `referrals.care-plan` — ห้ามแก้ไข `status`, `confirmed_summary`, `confirmed_by`, หรือ `confirmed_at` ในขั้นตอนนี้เด็ดขาด

**AC-SUMMARY-02 — เชื่อมต่อ AI ไม่ได้ (connection failure) ต้องไม่แก้ไขข้อมูลใด ๆ**
เมื่อ `Http::post()` ใน `AiService::callOllama()` โยน `\Throwable` ระดับ connection (เช่น timeout/DNS) ระบบต้องโยน `\RuntimeException('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ AI ได้ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง')` แล้ว `ReferralController::generateAiSummary` ต้อง `report($e)` และ redirect ไปที่ `referrals.show` พร้อม flash key `error` = ข้อความข้างต้น โดย `ai_summary`, `ai_summary_generated_at` ของ referral ต้อง **ไม่เปลี่ยนแปลงจากค่าก่อนเรียก** (ทั้ง `update()` จะไม่ถูกเรียกเลยในเส้นทางนี้)

**AC-SUMMARY-03 — Ollama ตอบ HTTP failure ต้องไม่แก้ไขข้อมูลใด ๆ**
เมื่อ `$response->failed()` เป็น true (เช่น 500/timeout จาก Ollama) ระบบต้องโยน `\RuntimeException('เรียกใช้ AI ไม่สำเร็จ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง')` และมีผลเหมือน AC-SUMMARY-02 (referral ไม่ถูกแก้ไข, flash `error`, redirect ไป `referrals.show`) — นี่คือข้อความคนละอันจาก AC-SUMMARY-02 ห้ามสับสน/รวมกัน

**AC-SUMMARY-04 — AI ตอบไม่เป็น JSON ที่ถูกต้อง (`parse_error`) ต้องบันทึกร่างแบบเสื่อมคุณภาพ ไม่ใช่ error**
เมื่อ `parseJsonResponse()` พบว่า raw text จาก Ollama ไม่ใช่ JSON ที่ถูกต้อง หรือ decode แล้วไม่ใช่ array ต้อง **ไม่โยน exception** — ต้องคืนค่า defaults ผสานกับ `parse_error: true, raw_response: <raw text>` และ `generateAiSummary` ต้องบันทึกค่านี้ลง `ai_summary` ตามปกติ (เหมือน AC-SUMMARY-01) แล้ว redirect ไปที่ `referrals.care-plan` — เส้นทางนี้ต้องแยกออกจาก AC-SUMMARY-02/03 อย่างชัดเจน: ที่นี่ referral **ถูกแก้ไข** (มีร่างเสื่อมคุณภาพให้ดู), ที่นั่น referral **ไม่ถูกแก้ไขเลย**

**AC-SUMMARY-05 — ขอ AI สรุปใหม่ก่อนยืนยัน overwrite ร่างเดิม**
ถ้า referral มี `ai_summary` อยู่แล้ว (ยังไม่ confirm) และเรียก `generateAiSummary` ซ้ำ ค่าใหม่ต้อง overwrite ทั้ง `ai_summary` และ `ai_summary_generated_at` ทั้งหมด (ไม่ merge กับของเก่า) และทำได้ไม่จำกัดจำนวนครั้งตราบใดที่ `confirmed_at` ยังเป็น null

### กลุ่ม: การแสดงผลร่าง (`showCarePlan`) — DESIGN.md §3.3

**AC-SUMMARY-06 — สถานะ AI-Draft box ต้องตรงกับ `isConfirmed()`**
เมื่อ `$referral->isConfirmed()` (นิยามจาก `confirmed_at !== null`) เป็น false ต้องแสดงกรอบเส้นประ (`border-dashed`) พร้อมป้าย "ร่างจาก AI — ยังไม่ยืนยัน" เมื่อเป็น true ต้องแสดงกรอบเส้นทึบพร้อมป้าย "ยืนยันแล้วโดย {ชื่อผู้ยืนยันจาก `$referral->confirmer->name`}" ตาม DESIGN.md §3.3 — ห้ามมีสถานะกลาง ๆ ที่ไม่ตรงกับ flag นี้

### กลุ่ม: การยืนยันของพยาบาล (`confirmCarePlan` / `ConfirmCarePlanRequest`)

**AC-SUMMARY-07 — ห้ามยืนยันโดยขาดฟิลด์บังคับ แม้ AI จะสร้างร่างไว้แล้วก็ตาม**
`case_type_id` (required, ต้องมีอยู่จริงใน `case_types` ตาม `exists:case_types,id`), `patient_type` (required string, max:255), `main_problem` (required string), `follow_up_need` (required string) ต้องถูก validate ว่าไม่ว่างเสมอ — ไม่ว่า `ai_summary` จะมีค่าอยู่แล้วหรือไม่ก็ตาม การมี `ai_summary` ที่ไม่ว่างไม่ทำให้ validation ผ่านโดยอัตโนมัติ ผู้ใช้ (นางพยาบาล) ต้องมีค่าอยู่ในฟอร์มที่ submit จริง

**AC-SUMMARY-08 — `risk_signals` เป็น optional และต้องถูกแปลงเป็น array ด้วย `riskSignalsArray()`**
`risk_signals` เป็น `nullable|string` ที่ฟอร์ม submit เป็น textarea หลายบรรทัด `ConfirmCarePlanRequest::riskSignalsArray()` ต้อง: split ด้วย `\r\n|\r|\n`, trim ทุกบรรทัด, กรองบรรทัดว่างออก (`filter()`), แล้ว reindex (`values()`) — ผลลัพธ์ที่เก็บใน `confirmed_summary['risk_signals']` ต้องเป็น array ของ string ที่ไม่มีบรรทัดว่างหรือ whitespace ล้วนหลงเหลืออยู่

**AC-SUMMARY-09 — `initial_pps_score` เป็น optional integer ในช่วง 0–100 เท่านั้น**
Rule: `nullable|integer|min:0|max:100` ค่านอกช่วง (เช่น -1, 101, ค่าที่ไม่ใช่ตัวเลข) ต้องถูก reject ด้วย validation error และ **ไม่มีการ update ใด ๆ เกิดขึ้นกับ referral** (ทั้ง request ล้มเหลวเป็นก้อนเดียว)

**AC-SUMMARY-10 — `confirmed_summary` ต้องสร้างจากฟิลด์ที่ submit เท่านั้น ห้าม copy จาก `ai_summary` โดยอัตโนมัติ**
ใน `ReferralController::confirmCarePlan`, `confirmed_summary` ต้องถูกประกอบขึ้นจาก `$request->validated('patient_type')`, `$request->validated('main_problem')`, `$request->validated('follow_up_need')`, และ `$request->riskSignalsArray()` เท่านั้น — ต้อง **ไม่มีโค้ดใดอ่านค่าจาก `$referral->ai_summary` มาเติมลง `confirmed_summary` โดยตรง** หาก nurse แก้ไขข้อความในฟอร์มก่อน submit ค่าที่บันทึกต้องเป็นค่าที่แก้ไขแล้ว ไม่ใช่ค่าดิบจาก AI

**AC-SUMMARY-11 — การยืนยันต้องบันทึก audit fields และเปลี่ยนสถานะเป็น `STATUS_PLAN_CONFIRMED` แบบอะตอมมิก**
เมื่อ validation ผ่านทั้งหมด referral ต้องถูก update พร้อมกันในครั้งเดียวด้วย: `case_type_id`, `confirmed_summary`, `confirmed_by = Auth::id()`, `confirmed_at = now()`, `status = Referral::STATUS_PLAN_CONFIRMED` (ค่าคือ `'plan_confirmed'`) — หลังจากนี้ `isConfirmed()` ต้องคืน true เสมอ

**AC-SUMMARY-12 — `VisitPlanService::generateInitialPlans()` ต้องถูกเรียกเฉพาะภายใน `confirmCarePlan` เท่านั้น**
ห้ามมี `FollowUpPlan` แถวใดถูกสร้างขึ้นจากการเรียก `generateAiSummary` เพียงอย่างเดียว (ไม่ว่าจะสำเร็จ, connection fail, HTTP fail, หรือ parse_error) — `generateInitialPlans()` ถูกเรียกด้วย `$referral->fresh('caseType')` และ `$request->validated('initial_pps_score')` ก็ต่อเมื่อ `confirmCarePlan` ผ่าน validation และ update สำเร็จแล้วเท่านั้น การมีร่าง AI ที่สมบูรณ์แบบเพียงอย่างเดียวไม่ทำให้เกิดกำหนดการติดตามใด ๆ

**AC-SUMMARY-13 — ประเภทเคสที่ยังไม่มี `VisitRule` ที่ active ต้องยืนยันสำเร็จได้ แต่ไม่มีแผนถูกสร้าง**
ถ้า `generateInitialPlans()` คืนค่าที่ falsy/empty (ไม่มี active `VisitRule` สำหรับ `case_type_id` ที่เลือก) การยืนยันต้อง**ยังสำเร็จ** (`status` เปลี่ยนเป็น `plan_confirmed`, audit fields ถูกบันทึกตาม AC-SUMMARY-11) แต่ flash message ต้องเป็นข้อความแบบ B: "ยืนยันแผนติดตามเรียบร้อยแล้ว (ยังไม่มีเกณฑ์จำนวนครั้งเยี่ยมสำหรับประเภทเคสนี้ — กรุณาตั้งค่าที่หน้าแอดมิน)" และไม่มี `FollowUpPlan` แถวใดถูกสร้างสำหรับ referral นี้

**AC-SUMMARY-14 — `case_type_id` ที่ไม่ถูกต้อง/ไม่มีอยู่จริงต้องถูก reject ทั้งก้อน**
ค่า `case_type_id` ที่ไม่ตรงกับ `exists:case_types,id` (เช่น ID ที่ถูกลบไปแล้ว, ID ของ record ที่ไม่มีอยู่, ค่าที่ไม่ใช่ตัวเลข) ต้องทำให้ request ทั้งก้อนล้มเหลวด้วย validation error — referral ต้องไม่ถูกแก้ไขใด ๆ (status, confirmed_* ทั้งหมดคงค่าเดิม)

**AC-SUMMARY-15 — สามเอนด์พอยต์ต้องบังคับ auth เท่านั้น ไม่มี role gate เพิ่ม**
`referrals.ai-summary`, `referrals.care-plan` (GET), `referrals.care-plan.confirm` ต้องอยู่ใต้ `middleware('auth')` เท่านั้น ผู้ใช้ที่ยังไม่ล็อกอินต้องถูก redirect ไปหน้า login เมื่อพยายามเข้าถึงเส้นทางใดเส้นทางหนึ่ง และผู้ใช้ที่ล็อกอินแล้วไม่ว่า role ใด (`ward_staff`/`home_visit_team`/`admin`) ต้องสามารถกดยืนยันแผนได้เหมือนกันหมด (ไม่มี `role:` middleware ผูกกับ route กลุ่มนี้)

**AC-SUMMARY-16 — การยืนยันเป็น one-way transition**
เมื่อ `confirmed_at` ถูกตั้งค่าแล้วครั้งหนึ่ง ไม่มี action ใดในโมดูลนี้ที่เคลียร์ `confirmed_at`/`confirmed_by`/`status` กลับไปเป็นก่อนยืนยัน (ไม่มี route "unconfirm") — `showCarePlan` หลัง confirm ยังคง GET ได้ แต่ฟอร์ม submit ซ้ำจะยังคง overwrite `confirmed_summary`/`case_type_id`/`confirmed_by`/`confirmed_at` ได้อีก (controller ไม่ได้ guard ไม่ให้ submit ซ้ำ) — จุดนี้ให้บันทึกเป็นข้อสังเกตเพื่อทดสอบพฤติกรรมจริง มิใช่ยืนยันว่ามี guard (ดู [Known Gaps ใน TEST_PLAN.md](TEST_PLAN.md#known-gaps--product-decisions-needed))

---

## SCHED — Visit Scheduling Engine & Case Type / Visit Rule Admin

**AC-SCHED-01 — สร้างแผนติดตามล่วงหน้าครบตามจำนวน (fixed_count)**
เมื่อประเภทเคสของ referral มีเกณฑ์ที่ใช้งานอยู่เป็น `rule_type = fixed_count` และเรียก `generateInitialPlans()` ครั้งแรก (referral ยังไม่มีแผนใด ๆ) ระบบต้องสร้าง `FollowUpPlan` จำนวนเท่ากับ `fixed_visit_count` ทันทีในการเรียกครั้งเดียว โดย `plan_number` ไล่ตั้งแต่ 1..N, `due_date` ของแผนที่ i = วันนี้ + (`fixed_interval_days` × i) วัน และทุกแผนมี `method` เดียวกันตาม zone ของ referral (`in_area` → `home_visit`, อื่น ๆ → `phone_call`), `status = scheduled`

**AC-SCHED-02 — สร้างแผนแรกเท่านั้นสำหรับ score_based พร้อม fallback 14 วัน**
เมื่อเกณฑ์ที่ใช้งานอยู่เป็น `rule_type = score_based`, `generateInitialPlans()` ต้องสร้างแผนเพียง **1 แผน** (`plan_number = 1`) เสมอ โดย `due_date` คำนวณจาก: ถ้าส่ง `initialPpsScore` มาและมีช่วงคะแนนใน `score_rules` ที่ครอบคลุมค่านั้น → ใช้ `interval_days` ของช่วงนั้น; ถ้าไม่ส่งมา หรือส่งมาแต่ไม่ตรงกับช่วงใดเลย → fallback เป็น 14 วันเสมอ

**AC-SCHED-03 — generateNextPlan ต้องไม่สร้างแผนซ้ำ (idempotent) เมื่อยังมีแผนที่รออยู่**
`generateNextPlan()` ต้อง return `null` และไม่สร้างแผนใหม่ ทุกครั้งที่ referral มีแผนสถานะ `scheduled` ที่ `plan_number` มากกว่าแผนของ record ปัจจุบันอยู่แล้ว — ครอบคลุมทั้งกรณี fixed_count ปกติและกรณี edge อื่นที่บังเอิญมีแผนถัดไปเหลืออยู่

**AC-SCHED-04 — แหล่งที่มาของ interval ใน generateNextPlan ยึดตามเกณฑ์ที่ active ณ เวลาตัดสินใจ ไม่ใช่เกณฑ์ตอนสร้างแผนแรก**
เมื่อ `generateNextPlan()` ดำเนินการสร้างแผนใหม่จริง ค่า `intervalDays` ต้องคำนวณจากเกณฑ์ที่ `activeVisitRule()` คืนค่า ณ ขณะนั้นเสมอ ตามลำดับ: (1) active rule เป็น `score_based` และมี `pps_score` → `intervalDaysForScore()`, ไม่ตรงช่วงใด → 14; (2) active rule เป็น `fixed_count` → `fixed_interval_days`, ไม่มีค่า → fallback 7; (3) ไม่มี active rule เลย → fallback 14 — `method` ของแผนใหม่ต้องคงค่าเดิมจากแผนก่อนหน้าเสมอ

**AC-SCHED-05 — cancelRemainingPlans ยกเลิกเฉพาะแผนที่ยังรอดำเนินการ**
เมื่อพยาบาลตัดสินใจ "ปิดเคส" ระบบต้องเปลี่ยนสถานะแผนทุกแผนของ referral ที่ยังเป็น `scheduled` ให้เป็น `cancelled` เท่านั้น แผนที่มีสถานะ `done`, `overdue`, หรือ `cancelled` อยู่แล้วต้องไม่ถูกแก้ไข/แตะต้องใด ๆ

**AC-SCHED-06 — ความหมายของ isOverdue()**
`FollowUpPlan::isOverdue()` ต้องคืนค่า `true` เมื่อและเมื่อ `status === scheduled` **และ** `due_date` เป็นวันที่ในอดีตเท่านั้น แผนที่มีสถานะ `done` หรือ `cancelled` ต้องคืนค่า `false` เสมอไม่ว่า `due_date` จะผ่านไปแล้วเท่าใด

**AC-SCHED-07 — slug ของประเภทเคสต้องไม่ซ้ำ**
ระบบต้องปฏิเสธการบันทึกประเภทเคส (สร้างหรือแก้ไข) ถ้า `slug` ซ้ำกับประเภทเคสอื่นที่มีอยู่แล้วในระบบ (unique ต่อ `case_types.slug` โดยไม่รวมตัวเอง เมื่อแก้ไข) และ `slug` ต้องผ่าน `alpha_dash`

**AC-SCHED-08 — ฟิลด์ที่บังคับกรอกขึ้นกับ rule_type ที่เลือก**
เมื่อเลือก `rule_type = fixed_count`: `fixed_visit_count` และ `fixed_interval_days` ต้องเป็นจำนวนเต็ม ≥ 1 และบังคับกรอก (`score_rules` ถูกบันทึกเป็น null เสมอ); เมื่อเลือก `rule_type = score_based`: `score_rules_text` บังคับกรอก (`fixed_visit_count`/`fixed_interval_days` ถูกบันทึกเป็น null เสมอ) การส่งฟอร์มที่ขาดฟิลด์บังคับตามเงื่อนไขต้องถูกปฏิเสธด้วย validation error โดยไม่มีการบันทึกข้อมูลใด ๆ

**AC-SCHED-09 — เส้นทาง admin/case-types/* ต้องจำกัดเฉพาะบทบาท admin**
ทุก route ภายใต้ `admin/case-types/*` (`index`, `create`, `store`, `edit`, `update`) ต้องถูกป้องกันด้วย middleware `role:admin` ผู้ใช้ที่มี role `ward_staff` หรือ `home_visit_team` ต้องได้รับ HTTP 403 เมื่อเข้าถึง ไม่ว่าจะเป็น GET หรือ POST/PUT

**AC-SCHED-10 — ความไม่สมมาตรของค่า default ของ is_active ระหว่าง create และ update**
เมื่อสร้างประเภทเคสใหม่ (`store`) และฟอร์มไม่ได้ส่งค่า checkbox `is_active` มา ระบบต้องบันทึก `is_active = true` (default ของ store); เมื่อแก้ไขประเภทเคสที่มีอยู่ (`update`) และฟอร์มไม่ได้ส่งค่า checkbox `is_active` มา ระบบต้องบันทึก `is_active = false` (default ของ update) — พฤติกรรมนี้แตกต่างกันโดยเจตนาของโค้ดปัจจุบัน และเป็นความเสี่ยงที่ QA ต้องยืนยันพฤติกรรมจริงทุกครั้งที่มีการแก้ไข

**AC-SCHED-11 — generateInitialPlans ต้อง no-op ในกรณีที่ไม่ควรสร้างแผน**
`generateInitialPlans()` ต้องคืนค่า array ว่างและไม่สร้างแผนใด ๆ เมื่อ (ก) referral มีแผนติดตามอยู่แล้วอย่างน้อย 1 แผน (ไม่ว่าสถานะใด) หรือ (ข) ประเภทเคสของ referral ไม่มี `VisitRule` ที่ `is_active = true` เลย

**AC-SCHED-12 — บรรทัดที่พิมพ์ผิดรูปแบบใน score_rules_text ถูกข้ามอย่างเงียบ ๆ**
เมื่อพาร์ส `score_rules_text` บรรทัดที่ trim แล้วมีจำนวนส่วนที่คั่นด้วยจุลภาคน้อยกว่า 3 ส่วน หรือเป็นบรรทัดว่าง ต้องถูกข้ามไปโดยไม่มี validation error ปรากฏต่อผู้ใช้ และไม่ทำให้การบันทึกทั้งฟอร์มล้มเหลว ส่วนที่เป็นตัวเลข (min/max/interval_days) ที่ไม่ใช่ตัวเลขล้วนจะถูกแปลงเป็น 0 โดย PHP cast โดยไม่มีการแจ้งเตือนใด ๆ เช่นกัน — เป็นพฤติกรรม silent-failure ที่ยืนยันแล้วในโค้ด ต้องมี regression test คุมไว้

**AC-SCHED-13 — VisitRule ต่อประเภทเคสมีแถว "active" ได้เพียงแถวเดียวที่จัดการผ่านฟอร์ม admin**
การบันทึกเกณฑ์ผ่านฟอร์ม admin ใช้ `updateOrCreate(['case_type_id', 'is_active' => true], [...])` เสมอ ดังนั้นการแก้ไขประเภทเคสซ้ำ (รวมถึงการสลับ `rule_type`) ต้อง**อัปเดต**แถว active เดิม ไม่สร้างแถวใหม่ซ้อน — จำนวนแถว `visit_rules` ที่ active สำหรับ 1 ประเภทเคสต้องไม่เกิน 1 แถวเสมอหลังบันทึกซ้ำหลายครั้ง

---

## RECORD — Follow-Up Guide & Outcome Recording

โมดูลนี้ครอบคลุม `FollowUpController@guide/@generateGuide/@createRecord/@storeRecord`,
`StoreFollowUpRecordRequest`, `AiService::suggestFollowUpGuide`. ทุก route อยู่ใต้ `auth` เท่านั้น ไม่มี `role:` gate

**AC-RECORD-01 — สร้างคู่มือติดตามสำเร็จ**
เมื่อผู้ใช้ที่ล็อกอินแล้วกด "ให้ AI แนะนำหัวข้อประเมิน" บนหน้า guide ของแผนที่ยังไม่มี `ai_guide` ระบบต้องเรียก `AiService::suggestFollowUpGuide($plan)`, บันทึกผลลัพธ์ลง `FollowUpPlan.ai_guide` เป็น `{topics: [{title, note}], parse_error: false}`, และ redirect กลับหน้า guide เดิมพร้อมแสดงหัวข้อที่ AI แนะนำ

**AC-RECORD-02 — สร้างคู่มือติดตามล้มเหลว (Ollama ไม่ตอบสนอง/HTTP error)**
เมื่อ `AiService::suggestFollowUpGuide` throw `\Throwable` ระบบต้อง `report()` exception, redirect กลับหน้า guide พร้อม flash `error` และ **ต้องไม่แก้ไข** `FollowUpPlan.ai_guide` เดิม

**AC-RECORD-03 — ขอคู่มือใหม่ซ้ำได้ (regenerate) และ overwrite ค่าเดิม**
เมื่อแผนมี `ai_guide` อยู่แล้ว การกดขอใหม่ต้องเรียก AI ใหม่อีกครั้งและ **แทนที่** ค่า `ai_guide` เดิมทั้งหมดด้วยผลลัพธ์ล่าสุด (ไม่สะสม/ไม่ merge กับของเก่า)

**AC-RECORD-04 — คู่มือ AI เป็นแนวทางเท่านั้น ไม่มีผลต่อการตัดกำหนดการ/ข้อมูลเคส**
`ai_guide` ต้องถูกบันทึกเฉพาะบน `FollowUpPlan` เท่านั้น ต้อง**ไม่**ถูกเขียนกลับไปยัง `Referral`/`Patient` ใดๆ และการสร้าง/สร้างใหม่ของ `ai_guide` ต้อง**ไม่มีผล**ต่อ `FollowUpPlan.status`, ต่อจำนวน/กำหนดการของแผนติดตามอื่น หรือต่อการตัดสินใจใดๆ

**AC-RECORD-05 — สถานะ parse_error ของคู่มือต้องแสดงผลได้โดยไม่ crash**
ถ้า AI ตอบกลับไม่เป็น JSON ที่ถูกต้อง ระบบต้องบันทึก `ai_guide = {topics: [], parse_error: true, raw_response: <ข้อความดิบ>}` และหน้า guide ต้องแสดงข้อความแจ้งเตือนแทนรายการหัวข้อ โดยไม่ error/crash แม้ `topics` เป็น array ว่าง

**AC-RECORD-06 — บันทึกผลติดตามได้ครั้งเดียวต่อแผน (GET)**
เมื่อแผนมี `FollowUpRecord` อยู่แล้ว (ไม่ว่าค่าใดๆ) การเข้าหน้า `GET /follow-up-plans/{plan}/record` ต้องตอบ **HTTP 403** พร้อมข้อความ "บันทึกผลติดตามครั้งนี้ไปแล้ว" — ไม่ใช่แสดงหน้าแบบอ่านอย่างเดียว

**AC-RECORD-07 — บันทึกผลติดตามได้ครั้งเดียวต่อแผน (POST, race-condition safe)**
`storeRecord` ต้อง re-check การมีอยู่ของ `record()` **อีกครั้ง** ที่จุดเริ่มของ action ดังนั้นถ้าสอง request POST ยิงพร้อมกันสำหรับแผนเดียวกัน อย่างน้อยหนึ่งใน request ที่สองต้องได้ 403 และมี `FollowUpRecord` เพียงแถวเดียวต่อแผนเสมอ

**AC-RECORD-08 — การบันทึกผลติดตามทำให้แผนเป็น "done" เสมอ ไม่ว่าเนื้อหาใด**
เมื่อ `storeRecord` สำเร็จ ระบบต้องตั้ง `FollowUpPlan.status = STATUS_DONE` เสมอ ไม่ขึ้นกับว่า `pps_score` เป็น null หรือมีค่า และไม่ขึ้นกับเนื้อหาใน `raw_notes`

**AC-RECORD-09 — ขอบเขตค่า pps_score**
`pps_score` เป็น nullable integer ที่ต้องอยู่ในช่วง 0–100 (inclusive) เท่านั้น ค่าที่ไม่ใช่ integer, ต่ำกว่า 0, หรือสูงกว่า 100 ต้องถูกปฏิเสธด้วย validation error และไม่มีการสร้าง `FollowUpRecord` หรือเปลี่ยนสถานะแผนใดๆ

**AC-RECORD-10 — raw_notes เป็นฟิลด์บังคับ**
`raw_notes` ต้องเป็น string และห้ามเว้นว่าง/ไม่ส่งมา (ไม่มีการจำกัดความยาวสูงสุด) หากไม่ผ่าน validation ต้อง redirect กลับพร้อม error ที่ label เป็น "อาการ/ปัญหาที่พบ" และไม่มีการสร้างระเบียนหรือเปลี่ยนสถานะแผน

**AC-RECORD-11 — visited_at ไม่บังคับกรอก ใช้เวลาปัจจุบันเป็นค่าตั้งต้น**
`visited_at` เป็น nullable date; ถ้าไม่ส่งมาหรือส่งเป็นค่าว่าง ระบบต้องใช้ `now()` ณ เวลาที่ประมวลผล request เป็นค่าบันทึกจริงใน `FollowUpRecord.visited_at`

**AC-RECORD-12 — การสร้างระเบียนและการตัดสถานะแผนต้องเป็น atomic**
การสร้าง `FollowUpRecord` และการตั้ง `FollowUpPlan.status = done` ต้องอยู่ใน `DB::transaction` เดียวกัน

**AC-RECORD-13 — ไม่มีการจำกัดบทบาทผู้ใช้ (known gap)**
ทั้ง 4 route ของโมดูลนี้อยู่ภายใต้ middleware `auth` เท่านั้น ไม่มี `role:` middleware — ผู้ใช้ที่ล็อกอินแล้วไม่ว่า role ใดสามารถสร้าง/ขอคู่มือใหม่ และบันทึกผลติดตามได้ทั้งหมด แม้ product intent จะระบุว่า `home_visit_team` ควรเป็นผู้ใช้หลักของหน้านี้ (ดู [Known Gaps](TEST_PLAN.md#known-gaps--product-decisions-needed))

**AC-RECORD-14 — ไม่มีการตรวจสอบสถานะแผนก่อนบันทึกผล (known gap)**
Controller ไม่ตรวจ `plan->status` ก่อนอนุญาตให้เข้าหน้า/บันทึกผลติดตาม ดังนั้นแผนที่ถูกยกเลิกไปแล้ว (`status = cancelled`) แต่ยังไม่มี record จะยังสามารถถูกบันทึกผลติดตามได้ผ่าน URL ตรง และจะถูกเปลี่ยนสถานะเป็น `done` ทับค่า `cancelled` เดิม

**AC-RECORD-15 — ผู้ใช้ที่ไม่ได้ล็อกอินต้องเข้าถึงไม่ได้**
การเรียก 4 route ใดๆ ของโมดูลนี้โดยไม่มี session ที่ล็อกอินอยู่ ต้อง redirect ไปหน้า login

---

## DECISION — AI Risk Analysis & Mandatory Nurse Decision

โมดูลนี้ครอบคลุม `review`/`analyzeRecord`/`confirmDecision` — จุดเดียวที่ขับเคลื่อนการสร้างกำหนดการติดตามครั้ง
ถัดไปและการเปลี่ยนสถานะเคส กฎหลัก (DESIGN.md §4.1, §3.4): **AI เสนอได้เฉพาะ "ร่าง/ข้อเสนอแนะ" — พยาบาลต้อง
เลือกและยืนยันการตัดสินใจเองทุกครั้ง 100%**

**AC-DECISION-01 — ต้องมีบันทึกผลติดตามก่อนเข้าถึงหน้าใด ๆ ของโมดูลนี้**
ทั้งสามเส้นทาง (`review`, `analyze`, `decision`) ของ `FollowUpPlan` ที่ยังไม่มี `FollowUpRecord` ต้องตอบกลับ HTTP 404 เสมอ (`abort_unless($plan->record, 404)`)

**AC-DECISION-02 — การวิเคราะห์ของ AI เป็นได้แค่ "ข้อเสนอแนะ" ไม่ใช่การตัดสินใจ**
ผลจาก `AiService::analyzeFollowUpRecord()` ต้องถูกบันทึกลงเฉพาะ `ai_analysis` (และ `ai_analysis_generated_at`) เท่านั้น ห้ามเขียนค่าใด ๆ ลงในฟิลด์ที่ขับเคลื่อนการตัดสินใจจริง (`nurse_decision`, `decision_notes`, `risk_flag`, `confirmed_by`, `confirmed_at`) ไม่ว่ากรณีใด

**AC-DECISION-03 — `suggested_decision` ของ AI ต้องไม่ auto-fill เข้า `nurse_decision`**
หน้า review อาจเลือก radio ที่ตรงกับ `suggested_decision` ไว้ล่วงหน้าเป็นค่าเริ่มต้น (UX convenience) แต่ค่าที่บันทึกจริงลงคอลัมน์ `nurse_decision` ต้องมาจาก radio ที่พยาบาล submit มาเท่านั้น (`required|in:repeat,refer,close`) การเปลี่ยนไปเลือกค่าอื่นที่ไม่ตรงกับ `suggested_decision` ต้องทำได้เสมอและถูกบันทึกตามที่พยาบาลเลือก

**AC-DECISION-04 — `risk_flag` เป็นการประเมินของพยาบาลเอง ไม่ผูกกับ `ai_analysis.risk_detected`**
`risk_flag` ที่บันทึกจริงต้องมาจาก checkbox ที่พยาบาล submit มาเท่านั้น ไม่มี validation หรือ logic ใดบังคับให้ต้องตรงกับ `ai_analysis.risk_detected`

**AC-DECISION-05 — การวิเคราะห์ (analyze) ไม่ใช่เงื่อนไขบังคับก่อนยืนยันการตัดสินใจ**
`confirmDecision` ตรวจสอบเพียงว่า `$plan->record` มีอยู่เท่านั้น — พยาบาลต้องสามารถกด "ยืนยันการตัดสินใจ" ได้สำเร็จแม้ `ai_analysis` ยังเป็น null (ไม่เคยกดให้ AI วิเคราะห์เลย)

**AC-DECISION-06 — วิเคราะห์ซ้ำได้ก่อนยืนยัน และ AI ล้มเหลวไม่กระทบข้อมูลเดิม**
ก่อนที่ `FollowUpRecord` จะถูกยืนยัน พยาบาลสามารถกด "ให้ AI วิเคราะห์ใหม่" ซ้ำได้หลายครั้ง โดยแต่ละครั้งที่สำเร็จจะเขียนทับ `ai_analysis`/`ai_analysis_generated_at` เดิม เมื่อ AI ล้มเหลว (exception) ต้อง catch/report/flash error โดย `ai_analysis` เดิมต้อง**ไม่ถูกแก้ไข**

**AC-DECISION-07 — การตัดสินใจ "ปิดเคส" ยกเลิกแผนที่เหลือทั้งหมดของ referral และปิดเคส**
เมื่อ `nurse_decision = close` การยืนยันต้อง (ในทรานแซกชันเดียว): เรียก `cancelRemainingPlans($referral)` (ยกเลิกทุกแผน `scheduled` ของ referral ทั้งหมด ไม่ใช่แค่แผนปัจจุบัน) และอัปเดต `Referral.status = closed`, `closed_at = now()` — ต้อง**ไม่**เรียก `generateNextPlan()` และต้อง**ไม่**ตั้งค่า `next_follow_up_plan_id`

**AC-DECISION-08 — "ติดตามซ้ำ" และ "ส่งต่อ" ให้ผลลัพธ์การจัดกำหนดการที่เหมือนกันทุกประการ (ข้อกำหนดปัจจุบันของระบบ ไม่ใช่บั๊ก)**
เมื่อ `nurse_decision` เป็น `repeat` หรือ `refer` ระบบต้องเรียก `generateNextPlan($record)` และอัปเดต `Referral.status` เป็น `in_progress` (ถ้ายังไม่ใช่) ด้วย logic ที่เหมือนกันทุกประการ — ปัจจุบันไม่มี side effect เฉพาะสำหรับ "ส่งต่อ" เอกสารนี้บันทึกพฤติกรรมนี้ไว้ว่าเป็นขอบเขตปัจจุบันที่ตั้งใจ (ดู [Known Gaps](TEST_PLAN.md#known-gaps--product-decisions-needed))

**AC-DECISION-09 — `next_follow_up_plan_id` สะท้อนแค่ "ลิงก์ที่สร้างจริง" ไม่สะท้อน "ยังมีเยี่ยมต่อหรือไม่"**
เมื่อ `generateNextPlan()` คืนค่าแผนใหม่ ต้องอัปเดต `next_follow_up_plan_id` ให้ชี้ไปที่แผนนั้น เมื่อคืนค่า `null` (เช่น referral แบบ `fixed_count`) `next_follow_up_plan_id` ต้องคงเป็น `null` ต่อไป — `null` ไม่ได้แปลว่าไม่มีการเยี่ยมครั้งถัดไปแล้ว

**AC-DECISION-10 — สถานะของ referral ต้องเดินหน้าทางเดียว ไม่ถอยกลับ**
ลำดับสถานะ: `pending_review` → `plan_confirmed` → `in_progress` → `closed` เท่านั้น ไม่มีทางที่โค้ดในโมดูลนี้จะเปลี่ยนสถานะย้อนกลับไปที่ขั้นก่อนหน้า

**AC-DECISION-11 — `decision_notes` เป็นทางเลือก (optional)**
`decision_notes` เป็น `nullable|string` — การยืนยันการตัดสินใจโดยไม่กรอกต้องผ่านการ validate และบันทึกสำเร็จ (ค่าเป็น null)

**AC-DECISION-12 — การยืนยันการตัดสินใจทั้งหมดต้องเกิดในทรานแซกชันเดียว**
การอัปเดต `FollowUpRecord` และผลข้างเคียงต่อ `VisitPlanService`/`Referral` ทั้งหมดต้องอยู่ใน `DB::transaction` เดียวกัน

**AC-DECISION-13 — ไม่มีการจำกัด role ในการยืนยันการตัดสินใจ แต่ต้องผ่านการยืนยันตัวตนเสมอ**
เส้นทาง `review`/`analyze`/`decision` ผูกกับ middleware `auth` เท่านั้น ผู้ใช้ที่ยังไม่ล็อกอินต้องถูก redirect ไปหน้า login ส่วนผู้ใช้ที่ล็อกอินแล้วไม่ว่า role ใดต้องสามารถยืนยันการตัดสินใจได้

---

## ADMINRBAC — User & Role Administration, Access Control Matrix

โมดูลนี้ครอบคลุมทั้ง role administration CRUD (`Admin\UserController`) และ RBAC enforcement ทั้งระบบ
(`EnsureUserHasRole`, alias `role`) ปัจจุบันใช้เฉพาะกับกลุ่ม `admin.*` เท่านั้น

### Role assignment CRUD

**AC-ADMINRBAC-01** — หน้า `GET /admin/users` (index) ต้องแสดงรายชื่อผู้ใช้ทั้งหมดในระบบ เรียงตามชื่อ ครบทุก role โดยไม่กรองตัวใดออก

**AC-ADMINRBAC-02** — หน้า `GET /admin/users/{user}/edit` ต้องแสดงข้อมูลผู้ใช้ปัจจุบันและมีฟอร์มให้เลือก role ใหม่จากรายการ `User::ROLES` เท่านั้น (ไม่ใช่ free-text)

**AC-ADMINRBAC-03** — เมื่อแอดมินส่งฟอร์ม `PUT /admin/users/{user}` ด้วย role ที่ถูกต้อง ระบบต้องอัปเดตคอลัมน์ `role` ของผู้ใช้เป้าหมายทันที และ redirect กลับไปหน้า `admin.users.index` พร้อมข้อความยืนยัน "อัปเดตสิทธิ์ผู้ใช้เรียบร้อยแล้ว"

**AC-ADMINRBAC-04** — การอัปเดต `department` ต้องทำได้อิสระจากการเปลี่ยน role — เปลี่ยน department โดยไม่เปลี่ยน role ต้องสำเร็จ และเปลี่ยนทั้งสองพร้อมกันในคำขอเดียวต้องสำเร็จเช่นกัน

**AC-ADMINRBAC-05** — ค่า `department` เป็น optional (`nullable`) — ส่งค่าว่าง/ไม่ส่งเลยต้องผ่าน validation ได้ ส่วนค่าที่ส่งมาต้องเป็น string ความยาวไม่เกิน 255 ตัวอักษร มิฉะนั้นต้องถูก reject

**AC-ADMINRBAC-06 (defensive/validation-layer only)** — หากค่า `role` ที่ส่งมาไม่อยู่ใน `array_keys(User::ROLES)` ระบบต้อง reject คำขอด้วย validation error บนฟิลด์ `role` และต้อง**ไม่**บันทึกการเปลี่ยนแปลงใด ๆ — การป้องกันนี้อยู่ที่ระดับ `UpdateUserRoleRequest` เท่านั้น ไม่มีการบังคับที่ระดับ model/DB

### Role middleware — blocking every `admin/*` route for non-admins

**AC-ADMINRBAC-07** — ผู้ใช้ role `ward_staff` ต้องได้รับ HTTP 403 พร้อมข้อความ "คุณไม่มีสิทธิ์เข้าถึงหน้านี้" เมื่อเข้าถึง**ทุกเส้นทาง**ในกลุ่ม `admin.*` (ทั้ง 8 route) โดยไม่มีข้อยกเว้น

**AC-ADMINRBAC-08** — ผู้ใช้ role `home_visit_team` ต้องได้รับ HTTP 403 เช่นเดียวกัน สำหรับ**ทุกเส้นทาง**ในกลุ่ม `admin.*` ทั้ง 8 route

**AC-ADMINRBAC-09** — ผู้ใช้ที่**ยังไม่ล็อกอิน** (guest) ที่พยายามเข้าถึงเส้นทางใด ๆ ในกลุ่ม `admin.*` ต้องถูก **redirect ไปหน้า login** — **ไม่ใช่** 403 — เพราะ `auth` middleware ครอบกลุ่มทั้งหมดไว้ชั้นนอกและทำงานก่อน `role:admin` เสมอ

**AC-ADMINRBAC-10** — ผู้ใช้ role `admin` ต้องเข้าถึงได้ทุกเส้นทางในกลุ่ม `admin.*` ทั้ง 8 route ได้สำเร็จ โดยไม่ถูกบล็อก

### Non-admin routes remain equally accessible to all roles (documented fact, not a gap)

**AC-ADMINRBAC-11** — ทุกเส้นทางที่ไม่ได้อยู่ภายใต้ `admin.*` (`dashboard`, `profile.*`, `referrals.*`, `follow-up-plans.*`) ต้องเข้าถึงได้เท่าเทียมกันโดยผู้ใช้ทั้ง 3 role — ปัจจุบันมีเพียง middleware `auth` (และ `verified` สำหรับ `dashboard`) เท่านั้น นี่คือพฤติกรรมที่ตั้งใจและได้รับการยืนยันแล้วในโค้ดปัจจุบัน

### Self-demotion (documented current gap / risk)

**AC-ADMINRBAC-12 (risk, as-is behavior)** — ระบบปัจจุบัน**ไม่มี**การป้องกันแอดมินที่แก้ไข role ของ**ตัวเอง** — แอดมินสามารถส่งฟอร์มเปลี่ยน role ของ account ตนเองเป็น `ward_staff` หรือ `home_visit_team` ได้สำเร็จ ไม่มีการตรวจสอบ self-edit และไม่มี "last admin" safeguard (ดู [Known Gaps](TEST_PLAN.md#known-gaps--product-decisions-needed))

### Registration default role

**AC-ADMINRBAC-13** — ผู้ใช้ที่ลงทะเบียนใหม่ผ่านหน้า Breeze registration ต้องได้ค่า `role` เริ่มต้นเป็น `ward_staff` เสมอ โดยไม่มีช่องทางใดในหน้าลงทะเบียนให้เลือก role อื่น

**AC-ADMINRBAC-14 (setup/documentation gap)** — ไม่มี seeder หรือกลไกอัตโนมัติใด ๆ ในโค้ดปัจจุบันสำหรับสร้างผู้ใช้ `admin` คนแรกของระบบ (`CaseTypeSeeder` สร้างเฉพาะ case types) — การตั้งค่าระบบใหม่ต้องมีขั้นตอนสร้าง admin คนแรกด้วยมือก่อนจึงจะมีผู้ใช้ที่สามารถ promote คนอื่นได้

---

## DASHNFR — Dashboard KPIs, AI Resilience & Design-System Compliance

### กลุ่ม A — ตรรกะ KPI ของแดชบอร์ด

**AC-DASHNFR-01** — `totalPatients` ต้องนับ `Patient` ทุกรายในระบบ (ไม่มีการกรองตาม zone, สถานะใบส่งต่อ, หรือสถานะแผนติดตามใดๆ)

**AC-DASHNFR-02** — `dueTodayCount` ต้องนับเฉพาะ `FollowUpPlan` ที่ `status = scheduled` และ `due_date` ตรงกับวันนี้แบบเท่ากันเท่านั้น (`whereDate('due_date', $today)`) — ไม่ใช่ `<=` วันนี้

**AC-DASHNFR-03** — `overdueCount` ต้องนับเฉพาะ `FollowUpPlan` ที่ `status = scheduled` และ `due_date` **ก่อน** วันนี้อย่างเคร่งครัด (`<`) — แผนที่ครบกำหนด "วันนี้" ต้องไม่ถูกนับซ้ำเป็น "เกินกำหนด"

**AC-DASHNFR-04** — `riskCount` ต้องนับ `FollowUpRecord` ที่ `risk_flag = true` **เฉพาะ**เมื่อใบส่งต่อของแผนนั้นยังไม่ใช่ `closed` — บันทึกที่มี `risk_flag = true` แต่ผูกกับเคสที่ปิดแล้วต้องไม่ถูกนับในตัวเลขนี้ แม้ค่า `risk_flag` ในฐานข้อมูลจะยังเป็น `true` อยู่ก็ตาม

**AC-DASHNFR-05** — ตัวแปร `upcomingPlans` (แสดงในหัวตารางว่า "รายการที่ต้องติดตามวันนี้/เกินกำหนด") ต้องแสดงผล `FollowUpPlan` ที่ `status = scheduled` และ `due_date <= วันนี้` เท่านั้น (เกินกำหนด + ครบกำหนดวันนี้) เรียงจาก `due_date` น้อยไปมาก จำกัด 20 แถว — ต้อง**ไม่**มีแผนที่ `due_date` เป็นอนาคตปรากฏอยู่ ทั้งที่ชื่อตัวแปรสื่อว่าเป็น "แผนที่จะมาถึง" (ดู [Known Gaps](TEST_PLAN.md#known-gaps--product-decisions-needed))

**AC-DASHNFR-06** — `recentRiskRecords` ต้องแสดง `FollowUpRecord` ที่ `risk_flag = true` 5 รายการล่าสุดตาม `confirmed_at` โดย**ไม่กรองสถานะใบส่งต่อ** — บันทึกความเสี่ยงของเคสที่ปิดแล้วสามารถปรากฏในรายการนี้ได้ ทั้งที่บันทึกเดียวกันถูกตัดออกจาก `riskCount` (AC-04) — ความไม่สอดคล้องกันนี้เป็นพฤติกรรมที่บันทึกไว้

**AC-DASHNFR-07** — `pendingReviewCount` ต้องนับ `Referral` ที่ `status = pending_review` เท่านั้น แบนเนอร์เตือนต้องแสดงเมื่อค่านี้ > 0 เท่านั้น และต้องหายไปเมื่อค่ากลับเป็น 0

**AC-DASHNFR-08** — เส้นทาง `/dashboard` ต้องบังคับทั้ง `auth` **และ** `verified` ซึ่งเข้มกว่าทุกเส้นทางอื่นในระบบที่ต้อง login แล้วเข้าได้ทันที ผู้ใช้ที่ login แล้วแต่ยังไม่ยืนยันอีเมลต้องถูกกันไม่ให้เข้า `/dashboard` แต่ยังต้องเข้าเส้นทางอื่นที่ใช้แค่ `auth` ได้ตามปกติ

### กลุ่ม B — ความทนทานของ AI/Ollama (ใช้ร่วมกันทั้ง 3 จุดเรียกใช้งาน)

**AC-DASHNFR-09** — ที่ `POST /referrals/{referral}/ai-summary` หาก `AiService::summarizeReferral` โยน exception ต้องถูกจับ, `report($e)`, redirect กลับพร้อม flash `error`, ต้อง**ไม่**เกิด HTTP 500 และต้อง**ไม่**มีการเขียนค่าใดๆ ลงฟิลด์ `ai_summary`/`confirmed_summary`

**AC-DASHNFR-10** — ที่ `POST /follow-up-plans/{plan}/guide` ต้องมีการันตีเดียวกันกับ AC-09 สำหรับ `AiService::suggestFollowUpGuide`

**AC-DASHNFR-11** — ที่ `POST /follow-up-plans/{plan}/analyze` ต้องมีการันตีเดียวกันกับ AC-09 สำหรับ `AiService::analyzeFollowUpRecord`

**AC-DASHNFR-12** — ความล้มเหลวสองประเภทที่ `AiService::callOllama` แยกไว้ภายใน (connection exception vs non-2xx response) ต้องถูกบันทึกด้วย `Log::error` คนละข้อความที่ระบุสาเหตุต่างกันอย่างชัดเจน แม้ทั้งสองกรณีจะไปจบที่ catch เดียวกันในทุก controller

**AC-DASHNFR-13** — เส้นทาง fallback ของ `parseJsonResponse` เป็นโค้ดร่วมที่ใช้เหมือนกันในทั้ง 3 เมธอดของ `AiService` — ยืนยันครบเพียงครั้งเดียวที่ระดับ service บวกการยืนยันเพิ่มอีก 1 ครั้งต่อจุดเรียกใช้ก็เพียงพอ

### กลุ่ม C — การตั้งค่า/deploy (ตรวจสอบตอน config-review ไม่ใช่ผ่าน UI)

**AC-DASHNFR-14** — ในทุกสภาพแวดล้อมที่ deploy จริง ค่า `OLLAMA_URL` ต้องชี้ไปยังที่อยู่ในเครือข่ายภายในโรงพยาบาลเท่านั้น — ต้อง**ห้ามเด็ดขาด**ที่จะชี้ไปยัง endpoint สาธารณะ/cloud ใดๆ เพราะข้อมูลที่ส่งเข้า prompt เป็นข้อมูลผู้ป่วย (PHI) ข้อกำหนดนี้ตรวจสอบผ่าน config-review checklist ก่อนขึ้นระบบจริงทุกครั้ง

### กลุ่ม D — ความสอดคล้องกับ Design System (Visual/Manual QA)

**AC-DASHNFR-15** *(Visual/Manual QA)* — ทุกจุดที่แสดงสถานะของ `Referral.status`, `FollowUpPlan.status`, และ `Patient.zone` ต้องแสดงทั้งสีและข้อความกำกับเสมอ ตาม DESIGN.md §3.2 — ห้ามใช้สีอย่างเดียวสื่อความหมาย

**AC-DASHNFR-16** *(Visual/Manual QA)* — ทุกฟิลด์ที่มาจาก AI ต้องแสดงด้วยกรอบเส้นประ + ป้าย "ร่างจาก AI — ยังไม่ยืนยัน" ก่อนยืนยัน และเปลี่ยนเป็นกรอบเส้นทึบ + ป้าย "ยืนยันแล้วโดย [ชื่อ] เมื่อ [วันที่-เวลา]" หลังยืนยัน ตาม DESIGN.md §3.3

**AC-DASHNFR-17** *(Visual/Manual QA)* — จุดตัดสินใจของพยาบาลต้องแสดงเป็น radio-card ที่เห็นตัวเลือกทั้งหมดพร้อมกัน ห้ามใช้ `<select>` dropdown ตาม DESIGN.md §3.4

**AC-DASHNFR-18** *(Visual/Manual QA)* — KPI stat tile บนแดชบอร์ดต้องเป็นการ์ดพื้นขาว, label ขนาด caption สีเทา, ตัวเลขขนาด display, และใช้สี semantic กับตัวเลขเฉพาะเมื่อค่านั้นผิดปกติเท่านั้น ตาม DESIGN.md §3.5

**AC-DASHNFR-19** *(Accepted known gap — ไม่ใช่ defect ใหม่)* — หน้าจอ Blade ปัจจุบันทั้งหมดยังใช้ top-nav แบบ default ของ Breeze แทน sidebar navigation ตาม DESIGN.md §3.7 — ทุกครั้งที่มีการรีวิว compliance ต้องบันทึกช่องว่างนี้ไว้ว่าเป็น "known/accepted deviation" ไม่ใช่ทำเงียบและไม่ใช่รายงานเป็นบั๊กใหม่ซ้ำซ้อน
