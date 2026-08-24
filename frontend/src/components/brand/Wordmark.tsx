type WordmarkProps = {
  className?: string;
  uid?: string;
  size?: "nav" | "hero";
};

export function Wordmark({ className = "", uid = "wm", size = "nav" }: WordmarkProps) {
  const height = size === "hero" ? 118 : 28;
  const width = size === "hero" ? 520 : 124;

  return (
    <svg
      className={className}
      viewBox="0 0 536 120"
      width={width}
      height={height}
      role="img"
      aria-label="AIVVA"
    >
      <defs>
        <linearGradient id={`${uid}-a`} x1="0" y1="1" x2="0.4" y2="0">
          <stop offset="0%" stopColor="#ff8a3d" />
          <stop offset="100%" stopColor="#ffe36a" />
        </linearGradient>
        <linearGradient id={`${uid}-i`} x1="0" y1="1" x2="0" y2="0">
          <stop offset="0%" stopColor="#1ad4ff" />
          <stop offset="100%" stopColor="#4d8fff" />
        </linearGradient>
        <linearGradient id={`${uid}-v1`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#b44dff" />
          <stop offset="100%" stopColor="#ff4dcf" />
        </linearGradient>
        <linearGradient id={`${uid}-v2`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#b8ff4a" />
          <stop offset="100%" stopColor="#22e3d0" />
        </linearGradient>
        <linearGradient id={`${uid}-a2`} x1="0" y1="1" x2="1" y2="0">
          <stop offset="0%" stopColor="#22e3d0" />
          <stop offset="55%" stopColor="#4d8fff" />
          <stop offset="100%" stopColor="#e85dff" />
        </linearGradient>
        <filter id={`${uid}-glow`} x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur stdDeviation="3.2" result="blur" />
          <feMerge>
            <feMergeNode in="blur" />
            <feMergeNode in="SourceGraphic" />
          </feMerge>
        </filter>
      </defs>
      <g filter={`url(#${uid}-glow)`}>
        <polygon points="10,112 62,8 114,112" fill={`url(#${uid}-a)`} />
        <polygon points="62,34 86,94 38,94" fill="#fff6d2" opacity="0.22" />
        <rect x="140" y="38" width="20" height="74" rx="3" fill={`url(#${uid}-i)`} />
        <circle cx="150" cy="18" r="10" fill="#5aa7ff" />
        <circle cx="150" cy="18" r="5" fill="#eef7ff" />
        <polygon points="184,8 236,112 288,8" fill={`url(#${uid}-v1)`} />
        <polygon points="236,86 212,30 260,30" fill="#ffe6ff" opacity="0.18" />
        <polygon points="308,8 360,112 412,8" fill={`url(#${uid}-v2)`} />
        <polygon points="360,86 336,30 384,30" fill="#f4ffe8" opacity="0.16" />
        <polygon points="424,112 476,8 528,112" fill={`url(#${uid}-a2)`} />
        <polygon points="476,34 500,94 452,94" fill="#e8f4ff" opacity="0.2" />
      </g>
    </svg>
  );
}
