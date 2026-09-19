import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AdminAccessGuard } from "@/features/admin/components/AdminAccessGuard";
import { resetApiClientStateForTests } from "@/lib/api-client";

const replaceMock = vi.fn();
const fetchAdminContext = vi.fn();
const useAuthMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin",
}));

vi.mock("@/features/auth/hooks/use-auth", () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock("@/features/admin/api/admin-api", () => ({
  fetchAdminContext: (...args: unknown[]) => fetchAdminContext(...args),
}));

describe("AdminAccessGuard", () => {
  beforeEach(() => {
    replaceMock.mockReset();
    fetchAdminContext.mockReset();
    useAuthMock.mockReset();
    resetApiClientStateForTests();
  });

  it("shows loading while auth is unresolved and never renders children", () => {
    useAuthMock.mockReturnValue({
      status: "loading",
      user: null,
      logout: vi.fn(),
      refresh: vi.fn(),
    });

    render(
      <AdminAccessGuard>
        <p>Private admin content</p>
      </AdminAccessGuard>,
    );

    expect(screen.getByRole("status")).toHaveTextContent(
      "Loading admin access",
    );
    expect(screen.queryByText("Private admin content")).not.toBeInTheDocument();
    expect(fetchAdminContext).not.toHaveBeenCalled();
  });

  it("redirects unauthenticated users with a safe next path", async () => {
    useAuthMock.mockReturnValue({
      status: "unauthenticated",
      user: null,
      logout: vi.fn(),
      refresh: vi.fn(),
    });

    render(
      <AdminAccessGuard>
        <p>Private admin content</p>
      </AdminAccessGuard>,
    );

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const target = String(replaceMock.mock.calls[0]?.[0] ?? "");
    expect(target.startsWith("/login?next=")).toBe(true);
    expect(decodeURIComponent(target.split("next=")[1] ?? "")).toMatch(
      /^\/admin/,
    );
    expect(screen.queryByText("Private admin content")).not.toBeInTheDocument();
  });

  it("loads admin context and renders children when access is granted", async () => {
    useAuthMock.mockReturnValue({
      status: "authenticated",
      user: { id: 1, email: "admin@example.test" },
      logout: vi.fn(),
      refresh: vi.fn(),
    });
    fetchAdminContext.mockResolvedValue({
      user: {
        id: 1,
        first_name: "Ada",
        last_name: "Admin",
        email: "admin@example.test",
        email_verified: true,
        status: "active",
      },
      roles: ["admin"],
      permissions: ["admin.access", "users.view"],
    });

    render(
      <AdminAccessGuard>
        <p>Private admin content</p>
      </AdminAccessGuard>,
    );

    expect(
      await screen.findByText("Private admin content"),
    ).toBeInTheDocument();
    expect(fetchAdminContext).toHaveBeenCalledTimes(1);
  });

  it("redirects to forbidden when admin.access is missing", async () => {
    useAuthMock.mockReturnValue({
      status: "authenticated",
      user: { id: 2, email: "customer@example.test" },
      logout: vi.fn(),
      refresh: vi.fn(),
    });

    const { ApiClientError } = await import("@/lib/api-client");
    fetchAdminContext.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "ADMIN_ACCESS_REQUIRED",
        message: "Admin access required.",
      }),
    );

    render(
      <AdminAccessGuard>
        <p>Private admin content</p>
      </AdminAccessGuard>,
    );

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalledWith("/admin/forbidden");
    });
    expect(screen.queryByText("Private admin content")).not.toBeInTheDocument();
  });
});
