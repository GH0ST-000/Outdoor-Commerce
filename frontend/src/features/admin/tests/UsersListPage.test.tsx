import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { UsersListPage } from "@/features/admin/components/UsersListPage";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchAdminUsers = vi.fn();
const replaceMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/users",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/admin/api/admin-api", () => ({
  fetchAdminUsers: (...args: unknown[]) => fetchAdminUsers(...args),
}));

function renderUsers() {
  return render(
    <TestProviders>
      <UsersListPage />
    </TestProviders>,
  );
}

describe("UsersListPage", () => {
  beforeEach(() => {
    fetchAdminUsers.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();
  });

  it("shows a loading state then rows", async () => {
    let resolveUsers: (value: unknown) => void = () => undefined;
    fetchAdminUsers.mockReturnValue(
      new Promise((resolve) => {
        resolveUsers = resolve;
      }),
    );

    renderUsers();
    expect(screen.getByRole("status")).toHaveTextContent("Loading users");

    resolveUsers({
      data: [
        {
          id: 9,
          first_name: "Ana",
          last_name: "Hunter",
          email: "ana@example.test",
          email_verified: true,
          status: "active",
          last_login_at: null,
          roles: ["catalog-manager"],
          created_at: "2026-01-01T00:00:00+00:00",
          updated_at: "2026-01-01T00:00:00+00:00",
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    expect(await screen.findByText("Ana Hunter")).toBeInTheDocument();
    expect(screen.getByText("ana@example.test")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Ana Hunter" })).toHaveAttribute(
      "href",
      "/admin/users/9",
    );
  });

  it("shows an empty state when no users match", async () => {
    fetchAdminUsers.mockResolvedValue({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
      },
    });

    renderUsers();
    expect(
      await screen.findByText("No users match these filters."),
    ).toBeInTheDocument();
  });

  it("shows an error state when the API fails", async () => {
    const { ApiClientError } = await import("@/lib/api-client");
    fetchAdminUsers.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "PERMISSION_DENIED",
        message: "Permission denied.",
      }),
    );

    renderUsers();
    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("Permission denied.");
    });
  });
});
