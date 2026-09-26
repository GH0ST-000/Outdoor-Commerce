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
  NEXT_PUBLIC_MAP_STYLE_URL: z.string().url().optional(),
  NEXT_PUBLIC_MAP_TOKEN: z.string().min(1).optional(),
  NEXT_PUBLIC_MAP_CENTER_LNG: z.string().optional(),
  NEXT_PUBLIC_MAP_CENTER_LAT: z.string().optional(),
  NEXT_PUBLIC_MAP_ZOOM: z.string().optional(),
  NEXT_PUBLIC_MAP_MIN_ZOOM: z.string().optional(),
  NEXT_PUBLIC_MAP_MAX_ZOOM: z.string().optional(),
  NEXT_PUBLIC_MAP_BOUNDS: z.string().optional(),
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
    NEXT_PUBLIC_MAP_STYLE_URL: blankToUndefined(
      process.env.NEXT_PUBLIC_MAP_STYLE_URL,
    ),
    NEXT_PUBLIC_MAP_TOKEN: blankToUndefined(process.env.NEXT_PUBLIC_MAP_TOKEN),
    NEXT_PUBLIC_MAP_CENTER_LNG: process.env.NEXT_PUBLIC_MAP_CENTER_LNG,
    NEXT_PUBLIC_MAP_CENTER_LAT: process.env.NEXT_PUBLIC_MAP_CENTER_LAT,
    NEXT_PUBLIC_MAP_ZOOM: process.env.NEXT_PUBLIC_MAP_ZOOM,
    NEXT_PUBLIC_MAP_MIN_ZOOM: process.env.NEXT_PUBLIC_MAP_MIN_ZOOM,
    NEXT_PUBLIC_MAP_MAX_ZOOM: process.env.NEXT_PUBLIC_MAP_MAX_ZOOM,
    NEXT_PUBLIC_MAP_BOUNDS: process.env.NEXT_PUBLIC_MAP_BOUNDS,
  });
}

function blankToUndefined(value: string | undefined): string | undefined {
  if (value === undefined || value.trim() === "") {
    return undefined;
  }
  return value;
}

const DEFAULT_API_BASE_URL = "http://localhost:8000/api";

function stripTrailingSlash(url: string): string {
  return url.replace(/\/$/, "");
}

function configuredApiBaseUrl(): string {
  const configured = getPublicEnv().NEXT_PUBLIC_API_URL;
  return stripTrailingSlash(configured ?? DEFAULT_API_BASE_URL);
}

/** Prefer IPv4 loopback so Node does not resolve `localhost` to Docker's IPv6 bind. */
export function toLoopbackIpv4(url: string): string {
  return url.replace(/:\/\/localhost(?=[:/?#]|$)/i, "://127.0.0.1");
}

/**
 * Keep browser calls on the same loopback hostname as the page.
 * `localhost` and `127.0.0.1` are different cookie hosts; Sanctum CSRF
 * (`XSRF-TOKEN` via `document.cookie`) only works when they match.
 */
export function alignLoopbackHost(url: string, pageHost: string): string {
  const pageIsLoopback = pageHost === "localhost" || pageHost === "127.0.0.1";
  if (!pageIsLoopback) {
    return url;
  }

  try {
    const parsed = new URL(url);
    const apiIsLoopback =
      parsed.hostname === "localhost" || parsed.hostname === "127.0.0.1";
    if (apiIsLoopback) {
      parsed.hostname = pageHost;
    }
    return stripTrailingSlash(parsed.toString());
  } catch {
    return url;
  }
}

function isLoopbackHost(host: string): boolean {
  return host === "localhost" || host === "127.0.0.1";
}

/**
 * Browser calls stay same-origin (`/api`, `/sanctum`) so Sanctum cookies are
 * first-party. next.config rewrites those paths to the IPv4 backend, which
 * avoids the localhost (Docker IPv6 :8000) vs 127.0.0.1 (host artisan) split.
 */
export function getApiBaseUrl(): string {
  const configured = configuredApiBaseUrl();

  if (typeof window === "undefined") {
    return toLoopbackIpv4(configured);
  }

  try {
    const apiHost = new URL(configured).hostname;
    if (isLoopbackHost(apiHost) && isLoopbackHost(window.location.hostname)) {
      return "/api";
    }
  } catch {
    return "/api";
  }

  return alignLoopbackHost(configured, window.location.hostname);
}

/**
 * Server-side catalog fetch candidates. Docker uses BACKEND_INTERNAL_URL first
 * (`http://backend:8000`); host `next dev` falls back to NEXT_PUBLIC_API_URL
 * when the compose hostname cannot be resolved.
 */
export function resolveServerApiBaseUrls(): string[] {
  const publicUrl = toLoopbackIpv4(configuredApiBaseUrl());
  const internal = getServerEnv().BACKEND_INTERNAL_URL;
  const urls: string[] = [];

  if (internal) {
    const internalApi = toLoopbackIpv4(`${stripTrailingSlash(internal)}/api`);
    urls.push(internalApi);
  }

  if (!urls.includes(publicUrl)) {
    urls.push(publicUrl);
  }

  return urls;
}

/** Backend origin for Sanctum CSRF cookie (no `/api` suffix). */
export function getBackendOrigin(): string {
  const api = getApiBaseUrl();
  if (api.startsWith("/")) {
    return "";
  }

  const configured = getPublicEnv().NEXT_PUBLIC_BACKEND_URL;
  if (configured) {
    if (typeof window === "undefined") {
      return toLoopbackIpv4(stripTrailingSlash(configured));
    }
    return alignLoopbackHost(
      stripTrailingSlash(configured),
      window.location.hostname,
    );
  }

  return api.replace(/\/api$/, "");
}
