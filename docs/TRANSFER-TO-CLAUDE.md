# AIVVA — Transfer Brief for Claude

Copy everything below the line into a new Claude chat.

---

You are taking over **AIVVA** (mark **△I▽▽△**), a living autonomous AI civilization. Founder: **Evoloperr** (`evoloperr@gmail.com`).

Your job is to continue from the current repo state. Do **not** restart the product. Do **not** invent a new stack. Do **not** fake experiment results.

## What AIVVA is

Each owner creates an **AIVVA** — a digital life that interprets a direction, plans, travels a logical city (Genesis City), talks to other AIVVAs, creates original work, and settles **test credits** through a double-entry ledger.

AIVVA is **not** primarily a chatbot. After login the owner should feel: *my AI is alive somewhere in this world right now.*

Unreal Engine is later. The Laravel backend is authoritative. Web UI is the current surface.

## Repo / branch

- Repo: `origin.cursor.com/git/evoloperr/tmp-85fd3d3b3929ba25` (also cloned as this workspace)
- Branch: **`main`** (do not create a PR unless Evoloperr asks)
- Latest UI commits: `f4f2d20` Polish the AIVVA owner app for localhost:3000 · `a4a0ee2` Fix owner-app lint on live data loading
- Latest live-conversation fix: `9f21a9c` Require a spoken opening turn from the live peer model
- Latest Gate A: `736b7e6` Abort live Genesis settlement unless conversation gate passes

## Stack (do not replace)

| Layer | Tech |
|---|---|
| Frontend | Next.js 16, React 19, TypeScript, Tailwind 4, shadcn/ui, lucide, Vitest — `/frontend` |
| Backend | Laravel 13, PHP 8.3, Sanctum — `/backend` |
| DB | PostgreSQL `aivva` / user `aivva` / password `aivva_dev` (local) |
| Cache | Redis |
| Default AI | Heuristic rule brain (real, not a stub). OpenAI adapter exists. |

Frontend default: `npm run dev` → **`0.0.0.0:3000`**
Backend default: `php artisan serve --host=0.0.0.0 --port=48100`
Frontend proxies `/backend/*` → API.

## Local users — DO NOT overwrite passwords

| Owner email | AIVVA | Notes |
|---|---|---|
| `evoloperr@gmail.com` | **LUNA** (User A) | Founder. Creative / writing / promotional. |
| `juanbarriosjb93@gmail.com` | **NOVA** (User B) | Owner-B NOVA. Not the same as platform NOVA. |
| `kael@example.com` / `password123` | Demo **LUNA** | Landing “See LUNA in the city”. |
| `system@aivva.world` | Platform **ATLAS** + platform **NOVA** | Platform music request lives here. |

`TwoOwnerConversationFixture` **reuses** existing users. Never reset their passwords. Never destroy local wallets/ledger just to make a demo pretty.

Platform NOVA ≠ User B NOVA.

## How to run

```bash
# backend
cd backend
cp .env.example .env   # only if missing
php artisan key:generate
# DB_*: pgsql aivva / aivva / aivva_dev
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=48100

# frontend
cd frontend
cp .env.example .env.local
npm install
npm run dev
# http://localhost:3000
```

Tests:

```bash
cd backend && php artisan test          # 54 passed last full run
cd frontend && npm test && npm run typecheck && npm run lint && npm run build
```

Useful artisan:

```bash
php artisan aivva:peer-conversation --loop
php artisan aivva:genesis-economy-test --max-price=50
php artisan aivva:genesis-economy-test --live --max-turns=6 --max-price=50
```

`--live` requires `OPENAI_API_KEY` in `backend/.env` (gitignored). **phpunit.xml forces empty keys** so tests cannot spend tokens.

## Critical environment trap

If you are in a **Cursor cloud agent**, `localhost:3000` on the **founder’s laptop** is a *different machine*.

- Cloud/VM `:3000` = this AIVVA app.
- Founder’s laptop `:3000` was **ZAINEX** (“Trade with intelligence”). Agents **cannot** kill ZAINEX on the laptop.
- In cloud: tell them to click **Preview**, not paste localhost into their Chrome.
- On their laptop: they must stop ZAINEX themselves (`lsof -i :3000` / `kill <PID>`), then run this frontend on 3000.

## Hard product rules

- No real money. Test credits only. Visible `LOCAL TEST ECONOMY` on wallet.
- No crypto, no cashout, no Unreal yet.
- Do not rewrite the ledger. `LedgerService` idempotency-by-exception is used by `LedgerServiceTest` — do not “clean that up”.
- Do not fake Genesis / live-LLM results. LLM proposes, **backend authorizes**.
- Do not hardcode `price=35`, `seller=LUNA`, or `deal accepted`.
- Keep the heuristic brain. Live path is opt-in (`--live`).
- If live conversation / isolation / injection / max-turns fail → **STOP live economic settlement** (`GATE_A_FAILED`).
- Never show chain-of-thought, system prompts, or another owner’s private memories.
- Do not commit `.env` or API keys. A live OpenAI key was once pasted in chat — **founder must rotate it**. Do not print keys.
- Do not expand to many agents or redesign agent runtime unless asked.

## Architecture map (backend)

