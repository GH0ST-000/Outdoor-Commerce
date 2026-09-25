import type { Metadata } from "next";
import {
  Fraunces,
  Inter,
  Manrope,
  Montserrat,
  Noto_Sans_Georgian,
  Noto_Serif_Georgian,
} from "next/font/google";
import { AppProviders } from "@/components/app-providers";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { brandConfig } from "@/features/storefront/config/brand";
import "./globals.css";

const fraunces = Fraunces({
  subsets: ["latin"],
  weight: ["500", "600"],
  variable: "--font-fraunces",
  display: "swap",
});

const manrope = Manrope({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-manrope",
  display: "swap",
});

const montserrat = Montserrat({
  subsets: ["latin", "latin-ext"],
  weight: ["600", "700", "800"],
  variable: "--font-montserrat",
  display: "swap",
});

const inter = Inter({
  subsets: ["latin", "latin-ext"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-inter",
  display: "swap",
});

const notoGeorgian = Noto_Sans_Georgian({
  subsets: ["georgian"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-noto-georgian",
  display: "swap",
});

const notoSerifGeorgian = Noto_Serif_Georgian({
  subsets: ["georgian"],
  weight: ["500", "600"],
  variable: "--font-noto-serif-georgian",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: `${brandConfig.displayName.ka} · აუთდორ კომერცია საქართველოსთვის`,
    template: `%s · ${brandConfig.displayName.ka}`,
  },
  description: brandConfig.support.ka,
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const locale = await getRequestCatalogLocale();

  return (
    <html
      lang={locale}
      suppressHydrationWarning
      className={`${fraunces.variable} ${manrope.variable} ${montserrat.variable} ${inter.variable} ${notoGeorgian.variable} ${notoSerifGeorgian.variable}`}
    >
      <body className="font-[family-name:var(--font-manrope),var(--font-noto-georgian),sans-serif]">
        <AppProviders initialLocale={locale}>{children}</AppProviders>
      </body>
    </html>
  );
}
