import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest, resetApiClientStateForTests } from "@/lib/api-client";

describe("api client CSRF retry", () => {
  beforeEach(() => {
    resetApiClientStateForTests();
    vi.restoreAllMocks();
  });

  it("retries a CSRF failure at most once", async () => {
    const fetchMock = vi
      .fn()
      // csrf cookie
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      // first mutation → 419
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            error: { code: "CSRF_TOKEN_MISMATCH", message: "CSRF" },
          }),
          {
            status: 419,
            headers: { "Content-Type": "application/json" },
          },
        ),
      )
      // csrf refresh
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      // retry success
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { ok: true } }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );

    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("document", {
      cookie: "XSRF-TOKEN=test-token",
    });

    const result = await apiRequest<{ data: { ok: boolean } }>(
      "/v1/auth/login",
      {
        method: "POST",
        body: { email: "a@example.test", password: "SecurePass12" },
      },
    );

    expect(result.data.ok).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(4);
  });
});
