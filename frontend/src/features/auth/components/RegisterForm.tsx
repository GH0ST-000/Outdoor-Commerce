"use client";

import { FormEvent, useId, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Eye, EyeOff } from "lucide-react";
import { useAuth } from "@/features/auth/hooks/use-auth";
import { registerSchema } from "@/features/auth/schemas/auth-schemas";
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

export function RegisterForm() {
  const { register } = useAuth();
  const { t } = useLocale();
  const router = useRouter();
  const formId = useId();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
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
    const parsed = registerSchema.safeParse({
      first_name: firstName,
      last_name: lastName,
      email,
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
      await register(parsed.data);
      router.replace("/account");
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
        setFormError(t.auth.unableRegister);
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card className="border-border/60 shadow-sm">
      <CardHeader>
        <CardTitle>{t.auth.registerTitle}</CardTitle>
        <CardDescription>{t.auth.registerLead}</CardDescription>
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
            <Label htmlFor={`${formId}-first-name`}>{t.auth.firstName}</Label>
            <Input
              id={`${formId}-first-name`}
              name="first_name"
              autoComplete="given-name"
              value={firstName}
              onChange={(event) => setFirstName(event.target.value)}
              aria-invalid={Boolean(fieldErrors.first_name)}
              required
            />
            {fieldErrors.first_name ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.first_name}
              </p>
            ) : null}
          </div>

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-last-name`}>{t.auth.lastName}</Label>
            <Input
              id={`${formId}-last-name`}
              name="last_name"
              autoComplete="family-name"
              value={lastName}
              onChange={(event) => setLastName(event.target.value)}
              aria-invalid={Boolean(fieldErrors.last_name)}
              required
            />
            {fieldErrors.last_name ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.last_name}
              </p>
            ) : null}
          </div>

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
                autoComplete="new-password"
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

          <div className="grid gap-2">
            <Label htmlFor={`${formId}-password-confirmation`}>
              {t.auth.confirmPassword}
            </Label>
            <Input
              id={`${formId}-password-confirmation`}
              type={showPassword ? "text" : "password"}
              name="password_confirmation"
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              aria-invalid={Boolean(fieldErrors.password_confirmation)}
              required
            />
            {fieldErrors.password_confirmation ? (
              <p className="text-sm text-destructive" role="alert">
                {fieldErrors.password_confirmation}
              </p>
            ) : null}
          </div>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? t.auth.creatingAccount : t.auth.createAccount}
          </Button>
        </form>
      </CardContent>
      <CardFooter>
        <Link
          href="/login"
          className="text-sm text-muted-foreground underline hover:text-foreground"
        >
          {t.auth.alreadyHaveAccount}
        </Link>
      </CardFooter>
    </Card>
  );
}
