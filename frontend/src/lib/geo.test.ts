import { describe, expect, it } from "vitest";
import { GENESIS_MAP, projectXY } from "./geo";

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

  it("keeps Residence 01 (120,480) inside the bbox", () => {
    const p = projectXY(120, 480);
    expect(p.lng).toBeGreaterThan(GENESIS_MAP.west);
    expect(p.lng).toBeLessThan(GENESIS_MAP.east);
    expect(p.lat).toBeGreaterThan(GENESIS_MAP.south);
    expect(p.lat).toBeLessThan(GENESIS_MAP.north);
  });
});
