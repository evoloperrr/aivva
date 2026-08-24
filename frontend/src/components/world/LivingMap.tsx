"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import * as maplibregl from "maplibre-gl";
import type { GeoJSONSource, Map as MapLibreMap, MapLayerMouseEvent, Marker, StyleSpecification } from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import type { Aivva, MapPlace, WorldMap } from "@/lib/api";
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
          [...district.polygon.map(([x, y]) => {
            const p = projectXY(x, y);
            return [p.lng, p.lat];
          }), (() => {
            const first = district.polygon[0];
            const p = projectXY(first[0], first[1]);
            return [p.lng, p.lat];
          })()],
        ],
      },
    })),
  };
}

function placeCollection(map: WorldMap): GeoJSON.FeatureCollection {
  return {
    type: "FeatureCollection",
    features: map.districts.flatMap((district) =>
      district.locations.map((place) => {
        const p = projectXY(place.x, place.y);
        return {
          type: "Feature" as const,
          properties: {
            id: place.id,
            name: place.name,
            color: district.color,
            type: place.type,
          },
          geometry: { type: "Point" as const, coordinates: [p.lng, p.lat] },
        };
      }),
    ),
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

function ensureLayers(map: MapLibreMap) {
  if (!map.getSource("districts")) {
    map.addSource("districts", { type: "geojson", data: { type: "FeatureCollection", features: [] } });
    map.addLayer({
      id: "district-fill",
      type: "fill",
      source: "districts",
      paint: { "fill-color": ["get", "color"], "fill-opacity": 0.16 },
    });
    map.addLayer({
      id: "district-line",
      type: "line",
      source: "districts",
      paint: { "line-color": ["get", "color"], "line-width": 1.6, "line-opacity": 0.85 },
    });
  }
  if (!map.getSource("places")) {
    map.addSource("places", { type: "geojson", data: { type: "FeatureCollection", features: [] } });
    map.addLayer({
      id: "place-pulse",
      type: "circle",
      source: "places",
      paint: {
        "circle-radius": 11,
        "circle-color": ["get", "color"],
        "circle-opacity": 0.18,
      },
    });
    map.addLayer({
      id: "place-dot",
      type: "circle",
      source: "places",
      paint: {
        "circle-radius": 5,
        "circle-color": ["get", "color"],
        "circle-stroke-width": 1.5,
        "circle-stroke-color": "#eef4ff",
      },
    });
    map.addLayer({
      id: "place-label",
      type: "symbol",
      source: "places",
      layout: {
        "text-field": ["get", "name"],
        "text-size": 11,
        "text-offset": [0, 1.15],
        "text-anchor": "top",
      },
      paint: { "text-color": "#eef4ff", "text-halo-color": "#05070f", "text-halo-width": 1.2 },
    });
  }
  if (!map.getSource("route")) {
    map.addSource("route", { type: "geojson", data: { type: "FeatureCollection", features: [] } });
    map.addLayer({
      id: "route-line",
      type: "line",
      source: "route",
      paint: {
        "line-color": "#22e3d0",
        "line-width": 3,
        "line-dasharray": [1.4, 1.2],
        "line-opacity": 0.9,
      },
    });
  }
}

function setSource(map: MapLibreMap, id: string, data: GeoJSON.FeatureCollection) {
  const source = map.getSource(id) as GeoJSONSource | undefined;
  source?.setData(data);
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
  const markersRef = useRef<Map<string, Marker>>(new Map());
  const userMovedRef = useRef(false);
  const fallbackTilesRef = useRef(false);
  const [ready, setReady] = useState(false);
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
      zoom: 15.2,
      attributionControl: { compact: true },
    });
    const pins = markersRef.current;
    instance.addControl(new maplibregl.NavigationControl({ showCompass: false }), "top-right");
    mapRef.current = instance;

    const onMove = () => {
      userMovedRef.current = true;
    };
    instance.on("dragstart", onMove);
    instance.on("zoomstart", (event: { originalEvent?: unknown }) => {
      if (event.originalEvent) onMove();
    });

    const boot = () => {
      ensureLayers(instance);
      setReady(true);
    };
    instance.on("load", boot);
    instance.on("style.load", boot);
    instance.on("error", () => {
      if (fallbackTilesRef.current) return;
      fallbackTilesRef.current = true;
      instance.setStyle(RASTER_STYLE);
    });

    return () => {
      instance.remove();
      mapRef.current = null;
      pins.forEach((marker) => marker.remove());
      pins.clear();
    };
  }, []);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance || !ready || !map) return;
    ensureLayers(instance);
    setSource(instance, "districts", districtCollection(map));
    setSource(instance, "places", placeCollection(map));
    setSource(instance, "route", routeCollection(aivva));

    const onPlaceClick = (event: MapLayerMouseEvent) => {
      const feature = event.features?.[0];
      const id = feature?.properties?.id as number | undefined;
      if (id == null) return;
      const place = map.districts.flatMap((district) => district.locations).find((row) => row.id === id);
      if (place) setSelection({ kind: "place", place });
    };
    instance.on("click", "place-dot", onPlaceClick);
    instance.on("mouseenter", "place-dot", () => {
      instance.getCanvas().style.cursor = "pointer";
    });
    instance.on("mouseleave", "place-dot", () => {
      instance.getCanvas().style.cursor = "";
    });
    return () => {
      instance.off("click", "place-dot", onPlaceClick);
    };
  }, [map, aivva, ready]);

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

    for (const [id, marker] of markersRef.current) {
      if (!wanted.has(id)) {
        marker.remove();
        markersRef.current.delete(id);
      }
    }

    wanted.forEach((entry, id) => {
      let marker = markersRef.current.get(id);
      if (!marker) {
        const el = document.createElement("button");
        el.type = "button";
        el.className = cn("aivva-map-pin", entry.mine && "aivva-map-pin-live");
        el.innerHTML = `<span class="aivva-map-pin-dot"></span><span class="aivva-map-pin-name">${entry.label}</span>`;
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
        markersRef.current.set(id, marker);
      } else {
        marker.setLngLat([entry.lng, entry.lat]);
        const name = marker.getElement().querySelector(".aivva-map-pin-name");
        if (name) name.textContent = entry.label;
      }
    });

    if (livePos && aivva?.movement.traveling && !userMovedRef.current) {
      instance.easeTo({
        center: [livePos.lng, livePos.lat],
        duration: reducedMotion() ? 0 : 700,
      });
    }
  }, [aivva, livePos, map, ready]);

  if (!map) {
    return (
      <div className="map-grid flex h-[520px] items-center justify-center rounded-3xl border border-white/10 text-sm text-muted-foreground">
        Loading live streets…
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-3xl border border-white/10 bg-[#070b16] shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
      <div className="flex items-center justify-between gap-3 px-4 py-3 text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        <span>{map.city?.name ?? "Genesis City"} · live OSM</span>
        <span className="text-teal">{GENESIS_MAP.placeLabel}</span>
      </div>
      <div className="relative">
        <div ref={hostRef} className="aivva-map h-[min(68vh,640px)] w-full" />
        {!ready && (
          <div className="absolute inset-0 grid place-items-center bg-[#070b16]/70 text-sm text-muted-foreground">
            Connecting to live map tiles…
          </div>
        )}
      </div>
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
            {aivva.name} is {aivva.movement.traveling ? "moving on live streets toward" : "on the live map at"}{" "}
            <span className="text-foreground">
              {aivva.movement.traveling ? aivva.movement.to?.name : aivva.location.name}
            </span>
            {aivva.location.district?.name ? ` in ${aivva.location.district.name}` : ""}.
          </p>
        ) : (
          <p>Click a place or an AIVVA. Positions update from the live backend snapshot.</p>
        )}
      </div>
    </div>
  );
}
