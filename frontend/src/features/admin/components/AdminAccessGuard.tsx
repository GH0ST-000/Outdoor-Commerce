"use client";

import { useEffect, useRef } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/features/auth/hooks/use-auth";
import {
  AdminContextProvider,
  useAdminContext,
} from "@/features/admin/hooks/use-admin-context";

function safeInternalPath(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }
  if (!path.startsWith("/") || path.startsWith("//") || path.includes("://")) {
    return null;
  }
  return path;
}

function AdminAccessGate({ children }: { children: React.ReactNode }) {
  const { status: authStatus } = useAuth();
  const { status: adminStatus, load, error } = useAdminContext();
  const router = useRouter();
  const pathname = usePathname();
  const loadStarted = useRef(false);

  useEffect(() => {
    if (authStatus === "loading") {
      return;
    }

    if (authStatus === "unauthenticated") {
      const search =
        typeof window !== "undefined" ? window.location.search : "";
      const next = safeInternalPath(`${pathname}${search}`) ?? "/admin";
      router.replace(`/login?next=${encodeURIComponent(next)}`);
      return;
    }

    if (
      authStatus === "authenticated" &&
      adminStatus === "idle" &&
      !loadStarted.current
    ) {
      loadStarted.current = true;
      void load();
    }
  }, [authStatus, adminStatus, load, pathname, router]);

  useEffect(() => {
    if (adminStatus === "forbidden") {
      router.replace("/admin/forbidden");
    }
  }, [adminStatus, router]);

  if (
    authStatus === "loading" ||
    adminStatus === "idle" ||
    adminStatus === "loading"
  ) {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-background px-6"
        role="status"
        aria-live="polite"
      >
        <p className="text-sm text-muted-foreground">Loading admin access…</p>
      </div>
    );
  }

  if (authStatus === "unauthenticated") {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-background px-6"
        role="status"
        aria-live="polite"
      >
        <p className="text-sm text-muted-foreground">Redirecting to sign in…</p>
      </div>
    );
  }

  if (adminStatus === "forbidden") {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-background px-6"
        role="status"
        aria-live="polite"
      >
        <p className="text-sm text-muted-foreground">
          Admin access required. Redirecting…
        </p>
      </div>
    );
  }

  if (adminStatus === "error") {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-background px-6"
        role="alert"
      >
        <div className="max-w-md space-y-3 text-center">
          <p className="text-sm text-destructive">
            {error ?? "Unable to load admin access."}
          </p>
          <button
            type="button"
            className="text-sm font-semibold text-primary underline-offset-4 hover:underline"
            onClick={() => {
              loadStarted.current = false;
              void load();
            }}
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  return children;
}

/**
 * Loads auth, then GET /admin/context. Never flashes private content.
 * Roles/permissions come only from the server context — never localStorage.
 */
export function AdminAccessGuard({ children }: { children: React.ReactNode }) {
  return (
    <AdminContextProvider>
      <AdminAccessGate>{children}</AdminAccessGate>
    </AdminContextProvider>
  );
}
