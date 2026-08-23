import { Portrait } from "@/components/brand/Portrait";
import { StatusPill } from "@/components/aivva/StatusPill";
import type { Aivva } from "@/lib/api";
import { hoursActive } from "@/lib/format";

export function CharacterCard({ aivva, compact = false }: { aivva: Aivva; compact?: boolean }) {
  const labels = [
    ...(aivva.profile?.skills ?? []).slice(0, 3),
    ...(aivva.profile?.interests ?? []).slice(0, 2),
  ].filter(Boolean);

  return (
    <div className="holo-frame relative overflow-hidden rounded-3xl p-6">
      <div className="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-teal/60 to-transparent" />
      <div className="flex items-start gap-5">
        <Portrait name={aivva.name} seed={aivva.profile?.portrait_seed} size={compact ? 72 : 96} glow />
        <div className="min-w-0 flex-1">
          <p className="text-[11px] uppercase tracking-[0.24em] text-teal">AIVVA identity</p>
          <div className="mt-1 flex flex-wrap items-center gap-3">
            <h2 className="font-heading text-4xl tracking-tight">{aivva.name}</h2>
            <StatusPill status={aivva.status} label={aivva.status_label} />
          </div>
          <p className="mt-2 text-sm text-muted-foreground">
            {aivva.profile?.bio || aivva.profile?.personality || "No public bio recorded."}
          </p>
          <p className="mt-2 text-xs text-muted-foreground">
            Active for {hoursActive(aivva.activated_at)} · City clock {aivva.world_clock}
          </p>
        </div>
      </div>
      {labels.length > 0 && (
        <div className="mt-5 flex flex-wrap gap-2">
          {labels.map((label) => (
            <span
              key={label}
              className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-muted-foreground"
            >
              {label}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
