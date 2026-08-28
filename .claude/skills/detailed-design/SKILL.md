---
name: detailed-design
description: Creates or improves a detailed/conceptual design document — with at least one sequence flow diagram — for a feature or flow in this project (Chira Continuity Care / Triple C). Use whenever the user wants a "detailed design", "conceptual design", "design doc", "sequence diagram/flow", wants to design or redesign how a feature works internally, or asks to improve/update an existing design document. Thai equivalents that should also trigger this: "ออกแบบ detailed design", "ทำ conceptual design", "เขียน sequence flow/diagram", "ปรับปรุง design ของ...". This is about internal/technical design (components, data flow, sequence of calls) — not visual UI mockups (use create-prototype for those) and not the DESIGN.md visual design system itself (only read/reference it).
---

# Detailed Design

This skill produces a Markdown detailed-design document for a specific feature or flow in this project —
conceptual design (actors, components, responsibilities) plus at least one sequence-flow diagram, grounded
in the actual Laravel code that exists in this repo (see `CLAUDE.md`), not invented from scratch. It also
handles improving/updating a design document that already exists.

You are the orchestrator, not the writer. The actual document gets written by the `detailed-design-writer`
subagent (`.claude/agents/detailed-design-writer.md`) — your job is to pin down scope, resolve every
ambiguity that would change the design (asking the user with concrete options, never guessing on anything
architecturally significant), and hand off a clear brief. All questions and summaries you produce for the
user should be written in Thai — that's the language this project's users and stakeholders work in — even
though these instructions are in English.

## 1. Work out what's being designed

Read whatever the user gave you — a feature name, a requirement, a backlog item, a piece of code they want
redesigned, or a rough description of a flow. If they gave you almost nothing to go on ("ทำ detailed
design ของฟีเจอร์ที่คุยกัน"), ask directly what feature/flow they mean before doing anything else.

Then ground yourself in the real system before deciding scope:
- Read `CLAUDE.md` for the architecture you must respect (roles, `Referral`/`FollowUpPlan`/
  `FollowUpRecord`, `VisitPlanService`, `AiService` and its human-in-the-loop rule).
- Use Glob/Grep/Read to find the actual models, services, controllers, migrations, and routes touching the
  requested feature. A detailed design for something that partly doesn't exist yet in code is fine — just
  be explicit in the plan (step 4) about what's existing vs. new.

If the request is broad (a whole module, several flows), don't try to document everything in one file by
default — propose splitting into the distinct flows that make sense, and confirm that split with the user
in the plan, the same way `create-prototype` breaks a large journey into screens before building.

## 2. Check for an existing design doc to improve

Design docs live under `docs/design/`, one file per feature/flow: `docs/design/<SLUG>_DESIGN.md` (same
naming convention as `docs/testing/TEST_PLAN.md` etc. — upper-snake-case slug).

- **No `docs/design/` folder yet, or nothing matching this topic:** this is a new document — proceed.
- **A file already covers this topic:** ask the user (in Thai) whether they want to **update it in place**
  or **start a fresh document**, with your recommendation and why:
  - Recommend **updating in place** when the request reads like a refinement, correction, or the design
    naturally evolving (most cases — a design doc is meant to track current understanding, not a history
    of every draft).
  - Recommend a **new document** only when the request is really a different/competing design for the same
    flow that the user may want to compare side by side, or a fully separate feature that happens to share
    a name.
- If it's ambiguous which existing file (if any) the request refers to, list the candidates you found and
  ask which one, rather than guessing.

## 3. Resolve ambiguity with real choices — before handing off

For anything about the request that's unclear in a way that would change the design — which of two
plausible flows is meant, sync vs. async, who triggers what, what happens on an edge case, scope
boundaries — do not ask a bare open-ended question and do not silently pick an interpretation. Ask via
AskUserQuestion, in Thai, with **at least 3 concrete options**, each with a short ข้อดี/ข้อเสีย
(pros/cons), so the user is making an informed choice. This mirrors `create-prototype`'s step 5 and is a
hard requirement for this skill, not a suggestion — every materially ambiguous point must get this
treatment before you write the brief in step 5.

Small, non-architectural details you can reasonably infer from `CLAUDE.md`/existing code don't need this —
reserve it for choices that actually change the shape of the design.

## 4. Present the plan before building anything

Write a short plan, in Thai, covering:
- Which feature/flow(s) are in scope, and which are explicitly out of scope
- New document vs. updating an existing one, and the target path
- That it will be grounded in the real code (name the key files/classes you already found in step 1) and
  will respect the human-in-the-loop rule for any AI-touching flow
- That it will include at least one sequence-flow diagram per distinct flow

Wait for confirmation before writing or editing any file — catching a scope misunderstanding here is much
cheaper than after a full document is written.

## 5. Hand off to detailed-design-writer

Once confirmed, call the Agent tool with `subagent_type: "detailed-design-writer"`. Give it:
- The feature/flow(s) in scope, pulled from the user's request and your step-1 grounding (don't paraphrase
  away specifics — pass along the actual requirement text and the file/class names you found)
- Whether this is a new document or an update to an existing one (pass the existing path if updating)
- The exact output file path (`docs/design/<SLUG>_DESIGN.md`)
- Any decisions the user already made in step 3, stated plainly so the agent documents them as resolved
  rather than re-raising them as open questions
- A reminder to read `CLAUDE.md` (and `DESIGN.md` if UI is involved) itself — it doesn't inherit your
  context

If more than one independent flow/document is in scope, launch them in parallel in the same turn.

## 6. Close out any Open Decisions the agent raised

The subagent's final response lists any "Open Decisions" it had to leave in the document (problem +
options + pros/cons) because they were architecturally significant but not resolved by your brief. If
there are any:
- Present each one to the user via AskUserQuestion, in Thai, using the options the agent already drafted
  (refine the pros/cons if you can add real project context).
- Once the user decides, update the document yourself (Edit) to mark that section resolved — replace the
  placeholder default with the chosen option, move the rejected options into the "Key Design Decisions &
  Alternatives Considered" section as the alternatives-not-taken, with the reasoning the user gave.
- If a decision the user makes has knock-on effects elsewhere in the document (e.g. it changes a sequence
  diagram), re-invoke `detailed-design-writer` with a targeted brief to update just that part, rather than
  hand-editing a diagram yourself.

## 7. Wrap up

Tell the user (in Thai) what was written/changed and where, and offer to publish the document as an
Artifact (Markdown artifacts render Mermaid diagrams natively) so it's easy to review the sequence
diagrams — or give them the local file path if they'd rather open it directly.
