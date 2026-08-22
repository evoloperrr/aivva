import type { ActivityItem } from "@/lib/api";

const tone: Record<string, string> = {
  birth: "text-amber",
  goal: "text-violet",
  plan: "text-violet",
  travel: "text-teal",
  arrive: "text-teal",
  research: "text-amber",
  opportunity: "text-amber",
  social: "text-coral",
  create: "text-violet",
  negotiate: "text-amber",
  earn: "text-teal",
  learn: "text-violet",
  blocked: "text-destructive",
  approval: "text-amber",
  status: "text-muted-foreground",
};

export function ActivityFeed({
  items,
  empty,
}: {
  items: ActivityItem[];
  empty: string;
}) {
  if (items.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-white/15 px-5 py-10 text-center text-sm text-muted-foreground">
        {empty}
      </div>
    );
  }

  return (
    <ol className="space-y-0">
      {items.map((item) => (
        <li key={item.id} className="grid grid-cols-[72px_1fr] gap-3 border-b border-white/5 py-3 last:border-0">
          <time className="pt-0.5 font-mono text-xs text-teal">{item.clock}</time>
          <div>
            <p className={`text-sm leading-6 ${tone[item.kind] ?? "text-foreground"}`}>{item.headline}</p>
            {item.body && <p className="mt-1 text-xs leading-5 text-muted-foreground">{item.body}</p>}
          </div>
        </li>
      ))}
    </ol>
  );
}
