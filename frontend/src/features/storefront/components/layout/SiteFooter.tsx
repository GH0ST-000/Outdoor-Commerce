import Link from "next/link";
import { brandConfig } from "@/features/storefront/config/brand";
import { primaryNav } from "@/features/storefront/fixtures/demo-catalog";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

export function SiteFooter({ locale = "en" }: { locale?: "en" | "ka" }) {
  const t = storefrontCopy[locale];
  const brand = brandConfig.displayName[locale];

  return (
    <footer className="border-t border-border/60 bg-[var(--deep-pine)] text-[#eee9de]">
      <div className="sf-container-wide sf-section grid gap-10 md:grid-cols-[1.2fr_1fr_1fr_1fr]">
        <div>
          <p className="sf-display text-3xl">{brand}</p>
          <p className="mt-3 max-w-sm text-sm leading-relaxed text-[#eee9de]/70">
            {brandConfig.support[locale]}
          </p>
        </div>
        <div>
          <p className="sf-label text-[#eee9de]/45">{t.nav.catalog}</p>
          <ul className="mt-3 space-y-2 text-sm">
            {primaryNav.slice(0, 6).map((item) => (
              <li key={item.id}>
                <Link
                  href={item.href}
                  className="text-[#eee9de]/80 no-underline hover:text-[#eee9de]"
                >
                  {t.nav[item.labelKey]}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <p className="sf-label text-[#eee9de]/45">{t.nav.fieldGuide}</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li>
              <Link
                href="/hunting-calendar"
                className="text-[#eee9de]/80 no-underline hover:text-[#eee9de]"
              >
                {t.nav.calendar}
              </Link>
            </li>
            <li>
              <Link
                href="/map"
                className="text-[#eee9de]/80 no-underline hover:text-[#eee9de]"
              >
                {t.nav.map}
              </Link>
            </li>
            <li>
              <Link
                href="/field-guide"
                className="text-[#eee9de]/80 no-underline hover:text-[#eee9de]"
              >
                {t.nav.fieldGuide}
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <p className="sf-label text-[#eee9de]/45">Legal</p>
          <p className="mt-3 text-sm leading-relaxed text-[#eee9de]/65">
            {brandConfig.legalDemoDisclaimer[locale]}
          </p>
        </div>
      </div>
      <div className="border-t border-white/10 py-4">
        <div className="sf-container-wide flex flex-wrap items-center justify-between gap-2 text-xs text-[#eee9de]/45">
          <p>
            © {new Date().getFullYear()} {brand}
          </p>
          <p>Design sprint storefront · fixture data</p>
        </div>
      </div>
    </footer>
  );
}
