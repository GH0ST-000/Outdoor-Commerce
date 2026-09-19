"use client";

import { FormEvent, useId, useState } from "react";
import Link from "next/link";
import { forgotPassword } from "@/features/auth/api/auth-api";
import { forgotPasswordSchema } from "@/features/auth/schemas/auth-schemas";
import { ApiClientError } from "@/lib/api-client";
import { useLocale } from "@/components/locale-provider";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export function ForgotPasswordForm() {
  const { t } = useLocale();
  const formId = useId();
  const [email, setEmail] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) {
      return;
    }

    setFormError(null);
    setSuccess(false);
    const parsed = forgotPasswordSchema.safeParse({ email });
    if (!parsed.success) {
      const next: Record<string, string> = {};
      for (const issue of parsed.error.issues) {
        const key = issue.path[0];
        if (typeof key === "string" && !next[key]) {
          next[key] = issue.message;
        }
      }
      setFieldErrors(next);
      return;
    }

    setFieldErrors({});
    setSubmitting(true);

    try {
      await forgotPassword(parsed.data);
      setSuccess(true);
    } catch (error) {
      if (error instanceof ApiClientError) {
        setFormError(error.message);
      } else {
        setFormError(t.auth.unableForgot);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card className="border-border/60 shadow-sm">
      <CardHeader>
        <CardTitle>{t.auth.forgotTitle}</CardTitle>
        <CardDescription>{t.auth.forgotLead}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className="grid gap-4" onSubmit={onSubmit} noValidate>
          {formError ? (
            <p
              className="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2 text-sm text-destructive"
              role="alert"
            >
              {formError}
            </p>
          ) : null}
          {success ? (
            <p
              className="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm text-foreground"
              role="status"
            >
              {t.auth.forgotSuccess}
            </p>
          ) : null}

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-email`}>{t.auth.email}</Label>
            <Input
              id={`${formId}-email`}
              type="email"
              name="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
            {fieldErrors.email ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.email}
              </p>
            ) : null}
          </div>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? t.auth.sending : t.auth.sendReset}
          </Button>
        </form>
      </CardContent>
      <CardFooter>
        <Link
          href="/login"
          className="text-sm text-muted-foreground underline hover:text-foreground"
        >
          {t.auth.backToSignIn}
        </Link>
      </CardFooter>
    </Card>
  );
}
