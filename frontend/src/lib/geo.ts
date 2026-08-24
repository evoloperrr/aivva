/**
 * Genesis City is a logical world. Places keep backend x/y.
 * The live World screen projects those onto a real OpenStreetMap
 * bounding box so travel is visible on actual streets.
 *
 * Canvas: Bonifacio Global City, Taguig (real OSM geometry).
 * District names stay Genesis names. This is an overlay, not a claim
 * that Genesis City is a legal district of Metro Manila.
 */

export const GENESIS_MAP = {
  width: 1000,
  height: 640,
  north: 14.5618,
  south: 14.5462,
  west: 121.0365,
  east: 121.0608,
  center: { lng: 121.04865, lat: 14.554 },
  placeLabel: "BGC, Taguig · OpenStreetMap",
  styleUrl: "https://tiles.openfreemap.org/styles/dark",
} as const;

export type LngLat = { lng: number; lat: number };

export function projectXY(x: number, y: number): LngLat {
  const { width, height, north, south, west, east } = GENESIS_MAP;
  const nx = Math.min(width, Math.max(0, x)) / width;
  const ny = Math.min(height, Math.max(0, y)) / height;
  return {
    lng: west + nx * (east - west),
    lat: north - ny * (north - south),
  };
}

export function projectPoint(point: { x?: number | null; y?: number | null } | null | undefined): LngLat | null {
  if (!point || point.x == null || point.y == null || Number.isNaN(point.x) || Number.isNaN(point.y)) {
    return null;
  }
  return projectXY(point.x, point.y);
}
