"use client";

import { LocaleProvider } from "@/components/locale-provider";

export function TestProviders({ children }: { children: React.ReactNode }) {
  return <LocaleProvider>{children}</LocaleProvider>;
}
