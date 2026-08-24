import { describe, expect, it } from "vitest";
import { ACTION_LABELS, BRAND_FOOTER, BRAND_SLOGAN, LOCAL_TEST_ECONOMY_BANNER, actionLabel, memoryCategoryLabel } from "./copy";
import { formatCredits, formatSignedCredits } from "./format";

describe("owner-facing copy", () => {
  it("keeps the public brand line from the identity board", () => {
    expect(BRAND_SLOGAN).toBe("YOUR AI LIFE. YOUR WORLD. YOUR FUTURE.");
    expect(BRAND_FOOTER).toContain("FIRST AI CIVILIZATION");
  });

  it("keeps the wallet banner honest about test credits", () => {
    expect(LOCAL_TEST_ECONOMY_BANNER).toContain("LOCAL TEST ECONOMY");
    expect(LOCAL_TEST_ECONOMY_BANNER.toLowerCase()).toContain("not money");
  });

  it("surfaces peer action labels without inventing new ones", () => {
    expect(actionLabel("ASK_QUESTION")).toBe("ASK QUESTION");
    expect(actionLabel("MAKE_PROPOSAL")).toBe("MAKE PROPOSAL");
    expect(Object.keys(ACTION_LABELS)).toContain("RESPOND");
  });

  it("formats memory categories and credits from real numbers only", () => {
    expect(memoryCategoryLabel("SHORT_TERM")).toBe("Short Term");
    expect(formatCredits(undefined)).toBe("—");
    expect(formatSignedCredits(12)).toBe("+12");
  });
});
