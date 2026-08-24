"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import * as maplibregl from "maplibre-gl";
import type { GeoJSONSource, Map as MapLibreMap, Marker, StyleSpecification } from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import type { Aivva, MapPlace, WorldMap } from "@/lib/api";
import { paintAdventureCartography, poiGlyph } from "@/lib/adventureStyle";
import { GENESIS_MAP, projectPoint, projectXY } from "@/lib/geo";
import { cn } from "@/lib/utils";

type Selection =
  | { kind: "place"; place: MapPlace }
  | { kind: "aivva"; name: string; activity: string; place: MapPlace | null };

const RASTER_STYLE: StyleSpecification = {
  version: 8,
  sources: {
    osm: {
      type: "raster",
      tiles: ["https://basemaps.cartocdn.com/dark_all/{z}/{x}/{y}@2x.png"],
      tileSize: 256,
      attribution: "© OpenStreetMap © CARTO",
    },
  },
  layers: [{ id: "osm", type: "raster", source: "osm" }],
};

function reducedMotion() {
  if (typeof window === "undefined") return true;
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function ownerPosition(aivva: Aivva | null) {
  if (!aivva) return null;
  if (aivva.movement.traveling) {
    return projectPoint({
      x: aivva.movement.x ?? aivva.location?.x,
      y: aivva.movement.y ?? aivva.location?.y,
    });
  }
  return projectPoint(aivva.location);
}

function districtCollection(map: WorldMap): GeoJSON.FeatureCollection {
  return {
    type: "FeatureCollection",
    features: map.districts.map((district) => ({
      type: "Feature",
      properties: { id: district.id, name: district.name, color: district.color },
      geometry: {
        type: "Polygon",
        coordinates: [
          [
            ...district.polygon.map(([x, y]) => {
              const p = projectXY(x, y);
              return [p.lng, p.lat];
            }),
            (() => {
              const first = district.polygon[0];
              const p = projectXY(first[0], first[1]);
              return [p.lng, p.lat];
            })(),
          ],
        ],
      },
    })),
  };
}

function routeCollection(aivva: Aivva | null): GeoJSON.FeatureCollection {
  const from = projectPoint(aivva?.movement.from);
  const to = projectPoint(aivva?.movement.to);
  if (!aivva?.movement.traveling || !from || !to) {
    return { type: "FeatureCollection", features: [] };
  }
  const here = ownerPosition(aivva) ?? from;
  return {
    type: "FeatureCollection",
    features: [
      {
        type: "Feature",
        properties: {},
        geometry: {
          type: "LineString",
          coordinates: [
            [from.lng, from.lat],
            [here.lng, here.lat],
            [to.lng, to.lat],
          ],
        },
      },
    ],
  };
}

function ensureOverlayLayers(map: MapLibreMap) {
  if (!map.getSource("districts")) {
    map.addSource("districts", { type: "geojson", data: { type: "FeatureCollection", features: [] } });
    map.addLayer({
      id: "district-fill",
      type: "fill",
      source: "districts",
      paint: { "fill-color": ["get", "color"], "fill-opacity": 0.1 },
    });
    map.addLayer({
      id: "district-line",
      type: "line",
      source: "districts",
      paint: {
        "line-color": "#d4b56a",
        "line-width": 1.4,
        "line-dasharray": [3, 2],
        "line-opacity": 0.75,
      },
    });
  }
  if (!map.getSource("route")) {
    map.addSource("route", { type: "geojson", data: { type: "FeatureCollection", features: [] } });
    map.addLayer({
      id: "route-line",
      type: "line",
      source: "route",
      paint: {
        "line-color": "#e8c36a",
        "line-width": 3.2,
        "line-dasharray": [1.2, 1.1],
        "line-opacity": 0.95,
      },
    });
  }
}

function setSource(map: MapLibreMap, id: string, data: GeoJSON.FeatureCollection) {
  const source = map.getSource(id) as GeoJSONSource | undefined;
  source?.setData(data);
}

function districtCenter(district: WorldMap["districts"][number]) {
  if (district.polygon.length === 0) return null;
  const x = district.polygon.reduce((sum, point) => sum + point[0], 0) / district.polygon.length;
  const y = district.polygon.reduce((sum, point) => sum + point[1], 0) / district.polygon.length;
  return projectXY(x, y);
}

export function LivingMap({
  map,
  aivva,
}: {
  map: WorldMap | null;
  aivva: Aivva | null;
}) {
  const hostRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<MapLibreMap | null>(null);
  const actorMarkersRef = useRef<Map<string, Marker>>(new Map());
  const placeMarkersRef = useRef<Map<string, Marker>>(new Map());
  const regionMarkersRef = useRef<Map<string, Marker>>(new Map());
  const userMovedRef = useRef(false);
  const fallbackTilesRef = useRef(false);
  const [ready, setReady] = useState(false);
  const [rasterFallback, setRasterFallback] = useState(false);
  const [selection, setSelection] = useState<Selection | null>(null);

  const selectedPlace = selection?.kind === "place" ? selection.place : selection?.place ?? null;
  const livePos = useMemo(() => ownerPosition(aivva), [aivva]);

  useEffect(() => {
    const host = hostRef.current;
    if (!host || mapRef.current) return;

    const instance = new maplibregl.Map({
      container: host,
      style: GENESIS_MAP.styleUrl,
      center: [GENESIS_MAP.center.lng, GENESIS_MAP.center.lat],
      zoom: 15.15,
      pitch: 38,
      bearing: -18,
      attributionControl: { compact: true },
    });
    const actorPins = actorMarkersRef.current;
    const placePins = placeMarkersRef.current;
    const regionPins = regionMarkersRef.current;
    mapRef.current = instance;

    const onMove = () => {
      userMovedRef.current = true;
    };
    instance.on("dragstart", onMove);
    instance.on("zoomstart", (event: { originalEvent?: unknown }) => {
      if (event.originalEvent) onMove();
    });

    const boot = () => {
      paintAdventureCartography(instance);
      ensureOverlayLayers(instance);
      setReady(true);
    };
    instance.on("load", boot);
    instance.on("style.load", boot);
    instance.on("error", () => {
      if (fallbackTilesRef.current) return;
      fallbackTilesRef.current = true;
      setRasterFallback(true);
      instance.setStyle(RASTER_STYLE);
    });

    return () => {
      instance.remove();
      mapRef.current = null;
      actorPins.forEach((marker) => marker.remove());
      placePins.forEach((marker) => marker.remove());
      regionPins.forEach((marker) => marker.remove());
      actorPins.clear();
      placePins.clear();
      regionPins.clear();
    };
  }, []);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance || !ready || !map) return;
    ensureOverlayLayers(instance);
    setSource(instance, "districts", districtCollection(map));
    setSource(instance, "route", routeCollection(aivva));
  }, [map, aivva, ready]);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance || !ready || !map) return;

    const wanted = new Map<string, MapPlace>();
    for (const district of map.districts) {
      for (const place of district.locations) {
        wanted.set(String(place.id), place);
      }
    }
    for (const [id, marker] of placeMarkersRef.current) {
      if (!wanted.has(id)) {
        marker.remove();
        placeMarkersRef.current.delete(id);
      }
    }
    wanted.forEach((place, id) => {
      const pos = projectPoint(place);
      if (!pos) return;
      let marker = placeMarkersRef.current.get(id);
      const glyph = poiGlyph(place.type);
      if (!marker) {
        const el = document.createElement("button");
        el.type = "button";
        el.className = `atlas-poi atlas-poi-${glyph.kind}`;
        el.innerHTML = `<span class="atlas-poi-flag">${glyph.mark}</span><span class="atlas-poi-name">${place.name}</span>`;
        el.addEventListener("click", (event) => {
          event.stopPropagation();
          setSelection({ kind: "place", place });
        });
        marker = new maplibregl.Marker({ element: el, anchor: "bottom" }).setLngLat([pos.lng, pos.lat]).addTo(instance);
        placeMarkersRef.current.set(id, marker);
      } else {
        marker.setLngLat([pos.lng, pos.lat]);
      }
    });

    for (const [id, marker] of regionMarkersRef.current) {
      if (!map.districts.some((district) => String(district.id) === id)) {
        marker.remove();
        regionMarkersRef.current.delete(id);
      }
    }
    for (const district of map.districts) {
      const center = districtCenter(district);
      if (!center) continue;
      const id = String(district.id);
      let marker = regionMarkersRef.current.get(id);
      if (!marker) {
        const el = document.createElement("div");
        el.className = "atlas-region";
        el.textContent = district.name;
        el.style.color = district.color;
        marker = new maplibregl.Marker({ element: el, anchor: "center" }).setLngLat([center.lng, center.lat]).addTo(instance);
        regionMarkersRef.current.set(id, marker);
      }
    }
  }, [map, ready]);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance || !ready) return;

    const wanted = new Map<string, { lng: number; lat: number; label: string; mine: boolean; activity: string; place: MapPlace | null }>();
    if (aivva && livePos) {
      wanted.set(aivva.id, {
        ...livePos,
        label: aivva.name,
        mine: true,
        activity: aivva.status_label,
        place: aivva.location,
      });
    }
    for (const other of map?.aivvas ?? []) {
      if (!other.location || other.id === aivva?.id) continue;
      const pos = projectPoint(other.location);
      if (!pos) continue;
      wanted.set(other.id, {
        ...pos,
        label: other.name,
        mine: false,
        activity: other.public_activity,
        place: other.location,
      });
    }

    for (const [id, marker] of actorMarkersRef.current) {
      if (!wanted.has(id)) {
        marker.remove();
        actorMarkersRef.current.delete(id);
      }
    }

    wanted.forEach((entry, id) => {
      let marker = actorMarkersRef.current.get(id);
      if (!marker) {
        const el = document.createElement("button");
        el.type = "button";
        el.className = cn("atlas-actor", entry.mine && "atlas-actor-party");
        el.innerHTML = entry.mine
          ? `<span class="atlas-actor-rune">△</span><span class="atlas-actor-name">${entry.label}</span>`
          : `<span class="atlas-actor-dot"></span><span class="atlas-actor-name">${entry.label}</span>`;
        el.addEventListener("click", (event) => {
          event.stopPropagation();
          setSelection({
            kind: "aivva",
            name: entry.label,
            activity: entry.activity,
            place: entry.place,
          });
        });
        marker = new maplibregl.Marker({ element: el, anchor: "bottom" }).setLngLat([entry.lng, entry.lat]).addTo(instance);
        actorMarkersRef.current.set(id, marker);
      } else {
        marker.setLngLat([entry.lng, entry.lat]);
        const name = marker.getElement().querySelector(".atlas-actor-name");
        if (name) name.textContent = entry.label;
      }
    });

    if (livePos && aivva?.movement.traveling && !userMovedRef.current) {
      instance.easeTo({
        center: [livePos.lng, livePos.lat],
        duration: reducedMotion() ? 0 : 800,
        pitch: 42,
      });
    }
  }, [aivva, livePos, map, ready]);

  if (!map) {
    return (
      <div className="atlas-frame flex h-[520px] items-center justify-center text-sm text-[#e6d7a8]/70">
        Unrolling the atlas…
      </div>
    );
  }

  return (
    <div className="atlas-frame">
      <div className="flex items-center justify-between gap-3 px-4 py-3">
        <div>
          <p className="font-heading text-lg tracking-[0.18em] text-[#e6d7a8]">World Atlas</p>
          <p className="text-[10px] uppercase tracking-[0.22em] text-[#e6d7a8]/55">
            {map.city?.name ?? "Genesis City"} · real streets · {GENESIS_MAP.placeLabel}
          </p>
        </div>
        <p className="mark text-xs text-[#22e3d0]">△I▽▽△</p>
      </div>
      <div className={cn("atlas-stage relative", rasterFallback && "atlas-stage-raster")}>
        <div ref={hostRef} className="aivva-map h-[min(70vh,680px)] w-full" />
        <div className="atlas-vignette" />
        <div className="atlas-compass" aria-hidden>
          <span>N</span>
        </div>
        <div className="atlas-legend">
          <p>Hearth ⌂</p>
          <p>Guild ✦</p>
          <p>Bazaar ◆</p>
          <p>Grove ※</p>
          <p>Party △</p>
        </div>
        {!ready && (
          <div className="absolute inset-0 grid place-items-center bg-[#10180f]/70 text-sm text-[#e6d7a8]/70">
            Charting live terrain…
          </div>
        )}
      </div>
      <div className="border-t border-[#d4b56a]/25 px-4 py-3 text-sm text-[#e6d7a8]/75">
        {selection?.kind === "aivva" ? (
          <div>
            <p className="font-medium text-[#f4ead0]">
              {selection.name} · {selection.activity}
            </p>
            <p>
              {selection.place
                ? `${selection.place.name}${selection.place.district?.name ? ` · ${selection.place.district.name}` : ""}`
                : "Location is not published."}
            </p>
            {selection.place?.description && <p className="mt-1">{selection.place.description}</p>}
          </div>
        ) : selectedPlace ? (
          <div>
            <p className="font-medium text-[#f4ead0]">
              {poiGlyph(selectedPlace.type).kind} · {selectedPlace.name}
            </p>
            <p>{selectedPlace.description}</p>
            {selectedPlace.services.length > 0 && (
              <p className="mt-1 text-xs uppercase tracking-wider text-[#d4b56a]">
                {selectedPlace.services.join(" · ")}
              </p>
            )}
          </div>
        ) : aivva?.location ? (
          <p>
            {aivva.name} {aivva.movement.traveling ? "follows the trail toward" : "is camped at"}{" "}
            <span className="text-[#f4ead0]">
              {aivva.movement.traveling ? aivva.movement.to?.name : aivva.location.name}
            </span>
            {aivva.location.district?.name ? ` in ${aivva.location.district.name}` : ""}.
          </p>
        ) : (
          <p>Choose a pennant or your party. Terrain is live OpenStreetMap; the markers are Genesis places.</p>
        )}
      </div>
    </div>
  );
}
