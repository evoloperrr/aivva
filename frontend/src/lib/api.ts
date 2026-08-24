const API = process.env.NEXT_PUBLIC_API_URL || "/backend";

export type User = {
  id: number;
  name: string;
  email: string;
  is_admin?: boolean;
};

export type Aivva = {
  id: string;
  name: string;
  slug: string;
  status: string;
  status_label: string;
  world_clock: string;
  energy: number;
  life_points: number;
  activated_at: string | null;
  last_activity_at: string | null;
  next_scheduled_at: string | null;
  profile: {
    personality: string | null;
    skills: string[];
    interests: string[];
    work_preferences?: string[];
    risk_tolerance: string;
    bio: string | null;
    portrait_seed: string;
  } | null;
  permissions: {
    autonomy_level: number;
    max_per_transaction: number;
    daily_spend_limit: number;
    can_travel: boolean;
    can_socialize: boolean;
    can_create: boolean;
    can_transact: boolean;
    autonomous_interaction: boolean;
    require_approval_above: number;
  } | null;
  goal: {
    id: string;
    raw_direction: string;
    goal_type: string;
    structured: Record<string, unknown>;
    status: string;
    progress: number;
  } | null;
  plan: {
    id: string;
    steps: Array<{ index: number; type: string; title: string; status: string }>;
    current_step: number;
    status: string;
  } | null;
  location: MapPlace | null;
  home: MapPlace | null;
  movement: {
    traveling: boolean;
    from: MapPlace | null;
    to: MapPlace | null;
    progress: number;
    x?: number;
    y?: number;
  };
  wallet: {
    available: number;
    held: number;
    currency: string;
    earned_today: number;
    spent_today: number;
  };
  trust: {
    economic: number;
    social: number;
    skills: Record<string, number> | null;
    overall: number;
  } | null;
  budgets: {
    actions_used: number;
    actions_limit: number;
    tokens_used: number;
    spend_used: number;
    spend_limit: number;
  };
};

export type MapPlace = {
  id: number;
  name: string;
  type: string;
  x: number;
  y: number;
  capacity: number;
  services: string[];
  description: string | null;
  district: { id: number; name: string; slug: string; color: string };
  city?: string;
};

export type ActivityItem = {
  id: string;
  clock: string;
  kind: string;
  headline: string;
  body: string | null;
  meta: Record<string, unknown> | null;
  created_at: string;
};

function token(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("aivva_token");
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  const t = token();
  if (t) headers.set("Authorization", `Bearer ${t}`);

  let response: Response;
  try {
    response = await fetch(`${API}${path}`, { ...init, headers });
  } catch {
    throw new Error("The AIVVA backend is offline. The city cannot update until it returns.");
  }
  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message =
      json.message ||
      json.reason ||
      (json.errors ? Object.values(json.errors).flat().join(" ") : null) ||
      `Request failed (${response.status})`;
    throw new Error(message);
  }
  return json as T;
}

export const auth = {
  register: (body: { name: string; email: string; password: string; password_confirmation: string }) =>
    api<{ token: string; user: User }>("/api/auth/register", { method: "POST", body: JSON.stringify(body) }),
  login: (body: { email: string; password: string }) =>
    api<{ token: string; user: User }>("/api/auth/login", { method: "POST", body: JSON.stringify(body) }),
  me: () => api<{ user: User }>("/api/auth/me"),
  logout: () => api<{ ok: boolean }>("/api/auth/logout", { method: "POST" }),
};

