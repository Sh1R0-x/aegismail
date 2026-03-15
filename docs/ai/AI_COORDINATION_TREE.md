# AI_COORDINATION_TREE.md

## Recommended repository structure

```text
/
├─ AGENTS.md
├─ CLAUDE.md
├─ docs/
│  └─ ai/
│     ├─ AEGIS_MAILING_MASTER_REFERENCE.md
│     ├─ AI_WORKFLOW_METHOD.md
│     ├─ AI_COORDINATION_TREE.md
│     ├─ FRONTEND_SCOPE.md
│     ├─ BACKEND_SCOPE.md
│     ├─ DECISIONS_LOG.md
│     ├─ FRONTEND_CONTRACTS.md
│     ├─ BACKEND_CONTRACTS.md
│     ├─ AEGIS_MAILING_CLAUDE_FRONTEND.md
│     └─ AEGIS_MAILING_CODEX_BACKEND.md
├─ app/
├─ bootstrap/
├─ config/
├─ database/
├─ resources/
│  ├─ js/
│  │  ├─ Components/
│  │  ├─ Layouts/
│  │  ├─ Pages/
│  │  ├─ Types/
│  │  └─ Utils/
│  └─ views/
└─ ...
```

## Why this shape

- `CLAUDE.md` is the project-level instruction file for Claude
- `AGENTS.md` is the project-level instruction file for Codex
- `docs/ai` stores the stable project guidance and contracts both tools must read first
- `AI_WORKFLOW_METHOD.md` carries the shared AI method
- scope files keep frontend and backend responsibilities separated
- contracts files help Claude and Codex stay synchronized
- detailed frontend/backend specifications may exist as annexes, but do not override master, scope, or contracts

## Minimal docs to maintain

### Required
- `AEGIS_MAILING_MASTER_REFERENCE.md`
- `AI_WORKFLOW_METHOD.md`
- `FRONTEND_SCOPE.md`
- `BACKEND_SCOPE.md`

### Strongly recommended
- `AI_COORDINATION_TREE.md`
- `DECISIONS_LOG.md`
- `FRONTEND_CONTRACTS.md`
- `BACKEND_CONTRACTS.md`

### Optional detailed annexes
- `AEGIS_MAILING_CLAUDE_FRONTEND.md`
- `AEGIS_MAILING_CODEX_BACKEND.md`

## Coordination rule

Whenever a task changes:
- product scope
- working method expectations
- payload shape
- status enum
- settings structure
- navigation structure

the relevant `docs/ai/*.md` file must be updated in the same task.
