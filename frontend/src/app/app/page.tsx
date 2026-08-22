"use client";

import Link from "next/link";
import { useState } from "react";
import { ActivityFeed } from "@/components/aivva/ActivityFeed";
import { OwnerChat } from "@/components/aivva/OwnerChat";
import { Portrait } from "@/components/brand/Portrait";
import { LivingMap } from "@/components/world/LivingMap";
import { Button, buttonVariants } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { aivvas, type Interpretation } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

function hoursActive(iso: string | null) {
  if (!iso) return "not yet activated";
  const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return `${h}h ${m}m`;
}

export default function CommandPage() {
  const { current, activity, map, loading, error, refresh } = useAivvaLive();
  const [direction, setDirection] = useState("Find ethical ways to create income using creative skills.");
  const [interpretation, setInterpretation] = useState<Interpretation | null>(null);
  const [goalId, setGoalId] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  if (loading) {
    return <p className="text-sm text-muted-foreground">Loading your AIVVA…</p>;
  }

  if (!current) {
    return (
      <div className="rounded-3xl border border-dashed border-white/15 px-6 py-16 text-center">
        <h1 className="font-heading text-3xl">No AIVVA yet</h1>
        <p className="mt-2 text-muted-foreground">Create one to give the city a new life.</p>
        <Link href="/app/create" className={cn(buttonVariants(), "mt-6")}>
          Create AIVVA
        </Link>
      </div>
    );
  }

  const net = current.wallet.earned_today - current.wallet.spent_today;

  return (
    <div className="space-y-6">
      <section className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="rounded-3xl border border-white/10 bg-card/70 p-6">
          <div className="flex items-start gap-4">
            <Portrait name={current.name} seed={current.profile?.portrait_seed} size={72} />
            <div className="min-w-0">
              <p className="text-xs uppercase tracking-[0.22em] text-teal">Welcome back</p>
              <h1 className="font-heading text-4xl">{current.name}</h1>
              <p className="mt-1 text-sm text-muted-foreground">
                Active for {hoursActive(current.activated_at)}. City clock {current.world_clock}.
              </p>
            </div>
            <span className="ml-auto rounded-full bg-white/5 px-3 py-1 text-xs uppercase tracking-wider text-teal">
              {current.status_label}
            </span>
          </div>
          <dl className="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
              <dt className="text-xs uppercase tracking-wider text-muted-foreground">Location</dt>
              <dd className="mt-1">{current.location?.district?.name ?? current.location?.name ?? "Unknown"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wider text-muted-foreground">Goal</dt>
              <dd className="mt-1">{current.goal?.raw_direction ?? "No active direction"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wider text-muted-foreground">Activity</dt>
              <dd className="mt-1">{current.status_label}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wider text-muted-foreground">Today</dt>
              <dd className="mt-1">
                Earned {current.wallet.earned_today} · Spent {current.wallet.spent_today} · Net {net >= 0 ? "+" : ""}
                {net}
              </dd>
            </div>
          </dl>
          <div className="mt-6 grid grid-cols-3 gap-3 text-center">
            <div className="rounded-2xl bg-white/5 px-3 py-3">
              <p className="font-heading text-2xl text-teal">{current.wallet.available}</p>
              <p className="text-xs text-muted-foreground">Credits</p>
            </div>
            <div className="rounded-2xl bg-white/5 px-3 py-3">
              <p className="font-heading text-2xl text-amber">{current.life_points}</p>
              <p className="text-xs text-muted-foreground">Life Points</p>
            </div>
            <div className="rounded-2xl bg-white/5 px-3 py-3">
              <p className="font-heading text-2xl text-violet">{current.trust?.overall ?? 50}</p>
              <p className="text-xs text-muted-foreground">Trust</p>
            </div>
          </div>
          <div className="mt-6 flex flex-wrap gap-2">
            {current.status === "DORMANT" || current.status === "PAUSED" ? (
              <Button
                type="button"
                onClick={async () => {
                  setBusy("activate");
                  await aivvas.activate(current.id);
                  await refresh();
                  setBusy(null);
                }}
                disabled={busy === "activate"}
              >
                Activate
              </Button>
            ) : (
              <Button
                type="button"
                variant="outline"
                onClick={async () => {
                  setBusy("pause");
                  await aivvas.pause(current.id);
                  await refresh();
                  setBusy(null);
                }}
              >
                Pause
              </Button>
            )}
            <Button type="button" variant="outline" onClick={() => aivvas.recall(current.id).then(refresh)}>
              Recall home
            </Button>
            <Button type="button" variant="outline" onClick={() => aivvas.stopSpending(current.id).then(refresh)}>
              Stop spending
            </Button>
            <Button type="button" variant="outline" onClick={() => aivvas.cancelGoal(current.id).then(refresh)}>
              Cancel goal
            </Button>
          </div>
        </div>

        <div className="rounded-3xl border border-white/10 bg-card/70 p-6">
          <h2 className="font-heading text-2xl">Give AIVVA direction</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            The system interprets first. You confirm before it becomes an active goal.
          </p>
          <Textarea className="mt-4" value={direction} onChange={(e) => setDirection(e.target.value)} rows={4} />
          <div className="mt-3 flex gap-2">
            <Button
              type="button"
              onClick={async () => {
                setBusy("interpret");
                setLocalError(null);
                try {
                  const res = await aivvas.interpret(current.id, direction);
                  setInterpretation(res.interpretation);
                  setGoalId(res.goal_id);
                } catch (err) {
                  setLocalError(err instanceof Error ? err.message : "Could not interpret.");
                } finally {
                  setBusy(null);
                }
              }}
              disabled={busy === "interpret"}
            >
              Interpret
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={!goalId || !interpretation?.allowed}
              onClick={async () => {
                if (!goalId) return;
                setBusy("confirm");
                await aivvas.confirm(current.id, goalId);
                setInterpretation(null);
                setGoalId(null);
                await refresh();
                setBusy(null);
              }}
            >
              Confirm
            </Button>
            <Button type="button" variant="ghost" onClick={() => { setInterpretation(null); setGoalId(null); }}>
              Cancel
            </Button>
          </div>
          {(localError || error) && <p className="mt-3 text-sm text-destructive">{localError || error}</p>}
          {interpretation && (
            <div className="mt-4 rounded-2xl bg-white/5 p-4 text-sm">
              {!interpretation.allowed ? (
                <p className="text-destructive">{interpretation.reason}</p>
              ) : (
                <div className="space-y-2">
                  <p>
                    <span className="text-muted-foreground">Type · </span>
                    {interpretation.goal.goal_type}
                  </p>
                  <p>
                    <span className="text-muted-foreground">Constraints · </span>
                    {interpretation.goal.ethical_constraint.join(", ")}
                  </p>
                  <p>
                    <span className="text-muted-foreground">Risk · </span>
                    {interpretation.goal.risk_level}
                  </p>
                  <p>
                    <span className="text-muted-foreground">Estimated spend · </span>
                    {interpretation.estimated_cost} credits
                  </p>
                  <p>
                    <span className="text-muted-foreground">Permissions · </span>
                    {interpretation.permissions_needed.join(", ")}
                  </p>
                </div>
              )}
            </div>
          )}
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
        <LivingMap map={map} aivva={current} />
        <div className="rounded-3xl border border-white/10 bg-card/70 p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-heading text-2xl">Activity</h2>
            <span className="text-xs text-muted-foreground">Explainable events only</span>
          </div>
          <ActivityFeed
            items={activity}
            empty={`${current.name} is waiting for a confirmed direction.`}
          />
        </div>
      </section>

      <section className="rounded-3xl border border-white/10 bg-card/70 p-5">
        <h2 className="font-heading text-2xl">Talk with {current.name}</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Ask where they are. Chat cannot spend credits or replace a confirmed direction.
        </p>
        <div className="mt-4">
          <OwnerChat aivvaId={current.id} name={current.name} />
        </div>
      </section>
    </div>
  );
}
