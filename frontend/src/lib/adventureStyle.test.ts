import { describe, expect, it } from "vitest";
import { poiGlyph } from "./adventureStyle";

describe("adventure map glyphs", () => {
  it("maps Genesis place types to atlas kinds", () => {
    expect(poiGlyph("home").kind).toBe("hearth");
    expect(poiGlyph("studio").kind).toBe("guild");
    expect(poiGlyph("market").kind).toBe("bazaar");
    expect(poiGlyph("park").kind).toBe("grove");
  });
});
