import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Outdoor Commerce",
  description: "Hunting, fishing, and outdoor equipment storefront",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
