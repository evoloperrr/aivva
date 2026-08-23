import { describe, expect, it } from "vitest";
import { compactStatus, isActiveStatus, statusTone } from "./status";

describe("AIVVA status display", () => {
  it("marks living statuses as active without inventing labels", () => {
    expect(isActiveStatus("SOCIALIZING")).toBe(true);
    expect(isActiveStatus("PAUSED")).toBe(false);
    expect(statusTone("ERROR")).toBe("danger");
    expect(compactStatus("Traveling")).toBe("TRAVELING");
  });
});
