import { describe, expect, it } from "vitest";
import { GENESIS_MAP, landmarkFor, paddedRing, placeLngLat, projectXY, unprojectLngLat } from "./geo";
import { boxesOverlap, visibleLabelIds } from "./mapLabels";

describe("Genesis overlay projection", () => {
  it("maps the northwest logical corner to the north-west of the real bbox", () => {
    const p = projectXY(0, 0);
    expect(p.lng).toBeCloseTo(GENESIS_MAP.west, 5);
    expect(p.lat).toBeCloseTo(GENESIS_MAP.north, 5);
  });

  it("maps the southeast logical corner to the south-east of the real bbox", () => {
    const p = projectXY(GENESIS_MAP.width, GENESIS_MAP.height);
    expect(p.lng).toBeCloseTo(GENESIS_MAP.east, 5);
    expect(p.lat).toBeCloseTo(GENESIS_MAP.south, 5);
  });

  it("keeps Residence 01 (120,480) inside the bbox as a fallback", () => {
    const p = projectXY(120, 480);
    expect(p.lng).toBeGreaterThan(GENESIS_MAP.west);
    expect(p.lng).toBeLessThan(GENESIS_MAP.east);
    expect(p.lat).toBeGreaterThan(GENESIS_MAP.south);
    expect(p.lat).toBeLessThan(GENESIS_MAP.north);
  });

  it("unprojects a real click back to logical x/y (round trip)", () => {
    const projected = projectXY(500, 320);
    const back = unprojectLngLat(projected.lng, projected.lat);
    expect(back.x).toBeCloseTo(500, 0);
    expect(back.y).toBeCloseTo(320, 0);
  });

  it("clamps an unprojected click outside the bbox to the grid edges", () => {
    const back = unprojectLngLat(GENESIS_MAP.west - 1, GENESIS_MAP.north + 1);
    expect(back.x).toBe(0);
    expect(back.y).toBe(0);
  });
});

describe("real BGC landmarks", () => {
  it("pins Residence 01 to Serendra", () => {
    const p = placeLngLat({ slug: "luna-home", x: 120, y: 480 });
    expect(p?.lng).toBeCloseTo(121.05376, 4);
    expect(p?.lat).toBeCloseTo(14.54984, 4);
    expect(landmarkFor("luna-home")?.osmName).toBe("Serendra");
  });

  it("pins Central Exchange to Market! Market!", () => {
    expect(landmarkFor("central-exchange")?.osmName).toBe("Market! Market!");
  });

  it("falls back to projected x/y when the slug is unknown", () => {
    const projected = projectXY(10, 10);
    const p = placeLngLat({ slug: "unknown-place", x: 10, y: 10 });
    expect(p).toEqual(projected);
  });

  it("builds a closed padded ring around landmarks", () => {
    const ring = paddedRing([
      { lng: 121.05, lat: 14.55 },
      { lng: 121.051, lat: 14.551 },
    ]);
    expect(ring).toHaveLength(5);
    expect(ring[0]).toEqual(ring[4]);
    expect(ring[0][0]).toBeLessThan(121.05);
  });
});

describe("atlas label collision", () => {
  it("detects overlapping boxes", () => {
    expect(boxesOverlap({ x: 0, y: 0, w: 40, h: 10 }, { x: 20, y: 0, w: 40, h: 10 })).toBe(true);
    expect(boxesOverlap({ x: 0, y: 0, w: 40, h: 10 }, { x: 80, y: 0, w: 40, h: 10 })).toBe(false);
  });

  it("keeps the actor name and hides a stacked region at close zoom", () => {
    const visible = visibleLabelIds(
      [
        { id: "actor-1", kind: "actor", priority: 1, x: 100, y: 100, w: 50, h: 14 },
        { id: "region-1", kind: "region", priority: 30, x: 105, y: 102, w: 120, h: 16 },
        { id: "place-1", kind: "place", priority: 12, x: 108, y: 140, w: 90, h: 14 },
      ],
      15.6,
    );
    expect(visible.has("actor-1")).toBe(true);
    expect(visible.has("region-1")).toBe(false);
    expect(visible.has("place-1")).toBe(true);
  });

  it("hides a place name that sits on top of an actor", () => {
    const visible = visibleLabelIds(
      [
        { id: "actor-1", kind: "actor", priority: 1, x: 40, y: 40, w: 48, h: 14 },
        { id: "place-1", kind: "place", priority: 12, x: 42, y: 41, w: 80, h: 14 },
      ],
      16,
    );
    expect([...visible]).toEqual(["actor-1"]);
  });
});
