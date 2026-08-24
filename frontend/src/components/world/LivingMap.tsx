"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import * as maplibregl from "maplibre-gl";
import type { GeoJSONSource, Map as MapLibreMap, Marker, StyleSpecification } from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import type { Aivva, MapPlace, WorldMap } from "@/lib/api";
import { poiGlyph } from "@/lib/adventureStyle";
import {
  GENESIS_MAP,
  OSM_RASTER_TILES,
  landmarkFor,
  lerpLngLat,
  paddedRing,
  placeLngLat,
  ringCenter,
} from "@/lib/geo";
import { visibleLabelIds, type AtlasLabel } from "@/lib/mapLabels";
import { cn } from "@/lib/utils";

type Selection =
  | { kind: "place"; place: MapPlace }
  | { kind: "aivva"; name: string; activity: string; place: MapPlace | null };

const WORLD_STYLE: StyleSpecification = {
  version: 8,
  glyphs: "https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf",
  sources: {
    osm: {
      type: "raster",
      tiles: [...OSM_RASTER_TILES],
      tileSize: 256,
      attribution: "© OpenStreetMap contributors © CARTO",
      maxzoom: 20,
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
    const from = placeLngLat(aivva.movement.from);
    const to = placeLngLat(aivva.movement.to);
    if (from && to) return lerpLngLat(from, to, aivva.movement.progress ?? 0);
  }
  return placeLngLat(aivva.location);
}

function districtCollection(map: WorldMap): GeoJSON.FeatureCollection {
  return {
    type: "FeatureCollection",
        features: map.districts.flatMap((district) => {
      const points = district.locations.map((place) => placeLngLat(place)).filter((p): p is NonNullable<typeof p> => Boolean(p));
      const ring = paddedRing(points);
      if (ring.length === 0) return [];
      return [{
        type: "Feature" as const,
        properties: { id: district.id, name: district.name, color: district.color },
        geometry: {
          type: "Polygon" as const,
          coordinates: [ring],
        },
      }];
    }),
  };
}

function routeCollection(aivva: Aivva | null): GeoJSON.FeatureCollection {
  const from = placeLngLat(aivva?.movement.from);
  const to = placeLngLat(aivva?.movement.to);
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
      paint: { "fill-color": ["get", "color"], "fill-opacity": 0.12 },
    });
    map.addLayer({
      id: "district-line",
      type: "line",
      source: "districts",
      paint: {
        "line-color": "#8a6a28",
        "line-width": 1.6,
        "line-dasharray": [2.4, 1.6],
        "line-opacity": 0.7,
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
        "line-color": "#c48a14",
        "line-width": 3,
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

function estimateBox(el: HTMLElement, fallbackW: number, fallbackH: number) {
  const w = el.offsetWidth || fallbackW;
  const h = el.offsetHeight || fallbackH;
  return { w, h };
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
  const [ready, setReady] = useState(false);
  const [selection, setSelection] = useState<Selection | null>(null);

  const selectedPlace = selection?.kind === "place" ? selection.place : selection?.place ?? null;
  const livePos = useMemo(() => ownerPosition(aivva), [aivva]);

  useEffect(() => {
    const host = hostRef.current;
    if (!host || mapRef.current) return;

    const instance = new maplibregl.Map({
      container: host,
      style: WORLD_STYLE,
      center: [GENESIS_MAP.center.lng, GENESIS_MAP.center.lat],
      zoom: 15.55,
      pitch: 28,
      bearing: -8,
      minZoom: 13.4,
      maxZoom: 18,
      maxBounds: [
        [121.031, 14.538],
        [121.07, 14.566],
      ],
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
      ensureOverlayLayers(instance);
      instance.resize();
      setReady(true);
    };
    instance.on("load", boot);
    instance.once("idle", () => instance.resize());

    const onResize = () => instance.resize();
    window.addEventListener("resize", onResize);

    return () => {
      window.removeEventListener("resize", onResize);
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
      const pos = placeLngLat(place);
      if (!pos) return;
      const glyph = poiGlyph(place.type);
      let marker = placeMarkersRef.current.get(id);
      if (!marker) {
        const el = document.createElement("button");
        el.type = "button";
        el.className = `atlas-poi atlas-poi-${glyph.kind}`;
        el.setAttribute("aria-label", place.name);
        el.innerHTML = `<span class="atlas-poi-flag">${glyph.mark}</span><span class="atlas-poi-name" data-atlas-label>${place.name}</span>`;
        el.addEventListener("click", (event) => {
          event.stopPropagation();
          setSelection({ kind: "place", place });
        });
        marker = new maplibregl.Marker({ element: el, anchor: "bottom" }).setLngLat([pos.lng, pos.lat]).addTo(instance);
        placeMarkersRef.current.set(id, marker);
      } else {
        marker.setLngLat([pos.lng, pos.lat]);
        const el = marker.getElement();
        el.setAttribute("aria-label", place.name);
        const flag = el.querySelector(".atlas-poi-flag");
        if (flag) flag.textContent = glyph.mark;
        const name = el.querySelector(".atlas-poi-name");
        if (name) name.textContent = place.name;
      }
    });

    for (const [id, marker] of regionMarkersRef.current) {
      if (!map.districts.some((district) => String(district.id) === id)) {
        marker.remove();
        regionMarkersRef.current.delete(id);
      }
    }
    for (const district of map.districts) {
      const points = district.locations.map((place) => placeLngLat(place)).filter((p): p is NonNullable<typeof p> => Boolean(p));
      const center = ringCenter(paddedRing(points));
      if (!center) continue;
      const id = String(district.id);
      let marker = regionMarkersRef.current.get(id);
      if (!marker) {
        const el = document.createElement("div");
        el.className = "atlas-region";
        el.dataset.atlasLabel = "1";
        el.textContent = district.name;
        el.style.color = district.color;
        marker = new maplibregl.Marker({ element: el, anchor: "center" }).setLngLat([center.lng, center.lat]).addTo(instance);
        regionMarkersRef.current.set(id, marker);
      } else {
        marker.setLngLat([center.lng, center.lat]);
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
      const pos = placeLngLat(other.location);
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
        el.setAttribute("aria-label", entry.label);
        el.innerHTML = entry.mine
          ? `<span class="atlas-actor-rune">△</span><span class="atlas-actor-name" data-atlas-label>${entry.label}</span>`
          : `<span class="atlas-actor-dot"></span><span class="atlas-actor-name" data-atlas-label>${entry.label}</span>`;
        el.addEventListener("click", (event) => {
          event.stopPropagation();
          setSelection({
            kind: "aivva",
            name: entry.label,
            activity: entry.activity,
            place: entry.place,
          });
        });
        marker = new maplibregl.Marker({ element: el, anchor: "bottom", offset: [0, -4] }).setLngLat([entry.lng, entry.lat]).addTo(instance);
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
        pitch: 32,
      });
    }
  }, [aivva, livePos, map, ready]);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance || !ready) return;

    const relayout = () => {
      const zoom = instance.getZoom();
      const labels: AtlasLabel[] = [];

      regionMarkersRef.current.forEach((marker, id) => {
        const el = marker.getElement();
        const lngLat = marker.getLngLat();
        const p = instance.project(lngLat);
        const { w, h } = estimateBox(el, 140, 18);
        labels.push({ id: `region-${id}`, kind: "region", priority: 30, x: p.x - w / 2, y: p.y - h / 2, w, h });
        el.dataset.labelId = `region-${id}`;
      });

      placeMarkersRef.current.forEach((marker, id) => {
        const name = marker.getElement().querySelector<HTMLElement>("[data-atlas-label]");
        if (!name) return;
        const lngLat = marker.getLngLat();
        const p = instance.project(lngLat);
        const { w, h } = estimateBox(name, 90, 14);
        labels.push({ id: `place-${id}`, kind: "place", priority: 12, x: p.x - w / 2, y: p.y + 8, w, h });
        name.dataset.labelId = `place-${id}`;
      });

      actorMarkersRef.current.forEach((marker, id) => {
        const name = marker.getElement().querySelector<HTMLElement>("[data-atlas-label]");
        if (!name) return;
        const lngLat = marker.getLngLat();
        const p = instance.project(lngLat);
        const { w, h } = estimateBox(name, 64, 14);
        labels.push({ id: `actor-${id}`, kind: "actor", priority: 1, x: p.x - w / 2, y: p.y + 10, w, h });
        name.dataset.labelId = `actor-${id}`;
      });

      const visible = visibleLabelIds(labels, zoom);

      regionMarkersRef.current.forEach((marker) => {
        const el = marker.getElement();
        el.classList.toggle("atlas-label-hidden", !visible.has(el.dataset.labelId ?? ""));
      });
      placeMarkersRef.current.forEach((marker) => {
        const name = marker.getElement().querySelector<HTMLElement>("[data-atlas-label]");
        if (name) name.classList.toggle("atlas-label-hidden", !visible.has(name.dataset.labelId ?? ""));
      });
      actorMarkersRef.current.forEach((marker) => {
        const name = marker.getElement().querySelector<HTMLElement>("[data-atlas-label]");
        if (name) name.classList.toggle("atlas-label-hidden", !visible.has(name.dataset.labelId ?? ""));
      });
    };

    relayout();
    const timer = window.setTimeout(relayout, 60);
    instance.on("move", relayout);
    instance.on("zoom", relayout);
    return () => {
      window.clearTimeout(timer);
      instance.off("move", relayout);
      instance.off("zoom", relayout);
    };
  }, [map, aivva, livePos, ready]);

  if (!map) {
    return (
      <div className="atlas-frame flex h-[520px] items-center justify-center text-sm text-[#e6d7a8]/70">
        Unrolling the atlas…
      </div>
    );
  }

  const landmark = selectedPlace ? landmarkFor(selectedPlace.slug) : null;

  return (
    <div className="atlas-frame">
      <div className="flex items-center justify-between gap-3 px-4 py-3">
        <div>
          <p className="font-heading text-lg tracking-[0.18em] text-[#e6d7a8]">World Atlas</p>
          <p className="text-[10px] uppercase tracking-[0.22em] text-[#e6d7a8]/55">
            {map.city?.name ?? "Genesis City"} · live OpenStreetMap · {GENESIS_MAP.placeLabel}
          </p>
        </div>
        <p className="mark text-xs text-[#22e3d0]">△I▽▽△</p>
      </div>
      <div className="atlas-stage relative">
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
          <div className="absolute inset-0 grid place-items-center bg-[#10180f]/55 text-sm text-[#e6d7a8]/80">
            Loading BGC streets…
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
                ? `${selection.place.name}${landmarkFor(selection.place.slug) ? ` · ${landmarkFor(selection.place.slug)?.osmName}` : ""}${selection.place.district?.name ? ` · ${selection.place.district.name}` : ""}`
                : "Location is not published."}
            </p>
            {selection.place?.description && <p className="mt-1">{selection.place.description}</p>}
          </div>
        ) : selectedPlace ? (
          <div>
            <p className="font-medium text-[#f4ead0]">
              {poiGlyph(selectedPlace.type).kind} · {selectedPlace.name}
            </p>
            {landmark && <p className="text-xs uppercase tracking-[0.14em] text-[#d4b56a]">{landmark.osmName}, Bonifacio Global City</p>}
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
            {landmarkFor((aivva.movement.traveling ? aivva.movement.to : aivva.location)?.slug)?.osmName
              ? ` on ${landmarkFor((aivva.movement.traveling ? aivva.movement.to : aivva.location)?.slug)?.osmName}`
              : ""}
            {aivva.location.district?.name ? ` in ${aivva.location.district.name}` : ""}.
          </p>
        ) : (
          <p>Choose a pennant or your party. Streets are live OpenStreetMap of BGC; pennants are Genesis places on real landmarks.</p>
        )}
      </div>
    </div>
  );
}
