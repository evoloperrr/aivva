"use client";

import { useEffect, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { EmptyState } from "@/components/chrome/PageStates";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { StatusPill } from "@/components/aivva/StatusPill";
import { aivvas, type RelationRecord } from "@/lib/api";

export default function RelationshipsPage() {
  return (
    <AivvaGate loadingLabel="Reading social graph…">
      {({ current }) => {
        if (!current) return null;
        return <RelationshipsBody aivvaId={current.id} aivvaName={current.name} />;
      }}
    </AivvaGate>
  );
}

function RelationshipsBody({ aivvaId, aivvaName }: { aivvaId: string; aivvaName: string }) {
  const [relations, setRelations] = useState<RelationRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    aivvas
      .relationships(aivvaId)
      .then((res) => {
        setRelations(res.data);
        setError(null);
      })
      .catch((err: Error) => setError(err.message))
      .finally(() => setLoading(false));
  }, [aivvaId]);

  return (
    <div className="space-y-5">
      <PageHeader
        kicker="Directed ties"
        title={`${aivvaName} relationships`}
        description="These ties are not assumed to be symmetric. A relationship from this AIVVA to another does not imply the reverse."
      />
      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">Loading relationships…</p>}
      {!loading && relations.length === 0 && (
        <EmptyState
          title="No relationships formed yet"
          body={`${aivvaName} has no stored social ties. When one exists it will show type, strength, and trust from this AIVVA’s side only.`}
        />
      )}
      <div className="grid gap-4 md:grid-cols-2">
        {relations.map((rel) => (
          <GlassPanel key={rel.id} holographic className="p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-[11px] uppercase tracking-[0.18em] text-magenta">{rel.type}</p>
                <h2 className="mt-1 font-heading text-3xl">{rel.other?.name ?? "Unknown AIVVA"}</h2>
              </div>
              {rel.other?.status && <StatusPill status={rel.other.status} />}
            </div>
            <div className="mt-5 grid grid-cols-2 gap-3">
              <Meter label="Strength" value={rel.strength} tone="magenta" />
              <Meter label="Trust from here" value={rel.trust} tone="violet" />
            </div>
            <p className="mt-4 text-xs text-muted-foreground">
              One-way record. Additional relationship types can be added later without assuming reciprocity.
            </p>
          </GlassPanel>
        ))}
      </div>
    </div>
  );
}

function Meter({ label, value, tone }: { label: string; value: number; tone: "magenta" | "violet" }) {
  const width = Math.max(0, Math.min(100, value));
  return (
    <div>
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>{label}</span>
        <span>{value}</span>
      </div>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
        <div
          className={tone === "magenta" ? "h-full bg-magenta" : "h-full bg-violet"}
          style={{ width: `${width}%` }}
        />
      </div>
    </div>
  );
}
