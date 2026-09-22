import type { NextConfig } from "next";
import path from "node:path";

const backendOrigin = (
  process.env.BACKEND_INTERNAL_URL || "http://127.0.0.1:8000"
).replace(/\/$/, "");

const nextConfig: NextConfig = {
  turbopack: {
    root: path.join(__dirname),
  },
  async rewrites() {
    return [
      {
        source: "/sanctum/:path*",
        destination: `${backendOrigin}/sanctum/:path*`,
      },
      {
        source: "/api/v1/:path*",
        destination: `${backendOrigin}/api/v1/:path*`,
      },
    ];
  },
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "images.unsplash.com",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        pathname: "/storage/media/**",
      },
      {
        protocol: "http",
        hostname: "127.0.0.1",
        pathname: "/storage/media/**",
      },
    ],
  },
};

export default nextConfig;
