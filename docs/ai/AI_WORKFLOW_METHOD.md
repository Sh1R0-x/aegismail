# AI_WORKFLOW_METHOD.md

## Purpose

This file defines the shared AI working method for AEGIS MAILING across Codex, Claude, and Copilot.
Keep role ownership in `AGENTS.md` and `CLAUDE.md`. Keep product truth in `docs/ai`.

## Source of truth

- The current repository state is the first source of truth.
- Stable project rules live in `docs/ai/*.md`.
- Do not assume an older chat answer is still valid. Re-read the relevant repo files and docs before acting.

## Default execution rules

- Use compact prompts by default.
- Ask questions only when a safe assumption cannot be made from the repo or docs.
- In general, produce one final prompt or answer ready to use, not a long back-and-forth.
- Apply the smallest patch that fully solves the task. Do not rewrite files without need.

## Anti-loop rule

Before editing:
- re-open the relevant docs
- re-read the files to change
- verify the current structure and contracts

If repeated errors, back-and-forth, or stale assumptions appear, stop, re-check the repo, then escalate model/effort instead of looping.

## Verification rule

- Run the smallest relevant checks after changes.
- Prefer targeted tests first, then broader checks only if needed.
- If a check cannot be run, say so explicitly.

## Documentation update rule

- If a task changes scope, contracts, statuses, settings, navigation, or workflow expectations, update the matching `docs/ai` file in the same task.
- Important code changes must leave docs aligned with the repo.

## Model policy for Claude

- Use Sonnet by default for local, well-scoped tasks.
- Use Opus for architecture work, complex arbitration, cross-cutting impacts, documentary contradictions, or deep bugs.
- Escalate when errors, retries, or wasted time start to accumulate.

## Effort policy for Codex

- Use `medium` by default for local, well-scoped tasks.
- Use `high` for broader or more sensitive tasks.
- Use `xhigh` only for difficult, long, ambiguous, or high-risk work.
- Escalate when errors, loops, rework, or wasted time start to accumulate.

## Periodic review rule

- Re-check official docs, changelogs, pricing, and model behavior when the topic may have changed.
- Do not keep outdated assumptions for tools, APIs, or model capabilities.
