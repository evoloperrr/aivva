export type NavGroup = "presence" | "social" | "economy" | "identity";

export type NavItem = {
  href: string;
  label: string;
  icon: string;
  group: NavGroup;
  admin?: boolean;
  match?: "exact" | "prefix";
};

export const NAV_GROUPS: Array<{ id: NavGroup; label: string }> = [
  { id: "presence", label: "Presence" },
  { id: "social", label: "Social" },
  { id: "economy", label: "Economy" },
  { id: "identity", label: "Identity" },
];

export const NAV_ITEMS: NavItem[] = [
  { href: "/app", label: "Home", icon: "home", group: "presence", match: "exact" },
  { href: "/app/aivva", label: "My AIVVA", icon: "sparkles", group: "presence" },
  { href: "/app/world", label: "World", icon: "globe", group: "presence" },
  { href: "/app/activity", label: "Activity", icon: "activity", group: "presence" },
  { href: "/app/messages", label: "Messages", icon: "messages", group: "social" },
  { href: "/app/relationships", label: "Relationships", icon: "relationships", group: "social" },
  { href: "/app/marketplace", label: "Marketplace", icon: "marketplace", group: "economy" },
  { href: "/app/jobs", label: "Jobs", icon: "jobs", group: "economy" },
  { href: "/app/business", label: "Business", icon: "business", group: "economy" },
  { href: "/app/wallet", label: "Wallet", icon: "wallet", group: "economy" },
  { href: "/app/trust", label: "Trust", icon: "trust", group: "identity" },
  { href: "/app/memory", label: "Memory", icon: "memory", group: "identity" },
  { href: "/app/settings", label: "Settings", icon: "settings", group: "identity" },
  { href: "/app/lab", label: "Genesis Lab", icon: "lab", group: "identity", admin: true },
];

export const REQUIRED_OWNER_NAV = [
  "Home",
  "My AIVVA",
  "World",
  "Activity",
  "Messages",
  "Relationships",
  "Marketplace",
  "Jobs",
  "Business",
  "Wallet",
  "Trust",
  "Memory",
  "Settings",
] as const;

export function visibleNav(isAdmin: boolean) {
  return NAV_ITEMS.filter((item) => !item.admin || isAdmin);
}

export function isNavActive(pathname: string, item: NavItem) {
  if (item.href === "/app/world" && (pathname === "/app/world" || pathname === "/app/map")) {
    return true;
  }
  if (item.match === "exact") return pathname === item.href;
  return pathname === item.href || pathname.startsWith(`${item.href}/`);
}
