# ai-brains — Documentation Index

Central index of AI/engineering planning + reference docs for the Laravel 13 Task Manager API.

## Reference (built system)
- [features/PROJECT_BRAIN.md](features/PROJECT_BRAIN.md) — full system reference: architecture, DB schema, all 39 API endpoints, feature deep-dives, conventions, Laravel 13 slim structure & version comparison.
- [architecture.md](architecture.md) — high-level architecture overview (existing modules + planned Chat).

## Chat module — PLAN ONLY (awaiting approval, no code yet)
- [features/chat.md](features/chat.md) — feature overview, goals, decisions, risks, future work.
- [chat-architecture.md](chat-architecture.md) — module boundaries, folder structure, ChatService, frontend plan (Blade hybrid), real-time strategy.
- [chat-database-design.md](chat-database-design.md) — tables, fields, indexes, FKs, ER diagram.
- [chat-api-design.md](chat-api-design.md) — every endpoint: URL, method, auth, validation, responses, errors.
- [chat-security.md](chat-security.md) — authz, ownership, rate limiting, XSS, CSRF, channel auth.
- [chat-performance.md](chat-performance.md) — cursor pagination, denormalization, indexes, caching, broadcast scope.
- [chat-edge-cases.md](chat-edge-cases.md) — 20 failure modes + handling, concurrency invariants.
- [chat-development-roadmap.md](chat-development-roadmap.md) — 9 independently-testable phases (P0–P8).

## Status legend
- **Reference** = describes code that exists.
- **PLAN ONLY** = design not yet implemented; do not assume the code exists.
