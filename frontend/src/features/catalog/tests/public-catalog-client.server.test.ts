/**
 * @vitest-environment node
 */
import { afterEach, describe, expect, it, vi } from "vitest";
import { getPublicCategory } from "@/features/catalog/api/public-catalog-client";
import { ApiClientError } from "@/lib/api-client";

describe("public catalog server fetch", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
  });

  it("falls back to the public API when the compose hostname cannot be resolved", async () => {
    vi.stubEnv("BACKEND_INTERNAL_URL", "http://backend:8000");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://localhost:8000/api");

    const fetchMock = vi
      .fn()
      .mockRejectedValueOnce(
        Object.assign(new TypeError("fetch failed"), {
          cause: {
            code: "ENOTFOUND",
            message: "getaddrinfo ENOTFOUND backend",
          },
        }),
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            data: { id: 1, name: "Hunting", slug: "hunting" },
            meta: {},
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    vi.stubGlobal("fetch", fetchMock);

    const result = await getPublicCategory("hunting", { locale: "ka" });

    expect(result.data.id).toBe(1);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
      "http://backend:8000/api",
    );
    expect(String(fetchMock.mock.calls[1]?.[0])).toContain(
      "http://localhost:8000/api",
    );
  });

  it("surfaces an unavailable catalog error instead of a raw fetch failure", async () => {
    vi.stubEnv("BACKEND_INTERNAL_URL", "http://backend:8000");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://localhost:8000/api");
    vi.stubGlobal(
      "fetch",
      vi.fn().mockRejectedValue(
        Object.assign(new TypeError("fetch failed"), {
          cause: { code: "ENOTFOUND" },
        }),
      ),
    );

    try {
      await getPublicCategory("hunting");
      throw new Error("expected getPublicCategory to fail");
    } catch (error) {
      expect(error).toBeInstanceOf(ApiClientError);
      expect((error as ApiClientError).code).toBe("CATALOG_UNAVAILABLE");
    }
  });
});
