# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository currently is

This is **not** a runnable Laravel application yet — it's the hand-written, domain-specific slice of a
Laravel app (migrations, models, controllers, services, views, config) with no framework scaffolding
(`composer.json`, `artisan`, `bootstrap/`, `vendor/`, `.env.example` do not exist in this repo). Per
[SETUP.md](SETUP.md), the intended workflow is:

1. `composer create-project laravel/laravel tmp-laravel`, then move the generated framework files into this
   folder (never overwrite the files that already exist here — they're the actual product code).
2. `composer require laravel/breeze --dev && php artisan breeze:install blade && npm install && npm run build`
3. Wire `EnsureUserHasRole` as the `role` middleware alias in `bootstrap/app.php`.
4. Configure `.env` (DB + `OLLAMA_URL`/`OLLAMA_MODEL`/`OLLAMA_TIMEOUT`, see below) and `config/catchment.php`.
5. `php artisan migrate && php artisan db:seed` (seed via `CaseTypeSeeder`, called manually from
   `DatabaseSeeder::run()` per SETUP.md step 5).

Once scaffolded, standard Laravel commands apply: `php artisan serve`, `php artisan test` (or `phpunit`),
`php artisan migrate`, `php artisan tinker`. There is no test suite in this repo yet — check before assuming
`php artisan test` has anything to run.

If you're asked to "run" or "test" the app and the framework scaffolding described above isn't present,
say so rather than assuming `artisan`/`composer`/`npm` commands will work.

## What the app does

**Chira Continuity Care (Triple C)** is a continuity-of-care system for a Thai hospital's home-visit/
follow-up team, replacing the discontinued national "Thai COC" system. The end-to-end loop (see SETUP.md's
closing summary) is:

รับเคส (intake) → AI สรุปข้อมูล → พยาบาลตรวจสอบ/ยืนยันแผนดูแล → สร้างกำหนดการติดตามอัตโนมัติ →
เยี่ยมบ้าน/โทรติดตาม (พร้อมคู่มือจาก AI) → บันทึกผล → AI วิเคราะห์ความเสี่ยง →
**พยาบาลยืนยันการตัดสินใจเสมอ 100%** (ติดตามซ้ำ / ส่งต่อ / ปิดเคส) → สร้างกำหนดการถัดไปอัตโนมัติ หรือปิดเคส

### The one rule that governs every AI-touching feature

**Human-in-the-loop is non-negotiable** (DESIGN.md §4.1). `AiService` only ever produces a *draft*
(`ai_summary`, the follow-up guide, `ai_analysis`) — nothing it returns is committed to a decision-bearing
field until a nurse explicitly reviews/edits and confirms it (`confirmed_summary`, `nurse_decision`, etc.).
When touching any AI-adjacent controller/view, preserve this separation — never wire an AI response directly
into a field that drives scheduling or case status.

### Roles

Defined as constants on `App\Models\User` (`ROLE_WARD_STAFF` / `ROLE_HOME_VISIT_TEAM` / `ROLE_ADMIN`,
column `role`, default `ward_staff` for new signups). Enforced via the `role` middleware alias
(`App\Http\Middleware\EnsureUserHasRole::handle`, usage: `->middleware('role:admin,home_visit_team')`) —
only `admin` can reach the `/admin/*` routes.

### Core data flow / model relationships

`Referral` is the central "case" entity, tying together:
- `Patient` (demographics, `zone` enum `in_area`/`out_area`, resolved by `ZoneResolver` against
  `config/catchment.php`'s `in_area_sub_districts` list — falls back to manual selection if that list is
  empty)
- `CaseType` → `VisitRule` (one active rule per case type; `rule_type` is `fixed_count` — N visits every
  fixed interval — or `score_based` — interval looked up from a JSON `score_rules` table of
  `{min, max, interval_days, label}` ranges, driven by PPS Score for Palliative Care)
- `FollowUpPlan` (a scheduled visit/call; `plan_number` sequence) → `FollowUpRecord` (the recorded outcome;
  `next_follow_up_plan_id` self-links to whatever plan gets generated after a nurse decision)
- `ReferralAttachment` (private-disk-only file uploads, never public; download gated through
  `ReferralController::downloadAttachment`)

`App\Services\VisitPlanService` owns all scheduling logic and is the one place that knows how
`fixed_count` vs `score_based` rules translate into `FollowUpPlan` rows:
- `generateInitialPlans()` — fixed_count creates every visit up front; score_based creates only plan #1
  (later intervals depend on a PPS Score that doesn't exist yet).
- `generateNextPlan()` — called after a nurse decision of "repeat"/"refer"; no-ops if an upcoming
  `scheduled` plan already exists (the fixed_count case, pre-generated).
- `cancelRemainingPlans()` — called on "close"; cancels all still-`scheduled` plans.

`App\Services\AiService` is the only thing that talks to the LLM (self-hosted Ollama over HTTP,
`config/ai.php` → `OLLAMA_URL`/`OLLAMA_MODEL`/`OLLAMA_TIMEOUT`). **The Ollama URL must always be an
intranet address** — patient data (PHI) must never leave the hospital network, so never point this at a
public/cloud endpoint. Its three methods (`summarizeReferral`, `suggestFollowUpGuide`,
`analyzeFollowUpRecord`) each build a Thai prompt demanding a strict-JSON response and parse it via
`parseJsonResponse()`, which sets `parse_error: true` and preserves `raw_response` if the model didn't
return valid JSON — callers/views must handle that fallback state rather than assuming AI output always
parses.

### Routes → controllers

See [routes/web.php](routes/web.php) for the full map. Key groupings: `referrals.*` (intake + care-plan
confirm, `ReferralController`), `follow-up-plans.*` (guide/record/review/decision, `FollowUpController`),
`admin.case-types.*` / `admin.users.*` (gated by `role:admin`, under `Admin\CaseTypeController` /
`Admin\UserController`).

## Design system

[DESIGN.md](DESIGN.md) is the source of truth for all UI work — colors/type/spacing tokens, and named
component patterns that recur across every screen: the **AI-Draft box** (§3.3, dashed border +
"ร่างจาก AI — ยังไม่ยืนยัน" label → solid border + "ยืนยันแล้วโดย [name] เมื่อ [datetime]" once confirmed),
the **Nurse-Decision box** (§3.4, thick solid border, radio-cards not dropdowns), status/zone **badges**
(§3.2, always color + text, never color alone), **KPI stat tiles** (§3.5), **timeline** (§3.6), and
**sidebar navigation** (§3.7). Read the relevant section before building or editing any view — this is what
keeps the several dozen screens feeling like one product instead of ad hoc pages. Note: the actual Blade
views under `resources/views/` still use Breeze's default top-nav layout, not the sidebar DESIGN.md
specifies — don't assume the current views already match the design system.

## Prototypes

Clickable HTML/CSS/JS prototypes (built via the `create-prototype` skill and the `prototype-builder` agent)
live under `prototypes/v<N>-<slug>/`, each with a `MANIFEST.md` describing what it covers and why. These are
static mockups for stakeholder review, not part of the Laravel app itself.

## Detailed design docs

Internal/technical design documents — conceptual design (actors/components/responsibilities) plus at least
one Mermaid sequence-flow diagram per flow — are built via the `detailed-design` skill and the
`detailed-design-writer` agent, and live under `docs/design/<SLUG>_DESIGN.md` (one file per feature/flow,
same naming convention as `docs/testing/*.md`). Unlike prototypes, these are updated in place rather than
versioned per attempt. Any architecturally significant point left unresolved gets its own "Open Decisions"
section (problem + ≥3 options + pros/cons) rather than being silently decided.
