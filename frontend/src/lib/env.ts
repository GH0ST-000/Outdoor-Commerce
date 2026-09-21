import { z } from "zod";

const serverSchema = z.object({
  NODE_ENV: z
    .enum(["development", "test", "production"])
    .default("development"),
  BACKEND_INTERNAL_URL: z.string().url().optional(),
});

const publicSchema = z.object({
  NEXT_PUBLIC_APP_URL: z.string().url().optional(),
  NEXT_PUBLIC_API_URL: z.string().url().optional(),
  NEXT_PUBLIC_BACKEND_URL: z.string().url().optional(),
  NEXT_PUBLIC_CART_ENABLED: z.string().optional(),
});

export type ServerEnv = z.infer<typeof serverSchema>;
export type PublicEnv = z.infer<typeof publicSchema>;

export function getServerEnv(): ServerEnv {
  return serverSchema.parse({
    NODE_ENV: process.env.NODE_ENV,
    BACKEND_INTERNAL_URL: process.env.BACKEND_INTERNAL_URL,
  });
}

export function getPublicEnv(): PublicEnv {
  return publicSchema.parse({
    NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL,
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
    NEXT_PUBLIC_BACKEND_URL: process.env.NEXT_PUBLIC_BACKEND_URL,
    NEXT_PUBLIC_CART_ENABLED: process.env.NEXT_PUBLIC_CART_ENABLED,
  });
}

/** Public API base including `/api` suffix, e.g. http://localhost:8000/api */
export function getApiBaseUrl(): string {
  const configured = getPublicEnv().NEXT_PUBLIC_API_URL;
  if (configured) {
    return configured.replace(/\/$/, "");
  }
  return "http://localhost:8000/api";
}

/**
 * Server-side catalog fetch candidates. Docker uses BACKEND_INTERNAL_URL first
 * (`http://backend:8000`); host `next dev` falls back to NEXT_PUBLIC_API_URL
 * when the compose hostname cannot be resolved.
 */
export function resolveServerApiBaseUrls(): string[] {
  const publicUrl = getApiBaseUrl();
  const internal = getServerEnv().BACKEND_INTERNAL_URL;
  const urls: string[] = [];

  if (internal) {
    const internalApi = `${internal.replace(/\/$/, "")}/api`;
    urls.push(internalApi);
  }

  if (!urls.includes(publicUrl)) {
    urls.push(publicUrl);
  }

  return urls;
}

/** Backend origin for Sanctum CSRF cookie (no `/api` suffix). */
export function getBackendOrigin(): string {
  const configured = getPublicEnv().NEXT_PUBLIC_BACKEND_URL;
  if (configured) {
    return configured.replace(/\/$/, "");
  }

  const api = getApiBaseUrl();
  return api.replace(/\/api$/, "");
}
