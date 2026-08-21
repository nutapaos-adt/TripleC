# Test Plan — Chira Continuity Care (Triple C)

| | |
|---|---|
| **ระบบ** | Chira Continuity Care (Triple C) — ระบบ continuity-of-care สำหรับทีมเยี่ยมบ้าน/ติดตามผู้ป่วยของโรงพยาบาล |
| **เวอร์ชันเอกสาร** | 1.0 |
| **สถานะโค้ด ณ วันที่เขียน** | โครง domain-specific ของ Laravel (models/controllers/services/views/migrations) — **ยังไม่ scaffold เป็นแอป Laravel ที่รันได้** (ไม่มี `composer.json`/`artisan`/`vendor/`) ดู [SETUP.md](../../SETUP.md) |
| **เอกสารที่เกี่ยวข้อง** | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md), [TEST_CASES.md](TEST_CASES.md), [CLAUDE.md](../../CLAUDE.md), [DESIGN.md](../../DESIGN.md) |

## 1. วัตถุประสงค์

เอกสารนี้กำหนดขอบเขต กลยุทธ์ และเกณฑ์การทดสอบของระบบ Triple C ทั้งหมด เพื่อยืนยันว่า:

1. กฎหลักของระบบ — **human-in-the-loop คือกฎข้อที่ 1 ไม่มีข้อยกเว้น** (DESIGN.md §4.1): AiService สร้างได้แค่
   "ร่าง" เท่านั้น ไม่มีทางที่ผลจาก AI จะไหลเข้าฟิลด์ที่ขับเคลื่อนสถานะ/กำหนดการโดยไม่ผ่านการยืนยันของพยาบาล —
   ถูกพิสูจน์ด้วย test case ในทุกจุดที่ AiService ถูกเรียกใช้
2. ตรรกะการคำนวณกำหนดการติดตาม (`VisitPlanService`) ถูกต้องตามเกณฑ์ `fixed_count`/`score_based` ทุก edge case
3. การควบคุมสิทธิ์ (RBAC) และการป้องกันข้อมูลผู้ป่วย (PHI) เป็นไปตามที่ออกแบบไว้
4. ช่องว่าง/พฤติกรรมที่อาจดูเหมือนบั๊กแต่เป็นขอบเขตปัจจุบันของโค้ด ถูกบันทึกไว้อย่างชัดเจนให้ทีมผลิตภัณฑ์ตัดสินใจ
   ไม่ใช่ปล่อยให้ QA รอบต่อไปค้นพบซ้ำหรือ "แก้" โดยไม่มีการตัดสินใจ

## 2. ขอบเขต (Scope)

### 2.1 อยู่ในขอบเขต — 7 โมดูล, รวม 84 acceptance criteria และ 152 test case

