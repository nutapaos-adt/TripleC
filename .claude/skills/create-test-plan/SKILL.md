---
name: create-test-plan
description: Creates or updates the project's test documentation set — TEST_PLAN.md, ACCEPTANCE_CRITERIA.md, and TEST_CASES.md under docs/testing/ — for the Chira Continuity Care (Triple C) project. Driven by whatever the user has — a Requirement, Backlog, Feature List, User Journey, a reference to already-built code, or any combination — the user doesn't need to supply all of them. Use this whenever the user asks to write/generate/update a test plan, test cases, or acceptance criteria (in Thai: "ทำ test plan", "สร้าง test case", "เขียน acceptance criteria", "ทำเอกสารทดสอบ"), whether for the whole system, a specific feature/module, or a not-yet-built feature described only in a spec. Also use when the user wants to extend existing test docs to cover a newly built feature.
---

# Create Test Plan

This skill produces (or extends) the project's living test documentation: `docs/testing/TEST_PLAN.md`,
`docs/testing/ACCEPTANCE_CRITERIA.md`, and `docs/testing/TEST_CASES.md`. Acceptance criteria and test cases
are only useful if they're grounded in something concrete — ideally the *actual* code (exact field names,
constants, validation rules, transaction boundaries), but for features that don't exist yet, a Requirement,
Backlog, Feature List, or User Journey the user provides is the next-best ground truth. This skill accepts
either or both, and is explicit about which one backed each AC/TC so nobody mistakes a spec-based guess for a
verified behavior.

You are the orchestrator here, not the writer. The actual reading-and-drafting work is done by the
`test-case-writer` subagent (`.claude/agents/test-case-writer.md`) — your job is to scope the work correctly,
gather whatever source material exists, avoid ID collisions with what already exists, fan the work out in
parallel, and assemble the results into consistent documents. All questions and summaries you produce for the
user should be written in Thai — that's the language this project's users work in — even though these
instructions are in English.

## 1. Work out the scope and gather source material

Figure out what needs AC/TC coverage, and what grounds it:

- **Whole system, code-only:** the user says something like "ทำ test plan ทั้งระบบ" with no separate spec
  document. Identify modules by reading `routes/web.php` and the `app/Http/Controllers/`, `app/Services/`
  directory structure — group related controllers/services into modules the way the existing docs do (one
  module per cohesive user-facing flow or cross-cutting concern, not one file per module). If `docs/testing/`
  already has coverage, treat this as "add whatever's missing" rather than starting over.
- **Specific feature/module, code-only:** the user names a feature, a controller, or references something
  just built, with no separate spec document. Confirm which files/routes are in scope by grepping/reading
  before treating it as decided — don't guess at boundaries for something ambiguous (e.g. "the referral
  stuff" could mean intake only, or intake + AI summary + confirmation).
- **Driven by a Requirement / Backlog / Feature List / User Journey (any subset, the user doesn't need all
  four):** the user pastes, attaches, or points to one or more of these for a feature — whether or not it's
  built yet. Read whatever they gave you. Two sub-cases matter for how you brief `test-case-writer` in step 4:
  - **The feature already has code** (check by grepping for related routes/controllers/models before
    assuming otherwise): pass the subagent BOTH the spec document(s) and the file list, so AC statements can
    explain *why* a rule exists (from the spec) while still verifying *what actually happens* against the
    real code. Code wins when the two disagree — treat a mismatch as a "known gap" finding, not silently
    resolved in favor of the spec.
  - **The feature has no code yet** (a planned/future feature): there is nothing to ground against. Pass the
    subagent the spec document(s) only, and make clear in the brief that this output must be labeled
    **pre-implementation / not yet verified against code** throughout (module heading, AC statements, and a
    note in the TC table) — see step 5 for exactly how this gets marked in the assembled docs.
- If the user gave almost nothing to go on and named no specific feature, code area, or document, ask
  directly what should be covered before doing anything else — don't guess at scope for something this easy
  to get wrong. If scope is ambiguous in a way that changes what gets built (whole system vs one flow, or
  code-grounded vs spec-only for an unbuilt feature), ask a multiple-choice question in Thai with concrete
  options — mirror how `create-prototype` handles this — rather than silently picking one interpretation.

## 2. Check what already exists (avoid ID collisions and duplicate work)

Read `docs/testing/ACCEPTANCE_CRITERIA.md` and `docs/testing/TEST_CASES.md` if they exist.

- Note every module prefix already in use (e.g. `INTAKE`, `SUMMARY`, `SCHED`, `RECORD`, `DECISION`,
  `ADMINRBAC`, `DASHNFR`) and the highest AC/TC number used within each.
- **New module in scope:** pick a new short, uppercase, single-word prefix that doesn't collide with an
  existing one (e.g. a new "reporting" feature might be `REPORT`).
