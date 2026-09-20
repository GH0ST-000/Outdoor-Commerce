import { getApiBaseUrl, getBackendOrigin } from "@/lib/env";
import type { ApiErrorEnvelope } from "@/features/auth/api/auth-types";

export class ApiClientError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details?: Record<string, string[]>;
  readonly requestId?: string;

  constructor(options: {
    status: number;
    code: string;
    message: string;
    details?: Record<string, string[]>;
    requestId?: string;
  }) {
    super(options.message);
    this.name = "ApiClientError";
    this.status = options.status;
    this.code = options.code;
    this.details = options.details;
    this.requestId = options.requestId;
  }
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
  csrfRetry?: boolean;
  signal?: AbortSignal;
};

type FormRequestOptions = {
  method?: string;
  formData: FormData;
  csrfRetry?: boolean;
  signal?: AbortSignal;
};

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie.match(
    new RegExp(
      `(?:^|; )${name.replace(/[$()*+.?[\\\]^{|}]/g, "\\$&")}=([^;]*)`,
    ),
  );

  return match ? decodeURIComponent(match[1]) : null;
}

let csrfReady = false;

export async function ensureCsrfCookie(): Promise<void> {
  const response = await fetch(`${getBackendOrigin()}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });

  if (!response.ok) {
    throw new ApiClientError({
      status: response.status,
      code: "CSRF_COOKIE_FAILED",
      message: "Unable to prepare a secure session. Please try again.",
      requestId: response.headers.get("X-Request-ID") ?? undefined,
    });
  }

  csrfReady = true;
}

async function parseError(response: Response): Promise<ApiClientError> {
  const requestId = response.headers.get("X-Request-ID") ?? undefined;
  let payload: ApiErrorEnvelope | null = null;

  try {
    payload = (await response.json()) as ApiErrorEnvelope;
  } catch {
    payload = null;
  }

  const code =
    payload?.error?.code ??
    (response.status === 401
      ? "UNAUTHORIZED"
      : response.status === 403
        ? "FORBIDDEN"
        : response.status === 419
          ? "CSRF_TOKEN_MISMATCH"
          : response.status === 422
            ? "VALIDATION_FAILED"
            : response.status === 429
              ? "TOO_MANY_REQUESTS"
              : "SERVER_ERROR");

  const message =
    payload?.error?.message ??
    (response.status === 429
      ? "Too many attempts. Please wait and try again."
      : "Something went wrong. Please try again.");

  return new ApiClientError({
    status: response.status,
    code,
    message,
    details: payload?.error?.details,
    requestId: payload?.meta?.request_id ?? requestId,
  });
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const isMutation = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isMutation && !csrfReady) {
    await ensureCsrfCookie();
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  if (isMutation) {
    const xsrf = readCookie("XSRF-TOKEN");
    if (xsrf) {
      headers["X-XSRF-TOKEN"] = xsrf;
    }
  }

  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  if (options.headers) {
    Object.assign(headers, options.headers);
  }

  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    method,
    credentials: "include",
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    signal: options.signal,
  });

  if (response.status === 419 && isMutation && options.csrfRetry !== false) {
    csrfReady = false;
    await ensureCsrfCookie();
    return apiRequest<T>(path, { ...options, csrfRetry: false });
  }

  if (response.status === 204) {
    return undefined as T;
  }

  if (!response.ok) {
    throw await parseError(response);
  }

  if (response.status === 202) {
    try {
      return (await response.json()) as T;
    } catch {
      return undefined as T;
    }
  }

  return (await response.json()) as T;
}

/**
 * Multipart mutation (upload). Does not set Content-Type so the browser adds the
 * boundary. JSON Accept header is still sent for Laravel error envelopes.
 */
export async function apiFormRequest<T>(
  path: string,
  options: FormRequestOptions,
): Promise<T> {
  const method = (options.method ?? "POST").toUpperCase();
  const isMutation = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isMutation && !csrfReady) {
    await ensureCsrfCookie();
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  if (isMutation) {
    const xsrf = readCookie("XSRF-TOKEN");
    if (xsrf) {
      headers["X-XSRF-TOKEN"] = xsrf;
    }
  }

  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    method,
    credentials: "include",
    headers,
    body: options.formData,
    signal: options.signal,
  });

  if (response.status === 419 && isMutation && options.csrfRetry !== false) {
    csrfReady = false;
    await ensureCsrfCookie();
    return apiFormRequest<T>(path, { ...options, csrfRetry: false });
  }

  if (response.status === 204) {
    return undefined as T;
  }

  if (!response.ok) {
    throw await parseError(response);
  }

  if (response.status === 202) {
    try {
      return (await response.json()) as T;
    } catch {
      return undefined as T;
    }
  }

  return (await response.json()) as T;
}

/** Test helper — resets CSRF readiness between cases. */
export function resetApiClientStateForTests(): void {
  csrfReady = false;
}
