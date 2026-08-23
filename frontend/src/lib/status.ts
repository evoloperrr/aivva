const ACTIVE = new Set([
  "IDLE",
  "THINKING",
  "PLANNING",
  "TRAVELING",
  "WORKING",
  "CREATING",
  "SOCIALIZING",
  "NEGOTIATING",
  "WAITING_APPROVAL",
  "WAITING_DELIVERY",
  "LEARNING",
]);

export function isActiveStatus(status: string | null | undefined) {
  return Boolean(status && ACTIVE.has(status));
}

export function statusTone(status: string | null | undefined) {
  if (!status) return "muted";
  if (status === "ERROR") return "danger";
  if (status === "PAUSED" || status === "DORMANT") return "muted";
  if (status === "WAITING_APPROVAL" || status === "WAITING_DELIVERY") return "amber";
  if (status === "SOCIALIZING") return "magenta";
  if (status === "CREATING" || status === "THINKING" || status === "PLANNING") return "violet";
  if (status === "NEGOTIATING" || status === "WORKING") return "orange";
  if (ACTIVE.has(status)) return "cyan";
  return "muted";
}

export function statusDotClass(status: string | null | undefined) {
  switch (statusTone(status)) {
    case "cyan":
      return "bg-teal shadow-[0_0_10px_rgba(30,224,176,0.7)]";
    case "violet":
      return "bg-violet shadow-[0_0_10px_rgba(139,124,255,0.7)]";
    case "magenta":
      return "bg-magenta shadow-[0_0_10px_rgba(232,93,255,0.7)]";
    case "orange":
      return "bg-orange shadow-[0_0_10px_rgba(255,138,61,0.7)]";
    case "amber":
      return "bg-amber shadow-[0_0_10px_rgba(244,185,66,0.7)]";
    case "danger":
      return "bg-coral shadow-[0_0_10px_rgba(255,122,107,0.7)]";
    default:
      return "bg-muted-foreground/70";
  }
}

export function compactStatus(label: string | null | undefined) {
  if (!label) return "UNKNOWN";
  return label.toUpperCase();
}
