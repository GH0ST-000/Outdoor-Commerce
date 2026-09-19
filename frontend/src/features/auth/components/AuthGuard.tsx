"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/features/auth/hooks/use-auth";
import { useLocale } from "@/components/locale-provider";

type AuthGuardProps = {
  children: React.ReactNode;
  mode?: "require-auth" | "guest-only";
  redirectTo?: string;
};

function safeInternalPath(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }
  if (!path.startsWith("/") || path.startsWith("//") || path.includes("://")) {
    return null;
  }
  return path;
}

export function AuthGuard({
  children,
  mode = "require-auth",
  redirectTo = "/login",
}: AuthGuardProps) {
  const { status } = useAuth();
  const { t } = useLocale();
  const router = useRouter();

  useEffect(() => {
    if (status === "loading") {
      return;
    }

    if (mode === "require-auth" && status === "unauthenticated") {
      const current =
        typeof window !== "undefined"
          ? `${window.location.pathname}${window.location.search}`
          : "/account";
      const next = safeInternalPath(current) ?? "/account";
      router.replace(`${redirectTo}?next=${encodeURIComponent(next)}`);
    }

    if (mode === "guest-only" && status === "authenticated") {
      router.replace("/account");
    }
  }, [mode, redirectTo, router, status]);

  if (status === "loading") {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        {t.common.loadingSession}
      </p>
    );
  }

  if (mode === "require-auth" && status !== "authenticated") {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        {t.common.redirectSignIn}
      </p>
    );
  }

  if (mode === "guest-only" && status === "authenticated") {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        {t.common.redirectAccount}
      </p>
    );
  }

  return children;
}
