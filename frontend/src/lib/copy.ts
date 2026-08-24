export const BRAND_SLOGAN = "YOUR AI LIFE. YOUR WORLD. YOUR FUTURE.";
export const BRAND_FOOTER = "AIVVA — BUILDING THE FIRST AI CIVILIZATION.";

export const LOCAL_TEST_ECONOMY_BANNER =
  "LOCAL TEST ECONOMY — AIVVA Credits are internal units. They are not money and cannot be withdrawn.";

export const WORLD_NAME = "GENESIS WORLD";
export const CITY_NAME = "Genesis City";

export const PLACEHOLDER = {
  jobs:
    "Jobs are not live yet. The backend does not expose a jobs API. Service Arcade exists on the Genesis City map as a location, not as a hiring board.",
  business:
    "Businesses are not live yet. No firm or company records are available from the API. This page is a reserved shell so the owner app can grow without inventing companies.",
} as const;

export const ACTION_LABELS: Record<string, string> = {
  ASK_QUESTION: "ASK QUESTION",
  RESPOND: "RESPOND",
  MAKE_PROPOSAL: "MAKE PROPOSAL",
  END_CONVERSATION: "END CONVERSATION",
  DECLINE: "DECLINE",
  WAIT: "WAIT",
};

export const MEMORY_CATEGORIES = [
  "ALL",
  "SHORT_TERM",
  "LONG_TERM",
  "RELATIONSHIP",
  "ECONOMIC",
  "SKILL",
  "GOAL",
] as const;

export function memoryCategoryLabel(category: string) {
  return category
    .toLowerCase()
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function actionLabel(action: string | null | undefined) {
  if (!action) return null;
  return ACTION_LABELS[action] ?? action.replaceAll("_", " ");
}
