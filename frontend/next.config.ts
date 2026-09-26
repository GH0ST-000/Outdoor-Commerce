import type { NextConfig } from "next";
import path from "node:path";

const backendOrigin = (
  process.env.BACKEND_INTERNAL_URL || "http://127.0.0.1:8000"
).replace(/\/$/, "");

const mapStyle = process.env.NEXT_PUBLIC_MAP_STYLE_URL;
let mapOrigin = "https://tiles.openfreemap.org";
if (mapStyle) {
  try {
    mapOrigin = new URL(mapStyle).origin;
  } catch {
    mapOrigin = "https://tiles.openfreemap.org";
  }
}

const isDev = process.env.NODE_ENV !== "production";

const publicBackend = (
  process.env.NEXT_PUBLIC_BACKEND_URL ||
  process.env.NEXT_PUBLIC_API_URL ||
  "http://localhost:8000"
).replace(/\/$/, "");
let mediaOrigin = "http://localhost:8000";
try {
  mediaOrigin = new URL(publicBackend).origin;
} catch {
  mediaOrigin = "http://localhost:8000";
}
const mediaOrigins = new Set<string>([mediaOrigin]);
if (isDev) {
  mediaOrigins.add("http://localhost:8000");
  mediaOrigins.add("http://127.0.0.1:8000");
}

const contentSecurityPolicy = [
  "default-src 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  "frame-ancestors 'self'",
  // React's development runtime uses eval() to rebuild call stacks. Production does not.
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`,
  "style-src 'self' 'unsafe-inline'",
  "font-src 'self' data:",
  `img-src 'self' data: blob: https://images.unsplash.com ${mapOrigin} ${[...mediaOrigins].join(" ")}`,
  `connect-src 'self' ${mapOrigin} https://tiles.openfreemap.org ${[...mediaOrigins].join(" ")}`,
  "worker-src 'self' blob:",
  "child-src 'self' blob:",
].join("; ");

const nextConfig: NextConfig = {
  turbopack: {
    root: path.join(__dirname),
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "Content-Security-Policy", value: contentSecurityPolicy },
          {
            key: "Permissions-Policy",
            value: "geolocation=(self), camera=(), microphone=()",
          },
        ],
      },
    ];
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
