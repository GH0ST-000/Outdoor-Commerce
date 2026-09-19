"use client";

import Link from "next/link";
import { fieldGuides } from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Button } from "@/components/ui/button";

export function FieldGuideArticle({ slug }: { slug: string }) {
  const { t, locale } = useStorefrontCopy();
  const guide = fieldGuides.find((item) => item.slug === slug);

  if (!guide) {
    return (
      <div className="sf-container sf-section space-y-4">
        <p role="alert">{t.common.error}</p>
        <Button asChild variant="outline">
          <Link href="/field-guide">{t.common.back}</Link>
        </Button>
      </div>
    );
  }

  return (
    <article className="sf-section">
      <div className="sf-container max-w-3xl space-y-6">
        <Link
          href="/field-guide"
          className="text-sm text-muted-foreground no-underline hover:text-foreground"
        >
          ← {t.nav.fieldGuide}
        </Link>
        <p className="sf-label text-muted-foreground">{guide.category[locale]}</p>
        <h1 className="sf-display text-4xl sm:text-5xl">{guide.title[locale]}</h1>
        <p className="text-lg leading-relaxed text-muted-foreground">
          {guide.excerpt[locale]}
        </p>
        <p className="leading-relaxed">
          {locale === "ka"
            ? "ეს არის სარედაქციო შაბლონი. სრული სტატიები და წყაროები მოგვიანებით დაემატება."
            : "This is an editorial template. Full articles and sourced content will arrive later."}
        </p>
        <footer className="border-t border-border/60 pt-4 text-sm text-muted-foreground">
          <p>
            {t.fieldGuide.updated}: 2026-09-20
          </p>
          <p className="mt-1">
            {t.fieldGuide.sources}: demo fixture — not authoritative.
          </p>
        </footer>
      </div>
    </article>
  );
}
