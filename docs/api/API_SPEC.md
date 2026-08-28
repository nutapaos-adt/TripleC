# API Spec — Chira Continuity Care (Triple C)

| | |
|---|---|
| **ระบบ / System** | Chira Continuity Care (Triple C) — ระบบ continuity-of-care สำหรับทีมเยี่ยมบ้าน/ติดตามผู้ป่วยของโรงพยาบาล |
| **เวอร์ชันเอกสาร / Doc version** | 1.0 |
| **วันที่ปรับปรุงล่าสุด** | 2026-08-28 |
| **สถานะโค้ด ณ วันที่เขียน** | โครง domain-specific ของ Laravel (models/controllers/services/views/migrations) — **ยังไม่ scaffold เป็นแอป Laravel ที่รันได้** (ไม่มี `composer.json`/`artisan`/`vendor/`) ดู `SETUP.md`. เอกสารนี้อธิบายพฤติกรรมของโค้ดที่มีอยู่จริงในวันนี้เท่านั้น ไม่รวม operation ที่ยังไม่ได้ implement |
| **เอกสารที่เกี่ยวข้อง / Related docs** | [CLAUDE.md](../../CLAUDE.md), [docs/database/DATABASE_SPEC.md](../database/DATABASE_SPEC.md) (เอกสารคู่กัน อธิบาย entity/attribute เดียวกันในมุมฐานข้อมูล), [docs/testing/TEST_PLAN.md](../testing/TEST_PLAN.md) |

## 1. Purpose and Scope

This document describes the conceptual API surface of Triple C — the domain operations available to
authenticated hospital staff, independent of transport protocol (no HTTP verbs/status codes are used; see
`routes/web.php` for the current route-to-controller mapping this document was derived from). It covers
exactly five resource groupings, matching what is implemented in code today:

1. **Referrals** — intake, AI-drafted summary, nurse-confirmed care plan, attachments.
2. **Follow-up Plans** — pre-visit guide, outcome recording, AI risk analysis, nurse decision.
3. **Admin — Case Types** — case-type catalog and its visit-scheduling rule.
4. **Admin — Users** — role/department administration.
5. **Dashboard** — read-only KPI/overview aggregation.

