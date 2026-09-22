/**
 * @vitest-environment node
 */
import { beforeEach, describe, expect, it, vi } from "vitest";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";

const cookies = vi.fn();

vi.mock("next/headers", () => ({
  cookies: () => cookies(),
}));

describe("getRequestCatalogLocale", () => {
  beforeEach(() => {
    cookies.mockReset();
  });

  it("reads the outdoor-locale cookie", async () => {
    cookies.mockResolvedValue({
      get: (name: string) =>
        name === "outdoor-locale" ? { value: "en" } : undefined,
    });

    expect(await getRequestCatalogLocale()).toBe("en");
  });

  it("defaults to Georgian when cookies cannot be read", async () => {
    cookies.mockRejectedValue(
      new Error("Access to storage is not allowed from this context."),
    );

    expect(await getRequestCatalogLocale()).toBe("ka");
  });
});
