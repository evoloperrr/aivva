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