| # | โมดูล | ครอบคลุม | AC | TC |
|---|---|---|---|---|
| 1 | [INTAKE](ACCEPTANCE_CRITERIA.md#intake--referral-intake--zone-resolution) | รับเคส, ข้อมูลผู้ป่วย, ตรวจจับเขตพื้นที่, ไฟล์แนบ | 14 | 28 |
| 2 | [SUMMARY](ACCEPTANCE_CRITERIA.md#summary--ai-draft-summary--nurse-care-plan-confirmation) | AI สรุปข้อมูล (ร่าง) + พยาบาลยืนยันแผนดูแล | 16 | 20 |
| 3 | [SCHED](ACCEPTANCE_CRITERIA.md#sched--visit-scheduling-engine--case-type--visit-rule-admin) | เครื่องมือคำนวณกำหนดการ (`VisitPlanService`) + Admin ตั้งค่าประเภทเคส/เกณฑ์ | 13 | 28 |
| 4 | [RECORD](ACCEPTANCE_CRITERIA.md#record--follow-up-guide--outcome-recording) | คู่มือก่อนเยี่ยม (AI) + บันทึกผลเยี่ยม/โทรติดตาม | 15 | 20 |
| 5 | [DECISION](ACCEPTANCE_CRITERIA.md#decision--ai-risk-analysis--mandatory-nurse-decision) | AI วิเคราะห์ความเสี่ยง (ร่าง) + การตัดสินใจบังคับของพยาบาล | 13 | 17 |
| 6 | [ADMINRBAC](ACCEPTANCE_CRITERIA.md#adminrbac--user--role-administration-access-control-matrix) | จัดการผู้ใช้/สิทธิ์ + role middleware ทั้งระบบ | 14 | 12 |
| 7 | [DASHNFR](ACCEPTANCE_CRITERIA.md#dashnfr--dashboard-kpis-ai-resilience--design-system-compliance) | KPI แดชบอร์ด, ความทนทานของ AI, PHI/config, ความสอดคล้อง Design System | 19 | 27 |

โมดูลถูกจัดลำดับตามลำดับการไหลของงานจริง (ดู CLAUDE.md): **INTAKE → SUMMARY → SCHED (ทริกเกอร์จาก SUMMARY) →
RECORD → DECISION → SCHED (ทริกเกอร์ซ้ำ)** โดยมี **ADMINRBAC** และ **DASHNFR** เป็นโมดูล cross-cutting ที่ค้ำ
ทุกโมดูลข้างต้นอยู่

### 2.2 นอกขอบเขต (Out of Scope)

- **คุณภาพ/ความถูกต้องของผลลัพธ์ AI เชิงเนื้อหา** (เช่น โมเดล Ollama วินิจฉัยถูกไหม) — ทดสอบเฉพาะว่าระบบ
  "จัดการ" ผลลัพธ์นั้นถูกต้อง (บันทึก/แสดง/fallback ตามสัญญา JSON) ไม่ตัดสินคุณภาพทางคลินิกของคำตอบ AI
- **Performance/Load testing** และ **Penetration testing เต็มรูปแบบ** — มีเพียง test case เชิง security ที่
  เกิดจากการอ่านโค้ดจริง (เช่น cross-referral attachment access, race condition) ไม่ใช่ pentest ครบวงจร
  หากต้องการ ควรเปิดเป็นงานแยกกับผู้เชี่ยวชาญด้าน security
- **ความเข้ากันได้ข้ามเบราว์เซอร์/อุปกรณ์ (cross-browser/device compatibility)** อย่างละเอียด — สมมติ Chrome/
  Edge เดสก์ท็อปรุ่นปัจจุบันเป็นมาตรฐานหลัก
- **การ scaffold ตัวแอป Laravel เอง** (composer/artisan/npm/.env ตาม SETUP.md ขั้น 1–5) — เป็นเงื่อนไขก่อน
  (precondition) ของการทดสอบ ไม่ใช่สิ่งที่ทดสอบ

## 3. กลยุทธ์การทดสอบ (Test Approach)

เนื่องจากรีโพนี้ยังไม่มี Laravel scaffolding/test runner (`composer.json`/`artisan`/`vendor/` ไม่มี — ดู
[CLAUDE.md](../../CLAUDE.md)) ทุก test case ใน [TEST_CASES.md](TEST_CASES.md) เขียนเป็น **manual/spec-level**
ก่อน โดยแบ่งเป็น 3 layer ตามลักษณะของสิ่งที่ทดสอบ:

| Layer | ใช้เมื่อ | เครื่องมือที่แนะนำ (หลัง scaffold) |
|---|---|---|
| **Functional (Feature/Unit)** | ตรรกะ business logic, validation, RBAC, transaction integrity — ส่วนใหญ่ของ TC ทั้งหมด | Pest หรือ PHPUnit + `RefreshDatabase` + `actingAs()`; `VisitPlanService` เหมาะเป็น Unit test แยกจาก Feature test ของ controller |
| **AI-path (mocked)** | ทุกจุดที่เรียก `AiService`/Ollama (SUMMARY, RECORD-guide, DECISION-analyze) | `Http::fake()` เพื่อจำลอง 3 เส้นทางแยกกัน: (1) connection exception, (2) HTTP response ที่ `->failed()`, (3) response 200 ที่ body ไม่ใช่ JSON ถูกต้อง — ทั้ง 3 ต้องมี fixture แยกกันเพราะพฤติกรรม/ข้อความต่างกัน |
| **Visual/Manual QA** | ความสอดคล้องกับ DESIGN.md (badge, AI-Draft box, Nurse-Decision box, KPI tile, sidebar nav) — DASHNFR กลุ่ม E | Checklist ตรวจด้วยสายตา ทำซ้ำทุกครั้งที่แก้ Blade view ที่เกี่ยวข้อง ไม่ automate |
| **Config-review** | ค่า `OLLAMA_URL` ต้องเป็น intranet เท่านั้น (PHI compliance) — DASHNFR-021 | ตรวจใน deployment checklist ก่อนขึ้นระบบทุกครั้งที่ `.env` เปลี่ยน ไม่ผ่าน UI |

**Traceability:** ทุก test case มีคอลัมน์ "Related AC" ที่ชี้กลับไปยัง AC ที่เกี่ยวข้องใน
[ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) — ใช้เป็น requirement-traceability matrix (RTM) แบบง่ายในตัว
เอกสารเดียวกัน ไม่ต้องทำไฟล์ RTM แยก

## 4. Test Environment & Data Setup

### 4.1 Precondition ระดับ environment

1. ทำตาม [SETUP.md](../../SETUP.md) ขั้น 1–5 ให้ครบก่อน (scaffold Laravel, ติดตั้ง Breeze, migrate, seed
   `CaseTypeSeeder`) — ไม่มีขั้นตอนนี้ ทดสอบ Feature/Unit test อัตโนมัติไม่ได้เลย
2. `config/catchment.php` → `in_area_sub_districts` เป็น **placeholder ว่างเปล่าโดย default** — ต้องเตรียม
   2 ค่าของ config นี้สำหรับทดสอบ: (ก) ว่าง (ค่า default ปัจจุบัน เพื่อทดสอบ fallback) และ (ข) มีรายชื่อ
   ตำบลจริงอย่างน้อย 1–2 รายการ (เพื่อทดสอบ auto-resolve) — ดู AC-INTAKE-04/06/07
3. `config/ai.php` (`OLLAMA_URL`/`OLLAMA_MODEL`/`OLLAMA_TIMEOUT`) — สำหรับ automated test **ไม่ต้องมี Ollama
   จริงรันอยู่**, ใช้ `Http::fake()` mock ทั้งหมด (ดู §3) สำหรับ manual/exploratory testing ควรมี instance
   Ollama ทดสอบที่ชี้ไปยัง URL ภายในเครือข่ายเท่านั้น (ห้ามชี้ไปยัง public/cloud แม้เป็นสภาพแวดล้อมทดสอบ —
   ข้อมูลทดสอบควรเป็นข้อมูลสมมติ ไม่ใช่ PHI จริง)
4. ต้องมีผู้ใช้ทดสอบอย่างน้อย 1 คนต่อ role (`ward_staff`, `home_visit_team`, `admin`) — **ไม่มี seeder สร้าง
   admin คนแรกอัตโนมัติ** (AC-ADMINRBAC-14) ต้องสร้างด้วยมือ (เช่น `php artisan tinker`) ก่อนเริ่มทดสอบ
   โมดูล ADMINRBAC/SCHED (ฝั่ง admin CRUD)
5. ต้องมี `CaseType` + `VisitRule` ทดสอบอย่างน้อย 2 ชุด: หนึ่งแบบ `fixed_count` (เช่น "หลังคลอด" N=3,
   interval=7) และหนึ่งแบบ `score_based` (เช่น "Palliative Care" พร้อม `score_rules` ครอบคลุมมากกว่า 1 ช่วง)
   เพื่อให้ครอบคลุม AC-SCHED-01/02 และทุก TC ที่ตามมา

### 4.2 Test data conventions

- ใช้ HN สมมติที่ไม่ซ้ำกับข้อมูลจริงเสมอ (เช่น `HN00001`, `HN00002`, ...) — **ห้ามใช้ข้อมูลผู้ป่วยจริงในการ
  ทดสอบ ไม่ว่าจะในสภาพแวดล้อม local/staging** เพราะ prompt ที่ส่งเข้า Ollama มีข้อมูล PHI
- วันที่ (`due_date`, `visited_at`, `confirmed_at`) ในหลาย TC อ้างอิง "วันนี้" แบบ relative — เมื่อ automate
  ต้อง freeze เวลาด้วย `Carbon::setTestNow()` เพื่อผลลัพธ์ที่ deterministic ไม่ใช่พึ่งเวลาจริงของเครื่องรัน test

## 5. Entry / Exit Criteria

**Entry criteria** (พร้อมเริ่มทดสอบรอบใดก็ตาม):
- โค้ดที่จะทดสอบผ่าน code review และ merge เข้า branch ทดสอบแล้ว
- Environment setup ตาม §4.1 ครบถ้วน (โดยเฉพาะ seed ผู้ใช้ 3 role + CaseType/VisitRule ทดสอบ 2 ชุด)
- ไม่มี P0/P1 defect ที่เปิดค้างจากรอบทดสอบก่อนหน้าที่ยังไม่ได้ตัดสินใจ (fix หรือ accept)

**Exit criteria** (ปิดรอบทดสอบได้):
- Test case ที่มี Priority = High ทั้งหมดผ่าน (pass) หรือมี waiver ที่ผู้มีอำนาจอนุมัติเป็นลายลักษณ์อักษร
- ทุกรายการใน [§7 Known Gaps](#7-known-gaps--product-decisions-needed) ถูกนำเสนอให้ทีมผลิตภัณฑ์แล้วอย่างน้อย
  หนึ่งครั้ง (ไม่จำเป็นต้อง fix ทั้งหมด แต่ต้องมีการตัดสินใจบันทึกไว้ — accept/fix-later/fix-now)
- Visual/Manual QA checklist (DASHNFR กลุ่ม E) ผ่านครบ หรือมี deviation ที่บันทึกไว้ชัดเจน (เช่น sidebar gap
  ที่ยอมรับแล้วตาม AC-DASHNFR-19)
- Config-review checklist (TC-DASHNFR-021, ตรวจ `OLLAMA_URL`) ผ่านก่อนทุก deploy ไปยัง environment ที่มีข้อมูล
  ผู้ป่วยจริง — ข้อนี้เป็น **hard gate แยกจาก exit criteria ปกติ** ห้าม deploy ถ้าไม่ผ่าน

## 6. บทบาทและความรับผิดชอบ (Roles & Responsibilities)

| บทบาท | ความรับผิดชอบ |
|---|---|
| Dev/QA ผู้ execute test | รันเคส Functional + AI-path (mocked), บันทึกผล pass/fail, เปิด defect พร้อมอ้าง TC/AC ID |
| Visual QA reviewer | รันเคสกลุ่ม Visual (DASHNFR-022 ถึง 026) เทียบกับ DESIGN.md ทุกครั้งที่ Blade view ที่เกี่ยวข้องถูกแก้ |
| ผู้ดูแลระบบ/ผู้ตรวจ config | รัน config-review checklist (TC-DASHNFR-021) ก่อน deploy ทุกครั้งที่ `.env`/`OLLAMA_URL` เปลี่ยน |
| ทีมผลิตภัณฑ์ (Product) | ตัดสินใจรายการใน §7 Known Gaps ว่าจะ accept/fix — โดยเฉพาะรายการที่กระทบ business logic (เช่น repeat vs refer parity, self-demotion) |

## 7. Known Gaps / Product Decisions Needed

รายการต่อไปนี้เป็นพฤติกรรม **ที่ยืนยันแล้วจากการอ่านโค้ดจริง** — ไม่ใช่บั๊กที่ค้นพบระหว่างทดสอบ แต่เป็น
พฤติกรรมปัจจุบันของระบบที่ QA ต้องทดสอบ "ตามที่เป็นอยู่" (as-is) และรายงานผลว่าตรงกับพฤติกรรมนี้จริง ไม่ใช่
ทึกทักว่าเป็น defect ที่ต้อง fix เอง ทุกข้อควรถูกนำเสนอให้ทีมผลิตภัณฑ์ตัดสินใจอย่างชัดแจ้งว่า **accept
ตามที่เป็นอยู่** หรือ **ขอให้แก้ไข** — แล้วบันทึกผลตัดสินใจนั้นไว้เป็นหลักฐาน

| # | รายการ | โมดูล | AC/TC อ้างอิง | ผลกระทบถ้าไม่แก้ |
|---|---|---|---|---|
| 1 | ไม่มี role gate บนโมดูล RECORD (guide/record) และ DECISION (review/analyze/decision) — ผู้ใช้ role ใดก็ได้ทำได้ทุกอย่าง | RECORD, DECISION | AC-RECORD-13, TC-RECORD-017; AC-DECISION-13, TC-DECISION-013 | ward_staff สามารถทำหน้าที่ที่ตั้งใจให้เป็นของ home_visit_team ได้ |
| 2 | แผนที่ถูก `cancelled` แล้วยังบันทึกผลติดตามทับเป็น `done` ได้ ไม่มี guard ตรวจ `plan->status` | RECORD | AC-RECORD-14, TC-RECORD-015 | ข้อมูลกำหนดการอาจไม่สอดคล้องกับความเป็นจริงทางคลินิก |
| 3 | ไม่มี guard กันการเรียก `analyze`/`decision` ซ้ำหลัง record ถูกยืนยันแล้ว (พึ่ง UI ซ่อนฟอร์มอย่างเดียว) | DECISION | AC-DECISION-02/12, TC-DECISION-017 | การตัดสินใจที่ยืนยันแล้วอาจถูกเปลี่ยนแดยไม่ได้รับอนุญาตผ่าน API ตรง |
| 4 | "ติดตามซ้ำ" (repeat) และ "ส่งต่อ" (refer) ให้ผลลัพธ์ scheduling เหมือนกันทุกประการ ไม่มี side effect เฉพาะของ "ส่งต่อ" | DECISION | AC-DECISION-08, TC-DECISION-009 | อาจไม่ตรงกับความคาดหวังทางธุรกิจว่า "ส่งต่อ" ควรมีการแจ้ง/บันทึกที่ต่างจาก "ติดตามซ้ำ" |
| 5 | ไม่มีการป้องกัน self-demotion ของ admin (รวมถึงไม่มี "last admin" safeguard) | ADMINRBAC | AC-ADMINRBAC-12, TC-ADMINRBAC-006 | ระบบอาจเหลือ admin 0 คนโดยไม่ได้ตั้งใจ |
| 6 | ไม่มี seeder สร้างผู้ใช้ admin คนแรก — ต้องสร้างด้วยมือทุกครั้งที่ deploy ใหม่ | ADMINRBAC | AC-ADMINRBAC-14, TC-ADMINRBAC-012 | ขั้นตอน deployment พลาดง่าย ถ้าไม่มี runbook ชัดเจน |
| 7 | ตัวแปร `upcomingPlans` ในแดชบอร์ดจริง ๆ แสดง "เกินกำหนด + ครบกำหนดวันนี้" ไม่ใช่ "แผนในอนาคต" ตามชื่อ | DASHNFR | AC-DASHNFR-05, TC-DASHNFR-004 | อาจสร้างความเข้าใจผิดให้ dev ใหม่ที่แก้โค้ดต่อในอนาคต แม้ end-user ไม่เห็นความแตกต่างจากชื่อตัวแปร |
| 8 | `riskCount` (ไม่รวมเคสปิดแล้ว) กับ `recentRiskRecords` (รวมเคสปิดแล้ว) ใช้เกณฑ์กรองไม่ตรงกัน | DASHNFR | AC-DASHNFR-04/06, TC-DASHNFR-007/008 | ตัวเลข KPI และรายการรายละเอียดข้างล่างอาจดูขัดแย้งกันในมุมมองผู้ใช้ |
| 9 | หน้าจอ Blade ปัจจุบันทั้งหมดยังใช้ top-nav ของ Breeze ไม่ใช่ sidebar ตาม DESIGN.md §3.7 | DASHNFR | AC-DASHNFR-19, TC-DASHNFR-026 | Known/accepted deviation ที่มีอยู่แล้วตาม CLAUDE.md — ไม่ใช่ของใหม่ |
| 10 | แก้ไขประเภทเคส (update) ที่ไม่ติ๊ก `is_active` จะปิดใช้งานทันที (default false) ต่างจากตอนสร้างใหม่ (default true) | SCHED | AC-SCHED-10, TC-SCHED-018 | Admin อาจปิดใช้งานประเภทเคสโดยไม่ได้ตั้งใจเพียงเพราะลืมติ๊กช่องซ้ำตอนแก้ไข |
| 11 | บรรทัดผิดรูปแบบใน `score_rules_text` ถูกข้ามอย่างเงียบ ๆ ไม่มี validation error แจ้งเตือน admin | SCHED | AC-SCHED-12, TC-SCHED-022/023 | Admin อาจไม่รู้ว่าตั้งค่าเกณฑ์ผิดจนกว่าจะเห็นผลกระทบกับผู้ป่วยจริง |
| 12 | การยืนยันแผนดูแล (`confirmCarePlan`) ไม่มี guard กันการ submit ซ้ำ — ยืนยันซ้ำได้เรื่อย ๆ | SUMMARY | AC-SUMMARY-16, TC-SUMMARY-020 | ข้อมูลที่ยืนยันแล้วอาจถูกเปลี่ยนแปลงโดยไม่ได้ตั้งใจ |

## 8. เครื่องมือที่แนะนำ (หลัง scaffold)

- **Pest** (แนะนำ) หรือ **PHPUnit** — Feature test สำหรับทุก controller action, Unit test แยกสำหรับ
  `VisitPlanService`, `ZoneResolver`, `AiService::parseJsonResponse`
- **`Illuminate\Foundation\Testing\RefreshDatabase`** — DB สะอาดทุก test
- **`Http::fake()`** — mock การเรียก Ollama ทั้ง 3 เส้นทาง (connection exception / HTTP failed / non-JSON 200)
  ห้ามยิงไปยัง Ollama จริงในการทดสอบอัตโนมัติ
- **`Carbon::setTestNow()`** — freeze เวลาสำหรับ TC ที่อ้างอิง "วันนี้"/"เมื่อวาน"/"พรุ่งนี้"
- **Laravel Dusk** (ถ้าต้องการ automate ส่วน browser-level ของ Visual QA ในอนาคต) — ปีนี้ยังแนะนำให้ทำ
  manual checklist ก่อน เพราะเกณฑ์ส่วนใหญ่เป็นเรื่องภาพ/สไตล์ที่ assertion ทั่วไปตรวจยาก

## 9. สรุป

เอกสารชุดนี้ (`TEST_PLAN.md` + `ACCEPTANCE_CRITERIA.md` + `TEST_CASES.md`) ครอบคลุมทั้งระบบตามคำขอ
รวม **84 acceptance criteria** และ **152 test case** ใน 7 โมดูล ถูกจัดทำโดย sub-agent 7 ตัวที่อ่านโค้ดจริง
ของแต่ละโมดูลแยกกัน (ไม่ใช่คาดเดา) แล้วประกอบเป็นเอกสารเดียวกันโดยยึด ID prefix ต่อโมดูลเพื่อไม่ให้ชนกัน —
ดูหัวข้อ [§7](#7-known-gaps--product-decisions-needed) เป็นจุดเริ่มต้นที่ควรพาไปคุยกับทีมผลิตภัณฑ์ก่อนเริ่ม
รอบทดสอบจริง เพราะหลายรายการเป็น "พฤติกรรมที่ถูก" หรือ "บั๊กที่ต้องแก้" ขึ้นกับ requirement ที่ยังไม่ได้ยืนยัน
ชัดเจนในเอกสารต้นทาง (DESIGN.md/SETUP.md)
