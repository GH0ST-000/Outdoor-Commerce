"use client";

import { FormEvent, useId, useMemo, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Eye, EyeOff } from "lucide-react";
import { resetPassword } from "@/features/auth/api/auth-api";
import { resetPasswordSchema } from "@/features/auth/schemas/auth-schemas";
import { ApiClientError } from "@/lib/api-client";
import { useLocale } from "@/components/locale-provider";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export function ResetPasswordForm() {
  const { t } = useLocale();
  const formId = useId();
  const searchParams = useSearchParams();
  const initialEmail = useMemo(
    () => searchParams.get("email") ?? "",
    [searchParams],
  );
  const token = useMemo(() => searchParams.get("token") ?? "", [searchParams]);

  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [showPassword, setShowPassword] = useState(false);
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
    const parsed = resetPasswordSchema.safeParse({
      email,
      token,
      password,
      password_confirmation: passwordConfirmation,
    });

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
      await resetPassword(parsed.data);
      setSuccess(true);
      setPassword("");
      setPasswordConfirmation("");
    } catch (error) {
      if (error instanceof ApiClientError) {
        if (error.details) {
          const next: Record<string, string> = {};
          for (const [key, messages] of Object.entries(error.details)) {
            if (messages[0]) {
              next[key] = messages[0];
            }
          }
          setFieldErrors(next);
        }
        setFormError(error.message);
      } else {
        setFormError(t.auth.unableReset);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card className="border-border/60 shadow-sm">
      <CardHeader>
        <CardTitle>{t.auth.resetTitle}</CardTitle>
        <CardDescription>{t.auth.resetLead}</CardDescription>
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
              className="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm"
              role="status"
            >
              {t.auth.resetSuccess}{" "}
              <Link href="/login" className="underline">
                {t.auth.signIn}
              </Link>
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

          <input type="hidden" name="token" value={token} readOnly />
          {fieldErrors.token ? (
            <p className="text-sm text-destructive" role="alert">
              {fieldErrors.token}
            </p>
          ) : null}

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-password`}>{t.auth.newPassword}</Label>
            <div className="flex gap-2">
              <Input
                id={`${formId}-password`}
                type={showPassword ? "text" : "password"}
                name="password"
                autoComplete="new-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
              />
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-pressed={showPassword}
                aria-label={
                  showPassword ? t.auth.hidePassword : t.auth.showPassword
                }
                onClick={() => setShowPassword((value) => !value)}
              >
                {showPassword ? <EyeOff /> : <Eye />}
              </Button>
            </div>
            {fieldErrors.password ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.password}
              </p>
            ) : null}
          </div>

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-password-confirmation`}>
              {t.auth.confirmNewPassword}
            </Label>
            <Input
              id={`${formId}-password-confirmation`}
              type={showPassword ? "text" : "password"}
              name="password_confirmation"
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              required
            />
            {fieldErrors.password_confirmation ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.password_confirmation}
              </p>
            ) : null}
          </div>

          <Button
            type="submit"
            disabled={submitting || success}
            className="w-full"
          >
            {submitting ? t.auth.updating : t.auth.updatePassword}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
