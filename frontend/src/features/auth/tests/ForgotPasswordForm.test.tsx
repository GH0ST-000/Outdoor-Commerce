import { render } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ForgotPasswordForm } from "@/features/auth/components/ForgotPasswordForm";
import { TestProviders } from "@/test/providers";

const forgotMock = vi.fn();

vi.mock("@/features/auth/api/auth-api", () => ({
  forgotPassword: (...args: unknown[]) => forgotMock(...args),
}));

function renderForgot() {
  return render(
    <TestProviders>
      <ForgotPasswordForm />
    </TestProviders>,
  );
}

describe("ForgotPasswordForm", () => {
  beforeEach(() => {
    forgotMock.mockReset();
  });

  it("shows a generic success message", async () => {
    const user = userEvent.setup();
    forgotMock.mockResolvedValue(undefined);

    const view = renderForgot();
    await user.type(view.getByLabelText("Email"), "anyone@example.test");
    await user.click(view.getByRole("button", { name: "Send reset link" }));

    expect(
      await view.findByText(
        /If an account exists for that email, password reset instructions/i,
      ),
    ).toBeInTheDocument();
  });

  it("displays rate-limit feedback", async () => {
    const user = userEvent.setup();
    const { ApiClientError } = await import("@/lib/api-client");
    forgotMock.mockRejectedValue(
      new ApiClientError({
        status: 429,
        code: "TOO_MANY_REQUESTS",
        message: "Too many attempts. Please wait and try again.",
      }),
    );

    const view = renderForgot();
    await user.type(view.getByLabelText("Email"), "anyone@example.test");
    await user.click(view.getByRole("button", { name: "Send reset link" }));

    expect(
      await view.findByText("Too many attempts. Please wait and try again."),
    ).toBeInTheDocument();
  });
});
