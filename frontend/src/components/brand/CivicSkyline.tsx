export function CivicSkyline() {
  const towers: Array<[number, number, number, number]> = [
    [20, 118, 48, 80],
    [78, 86, 70, 112],
    [160, 128, 36, 70],
    [210, 72, 58, 126],
    [280, 108, 42, 90],
    [338, 54, 80, 144],
    [430, 96, 50, 102],
    [494, 70, 64, 128],
    [572, 120, 40, 78],
    [628, 40, 92, 158],
    [736, 88, 54, 110],
    [802, 62, 76, 136],
    [892, 110, 44, 88],
    [948, 48, 88, 150],
    [1050, 92, 50, 106],
    [1114, 68, 62, 130],
    [1190, 124, 38, 74],
    [1240, 80, 72, 118],
    [1324, 104, 46, 94],
    [1382, 132, 40, 66],
  ];

  return (
    <svg className="civic-skyline" viewBox="0 0 1440 280" preserveAspectRatio="none" aria-hidden>
      <defs>
        <linearGradient id="civic-build" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#24366a" />
          <stop offset="100%" stopColor="#070b18" />
        </linearGradient>
        <linearGradient id="civic-water" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#102044" stopOpacity="0.55" />
          <stop offset="100%" stopColor="#03050c" stopOpacity="0" />
        </linearGradient>
      </defs>
      {towers.map(([x, y, w, h], i) => (
        <rect key={i} x={x} y={y} width={w} height={h} fill="url(#civic-build)" />
      ))}
      <rect x="0" y="198" width="1440" height="82" fill="url(#civic-water)" />
      {[
        [96, 102, "#22e3d0"],
        [248, 92, "#4d8fff"],
        [360, 78, "#e85dff"],
        [656, 62, "#ffe36a"],
        [830, 86, "#22e3d0"],
        [980, 72, "#ff4dcf"],
        [1140, 90, "#b8ff4a"],
        [1270, 104, "#4d8fff"],
      ].map(([x, y, color], i) => (
        <g key={`win-${i}`}>
          <rect x={Number(x)} y={Number(y)} width="4" height="8" fill={String(color)} opacity="0.92" />
          <rect x={Number(x) + 10} y={Number(y) + 14} width="4" height="8" fill={String(color)} opacity="0.55" />
          <rect x={Number(x) + 20} y={Number(y) + 6} width="4" height="8" fill={String(color)} opacity="0.7" />
        </g>
      ))}
    </svg>
  );
}
