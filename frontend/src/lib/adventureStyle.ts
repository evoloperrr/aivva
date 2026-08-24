import type { Map as MapLibreMap } from "maplibre-gl";

const FILL: Record<string, string> = {
  background: "#10180f",
  water: "#1b3f46",
  landcover_ice_shelf: "#10180f",
  landcover_glacier: "#10180f",
  landuse_residential: "#161c12",
  landcover_wood: "#173425",
  landuse_park: "#1e3d29",
  building: "#1c2016",
  "aeroway-area": "#10180f",
  road_area_pier: "#10180f",
};

const LINE: Record<string, string> = {
  waterway: "#1b3f46",
  highway_path: "#c9a44a",
  highway_minor: "#7d6634",
  highway_major_casing: "#3f3116",
  highway_major_inner: "#d4b56a",
  highway_major_subtle: "#8a7038",
  highway_motorway_casing: "#4a3a16",
  highway_motorway_subtle: "#8a7038",
  railway: "#5c4a28",
  railway_transit: "#5c4a28",
  railway_minor: "#5c4a28",
  road_pier: "#10180f",
};

export function paintAdventureCartography(map: MapLibreMap) {
  const style = map.getStyle();
  if (!style?.layers) return;

  for (const layer of style.layers) {
    try {
      if (layer.type === "background" && FILL.background) {
        map.setPaintProperty(layer.id, "background-color", FILL.background);
      }
      if (layer.type === "fill") {
        const color = FILL[layer.id];
        if (color) map.setPaintProperty(layer.id, "fill-color", color);
        if (layer.id === "building") {
          map.setPaintProperty(layer.id, "fill-outline-color", "#3a3320");
        }
      }
      if (layer.type === "line") {
        const color = LINE[layer.id];
        if (color) map.setPaintProperty(layer.id, "line-color", color);
        if (layer.id === "highway_path") {
          map.setPaintProperty(layer.id, "line-dasharray", [1.6, 1.4]);
        }
      }
      if (layer.type === "symbol") {
        map.setPaintProperty(layer.id, "text-color", "#e6d7a8");
        map.setPaintProperty(layer.id, "text-halo-color", "#0c120c");
        if (layer.id.startsWith("highway_name")) {
          map.setPaintProperty(layer.id, "text-opacity", 0.85);
        }
      }
    } catch {
      // Layer paint keys vary; skip what this style does not support.
    }
  }
}

export function poiGlyph(type: string) {
  switch (type) {
    case "home":
      return { mark: "⌂", kind: "hearth" };
    case "studio":
      return { mark: "✦", kind: "guild" };
    case "market":
      return { mark: "◆", kind: "bazaar" };
    case "park":
      return { mark: "※", kind: "grove" };
    case "school":
      return { mark: "✎", kind: "archive" };
    case "civic":
      return { mark: "▲", kind: "keep" };
    default:
      return { mark: "●", kind: "mark" };
  }
}
