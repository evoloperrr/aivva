"use client";

import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { StatusCard } from "@/components/chrome/StatusCard";

const KNOWN = ["economic", "social", "overall"] as const;

export default function TrustPage() {
  return (
    <AivvaGate loadingLabel="Measuring reputation…">
      {({ current }) => {
        if (!current) return null;
        const trust = current.trust;
        const skills = trust?.skills ?? null;

        return (
          <div className="space-y-6">
            <PageHeader
              kicker="Reputation"
              title={`${current.name} trust`}
              description="Scores are shown only when the backend has them. Unknown dimensions stay unlabeled rather than invented."
            />
            {!trust ? (
              <GlassPanel className="p-6">
                <p className="text-sm text-muted-foreground">
                  No trust record exists for {current.name} yet. Economic, social, and skill dimensions will appear here
                  when the city writes them.
                </p>
              </GlassPanel>
            ) : (
              <>
                <div className="grid gap-3 sm:grid-cols-3">
                  <StatusCard label="Overall" value={trust.overall} tone="violet" />
                  <StatusCard label="Economic" value={trust.economic} tone="teal" />
                  <StatusCard label="Social" value={trust.social} tone="magenta" />
                </div>
                <GlassPanel className="p-5">
                  <h2 className="font-heading text-2xl">Skill dimensions</h2>
                  {!skills || Object.keys(skills).length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                      Skill trust is unknown. The API did not return per-skill scores.
                    </p>
                  ) : (
                    <ul className="mt-4 space-y-3">
                      {Object.entries(skills).map(([name, value]) => (
                        <li key={name}>
                          <div className="flex items-center justify-between text-sm">
                            <span className="capitalize">{name}</span>
                            <span className="font-mono text-violet">{value}</span>
                          </div>
                          <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div className="h-full bg-violet" style={{ width: `${Math.max(0, Math.min(100, value))}%` }} />
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </GlassPanel>
                <p className="text-xs text-muted-foreground">
                  Known dimensions: {KNOWN.join(", ")}. Any other civic scores are not provided by this API.
                </p>
              </>
            )}
          </div>
        );
      }}
    </AivvaGate>
  );
}
