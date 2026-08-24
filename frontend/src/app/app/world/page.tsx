"use client";

import { useMemo, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { LivingMap } from "@/components/world/LivingMap";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { aivvas } from "@/lib/api";
import type { AivvaLiveState } from "@/lib/useAivva";

type Pending = { x: number; y: number; lng: number; lat: number };

export default function WorldPage() {
  return (
    <AivvaGate loadingLabel="Drawing the city…" allowEmpty>
      {(live) => <WorldView live={live} />}
    </AivvaGate>
  );
}

function WorldView({ live }: { live: AivvaLiveState }) {
  const { current, list, map, refresh } = live;
  const [picking, setPicking] = useState(false);
  const [pending, setPending] = useState<Pending | null>(null);
  const [targetId, setTargetId] = useState("");
  const [name, setName] = useState("Meeting Spot");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);

  const targets = useMemo(() => {
    if (!current) return [];
    const seen = new Map<string, string>();
    for (const other of list) {
      if (other.id !== current.id) seen.set(other.id, other.name);
    }
    for (const other of map?.aivvas ?? []) {
      if (other.id !== current.id && !seen.has(other.id)) seen.set(other.id, other.name);
    }
    return Array.from(seen, ([id, label]) => ({ id, label }));
  }, [current, list, map]);

  return (
    <div className="space-y-5">
      <PageHeader
        kicker="World atlas"
        title="Genesis City"
        description="Live OpenStreetMap of Bonifacio Global City, Taguig. Genesis hearths, guilds, and bazaars sit on real streets — Serendra, High Street, Market! Market!, Burgos Circle. Your AIVVA is the party marker."
      />

      {current && (
        <GlassPanel className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs uppercase tracking-[0.16em] text-teal">Meetup</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {picking
                  ? "Click anywhere on the map to drop a pin."
                  : pending
                    ? "Pick who should meet you there, then confirm."
                    : "Send two AIVVAs to the same real spot on the map."}
              </p>
            </div>
            {!picking && !pending && (
              <Button
                type="button"
                variant="secondary"
                disabled={targets.length === 0}
                onClick={() => {
                  setError(null);
                  setDone(null);
                  setPicking(true);
                }}
              >
                📍 Set a meeting point
              </Button>
            )}
            {picking && (
              <Button type="button" variant="ghost" onClick={() => setPicking(false)}>
                Cancel
              </Button>
            )}
          </div>

          {pending && (
            <form
              className="mt-4 flex flex-wrap items-end gap-3"
              onSubmit={async (event) => {
                event.preventDefault();
                if (!targetId || !current) return;
                setSubmitting(true);
                setError(null);
                try {
                  await aivvas.meetup(current.id, { target_aivva_id: targetId, name, x: pending.x, y: pending.y });
                  setDone(`${current.name} and the other AIVVA are both headed to ${name}.`);
                  setPending(null);
                  setTargetId("");
                  await refresh();
                } catch (err) {
                  setError(err instanceof Error ? err.message : "Could not set the meeting.");
                } finally {
                  setSubmitting(false);
                }
              }}
            >
              <div className="space-y-1">
                <Label htmlFor="meetup-name">Name</Label>
                <Input id="meetup-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} className="w-48" />
              </div>
              <div className="space-y-1">
                <Label htmlFor="meetup-target">Meet with</Label>
                <select
                  id="meetup-target"
                  value={targetId}
                  onChange={(e) => setTargetId(e.target.value)}
                  required
                  className="h-9 w-48 rounded-md border border-white/10 bg-transparent px-3 text-sm"
                >
                  <option value="" disabled>
                    Choose an AIVVA
                  </option>
                  {targets.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.label}
                    </option>
                  ))}
                </select>
              </div>
              <Button type="submit" disabled={submitting || !targetId}>
                {submitting ? "Sending…" : "Confirm meeting"}
              </Button>
              <Button type="button" variant="ghost" onClick={() => setPending(null)}>
                Cancel
              </Button>
            </form>
          )}

          {error && <p className="mt-3 text-sm text-destructive">{error}</p>}
          {done && <p className="mt-3 text-sm text-teal">{done}</p>}
        </GlassPanel>
      )}

      <LivingMap
        map={map}
        aivva={current}
        pickMode={picking}
        onPick={(point) => {
          setPending(point);
          setPicking(false);
        }}
      />

      <div className="grid gap-3 md:grid-cols-3">
        {(map?.districts ?? []).map((district) => (
          <GlassPanel key={district.id} className="p-4">
            <p className="text-xs uppercase tracking-[0.16em]" style={{ color: district.color }}>
              {district.theme}
            </p>
            <h2 className="mt-1 font-heading text-xl">{district.name}</h2>
            <p className="mt-2 text-sm text-muted-foreground">{district.description}</p>
            <p className="mt-3 text-xs text-muted-foreground">
              {district.locations.length} location{district.locations.length === 1 ? "" : "s"}
            </p>
          </GlassPanel>
        ))}
      </div>
    </div>
  );
}
