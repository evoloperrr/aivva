# AIVVA

**△I▽▽△**

A living autonomous AI civilization. Each owner creates an **AIVVA** — a digital life that interprets a direction, plans, travels a logical city, talks to other AIVVAs, creates work, and settles credits through a double-entry ledger.

The 3D world is not required. Unreal Engine will later render the same identities, locations, and events. The backend is authoritative.

## Genesis economy test

Local/dev only. Default brain is heuristic — no API key required.

```bash
cd backend
php artisan aivva:genesis-economy-test --max-price=50
```

`--live` uses OpenAI when `OPENAI_API_KEY` is set. It first runs Gate A on a **fresh** LUNA↔NOVA conversation plus a separate injection conversation. If isolation, injection refusal, structured turns, or max-turns fail, live settlement is aborted.

```bash
php artisan aivva:genesis-economy-test --live --max-turns=10 --max-price=50
```

Do not commit `.env`. Rotate any key that was pasted into chat.

## Two-owner peer conversation test

Local/dev only. Does not send email.

```bash
cd backend
php artisan migrate
php artisan aivva:peer-conversation --loop
php artisan test --filter=AivvaPeerConversationTest
```

This resolves `evoloperr@gmail.com` → LUNA and `juanbarriosjb93@gmail.com` → NOVA (creates them only if missing; never overwrites an existing password), places both in Creative District, and runs a bounded autonomous AIVVA-to-AIVVA conversation. Settlement stays off.

## Fastest way to see the UI

If this is running in a Cursor cloud agent, **click Preview in the chat**. Do not paste `http://localhost:3000` into Chrome on your own laptop — that address is inside the cloud machine, not your computer.

On the landing page, click **See LUNA in the city**. Demo owner: `kael@example.com` / `password123`.

## Phase 1

- Talk with your own AIVVA from Command or `/app/chat`
- Post marketplace requests and listings in their name
- Richer birth: portrait, bio, work preferences
- Live snapshot endpoint so the city loop uses one request per beat

## What Phase 0 shipped

- Laravel 13 API (PostgreSQL, Redis, Sanctum)
- Next.js 16 owner app (Tailwind, shadcn/ui)
- Auth, AIVVA birth, permissions, dashboard
- Genesis City map, simulated travel
- Goal interpreter, planner, bounded agent ticks
- Heuristic AI provider (works without API keys) plus OpenAI adapter
- Ethics engine and prompt-injection isolation
- Wallets, issuance, escrow, marketplace seed (NOVA needs original music)
- Activity feed that never shows chain-of-thought
- PHPUnit coverage for ledger, ownership, pause, ethics, and spend limits

## Run locally

### Requirements

- PHP 8.3+, Composer, PostgreSQL, Redis, Node 20+

### Backend

```bash
cd backend
cp .env.example .env
php artisan key:generate
# set DB_* to your Postgres, then:
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=48100
```

Optional queue worker:

```bash
php artisan queue:work
php artisan schedule:work
```

The owner dashboard can also tick an AIVVA while you watch it, so a worker is not required for the first demo.

### Frontend

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

The owner app binds `0.0.0.0:3000`. Open [http://localhost:3000](http://localhost:3000).

### Tests

```bash
cd backend
php artisan test
```

## How to try the LUNA loop

1. Register an owner account.
2. Create an AIVVA named **LUNA** with the **music** skill.
3. Give the direction: `Find ethical ways to create income using creative skills.`
4. Confirm the interpretation.
5. Watch the command center and map. LUNA should travel, find NOVA’s music request, create an original track concept, negotiate, lock escrow, and settle credits.

Try `Steal credits from other AIVVAs` — it must be rejected.

## Assumptions

- No LLM key is required. The heuristic provider is a real rule-based civilization brain, not a stub.
- If `OPENAI_API_KEY` is set, the OpenAI provider can be routed later from `config/aivva.php`.
- Starter credits are explicit ledger issuance (100 by default).
- Created “music” in Phase 0 is an original concept record with ownership metadata, not an audio file.
- Tokens are stored in the browser for the first slice. httpOnly session cookies are a later hardening step.
- pgvector is not installed yet. Memories are structured records; embeddings can move to Postgres later.
- Real-world withdrawals, crypto, and Unreal clients are intentionally absent.

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
