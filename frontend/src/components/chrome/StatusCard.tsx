import { cn } from "@/lib/utils";

export function StatusCard({
  label,
  value,
  hint,
  tone = "default",
}: {
  label: string;
  value: React.ReactNode;
  hint?: string;
  tone?: "default" | "teal" | "violet" | "amber" | "magenta" | "orange" | "blue";
}) {
  const tones = {
    default: "text-foreground",
    teal: "text-teal",
    violet: "text-violet",
    amber: "text-amber",
    magenta: "text-magenta",
    orange: "text-orange",
    blue: "text-blue",
  };

  return (
    <div className="glass-panel rounded-2xl px-4 py-4">
      <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
      <p className={cn("mt-2 font-heading text-2xl tracking-tight", tones[tone])}>{value}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}
