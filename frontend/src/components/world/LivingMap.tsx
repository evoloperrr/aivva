"use client";

import { useMemo, useState } from "react";
import type { Aivva, WorldMap } from "@/lib/api";

export function LivingMap({
  map,
  aivva,
}: {
  map: WorldMap | null;
  aivva: Aivva | null;
}) {
  const [selected, setSelected] = useState<string | null>(null);
  const selectedPlace = useMemo(() => {
    if (!map || !selected) return null;
    return map.districts.flatMap((d) => d.locations).find((l) => String(l.id) === selected) ?? null;
  }, [map, selected]);

  if (!map) {
    return (
      <div className="map-grid flex h-[420px] items-center justify-center rounded-2xl border border-white/10 bg-card/60 text-sm text-muted-foreground">
        Loading Genesis City…
      </div>
    );
  }

  const marker = aivva?.movement.traveling
    ? { x: aivva.movement.x ?? aivva.location?.x ?? 120, y: aivva.movement.y ?? aivva.location?.y ?? 480 }
    : { x: aivva?.location?.x ?? 120, y: aivva?.location?.y ?? 480 };

  const dest = aivva?.movement.to;

  return (
    <div className="overflow-hidden rounded-2xl border border-white/10 bg-[#0e1428]/80 shadow-[0_20px_80px_rgba(0,0,0,0.35)]">
      <div className="flex items-center justify-between px-4 py-3 text-xs uppercase tracking-[0.18em] text-muted-foreground">
        <span>{map.city?.name ?? "Genesis City"}</span>
        <span className="text-teal">{aivva?.status_label ?? "Map"}</span>
      </div>
      <svg viewBox="0 0 1000 640" className="map-grid h-auto w-full bg-[#0b1020]">
        {map.districts.map((district) => (
          <g key={district.id}>
            <polygon
              points={district.polygon.map((p) => p.join(",")).join(" ")}
              fill={district.color}
              fillOpacity={0.12}
              stroke={district.color}
              strokeOpacity={0.55}
              strokeWidth={2}
            />
            <text
              x={district.polygon[0][0] + 16}
              y={district.polygon[0][1] + 28}
              fill={district.color}
              fontSize="14"
              letterSpacing="1.5"
            >
              {district.name}
            </text>
            {district.locations.map((place) => (
              <g key={place.id} className="cursor-pointer" onClick={() => setSelected(String(place.id))}>
                <circle cx={place.x} cy={place.y} r={selected === String(place.id) ? 8 : 5} fill={district.color} />
                <text x={place.x + 10} y={place.y + 4} fill="#f4efe4" fontSize="11">
                  {place.name}
                </text>
              </g>
            ))}
          </g>
        ))}

        {dest && aivva?.movement.traveling && (
          <line
            x1={aivva.movement.from?.x ?? marker.x}
            y1={aivva.movement.from?.y ?? marker.y}
            x2={dest.x}
            y2={dest.y}
            stroke="#1ee0b0"
            strokeDasharray="8 6"
            strokeWidth={2}
          />
        )}

        {map.aivvas
          .filter((other) => other.id !== aivva?.id && other.location)
          .map((other) => (
            <g key={other.id}>
              <circle cx={other.location!.x} cy={other.location!.y - 16} r={7} fill="#8b7cff" />
              <text x={other.location!.x + 10} y={other.location!.y - 12} fill="#c9c1ff" fontSize="10">
                {other.name}
              </text>
            </g>
          ))}

        <g>
          <circle cx={marker.x} cy={marker.y} r={11} fill="#1ee0b0" className="animate-pulse" />
          <circle cx={marker.x} cy={marker.y} r={18} fill="none" stroke="#1ee0b0" strokeOpacity={0.4} />
          <text x={marker.x + 16} y={marker.y - 10} fill="#1ee0b0" fontSize="13" fontWeight={600}>
            {aivva?.name ?? "You"}
          </text>
        </g>
      </svg>
      <div className="border-t border-white/10 px-4 py-3 text-sm text-muted-foreground">
        {selectedPlace ? (
          <div>
            <p className="font-medium text-foreground">{selectedPlace.name}</p>
            <p>{selectedPlace.description}</p>
            {selectedPlace.services.length > 0 && (
              <p className="mt-1 text-xs uppercase tracking-wider text-teal">{selectedPlace.services.join(" · ")}</p>
            )}
          </div>
        ) : aivva?.location ? (
          <p>
            {aivva.name} is {aivva.movement.traveling ? "traveling toward" : "at"}{" "}
            <span className="text-foreground">
              {aivva.movement.traveling ? aivva.movement.to?.name : aivva.location.name}
            </span>
            {aivva.location.district?.name ? ` in ${aivva.location.district.name}` : ""}.
          </p>
        ) : (
          <p>Click a building to learn what happens there.</p>
        )}
      </div>
    </div>
  );
}
