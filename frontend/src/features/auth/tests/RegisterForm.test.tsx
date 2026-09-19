import { render } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RegisterForm } from "@/features/auth/components/RegisterForm";
import { TestProviders } from "@/test/providers";

const registerMock = vi.fn();
const replaceMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
}));

vi.mock("@/features/auth/hooks/use-auth", () => ({
  useAuth: () => ({
    login: vi.fn(),
    register: registerMock,
    logout: vi.fn(),
    user: null,
    status: "unauthenticated",
    refresh: vi.fn(),
    setUser: vi.fn(),
  }),
}));

function renderRegister() {
  return render(
    <TestProviders>
      <RegisterForm />
    </TestProviders>,
  );
}

describe("RegisterForm", () => {
  beforeEach(() => {
    registerMock.mockReset();
    replaceMock.mockReset();
  });

  it("renders required registration fields", () => {
    const view = renderRegister();
    expect(view.getByLabelText("First name")).toHaveAttribute(
      "autocomplete",
      "given-name",
    );
    expect(view.getByLabelText("Last name")).toHaveAttribute(
      "autocomplete",
      "family-name",
    );
    expect(view.getByLabelText("Email")).toHaveAttribute(
      "autocomplete",
      "email",
    );
    expect(view.getByLabelText("Password")).toHaveAttribute(
      "autocomplete",
      "new-password",
    );
  });

  it("validates password confirmation", async () => {
    const user = userEvent.setup();
    const view = renderRegister();

    await user.type(view.getByLabelText("First name"), "ლუკა");
    await user.type(view.getByLabelText("Last name"), "დათუნაშვილი");
    await user.type(view.getByLabelText("Email"), "luka@example.test");
    await user.type(view.getByLabelText("Password"), "SecurePass12");
    await user.type(view.getByLabelText("Confirm password"), "DifferentPass12");
    await user.click(view.getByRole("button", { name: "Create account" }));

    expect(
      await view.findByText("Passwords do not match."),
    ).toBeInTheDocument();
    expect(registerMock).not.toHaveBeenCalled();
  });

  it("submits valid Georgian names without role fields", async () => {
    const user = userEvent.setup();
    registerMock.mockResolvedValue({
      id: 1,
      first_name: "ლუკა",
      last_name: "დათუნაშვილი",
      email: "luka@example.test",
      email_verified: false,
      status: "active",
      preferred_locale: "ka",
      phone: null,
    });

    const view = renderRegister();

    await user.type(view.getByLabelText("First name"), "ლუკა");
    await user.type(view.getByLabelText("Last name"), "დათუნაშვილი");
    await user.type(view.getByLabelText("Email"), "luka@example.test");
    await user.type(view.getByLabelText("Password"), "SecurePass12");
    await user.type(view.getByLabelText("Confirm password"), "SecurePass12");
    await user.click(view.getByRole("button", { name: "Create account" }));

    expect(registerMock).toHaveBeenCalledTimes(1);
    expect(registerMock.mock.calls[0]?.[0]).toEqual({
      first_name: "ლუკა",
      last_name: "დათუნაშვილი",
      email: "luka@example.test",
      password: "SecurePass12",
      password_confirmation: "SecurePass12",
    });
    expect(registerMock.mock.calls[0]?.[0]).not.toHaveProperty("role");
    expect(registerMock.mock.calls[0]?.[0]).not.toHaveProperty("status");
  });
});
