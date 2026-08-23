import { cn } from "@/lib/utils";

function hashHue(seed: string) {
  let n = 0;
  for (let i = 0; i < seed.length; i++) n = (n * 31 + seed.charCodeAt(i)) % 360;
  return n;
}

export function Portrait({
  name,
  seed,
  size = 56,
  glow = false,
}: {
  name: string;
  seed?: string;
  size?: number;
  glow?: boolean;
}) {
  const hue = hashHue(seed || name);
  const initial = name.slice(0, 1).toUpperCase();

  return (
    <div className="relative shrink-0" style={{ width: size, height: size }} aria-hidden>
      {glow && (
        <div
          className="glow-ring pointer-events-none absolute -inset-2 rounded-full"
          style={{
            background: `conic-gradient(from 180deg, hsl(${hue} 80% 62%), hsl(${(hue + 80) % 360} 70% 58%), hsl(${(hue + 160) % 360} 80% 60%), hsl(${hue} 80% 62%))`,
            filter: "blur(8px)",
            opacity: 0.55,
          }}
        />
      )}
      <div
        className={cn(
          "relative overflow-hidden rounded-full shadow-[0_0_0_1px_rgba(238,244,255,0.16)]",
        )}
        style={{ width: size, height: size }}
      >
        <div
          className="absolute inset-0"
          style={{
            background: `conic-gradient(from 210deg, hsl(${hue} 70% 58%), hsl(${(hue + 40) % 360} 80% 62%), hsl(${(hue + 80) % 360} 55% 48%), hsl(${hue} 70% 58%))`,
          }}
        />
        <div className="absolute inset-[3px] rounded-full bg-[#05070f]/40 backdrop-blur-[1px]" />
        <div
          className="absolute inset-0 grid place-items-center font-heading text-white"
          style={{ fontSize: size * 0.42 }}
        >
          {initial}
        </div>
      </div>
    </div>
  );
}
