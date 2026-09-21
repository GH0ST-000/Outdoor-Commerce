"use client";

import type { ReactNode } from "react";
import { LocaleProvider } from "@/components/locale-provider";
import type { Locale } from "@/i18n/dictionaries";

export function TestProviders({
  children,
  locale = "en",
}: {
  children: ReactNode;
  locale?: Locale;
}) {
  if (typeof window !== "undefined") {
    window.localStorage.setItem("outdoor-locale", locale);
  }

  return <LocaleProvider>{children}</LocaleProvider>;
}
