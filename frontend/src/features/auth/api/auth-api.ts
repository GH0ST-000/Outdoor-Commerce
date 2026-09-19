import { apiRequest } from "@/lib/api-client";
import type {
  ApiSuccessEnvelope,
  AuthenticatedUser,
  ForgotPasswordPayload,
  LoginPayload,
  RegisterPayload,
  ResetPasswordPayload,
} from "@/features/auth/api/auth-types";

type UserEnvelope = ApiSuccessEnvelope<AuthenticatedUser>;

export async function registerCustomer(
  payload: RegisterPayload,
): Promise<AuthenticatedUser> {
  const response = await apiRequest<UserEnvelope>("/v1/auth/register", {
    method: "POST",
    body: payload,
  });
  return response.data;
}

export async function loginCustomer(
  payload: LoginPayload,
): Promise<AuthenticatedUser> {
  const response = await apiRequest<UserEnvelope>("/v1/auth/login", {
    method: "POST",
    body: payload,
  });
  return response.data;
}

export async function logoutCustomer(): Promise<void> {
  await apiRequest<void>("/v1/auth/logout", {
    method: "POST",
  });
}

export async function fetchCurrentUser(): Promise<AuthenticatedUser | null> {
  try {
    const response = await apiRequest<UserEnvelope>("/v1/auth/me");
    return response.data;
  } catch (error) {
    if (
      typeof error === "object" &&
      error !== null &&
      "status" in error &&
      (error as { status: number }).status === 401
    ) {
      return null;
    }
    throw error;
  }
}

export async function forgotPassword(
  payload: ForgotPasswordPayload,
): Promise<void> {
  await apiRequest("/v1/auth/forgot-password", {
    method: "POST",
    body: payload,
  });
}

export async function resetPassword(
  payload: ResetPasswordPayload,
): Promise<void> {
  await apiRequest("/v1/auth/reset-password", {
    method: "POST",
    body: payload,
  });
}

export async function resendVerificationEmail(): Promise<void> {
  await apiRequest("/v1/auth/email/verification-notification", {
    method: "POST",
  });
}
