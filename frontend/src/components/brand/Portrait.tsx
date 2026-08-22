function hashHue(seed: string) {
  let n = 0;
  for (let i = 0; i < seed.length; i++) n = (n * 31 + seed.charCodeAt(i)) % 360;
  return n;
}

export function Portrait({
  name,
  seed,
  size = 56,
}: {
  name: string;
  seed?: string;
  size?: number;
}) {
  const hue = hashHue(seed || name);
  const initial = name.slice(0, 1).toUpperCase();

  return (
    <div
      className="relative shrink-0 overflow-hidden rounded-full shadow-[0_0_0_1px_rgba(244,239,228,0.12)]"
      style={{ width: size, height: size }}
      aria-hidden
    >
      <div
        className="absolute inset-0"
        style={{
          background: `conic-gradient(from 210deg, hsl(${hue} 70% 58%), hsl(${(hue + 40) % 360} 80% 62%), hsl(${(hue + 80) % 360} 55% 48%), hsl(${hue} 70% 58%))`,
        }}
      />
      <div className="absolute inset-[3px] rounded-full bg-[#0b1020]/35 backdrop-blur-[1px]" />
      <div className="absolute inset-0 grid place-items-center font-heading text-white" style={{ fontSize: size * 0.42 }}>
        {initial}
      </div>
    </div>
  );
}
