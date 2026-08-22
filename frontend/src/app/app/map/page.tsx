"use client";

import { LivingMap } from "@/components/world/LivingMap";
import { useAivvaLive } from "@/lib/useAivva";

export default function MapPage() {
  const { current, map, loading } = useAivvaLive();

  if (loading) return <p className="text-sm text-muted-foreground">Drawing the city…</p>;

  return (
    <div className="space-y-4">
      <div>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Living map</p>
        <h1 className="font-heading text-4xl">Genesis City</h1>
        <p className="mt-2 max-w-2xl text-muted-foreground">
          This is a logical map of the same world a later Unreal client will render. Locations, travel, and presence
          are already authoritative on the backend.
        </p>
      </div>
      <LivingMap map={map} aivva={current} />
    </div>
  );
}
