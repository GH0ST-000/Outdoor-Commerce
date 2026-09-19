"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { AuthGuard } from "@/features/auth/components/AuthGuard";
import { EmailVerificationNotice } from "@/features/auth/components/EmailVerificationNotice";
import { useAuth } from "@/features/auth/hooks/use-auth";
import { useLocale } from "@/components/locale-provider";
import { SiteControls } from "@/components/site-controls";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import shellStyles from "@/features/auth/components/auth-shell.module.css";

function AccountContent() {
  const { user, logout } = useAuth();
  const { t } = useLocale();
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (!user) {
    return null;
  }

  async function onLogout() {
    if (submitting) {
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await logout();
      router.replace("/login");
    } catch {
      setError(t.account.unableSignOut);
      setSubmitting(false);
    }
  }

  return (
    <Card className="border-border/60 shadow-sm">
      <CardHeader>
        <CardTitle>{t.account.title}</CardTitle>
        <CardDescription>
          {user.first_name} {user.last_name}
        </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4">
        <p className="text-sm text-muted-foreground">{user.email}</p>
        <EmailVerificationNotice user={user} />
        {error ? (
          <p
            className="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            role="alert"
          >
            {error}
          </p>
        ) : null}
        <Button
          type="button"
          variant="outline"
          onClick={() => void onLogout()}
          disabled={submitting}
        >
          {submitting ? t.account.signingOut : t.account.signOut}
        </Button>
      </CardContent>
    </Card>
  );
}

export default function AccountPage() {
  const { t } = useLocale();

  return (
    <div className={shellStyles.shell}>
      <aside className={shellStyles.visual} aria-hidden="true">
        <div className={shellStyles.visualAtmosphere} />
        <p className={shellStyles.brandMark}>{t.brand}</p>
        <p className={shellStyles.visualCopy}>{t.account.visualCopy}</p>
      </aside>
      <section className={shellStyles.panel}>
        <div className="mb-4 flex justify-end">
          <SiteControls tone="light" />
        </div>
        <div className={shellStyles.panelInner}>
          <p className={shellStyles.mobileBrand}>{t.brand}</p>
          <AuthGuard>
            <AccountContent />
          </AuthGuard>
        </div>
      </section>
    </div>
  );
}
