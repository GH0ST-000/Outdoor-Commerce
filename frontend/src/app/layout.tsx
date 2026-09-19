import type { Metadata } from "next";
import { Fraunces, Manrope, Noto_Sans_Georgian } from "next/font/google";
import { AppProviders } from "@/components/app-providers";
import { brandConfig } from "@/features/storefront/config/brand";
import "./globals.css";

const fraunces = Fraunces({
  subsets: ["latin"],
  variable: "--font-fraunces",
  display: "swap",
});

const manrope = Manrope({
  subsets: ["latin"],
  variable: "--font-manrope",
  display: "swap",
});

const notoGeorgian = Noto_Sans_Georgian({
  subsets: ["georgian"],
  variable: "--font-noto-georgian",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: `${brandConfig.displayName.en} · Outdoor commerce for Georgia`,
    template: `%s · ${brandConfig.displayName.en}`,
  },
  description: brandConfig.support.en,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`${fraunces.variable} ${manrope.variable} ${notoGeorgian.variable}`}
    >
      <body className="font-[family-name:var(--font-manrope),var(--font-noto-georgian),sans-serif]">
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
