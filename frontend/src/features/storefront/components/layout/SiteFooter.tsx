import Link from "next/link";
import { brandConfig } from "@/features/storefront/config/brand";
import { primaryNav } from "@/features/storefront/fixtures/demo-catalog";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

export function SiteFooter({ locale = "en" }: { locale?: "en" | "ka" }) {
  const t = storefrontCopy[locale];
  const brand = brandConfig.displayName[locale];

  return (
    <footer className="border-t border-white/10 bg-[var(--espresso)] text-[var(--warm-bone)]">
      <div className="sf-container-wide sf-section grid gap-10 md:grid-cols-[1.2fr_1fr_1fr_1fr]">
        <div>
          <p className="sf-display text-3xl">{brand}</p>
          <p className="mt-3 max-w-sm text-sm leading-relaxed text-[var(--warm-bone)]/70">
            {brandConfig.support[locale]}
          </p>
        </div>
        <div>
          <p className="sf-label text-[var(--warm-bone)]/45">{t.nav.catalog}</p>
          <ul className="mt-3 space-y-2 text-sm">
            {primaryNav.slice(0, 6).map((item) => (
              <li key={item.id}>
                <Link
                  href={item.href}
                  className="text-[var(--warm-bone)]/80 no-underline transition-colors hover:text-[var(--warm-bone)]"
                >
                  {t.nav[item.labelKey]}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <p className="sf-label text-[var(--warm-bone)]/45">
            {t.nav.fieldGuide}
          </p>
          <ul className="mt-3 space-y-2 text-sm">
            <li>
              <Link
                href="/hunting-calendar"
                className="text-[var(--warm-bone)]/80 no-underline transition-colors hover:text-[var(--warm-bone)]"
              >
                {t.nav.calendar}
              </Link>
            </li>
            <li>
              <Link
                href="/map"
                className="text-[var(--warm-bone)]/80 no-underline transition-colors hover:text-[var(--warm-bone)]"
              >
                {t.nav.map}
              </Link>
            </li>
            <li>
              <Link
                href="/field-guide"
                className="text-[var(--warm-bone)]/80 no-underline transition-colors hover:text-[var(--warm-bone)]"
              >
                {t.nav.fieldGuide}
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <p className="sf-label text-[var(--warm-bone)]/45">
            {locale === "ka" ? "სამართლებრივი" : "Legal"}
          </p>
          <p className="mt-3 text-sm leading-relaxed text-[var(--warm-bone)]/65">
            {brandConfig.legalDemoDisclaimer[locale]}
          </p>
        </div>
      </div>
      <div className="border-t border-white/10 py-4">
        <div className="sf-container-wide flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--warm-bone)]/45">
          <p>
            © {new Date().getFullYear()} {brand}
          </p>
          <p>
            {locale === "ka"
              ? "საქართველოს საველე კატალოგი"
              : "Georgian field catalog"}
          </p>
        </div>
      </div>
    </footer>
  );
}
