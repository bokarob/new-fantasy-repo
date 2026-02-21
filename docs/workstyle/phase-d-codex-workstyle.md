# Working efficiently on Phase D coding tasks (VS Code + Codex + spec context)

This workflow is designed to keep implementation aligned with the Phase A–C spec docs you already created, while making Codex (in VS Code) *actually usable* without losing context.

---

## 1) Put the spec where Codex can read it

In your repo, keep a stable structure like:

- `docs/spec/`  
  - `phase-d-implementation-plan.md` (the “start here” map)
  - `auth-model.md`, `api-schemas-updated.md`, `api-errors-updated.md`, `caching-updated.md`, etc.
- `docs/tasks/`  
  - one file per coding task (short, targeted)

Codex works best when the relevant spec files are *in the repository* and opened in the editor panel (or referenced by path).

OpenAI’s Codex VS Code extension is designed to use context from open files/selections, and can run commands in “Agent mode”. (See Codex IDE docs / quickstart.) 

---

## 2) One task = one “task brief” markdown

Before you ask Codex to code, create a task brief:

`docs/tasks/TASK-001-auth-refresh.md`

Include only:
- Goal
- Non-goals
- Spec anchors (file + section)
- Acceptance criteria
- Test checklist
- “Don’t change contracts” reminder

This keeps prompts short and prevents drift.

(You can use the task packet in `phase-d-task-auth-refresh.md` as your TASK-001.)

---

## 3) Prompting pattern that consistently works

In VS Code, open:
- the task brief
- the relevant spec docs
- the target code files (routes/controllers/db/email)

Then prompt Codex like this:

**Prompt template:**
- “Implement TASK-001 exactly as described in `docs/tasks/TASK-001-auth-refresh.md`.”
- “Use the response shapes from `docs/spec/api-schemas-updated.md` (Auth section).”
- “Use error codes from `docs/spec/api-errors-updated.md`.”
- “Do not introduce new endpoints or fields unless you update the spec first.”
- “Add smoke tests described in the task brief.”

Because Codex pulls context from open files and selections, you can keep prompts shorter and still accurate. 

---

## 4) Make Codex safe by constraining its blast radius

**Recommended guardrails:**
- Work in a feature branch per task
- Require Codex to produce changes as a single cohesive diff
- Require it to run:
  - unit tests (if present)
  - a lint/format command
  - a small curl/Postman script to verify the endpoint
- Review every file it touched (especially DB + auth)

If you use Codex CLI, run it from the repo root and limit its scope to the relevant directory for the task.

---

## 5) “Two-pass” approach: plan, then code

For each task:
1) Ask Codex for a short implementation plan (files to touch + functions + queries)
2) Only then ask it to implement

This reduces random refactors and keeps changes minimal.

---

## 6) Keep a running “decision log” (lightweight)

If you decide something during coding (e.g., “forgot password never reveals if email exists”),
write a 2–3 line note in:

- `docs/spec/decisions.md`

This prevents re-deciding and keeps Codex aligned later.

---

## 7) A practical daily rhythm

- Pick 1–2 tasks/day max
- For each task:
  - write task brief (10–15 mins)
  - run Codex implementation (30–90 mins)
  - review diff + adjust (human)
  - run smoke tests
  - merge

---

## 8) Reference: Codex in VS Code / CLI

Codex docs (IDE + CLI) describe:
- using the VS Code extension panel
- “Agent mode” (read/edit/run in your project)
- delegating tasks and reviewing changes

Use the official docs as the source of truth for setup and capabilities.