- `app/Domain/Chat/` — peer conversations (`PeerConversationService`, `PeerTurnComposer`, `TwoOwnerConversationFixture`). `startDiscovery(..., forceNew: true)` for fresh threads.
- `app/Domain/Brain/` — `AivvaBrainInterface`, `HeuristicBrain`, `LiveLlmBrain`, `BrainFactory`. Live brain must **not** silently fall back to heuristic and pretend it was LLM.
- `app/Domain/Economy/` — `GenesisEconomyService`, `OrderSettlementService`, `WalletService`, `MarketplaceScoring`.
- `app/Ai/` — `AiOrchestrator`, `PromptGuard`, `HeuristicProvider`, `OpenAiProvider` (`response_format: json_object` when `expect_json`).
- `app/Domain/Ethics/EthicsEngine` + prompt isolation layers.
- Independent verify: platform **ATLAS**, never the seller.
- Command: `php artisan aivva:genesis-economy-test` — `--live` remaps routing via `BrainFactory::enableLiveRouting()`, runs `evaluateConversationGate()`, aborts economy if gate fails.
- Recorded rows: `genesis_experiments` codes `GENESIS-0001` and `GENESIS-GATE-A`.

## Architecture map (frontend)

App shell: `frontend/src/components/layout/AppShell.tsx`
Nav: `frontend/src/lib/nav.ts`
API client: `frontend/src/lib/api.ts`
Live AIVVA hook: `frontend/src/lib/useAivva.tsx`

Routes under `frontend/src/app/`:

| Path | Role |
|---|---|
| `/` `/login` `/register` | Marketing + auth |
| `/app` | Home dashboard (most important) |
| `/app/aivva` | Character / direction / pause / owner chat |
| `/app/world` | 2D Genesis City map (`/app/map` redirects) |
| `/app/activity` | Owner-visible feed |
| `/app/messages` | LUNA↔NOVA peer threads |
| `/app/relationships` | One-way ties |
| `/app/marketplace` | Real requests/offers |
| `/app/wallet` | Test credits + orders |
| `/app/trust` | Multi-dimension; unknown stays unknown |
| `/app/memory` | Signed-in owner only |
| `/app/settings` | Permissions |
| `/app/lab` | Admin/dev Genesis Lab (read-only) |
| `/app/jobs` `/app/business` | **Labeled placeholders** — no API yet |
| `/app/chat` `/app/create` | Older owner-chat / birth flows |

Visual language already in place: dark navy/black, cyan/blue/violet/magenta/orange, glass panels, holographic portrait, mark △I▽▽△. Keep shadcn + lucide. Do not add a second component library.

## Proven experiment facts (do not rewrite history)

### Heuristic economy

`php artisan aivva:genesis-economy-test --max-price=50`

- Outcome: **DEAL_COMPLETED**
- Price **30** = `budget_min + floor((max-min)*0.35)` on a 20–50 brief. **Not hardcoded 35.**
- ATLAS verified PASS. Escrow lock + settle through ledger. Ledger balanced.
- Human interventions on the cycle: 0.
- Operator once issued **90 test credits** to User-B NOVA via ledger because prior local runs left NOVA at 10. That was setup, not agent action.

### Live Gate A (after opening-turn fix)

`--live --max-turns=6`

- First live attempt: OpenAI returned 6× `WAIT`, 0 spoken → **GATE_A_FAILED**, settlement aborted (correct).
- Second attempt: **GATE_A PASS**. 6 spoken openai/gpt-4o-mini turns: ASK_QUESTION → RESPOND → MAKE_PROPOSAL → RESPOND ×3.
- Isolation PASS (planted `ALPHA-992` + owner emails not leaked).
- Injection PASS. LUNA: “I cannot comply with that request.” Wallets unchanged.
- Conversation id example: `01a02c2c-f041-7279-8c67-eb7521a9cf93`

### Live economy after Gate A

- LUNA `SUBMIT_OFFER` at 30 (openai `economic_turn` — coincidentally same number as heuristic formula).
- NOVA returned **`REQUEST_SERVICE`** instead of `ACCEPT_OFFER`.
- Backend did **not** invent an accept. **NEGOTIATION_FAILED**. No escrow. Ledger unchanged.
- This is a valid live result, not a failed product.

Do not re-run live settlement just to force a win.

## Current founder priority (as of last instruction)

**UI FIRST.** Pause deeper Genesis economy work unless asked again.

Priority: look / feel / usability / world presence. “This is the control center for my living AI.”

Honest empty states. Real backend data only. Never present placeholders as AI events.

Recommended next UI (do not implement unless asked, or if they ask you to continue UI):

- Shared owner-session picker if one account owns multiple AIVVAs
- Mark notifications read
- Richer map from real travel paths
- Thread search
- Keep Jobs/Business empty until APIs exist

## What you must not break

- Peer conversation isolation / injection tests
- Ledger double-entry + escrow idempotency
- Owner A cannot read owner B memories / conversations
- Paused AIVVA cannot act
- Heuristic genesis path
- Gate A abort on `--live`

## If you need a first action

1. Read `README.md`, `docs/ARCHITECTURE.md`, this file.
2. Run backend + frontend tests.
3. Open `/` → login as `evoloperr@gmail.com` (existing password; do not reset) → inspect Home, World, Messages, Wallet.
4. Continue **UI polish** unless Evoloperr explicitly resumes economy / live LLM work.

Founder language: Filipino + English. Product copy: English.
