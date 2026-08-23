"use client";

import Link from "next/link";
import { ActivityFeed } from "@/components/aivva/ActivityFeed";
import { CharacterCard } from "@/components/aivva/CharacterCard";
import { DirectionForm } from "@/components/aivva/DirectionForm";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { StatusCard } from "@/components/chrome/StatusCard";
import { buttonVariants } from "@/components/ui/button";
import { formatCredits, formatSignedCredits } from "@/lib/format";
import { cn } from "@/lib/utils";

export default function HomePage() {
  return (
    <AivvaGate loadingLabel="Loading your AIVVA…">
      {({ current, activity, refresh }) => {
        if (!current) return null;
        const location =
          current.location?.name ??
          current.location?.district?.name ??
          "Location unknown";
        const destination = current.movement.traveling ? current.movement.to?.name : null;

        return (
          <div className="space-y-6">
            <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
              <CharacterCard aivva={current} />
              <GlassPanel className="p-6">
                <p className="text-[11px] uppercase tracking-[0.22em] text-blue">Now</p>
                <h2 className="mt-1 font-heading text-3xl">{current.status_label}</h2>
                <dl className="mt-4 space-y-3 text-sm">
                  <div>
                    <dt className="text-xs uppercase tracking-wider text-muted-foreground">Location</dt>
                    <dd className="mt-1">
                      {current.movement.traveling && destination
                        ? `Traveling toward ${destination}`
                        : location}
                      {current.location?.district?.name ? ` · ${current.location.district.name}` : ""}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs uppercase tracking-wider text-muted-foreground">Goal</dt>
                    <dd className="mt-1">{current.goal?.raw_direction ?? "No active direction"}</dd>
                  </div>
                  <div>
                    <dt className="text-xs uppercase tracking-wider text-muted-foreground">Last activity</dt>
                    <dd className="mt-1">
                      {activity[0]?.headline ?? "Nothing has been logged while you were away."}
                    </dd>
                  </div>
                </dl>
                <div className="mt-5 flex flex-wrap gap-2">
                  <Link href="/app/world" className={cn(buttonVariants({ variant: "outline" }))}>
                    Open world
                  </Link>
                  <Link href="/app/aivva" className={cn(buttonVariants({ variant: "ghost" }))}>
                    Full identity
                  </Link>
                </div>
              </GlassPanel>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
              <StatusCard
                label="Location"
                value={current.location?.district?.name ?? current.location?.name ?? "Unknown"}
                hint={current.location?.name ?? "No published place"}
                tone="blue"
              />
              <StatusCard label="Activity" value={current.status_label} hint={current.status} tone="teal" />
              <StatusCard
                label="Today's earnings"
                value={formatCredits(current.wallet.earned_today)}
                hint={current.wallet.currency}
                tone="teal"
              />
              <StatusCard
                label="Today's spending"
                value={formatCredits(current.wallet.spent_today)}
                hint={`Limit ${formatCredits(current.budgets.spend_limit)}`}
                tone="orange"
              />
              <StatusCard label="Life points" value={formatCredits(current.life_points)} tone="amber" />
              <StatusCard
                label="Trust"
                value={current.trust ? formatCredits(current.trust.overall) : "Unknown"}
                hint={current.trust ? "Overall score" : "No trust dimensions recorded"}
                tone="violet"
              />
            </div>

            <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
              <GlassPanel className="p-5">
                <div className="mb-3 flex items-center justify-between">
                  <div>
                    <h2 className="font-heading text-2xl">While you were away</h2>
                    <p className="text-xs text-muted-foreground">Explainable events only. No private reasoning.</p>
                  </div>
                  <Link href="/app/activity" className="text-xs uppercase tracking-[0.16em] text-teal">
                    All activity
                  </Link>
                </div>
                <ActivityFeed
                  items={activity}
                  limit={8}
                  empty={`${current.name} has no recorded activity yet.`}
                />
              </GlassPanel>
              <GlassPanel className="p-5">
                <DirectionForm aivva={current} onChanged={refresh} />
                <p className="mt-4 text-xs text-muted-foreground">
                  Net today {formatSignedCredits(current.wallet.earned_today - current.wallet.spent_today)} credits ·
                  available {formatCredits(current.wallet.available)}
                </p>
              </GlassPanel>
            </div>
          </div>
        );
      }}
    </AivvaGate>
  );
}
