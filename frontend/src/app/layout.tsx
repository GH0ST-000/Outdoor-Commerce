import type { Metadata } from "next";
import {
  Fraunces,
  Manrope,
  Noto_Sans_Georgian,
  Noto_Serif_Georgian,
} from "next/font/google";
import { AppProviders } from "@/components/app-providers";
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

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="ka"
      suppressHydrationWarning
      className={`${fraunces.variable} ${manrope.variable} ${notoGeorgian.variable} ${notoSerifGeorgian.variable}`}
    >
      <body className="font-[family-name:var(--font-manrope),var(--font-noto-georgian),sans-serif]">
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
