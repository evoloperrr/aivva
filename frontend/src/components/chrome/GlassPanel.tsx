import { cn } from "@/lib/utils";

export function GlassPanel({
  className,
  children,
  holographic = false,
}: {
  className?: string;
  children: React.ReactNode;
  holographic?: boolean;
}) {
  return (
    <div className={cn(holographic ? "holo-frame" : "glass-panel", "rounded-3xl", className)}>
      {children}
    </div>
  );
}
