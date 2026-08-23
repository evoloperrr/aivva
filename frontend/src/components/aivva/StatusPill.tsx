import { compactStatus, statusDotClass } from "@/lib/status";
import { cn } from "@/lib/utils";

export function StatusPill({
  status,
  label,
  className,
}: {
  status?: string | null;
  label?: string | null;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.16em]",
        className,
      )}
    >
      <span className={cn("size-1.5 rounded-full", statusDotClass(status))} />
      {compactStatus(label ?? status)}
    </span>
  );
}
