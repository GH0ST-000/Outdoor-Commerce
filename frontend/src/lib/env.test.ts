import { describe, expect, it } from "vitest";
import { getPublicEnv, getServerEnv } from "@/lib/env";

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
});
