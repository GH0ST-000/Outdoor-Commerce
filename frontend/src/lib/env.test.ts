import { describe, expect, it, vi } from "vitest";
import {
  alignLoopbackHost,
  getApiBaseUrl,
  getBackendOrigin,
  getPublicEnv,
  getServerEnv,
  resolveServerApiBaseUrls,
  toLoopbackIpv4,
} from "@/lib/env";

describe("environment separation", () => {
  it("validates public and server env schemas separately", () => {
    const publicEnv = getPublicEnv();
    const serverEnv = getServerEnv();

    expect("BACKEND_INTERNAL_URL" in publicEnv).toBe(false);
    expect(serverEnv).toHaveProperty("NODE_ENV");
  });

  it("only allows NEXT_PUBLIC_ keys in the public schema shape", () => {
    for (const key of Object.keys(getPublicEnv())) {
      expect(key.startsWith("NEXT_PUBLIC_")).toBe(true);
    }
  });

  it("does not expose BACKEND_INTERNAL_URL through public env", () => {
    expect(Object.keys(getPublicEnv())).not.toContain("BACKEND_INTERNAL_URL");
  });

  it("tries the compose backend URL before the public API URL", () => {
    vi.stubEnv("BACKEND_INTERNAL_URL", "http://backend:8000");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://localhost:8000/api");
    expect(resolveServerApiBaseUrls()).toEqual([
      "http://backend:8000/api",
      "http://127.0.0.1:8000/api",
    ]);
    vi.unstubAllEnvs();
  });

  it("rewrites localhost API URLs to IPv4 loopback for Node", () => {
    expect(toLoopbackIpv4("http://localhost:8000/api")).toBe(
      "http://127.0.0.1:8000/api",
    );
  });

  it("aligns loopback hosts when the API is on a different origin", () => {
    expect(alignLoopbackHost("http://127.0.0.1:8000/api", "localhost")).toBe(
      "http://localhost:8000/api",
    );
    expect(alignLoopbackHost("http://localhost:8000/api", "127.0.0.1")).toBe(
      "http://127.0.0.1:8000/api",
    );
  });

  it("uses same-origin API and CSRF paths in the browser on loopback", () => {
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://127.0.0.1:8000/api");
    vi.stubEnv("NEXT_PUBLIC_BACKEND_URL", "http://127.0.0.1:8000");
    expect(getApiBaseUrl()).toBe("/api");
    expect(getBackendOrigin()).toBe("");
    vi.unstubAllEnvs();
  });
});