export const aivvas = {
  list: () => api<{ data: Aivva[] }>("/api/aivvas"),
  create: (body: Record<string, unknown>) =>
    api<{ data: Aivva }>("/api/aivvas", { method: "POST", body: JSON.stringify(body) }),
  get: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}`),
  activate: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}/activate`, { method: "POST" }),
  pause: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}/pause`, { method: "POST" }),
  recall: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}/recall`, { method: "POST" }),
  stopSpending: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}/stop-spending`, { method: "POST" }),
  cancelGoal: (id: string) => api<{ data: Aivva }>(`/api/aivvas/${id}/cancel-goal`, { method: "POST" }),
  permissions: (id: string, body: Record<string, unknown>) =>
    api<{ data: Aivva }>(`/api/aivvas/${id}/permissions`, { method: "PATCH", body: JSON.stringify(body) }),
  tick: (id: string) => api<{ tick: Record<string, unknown>; data: Aivva }>(`/api/aivvas/${id}/tick`, { method: "POST" }),
  live: (id: string) =>
    api<{ tick: Record<string, unknown>; data: Aivva; activity: ActivityItem[] }>(`/api/aivvas/${id}/live`),
  interpret: (id: string, direction: string) =>
    api<{ goal_id: string; interpretation: Interpretation }>(`/api/aivvas/${id}/direction`, {
      method: "POST",
      body: JSON.stringify({ direction }),
    }),
  confirm: (id: string, goal_id: string) =>
    api<{ data: Aivva }>(`/api/aivvas/${id}/direction/confirm`, {
      method: "POST",
      body: JSON.stringify({ goal_id }),
    }),
  activity: (id: string) => api<{ data: ActivityItem[] }>(`/api/aivvas/${id}/activity`),
  memories: (id: string) => api<{ data: MemoryRecord[] }>(`/api/aivvas/${id}/memories`),
  messages: (id: string) => api<{ data: MessageRecord[] }>(`/api/aivvas/${id}/messages`),
  conversations: (id: string) => api<{ data: ConversationRecord[] }>(`/api/aivvas/${id}/conversations`),
  relationships: (id: string) => api<{ data: RelationRecord[] }>(`/api/aivvas/${id}/relationships`),
  works: (id: string) => api<{ data: WorkRecord[] }>(`/api/aivvas/${id}/works`),
  wallet: (id: string) => api<{ wallet: WalletRecord; orders: OrderRecord[] }>(`/api/aivvas/${id}/wallet`),
  chat: (id: string) => api<{ data: ChatMessage[] }>(`/api/aivvas/${id}/chat`),
  sendChat: (id: string, message: string) =>
    api<{ reply: ChatMessage; data: ChatMessage[] }>(`/api/aivvas/${id}/chat`, {
      method: "POST",
      body: JSON.stringify({ message }),
    }),
  createRequest: (id: string, body: Record<string, unknown>) =>
    api<{ data: unknown }>(`/api/aivvas/${id}/marketplace/requests`, { method: "POST", body: JSON.stringify(body) }),
  createListing: (id: string, body: Record<string, unknown>) =>
    api<{ data: unknown }>(`/api/aivvas/${id}/marketplace/listings`, { method: "POST", body: JSON.stringify(body) }),
};

export type Interpretation = {
  allowed: boolean;
  reason: string | null;
  goal: {
    goal_type: string;
    ethical_constraint: string[];
    risk_level: string;
    priority: string;
    time_horizon: string;
  };
  estimated_cost: number;
  permissions_needed: string[];
};

export type ChatMessage = {
  id: string;
  role: "owner" | "aivva" | "system";
  body: string;
  intent: string;
  created_at: string;
};

export type MemoryRecord = {
  id: string;
  category: string;
  content: string;
  importance: number;
  created_at: string;
};

export type MessageRecord = {
  id: string;
  intent: string;
  payload: Record<string, unknown>;
  conversation_id?: string | null;
  action?: string | null;
  natural_language?: string | null;
  turn_number?: number | null;
  from?: { id: string; name: string };
  to?: { id: string; name: string };
  created_at: string;
};

export type ConversationRecord = {
  id: string;
  status: string;
  turn_count: number;
  max_turns: number;
  seed_event: string | null;
  place: string | null;
  participants: Array<{ id: string; name: string }>;
  messages: Array<{
    id: string;
    from?: { id: string; name: string } | null;
    action?: string | null;
    text: string | null;
    turn: number | null;
    created_at: string | null;
  }>;
};

export type RelationRecord = {
  id: string;
  type: string;
  strength: number;
  trust: number;
  other?: { id: string; name: string; status: string };
};

export type WorkRecord = {
  id: string;
  kind: string;
  title: string;
  body: Record<string, unknown>;
  tool_or_model: string;
  created_at: string;
};

export type WalletRecord = {
  available_balance: number;
  held_balance: number;
};

export type OrderRecord = {
  id: string;
  amount: number;
  status: string;
  buyer_aivva_id: string;
  seller_aivva_id: string;
  created_at: string;
};

export const world = {
  map: () => api<WorldMap>("/api/world/map"),
  locations: () => api<{ data: MapPlace[] }>("/api/world/locations"),
  marketplace: () => api<Marketplace>("/api/marketplace"),
  notifications: () => api<{ data: Notice[] }>("/api/notifications"),
};

export type WorldMap = {
  city: { id: number; name: string; slug: string; description: string } | null;
  districts: Array<{
    id: number;
    name: string;
    slug: string;
    color: string;
    theme: string;
    description: string;
    polygon: [number, number][];
    locations: MapPlace[];
  }>;
  aivvas: Array<{
    id: string;
    name: string;
    status: string;
    is_platform: boolean;
    portrait_seed: string | null;
    location: MapPlace | null;
    public_activity: string;
  }>;
};

export type Marketplace = {
  requests: Array<{
    id: string;
    title: string;
    category: string;
    budget_min: number;
    budget_max: number;
    description: string;
    status: string;
    buyer?: { id: string; name: string };
  }>;
  listings: Array<{
    id: string;
    title: string;
    category: string;
    price: number;
    description: string;
    status: string;
    seller?: { id: string; name: string };
  }>;
};

export type Notice = {
  id: string;
  type: string;
  title: string;
  body: string | null;
  created_at: string;
  read_at?: string | null;
};

export type AdminHealth = {
  active_aivvas: number;
  paused: number;
  open_requests: number;
  settled_orders: number;
  ledger: {
    balanced?: boolean;
    issues?: string[];
    [key: string]: unknown;
  };
  recent_ai: Array<{
    id: string;
    provider?: string | null;
    model?: string | null;
    purpose?: string | null;
    status?: string | null;
    latency_ms?: number | null;
    created_at?: string | null;
  }>;
  recent_ledger: Array<{
    id: string;
    type?: string | null;
    description?: string | null;
    reversed?: boolean;
    created_at?: string | null;
  }>;
};

export const admin = {
  health: () => api<AdminHealth>("/api/admin/health"),
};
