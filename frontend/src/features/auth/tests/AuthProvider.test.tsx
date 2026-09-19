import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AuthProvider, useAuth } from "@/features/auth/providers/AuthProvider";
import { resetApiClientStateForTests } from "@/lib/api-client";

const fetchCurrentUser = vi.fn();
const logoutCustomer = vi.fn();

vi.mock("@/features/auth/api/auth-api", () => ({
  fetchCurrentUser: (...args: unknown[]) => fetchCurrentUser(...args),
  loginCustomer: vi.fn(),
  registerCustomer: vi.fn(),
  logoutCustomer: (...args: unknown[]) => logoutCustomer(...args),
}));

function Probe() {
  const { status, user } = useAuth();
  return (
    <div>
      <span data-testid="status">{status}</span>
      <span data-testid="email">{user?.email ?? "none"}</span>
    </div>
  );
}

describe("AuthProvider", () => {
  beforeEach(() => {
    fetchCurrentUser.mockReset();
    logoutCustomer.mockReset();
    resetApiClientStateForTests();
  });

  it("loads an authenticated user", async () => {
    fetchCurrentUser.mockResolvedValue({
      id: 1,
      first_name: "Luka",
      last_name: "Test",
      email: "luka@example.test",
      email_verified: true,
      status: "active",
      preferred_locale: "ka",
      phone: null,
    });

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId("status")).toHaveTextContent("authenticated");
    });
    expect(screen.getByTestId("email")).toHaveTextContent("luka@example.test");
  });

  it("handles unauthenticated state", async () => {
    fetchCurrentUser.mockResolvedValue(null);

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated");
    });
    expect(screen.getByTestId("email")).toHaveTextContent("none");
  });
});
