/**
 * Genesis City keeps logical x/y in the backend. The World atlas
 * pins each seeded place onto a real OpenStreetMap landmark in
 * Bonifacio Global City, Taguig, then draws Carto/OSM street tiles
 * under the adventure HUD.
 */

export type LngLat = { lng: number; lat: number };

export type RealLandmark = LngLat & {
  osmName: string;
};

export const GENESIS_MAP = {
  width: 1000,
  height: 640,
  north: 14.5578,
  south: 14.5472,
  west: 121.0432,
  east: 121.0594,
  center: { lng: 121.0513, lat: 14.5525 },
  placeLabel: "BGC, Taguig · OpenStreetMap",
} as const;

/** Carto Voyager is OSM streets with readable names. Do not use osm.org tiles. */
export const OSM_RASTER_TILES = [
  "https://a.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}@2x.png",
  "https://b.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}@2x.png",
  "https://c.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}@2x.png",
] as const;

/**
 * Real BGC coordinates (OSM/Nominatim). Genesis names stay on the
 * pennants; the ground is the actual city.
 */
export const REAL_LANDMARKS: Record<string, RealLandmark> = {
  "luna-home": { lng: 121.05376, lat: 14.54984, osmName: "Serendra" },
  "garden-walk": { lng: 121.05295, lat: 14.55042, osmName: "Serendra Piazza" },
  "music-studio-03": { lng: 121.04554, lat: 14.55207, osmName: "The Mind Museum" },
  "writer-loft": { lng: 121.04463, lat: 14.55286, osmName: "Burgos Circle" },
  "central-exchange": { lng: 121.05599, lat: 14.54929, osmName: "Market! Market!" },
  "service-arcade": { lng: 121.04933, lat: 14.55119, osmName: "Bonifacio High Street" },
  "open-library": { lng: 121.05728, lat: 14.55545, osmName: "British School Manila" },
  "meeting-lawn": { lng: 121.0506, lat: 14.5534, osmName: "30th Street, BGC" },
  "records-hall": { lng: 121.0467, lat: 14.55144, osmName: "One Bonifacio High Street" },
};

export function projectXY(x: number, y: number): LngLat {
  const { width, height, north, south, west, east } = GENESIS_MAP;
  const nx = Math.min(width, Math.max(0, x)) / width;
  const ny = Math.min(height, Math.max(0, y)) / height;
  return {
    lng: west + nx * (east - west),
    lat: north - ny * (north - south),
  };
}

/** Inverse of projectXY: a real map click back into Genesis logical x/y (clamped to the grid). */
export function unprojectLngLat(lng: number, lat: number): { x: number; y: number } {
  const { width, height, north, south, west, east } = GENESIS_MAP;
  const nx = (lng - west) / (east - west);
  const ny = (north - lat) / (north - south);
  return {
    x: Math.round(Math.min(width, Math.max(0, nx * width))),
    y: Math.round(Math.min(height, Math.max(0, ny * height))),
  };
}

export function projectPoint(point: { x?: number | null; y?: number | null } | null | undefined): LngLat | null {
  if (!point || point.x == null || point.y == null || Number.isNaN(point.x) || Number.isNaN(point.y)) {
    return null;
  }
  return projectXY(point.x, point.y);
}

export function landmarkFor(slug?: string | null): RealLandmark | null {
  if (!slug) return null;
  return REAL_LANDMARKS[slug] ?? null;
}

export function placeLngLat(
  place: { slug?: string | null; x?: number | null; y?: number | null } | null | undefined,
): LngLat | null {
  const pinned = landmarkFor(place?.slug);
  if (pinned) return { lng: pinned.lng, lat: pinned.lat };
  return projectPoint(place ?? null);
}

export function lerpLngLat(from: LngLat, to: LngLat, t: number): LngLat {
  const u = Math.min(1, Math.max(0, t));
  return {
    lng: from.lng + (to.lng - from.lng) * u,
    lat: from.lat + (to.lat - from.lat) * u,
  };
}

export function paddedRing(points: LngLat[], pad = 0.00045): [number, number][] {
  if (points.length === 0) return [];
  const lngs = points.map((p) => p.lng);
  const lats = points.map((p) => p.lat);
  const west = Math.min(...lngs) - pad;
  const east = Math.max(...lngs) + pad;
  const south = Math.min(...lats) - pad;
  const north = Math.max(...lats) + pad;
  return [
    [west, north],
    [east, north],
    [east, south],
    [west, south],
    [west, north],
  ];
}

export function ringCenter(ring: [number, number][]): LngLat | null {
  if (ring.length < 2) return null;
  const verts = ring.slice(0, -1);
  const lng = verts.reduce((sum, [x]) => sum + x, 0) / verts.length;
  const lat = verts.reduce((sum, [, y]) => sum + y, 0) / verts.length;
  return { lng, lat };
}