Each operation lists its trigger, conceptual inputs/outputs (named consistently with
`docs/database/DATABASE_SPEC.md`'s entity/attribute names), preconditions, business rules, resulting state
changes, and the role(s) allowed to invoke it.

## 2. Actors / Roles

Defined on the `User` entity (`role` attribute, one of three values, default `ward_staff` for new signups):

| Role | Thai label | Conceptual responsibility (per CLAUDE.md's workflow) |
|---|---|---|
| `ward_staff` | พยาบาล/เจ้าหน้าที่หอผู้ป่วย | Intake, reviewing/confirming AI-drafted summaries into care plans, confirming follow-up decisions |
| `home_visit_team` | ทีมเยี่ยมบ้าน | Performing visits/calls, recording outcomes |
| `admin` | แอดมิน/หัวหน้าแผนก | Configuring case types/visit rules, managing user roles |

**Enforcement note:** access control is implemented via a single `role` middleware alias, applied only to
the whole `admin.*` route group (`role:admin`). Every other operation in scope requires only that the
caller be an authenticated user (`auth` middleware) — the code does **not** currently distinguish
`ward_staff` from `home_visit_team` for any operation (e.g. nothing stops a `home_visit_team` account from
confirming a care plan, or a `ward_staff` account from recording a home-visit outcome). Role requirements
below are stated per this actual enforcement, not per the conceptual division of labor. See
§7 Open Questions.

## 3. Cross-Cutting Rule: AI Draft vs. Confirmed (Human-in-the-Loop)

This is the project's non-negotiable rule (CLAUDE.md §"the one rule that governs every AI-touching
feature"): **`AiService` only ever produces a draft.** Nothing it returns is committed to a
decision-bearing field until a human explicitly reviews/edits and confirms it through a separate
operation. Three operations below generate AI drafts; each is paired with the confirmation operation
required before its content can affect case status or scheduling:

| AI draft operation | Writes only to (draft field) | Required confirmation operation before it can affect case state |
|---|---|---|
| Generate AI Draft Summary | `Referral.ai_summary`, `ai_summary_generated_at` | Confirm Care Plan (writes `confirmed_summary` from nurse-submitted form fields, never copies `ai_summary` directly) |
| Generate AI Draft Guide | `FollowUpPlan.ai_guide` | None — `ai_guide` is advisory-only reference material for staff conducting the visit/call; it never drives status or scheduling, so it has no confirmation step of its own |
| Generate AI Risk Analysis Draft | `FollowUpRecord.ai_analysis`, `ai_analysis_generated_at` | Confirm Nurse Decision (writes `nurse_decision`, `risk_flag`, `confirmed_by`, `confirmed_at` from the nurse's own submitted choice, never copies `ai_analysis.suggested_decision`/`risk_detected` directly, even when they agree) |

If the AI backend (self-hosted Ollama, intranet-only per CLAUDE.md) is unreachable, times out, or returns a
non-2xx response, the generate operation reports an error back to the caller and leaves all other referral
state untouched — the human can retry or proceed manually without AI input. If the AI responds but the body
isn't valid JSON, the draft is still saved with a `parse_error: true` flag and the raw text preserved under
`raw_response`, so the reviewer can still read it as free text; callers/views must handle this fallback
shape rather than assuming the structured fields are always populated.

## 4. Resource: Referrals

**Represents:** a patient's continuity-of-care case, from intake through AI-drafted summary, nurse-confirmed
care plan, generated follow-up schedule, and eventual closure. Central "case" entity tying together
`Patient`, `CaseType`, `FollowUpPlan`, and `ReferralAttachment`.

### 4.1 Key Operations

#### List Referrals
- **Trigger:** a staff member opens the referral queue/list view.
- **Inputs:** none (implicit page number for pagination).
- **Outputs:** paginated list (20 per page) of referrals, newest first, each with its `Patient` (name, HN),
  `CaseType`, `status`, `zone`, `created_at`, and creating `User`.
- **Preconditions:** authenticated.
- **Business rules:** read-only; no AI content involved.
- **Role:** any authenticated user.

#### View Intake Form
- **Trigger:** staff starts a new intake.
- **Inputs:** none.
- **Outputs:** the list of currently active `CaseType`s (id, name), for optional selection at intake time.
- **Role:** any authenticated user.

#### Create Referral (Intake)
- **Trigger:** submission of the intake form for a new or returning patient.
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `source_type` | enum (`ward`, `opd`, `internal_dept`, `external_hospital`) | Y | Where the referral originated |
| `source_detail` | text | N | Free-text elaboration on the source |
| `patient_hn` | text | Y | Hospital number — business key used to match/create the `Patient` |
| `patient_name` | text | Y | |
| `patient_national_id` | text | N | |
| `patient_dob` | date | N | |
| `patient_phone` | text | N | |
| `patient_address` | long_text | N | |
| `patient_sub_district` / `patient_district` / `patient_province` | text | N | Used for zone auto-detection |
| `zone` | enum (`in_area`, `out_area`) | Y | Submitted value; may be overridden — see business rules |
| `zone_override` | boolean | N | If true, the submitted `zone` is trusted as-is instead of being re-resolved |
| `case_type_id` | reference → CaseType (N:1) | N | Optional at intake time; can still be set/changed at care-plan confirmation |
| `raw_notes` | long_text | Y | Free-text clinical narrative — the input the AI summary step will later read |
| `attachments` | array of file/attachment | N | Each file limited to `pdf`/`jpg`/`jpeg`/`png`, max 10 MB |

- **Outputs:** the matched-or-created `Patient`; a new `Referral` at status `pending_review`; a
  `ReferralAttachment` row per uploaded file.
- **Preconditions:** authenticated.
- **Business rules:**
  - The `Patient` is matched by `hn` (find-or-create/upsert semantics) — if a patient with that HN already
    exists, **all** submitted demographic fields overwrite the existing record; there is no diff/merge or
    conflict warning (flagged in §7).
  - Zone resolution: unless `zone_override` is truthy, the system re-resolves the zone from
    `patient_sub_district` (via the same logic as Zone Lookup below) and that resolved value takes
    precedence over the submitted `zone` field; only an explicit override lets the manually chosen zone
    stand.
  - The new referral always starts at `status = pending_review`, with `created_by` set to the current user.
  - Attachments are stored on a private disk only and are never publicly reachable; the only sanctioned
    retrieval path is the Download Attachment operation below.
  - This operation never invokes AI — `raw_notes` is only captured here; AI summarization is a distinct,
    explicitly-triggered later step.
- **State changes:** `Referral` created at `pending_review`.
- **Role:** any authenticated user.

#### View Referral Detail
- **Trigger:** opening a specific referral.
- **Outputs:** the `Referral` with its `Patient`, `CaseType`, creating `User`, `ReferralAttachment`s (with
  uploader), and all `FollowUpPlan`s (with their `FollowUpRecord`, if recorded) — the full case timeline.
- **Role:** any authenticated user.

#### Zone Lookup
- **Trigger:** an async check while a sub-district is being typed/selected during intake.
- **Inputs:** `sub_district` (text).
- **Outputs:** `zone` (enum `in_area`/`out_area`, or nothing if undetectable) and a human-readable Thai
  detection label.
- **Business rules:** resolved by comparing the normalized sub-district name against the hospital's
  configured in-area sub-district list; returns "undetected" when the sub-district is blank or the
  configured list itself is empty — callers must fall back to manual zone selection in that case.
- **Business rules:** purely a lookup — no data is written.
- **Role:** any authenticated user.

#### Download Attachment
- **Trigger:** staff downloads a previously uploaded file from a referral.
- **Inputs:** referral identifier, attachment identifier.
- **Outputs:** the stored file, streamed back with its original filename and mime type.
- **Preconditions:** the attachment must belong to the referenced referral — cross-referral references are
  rejected as not found.
- **Business rules:** attachments live on a private disk; this is the only sanctioned path to read their
  content.
- **Role:** any authenticated user (no ownership/uploader-specific restriction observed beyond
  authentication).

#### Generate AI Draft Summary — *AI draft operation*
- **Trigger:** staff asks the AI to read the intake notes and propose a summary, after intake.
- **Inputs:** none beyond the referral identifier — the operation itself feeds the AI the referral's
  `raw_notes`, the patient's approximate age and zone, and the current active `CaseType` catalog.
- **Outputs (draft only):** `Referral.ai_summary` — a structured draft of `patient_type`, `main_problem`,
  `follow_up_need`, `risk_signals` (list), `suggested_case_type_slug`, plus a `parse_error` flag and
  `raw_response` fallback text if the AI didn't return valid JSON; `ai_summary_generated_at` timestamp.
- **Preconditions:** referral exists.
- **Draft-vs-confirmed:** writes only to `ai_summary`/`ai_summary_generated_at`. It never sets
  `confirmed_summary`, never changes `case_type_id`, and never changes `Referral.status`. A human must
  review this draft and explicitly invoke **Confirm Care Plan** before any of it can affect case type,
  status, or scheduling.
- **Business rules:** on AI failure, the referral is left completely untouched and an error is surfaced —
  the user can retry or fill the care plan manually.
- **State changes:** draft fields only; `Referral.status` unchanged.
- **Role:** any authenticated user.

#### View Care Plan (Confirmation Form)
- **Trigger:** after an AI summary has been drafted (or directly, if AI is skipped).
- **Outputs:** the referral, patient, list of active `CaseType` options, and the `ai_summary` draft (if any)
  to pre-fill the confirmation form.
- **Role:** any authenticated user.

#### Confirm Care Plan — *the confirmation counterpart to the AI draft summary*
- **Trigger:** a nurse/staff member submits the reviewed (and possibly edited) care plan.
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `case_type_id` | reference → CaseType (N:1) | Y | Must reference an existing case type |
| `patient_type` | text | Y | |
| `main_problem` | long_text | Y | |
| `follow_up_need` | long_text | Y | |
| `risk_signals` | text | N | Newline-separated free text, converted into a list |
| `initial_pps_score` | integer (0–100) | N | Only meaningful when the confirmed case type's rule is `score_based` |

- **Outputs:** `Referral` updated with `case_type_id`, `confirmed_summary` (structured: `patient_type`,
  `main_problem`, `follow_up_need`, `risk_signals` list), `confirmed_by` (current user), `confirmed_at`
  (now), `status = plan_confirmed`. Triggers generation of the initial `FollowUpPlan`(s).
- **Preconditions:** referral exists. (No check that the referral isn't *already* confirmed — see §7.)
- **Draft-vs-confirmed:** this is the **only** operation permitted to write `confirmed_summary`. It is
  populated exclusively from the human-submitted form fields above — never copied directly from
  `ai_summary`, even when the form was pre-filled from it.
- **Business rules:**
  - Immediately invokes the visit-scheduling logic (`VisitPlanService.generateInitialPlans`): if the
    confirmed case type's active `VisitRule` is `fixed_count`, every visit is scheduled up front, spaced by
    `fixed_interval_days`; if `score_based`, only visit #1 is created, using `initial_pps_score` (if
    supplied) to look up the due interval from the rule's `score_rules` ranges (defaulting to 14 days if no
    score was given or no range matched); if the case type has no active `VisitRule` at all, no plans are
    created and the response tells the user admin configuration is needed.
  - The scheduling method (`home_visit` vs `phone_call`) for generated plans is derived from the referral's
    `zone` (`in_area` → home visit, `out_area` → phone call) — it is not chosen directly in this operation.
  - Idempotency safeguard: if the referral already has any `FollowUpPlan` rows, no additional plans are
    created by this call.
- **State changes:** `Referral.status` → `plan_confirmed`; one or more `FollowUpPlan` rows created at
  `scheduled` (or zero, if no active rule exists for the case type).
- **Role:** any authenticated user.

### 4.2 Relationships

- Referral → Patient: N:1 (many referrals can reference the same patient over time, matched by HN).
- Referral → CaseType: N:1, optional until care-plan confirmation, required by the time status is
  `plan_confirmed`.
- Referral → FollowUpPlan: 1:N (all follow-up plans generated for this case).
- Referral → ReferralAttachment: 1:N.
- Referral → User: N:1 via `created_by` (creator) and N:1 via `confirmed_by` (confirmer).

### 4.3 Error / Edge Cases

- Uploading a disallowed file type or a file over 10 MB is rejected by validation before any attachment is
  created.
- An HN that already exists silently overwrites that patient's demographic fields on the next intake — no
  duplicate-detection warning is surfaced to the user (see §7).
- AI summary call failure (unreachable AI backend / non-2xx response): the caller is returned to the
  referral with an error message; referral state is unaffected; retry or manual entry is possible.
- AI response that isn't valid JSON: the draft is saved with `parse_error: true` and the raw text preserved
  under `raw_response` so the human can still read it as free text.
- Downloading an attachment whose id doesn't belong to the referral id in the request: rejected as not
  found.
- Confirming a care plan whose case type has no active `VisitRule`: the confirmation still succeeds
  (`status` becomes `plan_confirmed`) but zero `FollowUpPlan`s are generated; the response explicitly warns
  the user rather than silently failing.

## 5. Resource: Follow-up Plans

**Represents:** a single scheduled visit or phone-call touchpoint (`FollowUpPlan`) for a referral, and the
outcome recorded against it (`FollowUpRecord`), including AI-assisted preparation and risk analysis and the
mandatory nurse decision that follows.

### 5.1 Key Operations

#### View Pre-Visit Guide
- **Trigger:** staff preparing for an upcoming visit/call opens the plan's guide page.
- **Outputs:** the `FollowUpPlan` with its `Referral` → `Patient`/`CaseType`, and the existing `ai_guide`
  draft if one has already been generated.
- **Role:** any authenticated user.

#### Generate AI Draft Guide — *AI draft operation (advisory only)*
- **Trigger:** staff asks the AI to suggest topics/questions to check before this visit/call.
- **Inputs:** none beyond the plan identifier — reads the referral's `confirmed_summary` (falling back to
  `ai_summary` if the care plan hasn't been confirmed yet), the plan's method and sequence number, and prior
  `FollowUpRecord` history for context.
- **Outputs (draft only):** `FollowUpPlan.ai_guide` — a structured list of `topics` (each with `title` and
  `note`), plus `parse_error`/`raw_response` fallback.
- **Draft-vs-confirmed:** `ai_guide` is reference material for the person conducting the visit/call; it is
  never treated as recorded patient data, never drives `FollowUpPlan.status` or scheduling, and — unlike the
  other two AI drafts in this system — has **no separate confirmation step**, because it never feeds a
  decision-bearing field in the first place.
- **Business rules:** same failure-handling pattern as the referral AI summary (error surfaced, existing
  state untouched on failure).
- **Role:** any authenticated user.

#### View Record Form
- **Preconditions:** the plan must not already have a `FollowUpRecord` — a plan can only be recorded once;
  otherwise the operation is blocked with an "already recorded" message.
- **Outputs:** the plan with its referral/patient/case type context.
- **Role:** any authenticated user.

#### Record Follow-up Outcome
- **Trigger:** staff submits what happened during a completed visit/call.
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `visited_at` | datetime | N | Defaults to the current time if omitted |
| `pps_score` | integer (0–100) | N | Palliative Performance Scale score, if applicable |
| `raw_notes` | long_text | Y | Free-text description of what was observed |

- **Outputs:** a new `FollowUpRecord` (`performed_by` = current user, linked to the plan); the
  `FollowUpPlan.status` transitions to `done`.
- **Preconditions:** the plan must not already have a `FollowUpRecord` (enforced on both the form view and
  the store action).
- **Business rules:** this step only captures the observation — it does **not** set any decision, risk
  flag, or trigger scheduling. Those all wait for the separate Confirm Nurse Decision operation, optionally
  preceded by AI risk analysis.
- **State changes:** `FollowUpPlan.status` → `done`; `FollowUpRecord` created (unconfirmed — no
  `nurse_decision`/`confirmed_at` yet).
- **Role:** any authenticated user.

#### View Decision Review
- **Preconditions:** the plan must already have a `FollowUpRecord`, otherwise not found.
- **Outputs:** plan + referral + patient + case type + the record, plus its confirming user if already
  confirmed.
- **Role:** any authenticated user.

#### Generate AI Risk Analysis Draft — *AI draft operation*
- **Trigger:** staff asks the AI to read the just-recorded outcome and suggest whether risk is present and
  what should happen next.
- **Inputs:** none beyond the plan identifier — reads the record's `raw_notes`/`pps_score`, the referral's
  confirmed (or draft) summary, and prior follow-up history.
- **Outputs (draft only):** `FollowUpRecord.ai_analysis` — a structured draft of `risk_detected` (boolean),
  `risk_summary`, `recommendation`, `suggested_decision` (one of `repeat`/`refer`/`close`), plus
  `parse_error`/`raw_response` fallback; `ai_analysis_generated_at` timestamp.
- **Preconditions:** the plan must already have a `FollowUpRecord`.
- **Draft-vs-confirmed:** writes only to `ai_analysis`/`ai_analysis_generated_at` on the record. It never
  writes `nurse_decision` or `risk_flag` directly, even when `suggested_decision`/`risk_detected` are
  present. A human must review it and explicitly invoke **Confirm Nurse Decision** — the one operation
  permitted to set those decision-bearing fields. This is the system's mandatory 100%-human-confirmation
  point for follow-up outcomes.
- **Role:** any authenticated user.

#### Confirm Nurse Decision — *the confirmation counterpart to the AI risk analysis*
- **Trigger:** a nurse reviews the recorded outcome (and the optional AI risk analysis) and makes the actual
  call on what happens next.
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `nurse_decision` | enum (`repeat`, `refer`, `close`) | Y | The human decision — never auto-derived from AI output |
| `decision_notes` | long_text | N | |
| `risk_flag` | boolean | N | Defaults to false if omitted |

- **Outputs:** `FollowUpRecord` updated with `nurse_decision`, `decision_notes`, `risk_flag`, `confirmed_by`
  (current user), `confirmed_at` (now); possibly `next_follow_up_plan_id` set; `Referral.status` (and
  possibly `closed_at`) updated; a new `FollowUpPlan` may be created.
- **Preconditions:** the plan must already have a `FollowUpRecord`.
- **Business rules:**
  - If `nurse_decision = close`: all of the referral's still-`scheduled` `FollowUpPlan`s are cancelled
    (`VisitPlanService.cancelRemainingPlans`), and `Referral.status` becomes `closed` with `closed_at =
    now`. Plans already `done` are left as-is (cancellation only targets `scheduled` ones).
  - If `nurse_decision = repeat` or `refer`: `VisitPlanService.generateNextPlan` is invoked. It does nothing
    if an upcoming `scheduled` plan already exists (the pre-generated `fixed_count` case); otherwise it
    computes the next due date from the case type's active `VisitRule` — `score_based`: looked up from the
    just-recorded `pps_score` against `score_rules` (defaulting to 14 days if no score or no matching range);
    `fixed_count`: `fixed_interval_days` (defaulting to 7); no active rule at all: defaults to 14 — and
    creates the next `FollowUpPlan` (`plan_number` + 1) using the same `method` as the plan just completed.
    If a new plan was created, the record's `next_follow_up_plan_id` links to it. `Referral.status` advances
    to `in_progress` if it wasn't already.
  - This operation is the sole writer of `nurse_decision`, `risk_flag`, `confirmed_by`/`confirmed_at` on
    `FollowUpRecord` — never inferred automatically from `ai_analysis`.
- **State changes:** `FollowUpRecord` confirmed; `Referral.status` → `in_progress` or `closed`; a new
  `FollowUpPlan` possibly created, or all remaining `scheduled` plans on the referral cancelled.
- **Role:** any authenticated user (no role-specific restriction found in code — see §7, since CLAUDE.md
  frames this as a nurse action conceptually).

### 5.2 Relationships

- FollowUpPlan → Referral: N:1.
- FollowUpPlan → FollowUpRecord: 1:1 (a plan can have at most one record; enforced by the "already
  recorded" precondition, not by a unique constraint described here — see DATABASE_SPEC.md for the
  storage-level detail).
- FollowUpRecord → FollowUpPlan (self-referencing, via `next_follow_up_plan_id`): the record optionally
  points to whichever plan was generated as a result of the nurse's decision.
- FollowUpRecord → User: N:1 via `performed_by` and N:1 via `confirmed_by`.

### 5.3 Error / Edge Cases

- Opening the record form, or submitting a record, for a plan that already has one: blocked with an
  "already recorded" message.
- Reviewing, analyzing, or confirming a decision for a plan with no record yet: blocked as not found.
- AI guide/analysis call failures: same resilience pattern as the referral AI summary — error surfaced, no
  partial state written.
- Confirming a decision of `close` while other `FollowUpPlan`s for the same referral are still `scheduled`:
  **allowed** — those plans are automatically cancelled as part of closing, rather than blocking the close.
- Confirming `repeat`/`refer` when an upcoming plan already exists (the `fixed_count` case): allowed and
  idempotent — no duplicate plan is created.

## 6. Resource: Admin — Case Types

**Represents:** the catalog of case categories (e.g., post-partum, palliative) and, for each, the single
active `VisitRule` that governs how its follow-up schedule is computed. Managed exclusively by `admin`.

### 6.1 Key Operations

#### List Case Types
- **Outputs:** all case types (with their `VisitRule`s loaded), ordered by name.
- **Role:** `admin` only.

#### View Create Form
- **Role:** `admin` only.

#### Create Case Type
- **Trigger:** admin submits a new case type plus its scheduling rule in one step.
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `name` | text | Y | |
| `slug` | text | Y | Must be unique and URL-safe |
| `description` | long_text | N | |
| `is_active` | boolean | N | Defaults to true |
| `rule_type` | enum (`fixed_count`, `score_based`) | Y | Chooses which of the two rule shapes below applies |
| `fixed_visit_count` | integer (≥1) | Required if `rule_type = fixed_count` | Total number of visits to pre-schedule |
| `fixed_interval_days` | integer (≥1) | Required if `rule_type = fixed_count` | Days between each visit |
| `score_rules_text` | text | Required if `rule_type = score_based` | Multi-line `min,max,interval_days,label` entries, parsed into the structured `score_rules` table |

- **Outputs:** created `CaseType`; a `VisitRule` created and marked active, scoped to it.
- **Business rules:**
  - Exactly one `VisitRule` is treated as "the active one" per case type (matched by
    `case_type_id` + `is_active = true`); saving again replaces that rule's fields rather than
    accumulating a new rule row.
  - Fields not relevant to the chosen `rule_type` are cleared (e.g. `score_rules` is left empty when
    `fixed_count` is chosen, and vice versa) rather than validated as mutually exclusive.
  - `created_by` is recorded as the acting admin.
- **State changes:** `CaseType` created; its active `VisitRule` created.
- **Role:** `admin` only.

#### View Edit Form
- **Outputs:** the case type with its visit rules.
- **Role:** `admin` only.

#### Update Case Type
- **Inputs / business rules:** identical shape and upsert-by-`(case_type_id, is_active=true)` logic as
  Create, applied via update.
- **Business rule to flag:** this operation does not version historical `VisitRule`s — changing the
  interval/count on an existing active case type immediately affects **future** scheduling calculations
  (e.g. `generateNextPlan` reads whatever rule is active at decision time) with no retroactive effect on
  the `due_date` of `FollowUpPlan`s already created under the old rule.
- **Role:** `admin` only.

### 6.2 Relationships

- CaseType → VisitRule: 1:N in storage (all rule versions ever saved), but conceptually 1:1-active (only
  one `is_active = true` row is ever read by scheduling logic at a time).
- CaseType → Referral: 1:N.

### 6.3 Error / Edge Cases

- Duplicate `slug`: rejected by uniqueness validation (the record's own current slug is excluded from the
  uniqueness check on update).
- Submitting `rule_type = fixed_count` with `score_rules_text` also populated (or vice versa): the
  irrelevant fields are silently discarded rather than raising a validation error.

No AI-generated content is involved in this resource.

## 7. Resource: Admin — Users

**Represents:** a staff account — its identity plus its `role` (access level) and `department`. Managed
exclusively by `admin`.

### 7.1 Key Operations

#### List Users
- **Outputs:** all users, ordered by name.
- **Role:** `admin` only.

#### View Edit Form
- **Role:** `admin` only.

#### Update User Role / Department
- **Inputs:**

| Field | Conceptual type | Required | Notes |
|---|---|---|---|
| `role` | enum (`ward_staff`, `home_visit_team`, `admin`) | Y | |
| `department` | text | N | |

- **Outputs:** the `User`'s `role`/`department` updated.
- **Business rules:** this operation can change only `role` and `department` — it cannot touch `name`,
  `email`, or `password`. There is no safeguard preventing an admin from demoting themselves or removing the
  system's last remaining `admin` account (flagged in §9).
- **Role:** `admin` only.

### 7.2 Relationships

- User → Referral: 1:N via `created_by` (creator) and 1:N via `confirmed_by` (confirmer).
- User → ReferralAttachment: 1:N via `uploaded_by`.
- User → FollowUpRecord: 1:N via `performed_by` and 1:N via `confirmed_by`.

No AI-generated content is involved in this resource.

## 8. Resource: Dashboard

**Represents:** no entity of its own — a read-only aggregate/overview computed live from `Patient`,
`Referral`, `FollowUpPlan`, and `FollowUpRecord`, for the landing page every authenticated user sees.

### 8.1 Key Operation

#### Get Overview / KPIs
- **Trigger:** an authenticated and verified user opens the dashboard/home page.
- **Inputs:** none.
- **Outputs:**

| Field | Meaning |
|---|---|
| `totalPatients` | Count of all `Patient` records |
| `dueTodayCount` | Count of `FollowUpPlan`s at `status = scheduled` with `due_date` = today |
| `overdueCount` | Count of `FollowUpPlan`s at `status = scheduled` with `due_date` < today |
| `riskCount` | Count of `FollowUpRecord`s with `risk_flag = true` whose referral is not yet `closed` |
| `upcomingPlans` | Up to 20 `scheduled` `FollowUpPlan`s due today or earlier, oldest due date first, with referral/patient/case type |
| `recentRiskRecords` | Up to 5 most-recently-confirmed `risk_flag = true` records, with plan/referral/patient |
| `pendingReviewCount` | Count of `Referral`s at `status = pending_review` |

- **Business rules:** purely a read/aggregation operation — no state changes. "Overdue" is computed live
  from `due_date` vs. today at request time rather than relying on a stored status value — note that
  `FollowUpPlan` does define an `overdue` status value in its domain vocabulary, but this operation does not
  use or set it (see §9).
- **Role:** any authenticated **and verified** user. This is the only operation in scope that additionally
  requires the "verified" precondition (email verification) beyond plain authentication — every other
  operation in this document requires only authentication (see §9 on this inconsistency).

No AI-generated content is involved in this resource.

## 9. Open Questions / Assumptions

1. **Role granularity is not enforced in code beyond `admin`.** CLAUDE.md's workflow narrative implies
   `ward_staff` performs intake/summary-confirmation/decision-confirmation while `home_visit_team` performs
   visits/recording, but no route middleware or in-controller check enforces this split for any
   non-`admin.*` operation — any authenticated user of any role can invoke any Referrals/Follow-up Plans
   operation today. This document states role requirements as actually enforced; a reviewer should confirm
   whether finer-grained enforcement is intended for a future iteration or is deliberately left as a soft/UI
   convention.
2. **Patient upsert-by-HN has no conflict handling.** Creating a referral for an HN that already exists
   silently overwrites that patient's demographic fields with the newly submitted values — no merge, diff,
   or warning to the user. Worth confirming whether this is the intended behavior or a gap.
3. **No status guard on re-confirming a care plan.** `confirmCarePlan` does not check the referral's current
   `status` before applying changes, so it's unclear from the controller alone whether re-submitting the
   care-plan form for an already-`in_progress` or already-`closed` referral is an intended/supported
   operation or simply an untested path (the `generateInitialPlans` idempotency guard only protects against
   duplicate plan creation, not against re-writing `confirmed_summary`/`confirmed_by`/`confirmed_at`).
4. **Dashboard's `verified` middleware is the sole appearance of the "verified" precondition** in the whole
   route set — every other operation requires only `auth`. Flagged as a possible inconsistency rather than
   an assumed intentional design choice; a reviewer should confirm whether email verification is meant to
   gate more (or fewer) operations.
5. **`FollowUpPlan`'s `overdue` status value is defined in the domain vocabulary but never assigned** by any
   operation read for this document (no scheduled job/command was in scope to check). The Dashboard
   recomputes "overdue" live from `due_date` instead of relying on that stored status. Documented as
   observed behavior; not something this document invents a job/command to resolve.
6. **No safeguard against removing the last `admin` account or self-demotion** in the Update User
   Role/Department operation. Flagged for product decision, not silently assumed either way.

## 10. Revision History

| Date | Version | Change | Author |
|---|---|---|---|
| 2026-08-28 | 1.0 | Initial creation — Referrals, Follow-up Plans, Admin Case Types, Admin Users, Dashboard, derived from `routes/web.php`, controllers, models, services, and `CLAUDE.md`. | Claude (agent-api-database-schema task) |
