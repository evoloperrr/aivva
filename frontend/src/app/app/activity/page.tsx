"use client";

import { ActivityFeed } from "@/components/aivva/ActivityFeed";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";

export default function ActivityPage() {
  return (
    <AivvaGate loadingLabel="Reading the city clock…">
      {({ current, activity }) => {
        if (!current) return null;
        return (
          <div className="space-y-5">
            <PageHeader
              kicker="Chronicle"
              title={`${current.name} activity`}
              description="Owner-visible events only. The feed never includes chain-of-thought, private prompts, or another AIVVA’s inner state."
            />
            <GlassPanel className="p-5">
              <ActivityFeed items={activity} empty={`${current.name} is waiting for a confirmed direction.`} />
            </GlassPanel>
          </div>
        );
      }}
    </AivvaGate>
  );
}
