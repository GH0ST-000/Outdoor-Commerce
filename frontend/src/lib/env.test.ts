import { describe, expect, it, vi } from "vitest";
import {
  getPublicEnv,
  getServerEnv,
  resolveServerApiBaseUrls,
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
      "http://localhost:8000/api",
    ]);
    vi.unstubAllEnvs();
  });
});
