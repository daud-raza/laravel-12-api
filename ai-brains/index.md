# ai-brains — Documentation Index

Central index of AI/engineering planning + reference docs for the Laravel 13 Task Manager API.

Each feature has two docs in `features/`: a consolidated feature doc (`<feature>.md`) and a standalone DB-schema doc (`<feature>-database-design.md`).

## Reference (built system)
- [features/PROJECT_BRAIN.md](features/PROJECT_BRAIN.md) — full system reference: architecture, DB schema, all 39 API endpoints, feature deep-dives, conventions, Laravel 13 slim structure & version comparison.
- [features/task-manager.md](features/task-manager.md) — Task Manager core module: summary, decisions, architecture, complete API, security, performance, edge cases.
- [features/task-manager-database-design.md](features/task-manager-database-design.md) — tables, fields, cascade rules, ER diagram.
- [architecture.md](architecture.md) — high-level architecture overview (existing modules + planned Chat).

## Chat module — PLAN ONLY (awaiting approval, no code yet)
- [features/chat.md](features/chat.md) — consolidated plan: overview, goals, decisions, architecture, API design, security, performance, edge cases, roadmap.
- [features/chat-database-design.md](features/chat-database-design.md) — tables, fields, indexes, FKs, ER diagram.

## Status legend
- **Reference / BUILT** = describes code that exists.
- **PLAN ONLY** = design not yet implemented; do not assume the code exists.
