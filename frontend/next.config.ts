import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // next dev --hostname 0.0.0.0 treats 127.0.0.1 as a different origin and
  // 403s /_next chunks. Cursor Preview and local Chrome both need this.
  allowedDevOrigins: ["127.0.0.1", "localhost", "[::1]", "**.cursor.com", "**.cursor.sh"],
  async rewrites() {
    const api =
      process.env.API_PROXY_URL ||
      (process.env.VERCEL ? "https://aivva-backend.onrender.com" : "http://127.0.0.1:48100");
    return [
      {
        source: "/backend/:path*",
        destination: `${api}/:path*`,
      },
    ];
  },
};

export default nextConfig;
