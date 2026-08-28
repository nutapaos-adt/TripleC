---
name: create-architecture-doc
description: Creates or updates the conceptual, stack-agnostic high-level Architecture document for the Chira Continuity Care (Triple C) project — system context, conceptual components, data flow mapped to the user journey, trust boundaries, and other standard architecture-doc sections. Use this skill whenever the user asks to write, draft, review, or update a high-level/conceptual architecture document, system overview, or data-flow-by-user-journey doc — even if phrased loosely, e.g. "ทำเอกสาร architecture ให้หน่อย", "สรุป data flow ตาม user journey", "อยากได้ high level design ที่ไม่ผูกกับ tech stack", "ปรับปรุงเอกสาร architecture". Not for code-level/technical design docs, ER diagrams tied to a specific DB schema, or infrastructure/deployment diagrams — those are technical, not conceptual.
---

# Create / Update Architecture Document

This skill produces the project's high-level Architecture document: a **conceptual** description of the
system — components by responsibility, data flow walked through the user journey, trust boundaries, roles —
that stays valid even if the underlying technical stack changes. It exists because CLAUDE.md, SETUP.md, and
DESIGN.md each answer a different question (how to scaffold, what the UI looks like) but none of them is the
single place a new engineer or stakeholder can go to understand *how the system is shaped* without reading
every model and controller.

You are the orchestrator here, not the writer. The actual document content is produced by the
`architecture-doc-writer` subagent (`.claude/agents/architecture-doc-writer.md`) — your job is to gather
context, resolve ambiguity with the user, agree an outline, and hand off a clear, scoped brief. Any questions
or summaries you write for the user should be in Thai, matching how this project's stakeholders communicate,
even though these instructions are in English.

## 1. Work out what's being documented

Read whatever the user gave you — a requirement, backlog, feature list, user journey, a pasted spec, or just
a description of a change to an existing section. Any subset is fine.

If the request is vague ("ทำเอกสาร architecture ให้หน่อย") and there's no journey/requirement material to
work from at all, ask for at least a user journey or feature description before drafting — an architecture
document invented from nothing is worse than no document, since it will read as authoritative without being
grounded in anything real.

## 2. Gather what the repo already knows before asking the user anything

Read what's already here so you don't make the user repeat information that's sitting in the repo:

- **CLAUDE.md** — system purpose, the end-to-end user journey, the non-negotiable human-in-the-loop rule,
  roles, core data flow/model relationships, AI service boundaries
- **SETUP.md** — intended deployment/config shape (useful for spotting external dependencies and
  constraints, even though the doc itself must stay stack-agnostic)
- **docs/testing/*.md** (ACCEPTANCE_CRITERIA.md, TEST_CASES.md, TEST_PLAN.md) — often surface flow details
  and edge cases not obvious elsewhere
- **DESIGN.md** — named UI component patterns (AI-draft box, nurse-decision box) can hint at conceptual
  capabilities/control points worth naming in the architecture doc
- **The existing architecture doc**, if this is an update (see step 3 for its location)

## 3. Confirm the document's home and scope

- **No architecture doc yet:** the canonical location is `docs/architecture/ARCHITECTURE.md`, mirroring the
  existing `docs/testing/` convention in this repo. It's a single evolving document, not versioned copies —
  unlike `prototypes/`, there's no value in comparing architecture drafts side by side, and git history
  already serves as the version log. Confirm this path with the user only if they've suggested a different
  location; otherwise just state where it'll go.
- **Doc already exists:** read it in full first. Then work out with the user (ask if it's not obvious from
  their request) whether this is a wholesale rewrite, a new section, a revision to one journey/component, or
  an update to reflect a changed decision — and scope the subagent brief to exactly that, so unrelated
  sections don't get silently rewritten.

## 4. Keep it conceptual — this is the one rule that matters most

The entire point of this document is that it stays correct even if the technical stack underneath changes.
That means, throughout:

- Name components by **responsibility**, not by framework/library/language/product — "AI Draft Engine",
  "Scheduling Engine", "Case Intake", never "Laravel controller", "MySQL table", "Ollama". If a current
  concrete choice is worth flagging, it belongs in a clearly marked implementation-note aside, not as the
  architectural noun itself.
- Describe external dependencies by **role and constraint** — "a self-hosted LLM inference service reachable
  only inside the hospital intranet; no patient data may leave the network" rather than naming a specific
  product.
- Describe data by **concept and relationship** (entities, not schema/columns/tables).

Hold the subagent brief to this same rule in step 7.

## 5. What the document should cover

Use this as a scoping checklist with the user, not a template to fill mechanically — not every doc needs
every section on day one, especially for a partial update:

- Purpose & scope, and what's explicitly out of scope
- Actors/roles and their conceptual permissions
- System context — what's inside the system boundary vs. external, and why
- Conceptual component/capability map — the logical building blocks and what each owns
- **Data flow walked through the user journey, step by step** — what's created/read/updated at each step and
  who/what touches it. This is the section the user explicitly asked for; never compress or skip it in favor
  of a generic component diagram alone.
- Trust boundaries & control points — especially where a system-generated draft requires human confirmation
  before it becomes a decision (this project's human-in-the-loop rule, per CLAUDE.md, is the concrete example
  of the general pattern this section exists to capture)
- Conceptual data/domain model — entities and relationships, not a schema
- Non-functional concerns that shape the architecture — privacy/PHI handling, auditability, availability,
  offline/intranet-only constraints
- Assumptions & open questions, listed explicitly rather than buried in prose

## 6. When anything is unclear, offer real choices

Don't guess or silently pick an interpretation for anything that would change the architecture — which
component owns a shared responsibility, whether a step is synchronous or asynchronous, where a trust boundary
actually sits, whether an integration is in scope. Ask in Thai using the AskUserQuestion tool, with **at
least 3 concrete options and a short pros/cons note for each**, so the user is choosing between real
tradeoffs rather than answering an open-ended question or rubber-stamping a single suggestion.

## 7. Present the outline before writing full content

Write a short outline in Thai — the section list, and one line on what the user-journey data-flow section
will specifically walk through — and confirm it with the user before any content gets written. This is a
document stakeholders will treat as a reference; don't let the subagent produce a full draft before the user
has had a chance to redirect its shape.

## 8. Hand off to architecture-doc-writer

Once the outline is confirmed, call the Agent tool with `subagent_type: "architecture-doc-writer"`, passing:

- The confirmed outline
- All source material gathered in steps 1–2 (pointers to files it should read, plus any requirement/journey
  text that only exists in the conversation, pasted in full)
- The exact target file path from step 3
- An explicit reminder of the conceptual/stack-agnostic constraint from step 4 — don't assume it inherits
  this from context

## 9. Wrap up

After the subagent finishes:

- Read the result yourself before reporting back.
- Summarize, in Thai, what's new or changed, section by section.
- Surface every assumption or open question the subagent flagged so the user can resolve them now rather
  than downstream.
- If the document has diagrams, offer to publish it (or just the diagrams) as an Artifact for easier review.
