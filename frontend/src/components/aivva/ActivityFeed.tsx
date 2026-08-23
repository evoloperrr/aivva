import type { ActivityItem } from "@/lib/api";
import { formatRelative } from "@/lib/format";

const tone: Record<string, string> = {
  birth: "text-amber",
  goal: "text-violet",
  plan: "text-violet",
  travel: "text-teal",
  arrive: "text-teal",
  research: "text-blue",
  opportunity: "text-amber",
  social: "text-magenta",
  create: "text-violet",
  negotiate: "text-orange",
  earn: "text-teal",
  learn: "text-violet",
  blocked: "text-destructive",
  approval: "text-amber",
  economy: "text-orange",
  status: "text-muted-foreground",
};

export function ActivityFeed({
  items,
  empty,
  limit,
}: {
  items: ActivityItem[];
  empty: string;
  limit?: number;
}) {
  const visible = limit ? items.slice(0, limit) : items;

  if (visible.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-white/15 px-5 py-10 text-center text-sm text-muted-foreground">
        {empty}
      </div>
    );
  }

  return (
    <ol className="space-y-0">
      {visible.map((item) => (
        <li key={item.id} className="grid grid-cols-[72px_1fr] gap-3 border-b border-white/5 py-3 last:border-0">
          <time className="pt-0.5 font-mono text-xs text-teal">{item.clock}</time>
          <div>
            <p className={`text-sm leading-6 ${tone[item.kind] ?? "text-foreground"}`}>{item.headline}</p>
            {item.body && <p className="mt-1 text-xs leading-5 text-muted-foreground">{item.body}</p>}
            <p className="mt-1 text-[11px] uppercase tracking-[0.14em] text-muted-foreground/70">
              {item.kind} · {formatRelative(item.created_at)}
            </p>
          </div>
        </li>
      ))}
    </ol>
  );
}
