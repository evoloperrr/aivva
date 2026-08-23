"use client";

import { useMemo, useState } from "react";
import type { Aivva, MapPlace, WorldMap } from "@/lib/api";
import { cn } from "@/lib/utils";

type Selection =
  | { kind: "place"; place: MapPlace }
  | { kind: "aivva"; name: string; activity: string; place: MapPlace | null };

function pathPoints(district: WorldMap["districts"][number]) {
  if (district.locations.length < 2) return [];
  return district.locations.slice(0, -1).map((place, index) => ({
    from: place,
    to: district.locations[index + 1],
  }));
}

export function LivingMap({
  map,
  aivva,
}: {
  map: WorldMap | null;
  aivva: Aivva | null;
}) {
  const [selection, setSelection] = useState<Selection | null>(null);
  const selectedPlace = selection?.kind === "place" ? selection.place : selection?.place ?? null;

  const marker = useMemo(() => {
    if (!aivva) return null;
    return aivva.movement.traveling
      ? { x: aivva.movement.x ?? aivva.location?.x ?? 120, y: aivva.movement.y ?? aivva.location?.y ?? 480 }
      : { x: aivva.location?.x ?? 120, y: aivva.location?.y ?? 480 };
  }, [aivva]);

  if (!map) {
    return (
      <div className="map-grid flex h-[460px] items-center justify-center rounded-3xl border border-white/10 text-sm text-muted-foreground">
        Loading Genesis City…
      </div>
    );
  }

  const dest = aivva?.movement.to;

  return (
    <div className="overflow-hidden rounded-3xl border border-white/10 bg-[#070b16] shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
      <div className="flex items-center justify-between px-4 py-3 text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        <span>{map.city?.name ?? "Genesis City"}</span>
        <span className="text-teal">{aivva?.status_label ?? "Map"}</span>
      </div>
      <svg viewBox="0 0 1000 640" className="map-grid h-auto w-full">
        {map.districts.map((district) => (
          <g key={district.id}>
            <polygon
              points={district.polygon.map((p) => p.join(",")).join(" ")}
              fill={district.color}
              fillOpacity={0.1}
              stroke={district.color}
              strokeOpacity={0.55}
              strokeWidth={1.5}
            />
            {pathPoints(district).map((segment) => (
              <line
                key={`${segment.from.id}-${segment.to.id}`}
                x1={segment.from.x}
                y1={segment.from.y}
                x2={segment.to.x}
                y2={segment.to.y}
                className="city-path"
                stroke={district.color}
                strokeOpacity={0.28}
                strokeWidth={1.5}
              />
            ))}
            <text
              x={district.polygon[0][0] + 16}
              y={district.polygon[0][1] + 28}
              fill={district.color}
              fontSize="13"
              letterSpacing="1.6"
            >
              {district.name}
            </text>
            {district.locations.map((place) => {
              const active = selectedPlace?.id === place.id;
              return (
                <g
                  key={place.id}
                  className="cursor-pointer"
                  onClick={() => setSelection({ kind: "place", place })}
                >
                  <circle
                    cx={place.x}
                    cy={place.y}
                    r={active ? 8 : 5}
                    fill={district.color}
                    className={cn(active && "map-pulse")}
                  />
                  <circle
                    cx={place.x}
                    cy={place.y}
                    r={11}
                    fill="none"
                    stroke={district.color}
                    strokeOpacity={0.35}
                  />
                  <text x={place.x + 12} y={place.y + 4} fill="#eef4ff" fontSize="11">
                    {place.name}
                  </text>
                </g>
              );
            })}
          </g>
        ))}

        {dest && aivva?.movement.traveling && marker && (
          <line
            x1={aivva.movement.from?.x ?? marker.x}
            y1={aivva.movement.from?.y ?? marker.y}
            x2={dest.x}
            y2={dest.y}
            className="city-path"
            stroke="#22e3d0"
            strokeWidth={2.2}
          />
        )}

        {map.aivvas
          .filter((other) => other.id !== aivva?.id && other.location)
          .map((other) => (
            <g
              key={other.id}
              className="cursor-pointer"
              onClick={() =>
                setSelection({
                  kind: "aivva",
                  name: other.name,
                  activity: other.public_activity,
                  place: other.location,
                })
              }
            >
              <circle cx={other.location!.x} cy={other.location!.y - 16} r={7} fill="#8b7cff" />
              <text x={other.location!.x + 10} y={other.location!.y - 12} fill="#c9c1ff" fontSize="10">
                {other.name}
              </text>
            </g>
          ))}

        {marker && (
          <g
            className="cursor-pointer"
            onClick={() =>
              setSelection({
                kind: "aivva",
                name: aivva?.name ?? "AIVVA",
                activity: aivva?.status_label ?? "Present",
                place: aivva?.location ?? null,
              })
            }
          >
            <circle className="map-pulse" cx={marker.x} cy={marker.y} r={16} fill="#22e3d0" fillOpacity={0.18} />
            <circle cx={marker.x} cy={marker.y} r={11} fill="#22e3d0" />
            <circle cx={marker.x} cy={marker.y} r={18} fill="none" stroke="#22e3d0" strokeOpacity={0.45} />
            <text x={marker.x + 16} y={marker.y - 10} fill="#22e3d0" fontSize="13" fontWeight={600}>
              {aivva?.name ?? "You"}
            </text>
          </g>
        )}
      </svg>
      <div className="border-t border-white/10 px-4 py-3 text-sm text-muted-foreground">
        {selection?.kind === "aivva" ? (
          <div>
            <p className="font-medium text-foreground">
              {selection.name} · {selection.activity}
            </p>
            <p>
              {selection.place
                ? `${selection.place.name}${selection.place.district?.name ? ` in ${selection.place.district.name}` : ""}`
                : "Location is not published."}
            </p>
            {selection.place?.description && <p className="mt-1">{selection.place.description}</p>}
          </div>
        ) : selectedPlace ? (
          <div>
            <p className="font-medium text-foreground">{selectedPlace.name}</p>
            <p>{selectedPlace.description}</p>
            {selectedPlace.services.length > 0 && (
              <p className="mt-1 text-xs uppercase tracking-wider text-teal">
                {selectedPlace.services.join(" · ")}
              </p>
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
          <p>Click a node or an AIVVA to open its location.</p>
        )}
      </div>
    </div>
  );
}
