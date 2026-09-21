import type { NextConfig } from "next";
import path from "node:path";

const nextConfig: NextConfig = {
  turbopack: {
    root: path.join(__dirname),
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