- **Existing module needs more coverage** (e.g. a feature was added to an already-documented flow): continue
  numbering from the existing module's highest AC/TC number — never renumber or renumber-and-shift existing
  IDs, since other documents/tickets may already reference them.
- If neither file exists yet, this is a from-scratch run — proceed with fresh module prefixes.

## 3. Present the plan before dispatching any subagent

Tell the user, in Thai, before doing anything:
- Which module(s) you've identified and their ID prefixes (new or continuing existing ones)
- Which files/routes each module covers (so they can correct scope before work starts)
- Whether this creates the three docs fresh or extends existing ones

Wait for confirmation only if the scope was ambiguous enough to need step 1's question. If the user's original
request already specified scope clearly (e.g. named a specific feature), you can proceed straight to step 4
without a separate confirmation round-trip — don't add friction for something already decided.

## 4. Dispatch `test-case-writer` per module, in parallel

For each module in scope, call the Agent tool with `subagent_type: "test-case-writer"`. Give each call:

- The module's ID prefix (new, or continuing from step 2)
- The starting AC/TC number to continue from (1 for a new module, or existing-max + 1 for an extension)
- The exact list of relevant files to read (controllers, models, services, form requests, migrations,
  relevant `DESIGN.md`/`config/*.php` sections) — pull this from your own scoping in step 1, don't make the
  subagent rediscover project structure from scratch. Omit this entirely (see next bullet) for a feature
  that has no code yet.
- If a Requirement/Backlog/Feature List/User Journey was provided (step 1): the full text of whatever's
  relevant to this module — don't paraphrase it away, the subagent needs the actual requirement wording to
  ground AC statements in. State explicitly whether the module has real code to verify against or is
  spec-only (pre-implementation) — this determines whether the subagent's job is "verify the spec against
  the code, code wins on conflict" or "draft directly from the spec, mark everything as not yet verified"
- Enough context about the module's business purpose (what user-facing flow or concern it covers, and how it
  relates to the human-in-the-loop AI rule if AI is involved — see `CLAUDE.md`) so the subagent's AC/TC
  reflect *why* a rule exists, not just *what* the code does
- An instruction to flag (not silently fix or silently omit) anything it finds that looks like a gap between
  code behavior and what a reasonable person would expect — the existing docs' "Known Gaps" pattern — or, for
  a spec-only module, anything the requirement leaves genuinely ambiguous (e.g. an edge case the spec doesn't
  address) rather than silently picking an interpretation

If there's more than one module in scope, launch all the `test-case-writer` calls in the same turn — they're
independent reads of different code, there's no reason to serialize them.

## 5. Assemble the results yourself

Do not have subagents write directly into the shared docs — merge their output yourself, because consistent
numbering, a working table of contents, and the cross-file "Related AC" links depend on seeing everything at
once:

- **`docs/testing/ACCEPTANCE_CRITERIA.md`**: append each new/updated module's AC section in the existing
  format (ID, statement in Thai referencing exact field/const names). Update the module table of contents.
  For a **spec-only (pre-implementation) module**, add a one-line notice directly under the module heading —
  e.g. "⚠️ ยังไม่มีโค้ดจริงให้ตรวจสอบ — AC ชุดนี้ร่างจาก [ชื่อเอกสาร spec] เท่านั้น รอ verify ซ้ำหลัง implement" —
  so nobody downstream mistakes it for verified behavior.
- **`docs/testing/TEST_CASES.md`**: append each new/updated module's TC section/table, keep the running total
  count in the intro accurate, update the table of contents. Give a spec-only module's TC table the same
  pre-implementation notice as its AC section.
- **`docs/testing/TEST_PLAN.md`**: update the module coverage table (§2.1) with the new/changed AC/TC counts
  (mark spec-only modules in that table too, e.g. a "Grounded in" column value of "spec (pre-implementation)"
  vs "code"), and fold any newly-flagged behaviors — known gaps from code review, or open ambiguities from a
  spec — into the "Known Gaps / Product Decisions Needed" table (§7) — each row needs the item, module, AC/TC
  reference, and impact-if-not-fixed, exactly like the existing rows.

If this is the very first run (no existing docs), write all three files fresh following the same structure —
read the current versions of these three files first if they exist so your additions match the established
tone, heading levels, and table formats exactly (don't introduce a different style partway through).

## 6. Wrap up

Tell the user, in Thai:
- Which module(s) were added/updated, with their AC/TC counts
- The new running totals across all three documents
- Any newly-flagged items added to the Known Gaps table, and that these need a product decision (accept vs
  fix) rather than being treated as already-resolved
- Offer to commit the changes to git if they want (don't commit without being asked)
