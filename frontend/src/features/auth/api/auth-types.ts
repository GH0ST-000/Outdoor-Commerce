export type AuthenticatedUser = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  email_verified: boolean;
  status: "active" | "disabled";
  preferred_locale: string | null;
  phone: string | null;
};

export type ApiSuccessEnvelope<T> = {
  data: T;
  meta?: {
    request_id?: string;
  };
};

export type ApiErrorEnvelope = {
  error: {
    code: string;
    message: string;
    details?: Record<string, string[]>;
  };
  meta?: {
    request_id?: string;
  };
};

export type RegisterPayload = {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type LoginPayload = {
  email: string;
  password: string;
  remember?: boolean;
};

export type ForgotPasswordPayload = {
  email: string;
};

export type ResetPasswordPayload = {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
};
