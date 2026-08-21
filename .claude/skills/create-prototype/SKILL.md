---
name: create-prototype
description: Builds a clickable HTML/CSS/JS prototype for a feature, screen, or user flow in this project (Chira Continuity Care / Triple C), driven by a Requirement, Backlog, Feature List, User Journey, or any specific combination of these — the user doesn't need to supply all four, just whatever they have. Use this skill whenever the user wants to prototype, mock up, or visualize a feature or flow, even if they don't use the word "prototype" — phrases like "ลองทำหน้าตาให้ดูหน่อย", "อยากเห็นว่าหน้าตาจะเป็นแบบไหน", "ทำ mockup ให้หน่อย", "สร้างต้นแบบ", or a request to see a new feature/requirement/user journey visually before it gets built for real should all trigger this skill. Also use it when the user asks to adjust, redo, or add to a prototype that was made before.
---

# Create Prototype

This skill turns whatever the user has — a Requirement, a Backlog, a Feature List, a User Journey, or just a
rough description of one screen — into a clickable HTML prototype the user can actually click through in a
browser. It exists because this project's stakeholders (nurses, ward staff, department heads) are not
developers, and reviewing a written plan is a much weaker way for them to catch problems than clicking
through something that looks and behaves like the real thing.

You are the orchestrator here, not the builder. The actual HTML/CSS/JS gets written by the
`prototype-builder` subagent (`.claude/agents/prototype-builder.md`) — your job is to figure out exactly what
needs building, make sure the user has agreed to the plan, and hand off clear, scoped briefs. All questions
and summaries you produce for the user should be written in Thai — that's the language this project's users
work in — even though these instructions are in English.

## 1. Work out what's being prototyped

Read whatever the user gave you — this could be a full Requirement doc, a Backlog, a Feature List, a User
Journey, a pasted spec, or just a sentence describing one screen. Any subset is fine; don't insist on all
four artifact types. If the user gave you almost nothing to go on ("prototype the new thing we discussed"),
ask directly what they want prototyped before doing anything else — don't guess at scope for something this
visible.

If what they gave you is genuinely rich (a full journey with many steps, or a long feature list), don't try
to prototype everything in one shot by default — break it into the screens/steps that make sense and confirm
that breakdown with the user in the plan (step 4). Prototyping is meant to be reviewed in digestible pieces,
not dumped all at once.

## 2. Make sure a design system exists

Check for `DESIGN.md` at the project root.

- **If it exists:** read it now. Its color tokens, typography, spacing, and component patterns (status
  chips, the AI-draft box pattern, the nurse-confirmation box pattern, KPI tiles, timeline, sidebar nav) are
  what every prototype screen must be built from — this is what makes a prototype look like it belongs to
  the product instead of a generic template.
- **If it doesn't exist:** stop here and help the user create one before building anything. A prototype with
  no design system to draw from will just invent colors and styling on the spot, which produces
  inconsistent throwaway work instead of something reusable. Ask the user (in Thai):
  - What color tone/direction they want — offer a few concrete directions with pros/cons rather than a bare
    open question (see step 5 for how to phrase this kind of question)
  - Whether they have a logo or reference image to derive the palette/style from, and to share it if so
  - Once you have enough to go on, draft a `DESIGN.md` following the same four-section shape this project
    uses (Brand Identity & CI / Design Tokens / UI Components & Patterns / UX Guidelines & Rules), show it to
    the user, and get their sign-off before moving on to the prototype itself.

## 3. Work out the versioning

Prototypes live under `prototypes/` at the project root, one version folder per attempt:
`prototypes/v1-<short-slug>/`, `prototypes/v2-<short-slug>/`, etc. The slug should be a few words describing
what the prototype is (e.g. `v2-line-notification`), not just a number, so the folder list stays readable
on its own.

- **No `prototypes/` folder yet, or it's empty:** this is v1. No decision needed — just proceed.
- **Version folders already exist:** this means the user is either bringing a new requirement or asking to
  change something already prototyped. Always ask which they want, in Thai, and always give your
  recommendation with reasoning rather than a bare either/or:
  - Lean toward recommending a **new version folder** when the request reads like a different requirement, a
    substantially different flow, or something the user might want to compare side-by-side against the
    previous attempt.
  - Lean toward recommending **editing the latest folder in place** when the request reads like a small
    refinement, a fix, or a tweak to something that was just reviewed — spinning up a new folder for every
    minor change just makes the version history noisy and harder to navigate later.
  - State your recommendation and why, but let the user's answer decide — they may know context you don't
    (e.g. they specifically want to keep comparing against an older version even for a small change).

## 4. Present the plan before building anything

Write out, in Thai, a short plan covering:
- Which screens/steps you're about to build, and which input (requirement/backlog/feature/journey) each one
  comes from
- Which version folder this becomes (new or existing, per step 3)
- That it'll be built following `DESIGN.md`

Then wait for the user to confirm. Don't create or touch any prototype files before they've said yes — the
entire point of a lightweight clickable prototype is to catch misunderstandings before more work gets built
on top of them, and that only works if the user actually gets to react to the plan first.

## 5. When something is unclear, offer real choices

If anything about the request is ambiguous in a way that would change what you build — not just the
versioning question in step 3, but anything: which of two flows takes priority, whether a field is required
or optional, what happens on an edge case, what a vague requirement actually means in UI terms — don't ask an
open-ended question and don't quietly pick one interpretation. Ask a multiple-choice question in Thai with at
least 3 concrete options, each with a short pros/cons note, so the user is making an informed choice rather
than guessing what you're asking for. This mirrors how the rest of this project's planning has worked so
far — vague requirements get turned into concrete options with tradeoffs, not silent assumptions.

## 6. Hand off the build to prototype-builder

Once the plan is confirmed, for each distinct screen-set or journey in scope, call the Agent tool with
`subagent_type: "prototype-builder"`. Give each call:
- Exactly which screen(s)/flow/fields/states it needs to cover, with enough detail that the subagent isn't
  guessing at content (pull this from the requirement/backlog/feature/journey text, don't paraphrase it away)
- The exact output file path inside the version folder you resolved in step 3
- A reminder to read `DESIGN.md` itself (it doesn't inherit your context)

If there's more than one independent screen-set, launch them in parallel in the same turn — they don't
depend on each other, so there's no reason to make the user wait for them one at a time.

## 7. Wrap up

After the subagent(s) finish:
- Write or update `MANIFEST.md` in that version folder: the date, which requirement/backlog/feature/journey
  text drove this version, and — if this isn't v1 — one line on what changed from the previous version. This
  is what makes the version history actually useful later instead of a pile of anonymous folders.
- Tell the user (in Thai) what was built, where, and how to look at it — offer to publish the main HTML file
  as an Artifact for easy clicking-through, or give them the local file path to open directly.
