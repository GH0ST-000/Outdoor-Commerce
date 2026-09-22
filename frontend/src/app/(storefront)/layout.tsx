import { SiteHeader } from "@/features/storefront/components/layout/SiteHeader";
import { SiteFooterClient } from "@/features/storefront/components/layout/SiteFooterClient";

export const dynamic = "force-dynamic";

export default function StorefrontLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      <SiteHeader />
      <main id="storefront-main" className="flex-1 outline-none" tabIndex={-1}>
        {children}
      </main>
      <SiteFooterClient />
    </div>
  );
}
