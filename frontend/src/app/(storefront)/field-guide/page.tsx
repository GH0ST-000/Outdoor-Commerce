"use client";

import Image from "next/image";
import Link from "next/link";
import { fieldGuides } from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";

export default function FieldGuidePage() {
  const { t, locale } = useStorefrontCopy();

  return (
    <div className="sf-section">
      <div className="sf-container space-y-8">
        <header className="max-w-2xl">
          <h1 className="sf-display text-4xl sm:text-5xl">
            {t.fieldGuide.title}
          </h1>
          <p className="mt-3 text-muted-foreground">{t.fieldGuide.lead}</p>
        </header>
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          {fieldGuides.map((guide) => (
            <article
              key={guide.id}
              className="overflow-hidden rounded-2xl border border-border/70 bg-card"
            >
              <div className="relative aspect-[16/10]">
                <Image
                  src={guide.imageSrc}
                  alt=""
                  fill
                  sizes="(max-width:1024px) 100vw, 33vw"
                  className="object-cover"
                />
              </div>
              <div className="p-5">
                <p className="sf-label text-muted-foreground">
                  {guide.category[locale]}
                </p>
                <h2 className="mt-2 text-xl font-semibold">
                  {guide.title[locale]}
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                  {guide.excerpt[locale]}
                </p>
                <Link
                  href={guide.href}
                  className="mt-4 inline-flex text-sm font-semibold"
                >
                  {t.fieldGuide.read}
                </Link>
              </div>
            </article>
          ))}
        </div>
      </div>
    </div>
  );
}
