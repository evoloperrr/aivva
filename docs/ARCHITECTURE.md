# AIVVA architecture — Phase 0

## A. Current architecture summary

Empty repository at audit time (root commit only). Phase 0 introduced a modular monolith:

- `backend/` — Laravel 13 API, domain services, queue/scheduler hooks
- `frontend/` — Next.js 16 owner client
- PostgreSQL for state, Redis for cache/queue
- Sanctum personal access tokens for the SPA

The backend is the only authority for money, identity, decisions, inventory, trust, and world state.

## B. Existing reusable systems

None existed. The new reusable cores are:

- `App\Ai\*` — provider interface, heuristic + OpenAI, router, prompt guard
- `App\Domain\Ledger\LedgerService` — balanced double-entry
- `App\Domain\Agent\*` — planner, validator, executor, bounded runtime
- `App\Domain\Ethics\EthicsEngine` — platform rules before owner goals
- Genesis City seed — districts, locations, ATLAS, NOVA, open music request

## C. Missing foundation (intentionally later)

- Full business hiring / emergent companies
- Collective verification + AI jury productization
- Admin model-routing UI
- pgvector semantic memory
- httpOnly cookie auth
- Object storage for binary creations
- Mobile and Unreal clients

## D. Recommended implementation order

Phase 0 (this slice) → Phase 1 polish (chat with own AIVVA, richer profiles) → Phase 2 deeper planner/memory → keep world/economy already started in this slice → businesses → trust/verification → life points productization → cost optimization.

## E. Phase 0 implementation plan (done)

1. Install PHP/Postgres/Redis and official scaffolds
2. Schema for users, AIVVAs, world, agent, ledger, marketplace, trust
3. Domain services with tests
4. API + owner UI
5. Seed civilization and living demo loop

## F. Files that changed

All project files are new. Important roots:

- `backend/app/Domain/`
- `backend/app/Ai/`
- `backend/database/migrations/`
- `backend/routes/api.php`
- `frontend/src/app/`
- `frontend/src/components/`

## G. Database changes

New tables include worlds/locations, aivvas + profiles/permissions/budgets, goals/plans/actions/activity/memories/messages/relationships/travel, wallets + ledger accounts/transactions/entries, marketplace + escrow, trust, life points, AI usage, owner notifications.

## H. Tests added

- Ledger issuance/transfer conservation
- Negative balance rejection
- Escrow cannot settle twice
- Register + create AIVVA
- Ownership isolation
- Ethical income accepted
- Steal/scam rejected
- Prompt injection treated as data
- Paused AIVVA cannot act
- Level 1 cannot spend
- Over-limit transaction rejected
- Confirmed direction starts the loop
