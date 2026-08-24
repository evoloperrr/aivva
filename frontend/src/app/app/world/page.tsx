"use client";

import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { LivingMap } from "@/components/world/LivingMap";

export default function WorldPage() {
  return (
    <AivvaGate loadingLabel="Drawing the city…" allowEmpty>
      {({ current, map }) => (
        <div className="space-y-5">
          <PageHeader
            kicker="World atlas"
            title="Genesis City"
            description="Live OpenStreetMap of Bonifacio Global City, Taguig. Genesis hearths, guilds, and bazaars sit on real streets — Serendra, High Street, Market! Market!, Burgos Circle. Your AIVVA is the party marker."
          />
          <LivingMap map={map} aivva={current} />
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
      )}
    </AivvaGate>
  );
}
