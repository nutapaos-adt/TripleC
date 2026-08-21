# v1-full-coc-flow

**Date:** 2026-08-21

**Driven by:** No standalone Requirement/Backlog/Feature List/User Journey doc existed for this yet. This
version was reverse-engineered from the current Triple C codebase — `SETUP.md` (informal feature/completion
log), `DESIGN.md` (design system + component patterns), and the actual data model/routes in
`app/`, `database/migrations/`, and `routes/web.php` — then confirmed with the user as the full 12-screen
scope covering the entire continuity-of-care loop plus admin.

**Screens included:**

| File | Screen | Flow |
|---|---|---|
| `dashboard.html` | Team dashboard (KPI tiles, today/overdue follow-ups, risk signals) | Dashboard |
| `referrals-list.html` | Referral/case list | Case intake |
| `referral-create.html` | New referral intake form | Case intake |
| `referral-detail.html` | Referral detail hub (patient info, timeline, attachments) | Case intake |
| `care-plan-confirm.html` | AI-drafted care plan → nurse confirmation | AI care-plan confirmation |
| `followup-guide.html` | AI-suggested pre-visit/pre-call guide | Home-visit / phone follow-up |
| `followup-record.html` | Record follow-up outcome | Home-visit / phone follow-up |
| `review-decide.html` | AI risk analysis + mandatory nurse decision (repeat/refer/close) | Risk analysis + nurse decision |
| `admin-case-types-list.html` | Case types list | Admin |
| `admin-case-type-form.html` | Case type create/edit (fixed-count vs score-based visit rules) | Admin |
| `admin-users-list.html` | Users list | Admin |
| `admin-user-edit.html` | User role/department edit | Admin |

**Design decisions confirmed with user:**
- Full 12-screen scope in one pass (not split into smaller batches).
- Prototype follows the DESIGN.md spec fully, including the sidebar navigation (dark olive gradient) and
  patient-history timeline component — both specified in DESIGN.md but not yet implemented in the current
  Blade views. This prototype is intentionally ahead of the current app's actual UI.

**Known prototype simplifications (not bugs):**
- All referral rows in `referrals-list.html` link to the same `referral-detail.html` (one mock patient),
  and all admin list rows link to a single shared edit-form file — static prototypes don't have per-ID
  routing.
- Each screen batch was built by a separate agent; a link-consistency pass was done afterward to align
  sidebar nav filenames across all 12 files (a few agents initially invented slightly different filenames
  for sibling screens before the full set existed).
