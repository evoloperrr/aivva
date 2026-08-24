export type LabelBox = { x: number; y: number; w: number; h: number };

export type AtlasLabel = LabelBox & {
  id: string;
  kind: "actor" | "place" | "region";
  priority: number;
};

export function boxesOverlap(a: LabelBox, b: LabelBox, pad = 6): boolean {
  return !(a.x + a.w + pad < b.x || b.x + b.w + pad < a.x || a.y + a.h + pad < b.y || b.y + b.h + pad < a.y);
}

/**
 * Hide overlapping atlas text. Lower priority wins (0 is kept first).
 * Region labels drop out once the map is close enough to read streets and POIs.
 */
export function visibleLabelIds(labels: AtlasLabel[], zoom: number): Set<string> {
  const kept = new Set<string>();
  const taken: LabelBox[] = [];
  const ranked = [...labels].sort((a, b) => a.priority - b.priority || a.id.localeCompare(b.id));

  for (const label of ranked) {
    if (label.kind === "region" && zoom >= 15.05) continue;
    if (label.kind === "place" && zoom < 14.85) continue;
    if (taken.some((box) => boxesOverlap(label, box))) continue;
    taken.push(label);
    kept.add(label.id);
  }

  return kept;
}
