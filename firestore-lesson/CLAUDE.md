# CLAUDE.md — firestore-lesson

This folder is a **standalone Firestore + Firebase Auth/Hosting mini-app**, separate from the Laravel
Triple C application at the repo root (see the root [CLAUDE.md](../CLAUDE.md)). It exists to satisfy the
RAISE2 Module 2 coursework (week 6 Firestore lesson, week 7 CRUD/auth/ACL homework) using `Referral` — one
entity from the real Triple C domain — as the running example. See [SCOPE.md](SCOPE.md) for what is and
isn't covered, and [ENTITY_CONTEXT.md](ENTITY_CONTEXT.md) for where the data model comes from.

Nothing here talks to the Laravel app, its database, or `AiService` — this is a plain static site
(HTML/CSS/vanilla JS modules, Firebase JS SDK loaded from the `gstatic.com` CDN) plus one Node/Admin-SDK
script (`setup-users.js`) run manually, deployed to Firebase Hosting + Firestore directly.

## Running locally

```bash
powershell -File server.ps1   # serves this folder at http://localhost:8080
```

No build step — every page is a plain `.html` file importing its matching `js/*.js` module.

## Firestore collections

| Collection | Doc ID scheme | Purpose |
|---|---|---|
| `referrals` | auto-id | The case entity — see status list below. Fields: `patientId`, `caseTypeId`, `sourceType`, `sourceDetail`, `createdBy` (uid), `createdByName`, `rawNotes`, `aiSummary` (map/null — AI draft, never written by client code, seeded/null only), `confirmedSummary` (map/null), `confirmedBy` (uid/null), `confirmedAt` (Timestamp/null), `zone`, `status`, `closedAt` (Timestamp/null), `createdAt` (Timestamp) |
| `patients` | auto-id (seed data uses `patient_00N`) | `fullName`, `hn`, `zone` |
| `caseTypes` | auto-id (seed data uses `ct_*` slugs) | `name`, `slug`, `isActive` |
| `users` | **Firebase Auth uid** for real accounts (seed data also has legacy arbitrary ids like `user_ward01` — display-only, never referenced by new writes) | `name`, `role`, `email` — **this doc is the source of truth for role**, see below |

There is no `followUpPlans`/`followUpRecords` collection — the "follow-up rounds" shown on the case detail
page are illustrative UI only (see the comment in `js/referral-detail.js`), not backed by their own
documents. Don't wire real persistence to them without first adding a collection + rules for it.

## `referrals.status` — every value, and who moves it forward

```
pending_review → plan_confirmed → in_progress → closed
```

- `pending_review` — default status on create. Set only by `referral-create.js` (ward_staff/admin).
- `plan_confirmed` — set only by the "ยืนยันแผนดูแล" (confirm plan) action in `referral-detail.js`, by
  `home_visit_team`/`admin`, never by the referral's own creator.
- `in_progress` / `closed` — set by the follow-up round buttons (same role/self-approve rule). `closed`
  also sets `closedAt`.

No other code path may change `status`. If you add a new write path, it must go through the same
role + self-approve check as `onConfirmPlan`/`onConfirmRound`, and `firestore.rules` must be updated to
match — the rule enforces the *same* restriction server-side (`affectedKeys().hasOnly([...])`), so a
client-side-only change is not real enforcement.

## Roles

Read from the caller's own **`users/{uid}` Firestore document** (`js/session.js`'s `requireLogin()` does
a `getDoc()`), *not* a Firebase Auth custom claim — custom claims can only be set via the Admin SDK, which
would make self-service registration (`register.html`) impossible without adding Cloud Functions (out of
scope here). Three roles: `ward_staff`, `home_visit_team`, `admin`. Full capability matrix: see
[ACL.md](ACL.md).

- `ward_staff` / `home_visit_team` — created by self-registration (`register.html` → `js/register.js`),
  which writes its own `users/{uid}` doc. `firestore.rules`' `create` rule on `/users/{userId}` only
  accepts these two role values and only lets a user write their *own* uid's doc — never someone else's,
  never `admin`.
- `admin` — can **only** be provisioned via `setup-users.js` (Admin SDK, bypasses rules entirely). There is
  no self-service path to admin, by design.
- After creation, a `users/{uid}` doc cannot be edited by its own owner (`update`/`delete` require
  `myRole() == 'admin'`) — a self-registered user can never later rewrite their own role.

## Prohibitions — do not do these

- **Never commit `serviceAccountKey.json`** (already in the root `.gitignore`) — it is a Firebase Admin
  SDK credential with full project access, distinct from the public web `apiKey` in
  `js/firebase-config.js` (that one is meant to be public; security is enforced by `firestore.rules`, not
  by hiding the config).
- **Never hardcode or commit real login credentials** (email/password) anywhere in this repo, `README.md`
  included — this project used to ship 3 demo accounts with published passwords; they were deleted for
  exactly this reason. `setup-users.js` reads admin credentials from environment variables at run time,
  never from a literal in the script.
- **Never let `register.html`/`js/register.js` offer `admin` as a selectable role**, and never loosen the
  `/users/{userId}` `create` rule's `role in ['ward_staff', 'home_visit_team']` check — that check is the
  only thing standing between "self-service signup" and "self-service privilege escalation."
- **Never let a user's own `users/{uid}` doc be edited by anyone but an admin** after creation — if you add
  a "edit my profile" feature, keep the role field out of what a self-update can touch, or route role
  changes through an admin-only path.
- **Never read or write Firestore without going through `requireLogin()`** (`js/session.js`) on the client,
  *and* never weaken `firestore.rules` to allow `request.auth == null` — both halves are required, the
  client gate alone is not security.
- **Never let a ward_staff query return another user's referrals.** A `list` query whose `where` clause
  doesn't match the security rule's condition gets a blanket `permission-denied` from Firestore (rules
  are not a post-query filter) — so any new "list referrals" query for `ward_staff` must keep the
  `where('createdBy', '==', uid)` filter, not rely on the rule to hide rows.
- **Never let a user confirm/advance a case they created themselves** (self-approval), for any role,
  admin included — enforced in both `firestore.rules` (`!isOwner(resource.data)`) and
  `referral-detail.js`'s `canConfirm()`. If you add a new status-changing action, gate it the same way.
- **Never deploy Hosting without also deploying `firestore.rules`** — an open/default-allow ruleset
  exposes patient-shaped data to anyone. Deploy both together: `firebase deploy --only hosting,firestore:rules`.
- **Never point this at a different Firebase project** without updating both `js/firebase-config.js` and
  `.firebaserc` — they must reference the same project.
