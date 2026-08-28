---
name: spec-api-db
description: Creates or updates conceptual, technology-agnostic API spec and/or database schema/spec documents for this project (Chira Continuity Care / Triple C) — entity/resource-level documentation (attributes, cardinality, business rules, operations) that isn't tied to a DBMS engine or an API protocol. Use this whenever the user wants to document, design, or revise the data model or API contract at a conceptual level — phrases like "ทำ database spec", "เขียน API spec", "ออกแบบ ER Diagram", "อัปเดตสเปกฐานข้อมูล", "conceptual data model", or a request to document tables/entities/endpoints before or alongside implementation should all trigger this skill. Also use it when the user asks to revise or extend a spec document that was made before.
---

# API / Database Conceptual Spec

This skill produces or revises **conceptual** documentation of the data model and/or API contract for this
project — the kind of spec that describes what entities and operations exist and how they relate, without
committing to a database engine, ORM, or API protocol. It exists because decisions like "will this be
MySQL or Postgres", "REST or something else" shouldn't gate agreement on what the domain actually looks
like — that agreement should be reachable and reviewable on its own first.

You are the orchestrator here, not the writer. The actual document content gets written by the
`spec-writer` subagent (`.claude/agents/spec-writer.md`) — your job is to work out exactly what's in scope,
resolve every ambiguity with the user first, and hand off a clear, fully-resolved brief. All questions and
summaries you produce for the user should be written in Thai — that's the language this project's users
work in — even though these instructions are in English.

## 1. Work out what's being documented

Figure out whether this is:
- **Database schema/spec** only (entities/tables/ER diagram),
- **API spec** only (resources/operations), or
- **Both** (most requests that mention "the system" or a whole feature area probably want both, since an
  operation's inputs/outputs are only fully meaningful next to the entities they touch).

Also figure out the **module/feature scope** — the whole system, or a specific slice (e.g. just referrals +
follow-up plans, or just admin/user management). If the user gave almost nothing to go on ("ทำ database
spec ให้หน่อย"), ask directly what scope they mean rather than guessing at something this consequential —
but you can propose a sensible default scope (e.g. "the whole current data model, since that's what exists
in code today") as one of the options rather than asking a bare open question.

## 2. Ground the scope in what already exists

Before asking the user anything else, read what's derivable from the repo so you don't ask questions the
code already answers:

- `database/migrations/*.php` and `app/Models/*.php` — for the database side: tables, columns, casts,
  relationships (`belongsTo`/`hasMany`/etc.), constants (enum-like values).
- `routes/web.php` and the relevant `app/Http/Controllers/**/*.php` — for the API side: what operations exist
  per resource, grouped by route name prefix (`referrals.*`, `follow-up-plans.*`, `admin.case-types.*`,
  `admin.users.*`).
- `CLAUDE.md` — domain rules that don't live in code as such (the human-in-the-loop rule, role definitions,
  the `fixed_count`/`score_based` visit-rule split, zone resolution).
- Any existing docs under `docs/database/` or `docs/api/` (check with Glob) — if a spec already exists for
  this area, this is an **update**, not a fresh document; read it fully before planning changes.

## 3. When something is unclear, offer real choices

Code only tells you what's implemented — it won't tell you about planned-but-not-yet-built entities, the
right level of granularity, or documentation-format preferences. Whenever a decision would materially change
the document and isn't settled by the code, don't guess and don't ask a bare open-ended question. Ask a
multiple-choice question in Thai with **at least 3 concrete options, each with a short ข้อดี/ข้อเสีย
(pros/cons)** note, so the user is making an informed choice. Ambiguities worth surfacing this way typically
include:

- **API documentation style** — e.g. (a) pure conceptual/action-based (operation described by domain intent
  only), (b) conceptual with an informal HTTP-flavored mapping alongside it for readability, (c) conceptual
  plus a request/response example payload per operation. Trade off: (a) stays cleanest/most stack-agnostic
  but is less immediately actionable for a future implementer; (b)/(c) are more concrete but start to imply
  technical choices earlier than the user may want.
- **Granularity of per-table detail** — e.g. (a) attribute-level table only, (b) attribute-level plus a
  narrative business-rules section per entity, (c) attribute-level plus business rules plus sample data.
  Trade off: more detail is more useful to a reader unfamiliar with the domain but is more to keep in sync
  later.
- **Scope of "in progress" or planned entities** not yet in the code — e.g. (a) document only what's
  implemented today, (b) include planned entities marked clearly as "ยังไม่ implement", (c) document both in
  one file vs. splitting current/planned into separate files. Trade off: (a) stays perfectly accurate to
  code but may miss context a reviewer needs; (b)/(c) capture more intent but risk drifting from reality if
  not maintained.
- **New document vs. editing an existing one**, when a matching doc already exists and the request could
  read either way — mirror the reasoning `create-prototype` uses for prototype versions: lean toward
  editing in place for a refinement/fix to something just reviewed, lean toward a clearly-marked new
  section/revision for a substantially different scope.
- Anything else genuinely ambiguous: an entity's cardinality that the code doesn't make obvious, whether a
  soft-delete/audit-trail concern needs documenting, how much of the RBAC role matrix belongs in the API doc
  vs. only in the DB doc, etc.

## 4. Present the plan before writing anything

Write out, in Thai, a short plan covering:
- Which entities (for DB spec) and/or resources (for API spec) are in scope, and where each fact came from
  (code vs. user input)
- Which file(s) this becomes — new or existing (see §5) — and the resolved answers to every question from
  §3
- That the document(s) will stay conceptual (no DBMS types, no forced protocol framing) per the minimum
  content rules in `spec-writer`'s instructions

Then wait for the user to confirm. Don't create or touch any spec files before they've said yes.

## 5. Work out file placement

Default locations, one file per doc type so each can evolve independently:
- Database schema/spec → `docs/database/DATABASE_SPEC.md`
- API spec → `docs/api/API_SPEC.md`

If either already exists (check with Glob before assuming), or the user names a different path/structure
(e.g. one file per module instead of one big file), follow that instead — confirm the deviation as part of
the plan in §4 rather than silently choosing a layout.

## 6. Hand off the writing to spec-writer

Once the plan is confirmed, call the Agent tool with `subagent_type: "spec-writer"` — one call per document
(DB spec and API spec are independent once scope is resolved, so launch them in parallel in the same turn
when both are in scope). Give each call:
- The exact entities/resources in scope, pulled from your research in §2 (don't make the subagent
  re-derive scope from scratch — hand it the list)
- Every resolved decision from §3 (documentation style, granularity, planned-vs-implemented handling, etc.)
- The exact source-of-truth files to read (§2's list, scoped to what's relevant)
- The exact output file path from §5

## 7. Wrap up

After the subagent(s) finish, tell the user (in Thai) what was written or updated, where, and summarize any
"Open Questions / Assumptions" the subagent logged in the document so the user knows what still needs their
input. Offer to publish the document as an Artifact for easier review (Mermaid ER diagrams render natively
in Artifacts), or give them the local file path to open directly.
