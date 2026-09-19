import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { LoginForm } from "@/features/auth/components/LoginForm";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const loginMock = vi.fn();
const replaceMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/auth/hooks/use-auth", () => ({
  useAuth: () => ({
    login: loginMock,
    register: vi.fn(),
    logout: vi.fn(),
    user: null,
    status: "unauthenticated",
    refresh: vi.fn(),
    setUser: vi.fn(),
  }),
}));

function renderLogin() {
  return render(
    <TestProviders>
      <LoginForm />
    </TestProviders>,
  );
}

describe("LoginForm", () => {
  afterEach(() => {
    cleanup();
  });

  beforeEach(() => {
    loginMock.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();
  });

  it("renders required fields with accessible labels and autocomplete", () => {
    renderLogin();

    expect(screen.getByLabelText("Email")).toHaveAttribute(
      "autocomplete",
      "email",
    );
    expect(screen.getByLabelText("Password")).toHaveAttribute(
      "autocomplete",
      "current-password",
    );
  });

  it("shows validation errors for empty submit", async () => {
    const user = userEvent.setup();
    renderLogin();

    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findAllByRole("alert")).not.toHaveLength(0);
    expect(loginMock).not.toHaveBeenCalled();
  });

  it("submits valid credentials once", async () => {
    const user = userEvent.setup();
    loginMock.mockResolvedValue({
      id: 1,
      first_name: "Luka",
      last_name: "Test",
      email: "luka@example.test",
      email_verified: false,
      status: "active",
      preferred_locale: "ka",
      phone: null,
    });

    const view = renderLogin();

    await user.type(view.getByLabelText("Email"), "luka@example.test");
    await user.type(view.getByLabelText("Password"), "SecurePass12");
    await user.click(view.getByRole("button", { name: "Sign in" }));

    expect(loginMock).toHaveBeenCalledTimes(1);
    expect(loginMock).toHaveBeenCalledWith({
      email: "luka@example.test",
      password: "SecurePass12",
      remember: false,
    });
  });

  it("displays invalid credential errors from the API", async () => {
    const user = userEvent.setup();
    const { ApiClientError } = await import("@/lib/api-client");
    loginMock.mockRejectedValue(
      new ApiClientError({
        status: 401,
        code: "INVALID_CREDENTIALS",
        message: "The provided credentials are invalid.",
      }),
    );

    const view = renderLogin();
    await user.type(view.getByLabelText("Email"), "luka@example.test");
    await user.type(view.getByLabelText("Password"), "SecurePass12");
    await user.click(view.getByRole("button", { name: "Sign in" }));

    expect(
      await view.findByText("The provided credentials are invalid."),
    ).toBeInTheDocument();
  });
});
