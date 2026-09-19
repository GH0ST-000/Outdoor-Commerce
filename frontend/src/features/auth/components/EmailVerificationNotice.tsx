"use client";

import { useState } from "react";
import { resendVerificationEmail } from "@/features/auth/api/auth-api";
import type { AuthenticatedUser } from "@/features/auth/api/auth-types";
import { ApiClientError } from "@/lib/api-client";
import { useLocale } from "@/components/locale-provider";
import { Button } from "@/components/ui/button";

type EmailVerificationNoticeProps = {
  user: AuthenticatedUser;
};

export function EmailVerificationNotice({
  user,
}: EmailVerificationNoticeProps) {
  const { t } = useLocale();
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (user.email_verified) {
    return (
      <p
        className="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm"
        role="status"
      >
        {t.account.verified}
      </p>
    );
  }

  async function onResend() {
    if (submitting) {
      return;
    }
    setSubmitting(true);
    setError(null);
    setMessage(null);
    try {
      await resendVerificationEmail();
      setMessage(t.account.resendSuccess);
    } catch (err) {
      if (err instanceof ApiClientError && err.status === 429) {
        setError(t.account.resendThrottle);
      } else if (err instanceof ApiClientError) {
        setError(err.message);
      } else {
        setError(t.account.unableResend);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="grid gap-3">
      <p className="text-sm text-muted-foreground">{t.account.unverified}</p>
      {message ? (
        <p
          className="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm"
          role="status"
        >
          {message}
        </p>
      ) : null}
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
        variant="secondary"
        onClick={() => void onResend()}
        disabled={submitting}
      >
        {submitting ? t.account.sending : t.account.resendVerification}
      </Button>
    </div>
  );
}
