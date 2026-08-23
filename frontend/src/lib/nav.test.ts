import { describe, expect, it } from "vitest";
import { isNavActive, REQUIRED_OWNER_NAV, visibleNav } from "./nav";

describe("owner navigation", () => {
  it("includes every required owner destination", () => {
    const labels = visibleNav(false).map((item) => item.label);
    expect(labels).toEqual([...REQUIRED_OWNER_NAV]);
  });

  it("hides Genesis Lab from non-admins and shows it to admins", () => {
    expect(visibleNav(false).some((item) => item.label === "Genesis Lab")).toBe(false);
    expect(visibleNav(true).some((item) => item.href === "/app/lab")).toBe(true);
  });

  it("treats Home as an exact match and World as the map alias", () => {
    const home = visibleNav(false).find((item) => item.label === "Home");
    const world = visibleNav(false).find((item) => item.label === "World");
    expect(home && isNavActive("/app", home)).toBe(true);
    expect(home && isNavActive("/app/wallet", home)).toBe(false);
    expect(world && isNavActive("/app/map", world)).toBe(true);
  });
});
