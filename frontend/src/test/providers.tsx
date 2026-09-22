"use client";

import type { ReactNode } from "react";
import { LocaleProvider } from "@/components/locale-provider";
import { CartProvider } from "@/features/cart/providers/CartProvider";
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
    // Sync the cookie before the first child render so locale matches SSR.
    // eslint-disable-next-line react-hooks/immutability -- test harness, not application state
    document.cookie = `outdoor-locale=${locale}; path=/; max-age=31536000; samesite=lax`;
  }

  return (
    <LocaleProvider initialLocale={locale}>
      <CartProvider>{children}</CartProvider>
    </LocaleProvider>
  );
}
