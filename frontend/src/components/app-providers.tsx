"use client";

import { AuthProvider } from "@/features/auth/providers/AuthProvider";
import { CartProvider } from "@/features/cart/providers/CartProvider";
import { LocaleProvider } from "@/components/locale-provider";
import { ThemeProvider } from "@/components/theme-provider";
import { TooltipProvider } from "@/components/ui/tooltip";
import type { Locale } from "@/i18n/dictionaries";

export function AppProviders({
  children,
  initialLocale = "ka",
}: {
  children: React.ReactNode;
  initialLocale?: Locale;
}) {
  return (
    <ThemeProvider
      attribute="class"
      defaultTheme="system"
      enableSystem
      disableTransitionOnChange
    >
      <TooltipProvider>
        <LocaleProvider initialLocale={initialLocale}>
          <AuthProvider>
            <CartProvider>{children}</CartProvider>
          </AuthProvider>
        </LocaleProvider>
      </TooltipProvider>
    </ThemeProvider>
  );
}
