type EmblemProps = {
  className?: string;
  uid?: string;
};

export function Emblem({ className = "", uid = "em" }: EmblemProps) {
  return (
    <svg className={className} viewBox="0 0 64 72" width="36" height="40" role="img" aria-label="AIVVA mark">
      <defs>
        <linearGradient id={`${uid}-up`} x1="0" y1="1" x2="1" y2="0">
          <stop offset="0%" stopColor="#22e3d0" />
          <stop offset="100%" stopColor="#4d8fff" />
        </linearGradient>
        <linearGradient id={`${uid}-dn`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#b44dff" />
          <stop offset="100%" stopColor="#ff4dcf" />
        </linearGradient>
        <filter id={`${uid}-glow`} x="-30%" y="-30%" width="160%" height="160%">
          <feGaussianBlur stdDeviation="1.6" result="blur" />
          <feMerge>
            <feMergeNode in="blur" />
            <feMergeNode in="SourceGraphic" />
          </feMerge>
        </filter>
      </defs>
      <g filter={`url(#${uid}-glow)`}>
        <polygon points="32,2 58,28 32,28 6,28" fill={`url(#${uid}-up)`} />
        <polygon points="32,70 6,44 32,44 58,44" fill={`url(#${uid}-dn)`} />
        <rect x="28" y="30" width="8" height="12" rx="1.2" fill="#7ecbff" />
        <circle cx="32" cy="24" r="3.4" fill="#4d8fff" />
        <circle cx="32" cy="24" r="1.5" fill="#ffffff" />
      </g>
    </svg>
  );
}
