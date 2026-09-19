"use client";

import { FormEvent, useId, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Eye, EyeOff } from "lucide-react";
import { useAuth } from "@/features/auth/hooks/use-auth";
import { loginSchema } from "@/features/auth/schemas/auth-schemas";
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

function safeNextPath(value: string | null): string {
  if (
    !value ||
    !value.startsWith("/") ||
    value.startsWith("//") ||
    value.includes("://")
  ) {
    return "/account";
  }
  return value;
}

export function LoginForm() {
  const { login } = useAuth();
  const { t } = useLocale();
  const router = useRouter();
  const searchParams = useSearchParams();
  const formId = useId();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) {
      return;
    }

    setFormError(null);
    const parsed = loginSchema.safeParse({ email, password, remember });
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
      await login(parsed.data);
      router.replace(safeNextPath(searchParams.get("next")));
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
        setFormError(t.auth.unableSignIn);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card className="border-border/60 shadow-[var(--shadow-soft,0_20px_50px_rgba(8,18,14,0.08))]">
      <CardHeader>
        <CardTitle>{t.auth.signInTitle}</CardTitle>
        <CardDescription>{t.auth.signInLead}</CardDescription>
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

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-email`}>{t.auth.email}</Label>
            <Input
              id={`${formId}-email`}
              type="email"
              name="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              aria-invalid={Boolean(fieldErrors.email)}
              required
            />
            {fieldErrors.email ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.email}
              </p>
            ) : null}
          </div>

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-password`}>{t.auth.password}</Label>
            <div className="flex gap-2">
              <Input
                id={`${formId}-password`}
                type={showPassword ? "text" : "password"}
                name="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                aria-invalid={Boolean(fieldErrors.password)}
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

          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              className="size-4 accent-[var(--primary)]"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />
            <span>{t.auth.rememberMe}</span>
          </label>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? t.auth.signingIn : t.auth.signIn}
          </Button>
        </form>
      </CardContent>
      <CardFooter className="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
        <Link href="/register" className="underline hover:text-foreground">
          {t.auth.createAccountLink}
        </Link>
        <Link
          href="/forgot-password"
          className="underline hover:text-foreground"
        >
          {t.auth.forgotPasswordLink}
        </Link>
      </CardFooter>
    </Card>
  );
}
