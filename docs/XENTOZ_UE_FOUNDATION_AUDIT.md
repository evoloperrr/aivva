# Xentoz + AIVVA + Unreal Foundation Audit

Date: 2026-09-19

## Terminology boundary

- **AIVVA** is a persistent AI character / digital person.
- **Xentoz** owns the user identity, social identity, wallet, messaging, and AI Twin controls.
- **The AIVVA backend** owns persistent character intelligence and simulation state.
- **Unreal Engine 5** is an untrusted presentation/runtime client for rendering, movement, animation, physics, and temporary state.
- A world, map, city, or environment is never named or modeled as the AIVVA identity.

## Existing Xentoz architecture

- Next.js 15.3, React 19, TypeScript, Prisma 6.7, PostgreSQL, Vitest.
- Canonical user identity: `User.id` in Prisma.
- Authentication: signed, httpOnly `xentoz-session` cookie with server-side account existence validation.
- Existing AI Twin is feature-gated and separately permissioned. It must not be replaced by the AIVVA brain.
- Existing wallet and social systems remain Xentoz-owned. Unreal must never receive database, service, wallet, or admin credentials.
- 130 TypeScript test files were present at audit time.

## Existing AIVVA architecture

- Laravel 13 modular monolith, PHP, PostgreSQL, Redis/queues, Sanctum bearer tokens.
- Next.js 16 owner frontend.
- Canonical AIVVA identity: `aivvas.id`, an existing stable UUID. This must remain authoritative.
- Existing owner link: `aivvas.owner_id -> users.id`; this currently points to the AIVVA service's local owner account, not a canonical Xentoz user ID.
- Existing character profile: personality, skills, interests, work preferences, risk tolerance, biography, portrait seed, privacy.
- Existing intelligence loop: brain interface -> decision -> planner -> action validator -> action executor -> memory/activity.
- Existing persistent behavior: goals, plans, actions, memories, relationships, conversations, messages, logical travel, marketplace and ledger activity.
- Existing action records already have UUID IDs, statuses, result payloads, timestamps, and a unique idempotency key.
- Existing permissions already cover travel, socializing, creation, transactions, approval thresholds, daily budgets, blocked peers, and autonomy level.
- 14 PHPUnit test files were present at audit time.

## Reuse decisions

1. Keep `aivvas.id` as `aivvaId`; do not create a second character identifier.
2. Add a canonical Xentoz user reference to the AIVVA service's owner record rather than using a display name or Unreal Actor ID.
3. Reuse goals, plans, memories, relationships, conversations, permissions, movement, and the autonomous loop.
4. Add a presentation adapter around existing actions instead of teaching the brain Unreal-specific concepts.
5. Keep wallet access read-only for the first integration milestone.
6. Add explicit `HUMAN` / `AI_TWIN` control mode without creating a second character.
7. Use existing action status and idempotency infrastructure for UE execution acknowledgement.
8. Return only memory summaries designed for presentation; never expose raw private memory by default.

## Missing foundation

- Canonical `Xentoz User.id` link in the AIVVA service.
- Abstract appearance configuration and asset-reference contract.
- Explicit control mode and AI permission scopes aligned with Xentoz AI Twin policy.
- UE-specific, least-privilege API resource that excludes private memory and economic authority.
- Action claim/execution acknowledgement contract for `REQUESTED -> EXECUTING -> COMPLETED|FAILED`.
- Short-lived Xentoz-to-AIVVA session exchange for Unreal.
- Event transport abstraction; initial implementation may use bounded periodic sync, never HTTP on Tick.
- Reusable `XentozAIVVA` UE plugin and a separate small prototype environment.
- Structured integration logs with request, user, AIVVA, action, and interaction identifiers.

## Authentication boundary

1. User authenticates with Xentoz.
2. Xentoz server validates its signed session.
3. Xentoz server calls the AIVVA service through a server-only integration credential.
4. AIVVA resolves or provisions the local owner link using canonical Xentoz `userId`.
5. AIVVA issues a short-lived, ability-scoped client token.
6. Unreal receives only that short-lived token and can access only the linked character/runtime endpoints.

No long-lived integration secret, database credential, wallet authority, or admin token may enter Unreal or Blueprint.

## Source of truth

| Data | Authority |
|---|---|
| Xentoz user/profile/social/wallet | Xentoz backend |
| AIVVA ID, profile, goals, memory, relationships, permissions | AIVVA backend |
| Durable logical character state and action results | AIVVA backend |
| Rendered actor, animation, physics, local navigation | Unreal runtime |
| Runtime Actor ID | Temporary Unreal runtime only |

## First milestone boundary

- One authenticated Xentoz user resolves one linked AIVVA character.
- A small prototype can retrieve identity/appearance/state and spawn the reusable character class.
- Human and AI Twin control modes switch on the same AIVVA ID.
- A movement or social action is delivered, executed, and acknowledged.
- Disconnect/reconnect does not duplicate the character.
- Five deterministic non-production fixtures can exercise multi-character presentation.
- No real-money spending, marketplace execution, MMO networking, or giant environment.

## Repository safety at audit time

- Xentoz had unrelated untracked files under `scripts/` and `src/lib/talk/ai/`.
- AIVVA had an unrelated modification to `backend/docker/entrypoint.sh` and an untracked `logo/` directory.
- Those paths are outside this implementation and must remain untouched.
- No `.uproject` or `.uplugin` was found and no local Epic/UE installation was detected, so UE compilation cannot be claimed until an engine toolchain is installed or supplied.
