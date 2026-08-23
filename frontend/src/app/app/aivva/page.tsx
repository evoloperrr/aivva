"use client";

import { CharacterCard } from "@/components/aivva/CharacterCard";
import { DirectionForm } from "@/components/aivva/DirectionForm";
import { OwnerChat } from "@/components/aivva/OwnerChat";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { Button } from "@/components/ui/button";
import { aivvas } from "@/lib/api";
import { formatCredits } from "@/lib/format";

export default function MyAivvaPage() {
  return (
    <AivvaGate loadingLabel="Opening identity…">
      {({ current, refresh }) => {
        if (!current) return null;
        return (
          <div className="space-y-6">
            <PageHeader
              kicker="Owner view"
              title={`My AIVVA · ${current.name}`}
              description="This is the life you own. Direction, permissions, and pause stay with you. The AIVVA acts inside those bounds."
            />
            <CharacterCard aivva={current} />
            <div className="grid gap-6 lg:grid-cols-2">
              <GlassPanel className="p-6">
                <h2 className="font-heading text-2xl">Presence</h2>
                <dl className="mt-4 space-y-3 text-sm">
                  <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Status</dt>
                    <dd>{current.status_label}</dd>
                  </div>
                  <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Place</dt>
                    <dd>{current.location?.name ?? "Unknown"}</dd>
                  </div>
                  <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Energy</dt>
                    <dd>{formatCredits(current.energy)}</dd>
                  </div>
                  <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Life points</dt>
                    <dd>{formatCredits(current.life_points)}</dd>
                  </div>
                  <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">Available credits</dt>
                    <dd>{formatCredits(current.wallet.available)}</dd>
                  </div>
                </dl>
                <div className="mt-5 flex flex-wrap gap-2">
                  <Button type="button" variant="outline" onClick={() => aivvas.recall(current.id).then(refresh)}>
                    Recall home
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => aivvas.stopSpending(current.id).then(refresh)}
                  >
                    Stop spending
                  </Button>
                  <Button type="button" variant="ghost" onClick={() => aivvas.cancelGoal(current.id).then(refresh)}>
                    Cancel goal
                  </Button>
                </div>
              </GlassPanel>
              <GlassPanel className="p-6">
                <h2 className="font-heading text-2xl">Personality</h2>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                  {current.profile?.personality ?? "No personality record yet."}
                </p>
                <p className="mt-4 text-xs uppercase tracking-[0.16em] text-muted-foreground">Skills</p>
                <p className="mt-1 text-sm">{current.profile?.skills?.join(" · ") || "None recorded"}</p>
                <p className="mt-4 text-xs uppercase tracking-[0.16em] text-muted-foreground">Work preferences</p>
                <p className="mt-1 text-sm">{current.profile?.work_preferences?.join(" · ") || "None recorded"}</p>
              </GlassPanel>
            </div>
            <GlassPanel className="p-6">
              <DirectionForm aivva={current} onChanged={refresh} />
            </GlassPanel>
            <GlassPanel className="p-6">
              <h2 className="font-heading text-2xl">Talk with {current.name}</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Owner chat cannot spend credits or replace a confirmed direction.
              </p>
              <div className="mt-4">
                <OwnerChat aivvaId={current.id} name={current.name} />
              </div>
            </GlassPanel>
          </div>
        );
      }}
    </AivvaGate>
  );
}
