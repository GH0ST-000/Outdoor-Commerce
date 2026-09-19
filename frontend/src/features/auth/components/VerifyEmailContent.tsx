"use client";

import Link from "next/link";
import { useLocale } from "@/components/locale-provider";
import {
  Card,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function VerifyEmailContent({ status }: { status: string }) {
  const { t } = useLocale();

  const message =
    status === "success"
      ? t.auth.verifySuccess
      : status === "already"
        ? t.auth.verifyAlready
        : status === "error"
          ? t.auth.verifyError
          : t.auth.verifyUnknown;

  return (
    <Card className="border-border/60 shadow-sm">
      <CardHeader>
        <CardTitle>{t.auth.verifyTitle}</CardTitle>
        <CardDescription role="status">{message}</CardDescription>
      </CardHeader>
      <CardFooter className="flex flex-wrap gap-4 text-sm">
        <Link href="/account" className="underline hover:text-foreground">
          {t.auth.goToAccount}
        </Link>
        <Link href="/login" className="underline hover:text-foreground">
          {t.auth.signIn}
        </Link>
      </CardFooter>
    </Card>
  );
}
