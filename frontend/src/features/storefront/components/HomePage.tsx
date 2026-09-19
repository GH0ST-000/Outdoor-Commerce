"use client";

import Link from "next/link";
import Image from "next/image";
import { ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import {
  brandShowcase,
  categoryGateway,
  featuredProducts,
  fieldGuides,
  seasonDemo,
} from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";

export function HomePage() {
  const { t, brandName, tagline, support, locale } = useStorefrontCopy();

  return (
    <>
      <section className="relative isolate min-h-[100svh] overflow-hidden bg-[var(--night-forest)] text-[#eee9de]">
        <Image
          src="/storefront/hero-atmosphere.svg"
          alt=""
          fill
          priority
          sizes="100vw"
          className="object-cover opacity-90"
        />
        <div
          aria-hidden
          className="absolute inset-0 bg-[linear-gradient(105deg,rgba(11,15,12,0.82)_0%,rgba(11,15,12,0.45)_55%,rgba(11,15,12,0.7)_100%)]"
        />
        <div className="sf-container-wide relative flex min-h-[100svh] flex-col justify-end pb-16 pt-28 sm:pb-20">
          <p className="sf-display sf-reveal text-5xl sm:text-6xl md:text-7xl lg:text-8xl">
            {brandName}
          </p>
          <h1 className="sf-display sf-reveal sf-reveal-delay-1 mt-4 max-w-3xl text-3xl text-[#eee9de]/95 sm:text-4xl md:text-5xl">
            {tagline}
          </h1>
          <p className="sf-reveal sf-reveal-delay-2 mt-5 max-w-xl text-base leading-relaxed text-[#eee9de]/75 sm:text-lg">
            {support}
          </p>
          <div className="sf-reveal sf-reveal-delay-3 mt-8 flex flex-wrap gap-3">
            <Button asChild size="lg" className="bg-[#eee9de] text-[var(--night-forest)] hover:bg-white">
              <Link href="/catalog">{t.home.shopCta}</Link>
            </Button>
            <Button
              asChild
              size="lg"
              variant="outline"
              className="border-[#eee9de]/35 bg-transparent text-[#eee9de] hover:bg-white/10"
            >
              <Link href="/field-guide">{t.home.guideCta}</Link>
            </Button>
          </div>
        </div>
      </section>

      <section className="sf-section sf-paper-grain bg-[var(--paper)]">
        <div className="sf-container">
          <header className="mb-8 max-w-2xl">
            <h2 className="sf-display text-3xl sm:text-4xl">
              {t.home.categoriesTitle}
            </h2>
            <p className="mt-3 text-muted-foreground">{t.home.categoriesLead}</p>
          </header>
          <div className="grid auto-rows-[180px] grid-cols-2 gap-3 md:auto-rows-[220px] md:grid-cols-4 md:gap-4">
            {categoryGateway.map((category) => (
              <Link
                key={category.id}
                href={category.href}
                className={
                  category.span === "wide"
                    ? "relative col-span-2 overflow-hidden rounded-2xl no-underline md:row-span-1"
                    : category.span === "tall"
                      ? "relative col-span-1 row-span-2 overflow-hidden rounded-2xl no-underline"
                      : "relative overflow-hidden rounded-2xl no-underline"
                }
              >
                <Image
                  src={category.imageSrc}
                  alt=""
                  fill
                  sizes="(max-width:768px) 50vw, 25vw"
                  className="object-cover transition-transform duration-[var(--duration-panel)] hover:scale-[1.04]"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-4 text-[#eee9de]">
                  <p className="text-lg font-semibold sm:text-xl">
                    {category.name[locale]}
                  </p>
                  <p className="mt-1 text-sm text-[#eee9de]/75">
                    {category.label[locale]}
                  </p>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="sf-section">
        <div className="sf-container">
          <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div className="max-w-xl">
              <h2 className="sf-display text-3xl sm:text-4xl">
                {t.home.featuredTitle}
              </h2>
              <p className="mt-3 text-muted-foreground">{t.home.featuredLead}</p>
            </div>
            <Button asChild variant="outline">
              <Link href="/catalog">{t.nav.catalog}</Link>
            </Button>
          </header>
          <ProductGrid products={featuredProducts} />
        </div>
      </section>

      <section className="sf-section bg-[var(--deep-pine)] text-[#eee9de]">
        <div className="sf-container space-y-6">
          <header className="max-w-2xl">
            <h2 className="sf-display text-3xl sm:text-4xl">
              {t.home.seasonTitle}
            </h2>
            <p className="mt-3 text-[#eee9de]/7">{t.home.seasonLead}</p>
          </header>
          <LegalDemoBanner />
          <div className="grid gap-3 md:grid-cols-3">
            {seasonDemo.map((row) => (
              <article
                key={row.id}
                className="rounded-2xl border border-white/10 bg-white/5 p-5"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-lg font-semibold">{row.species[locale]}</p>
                    <p className="mt-1 text-sm text-[#eee9de]/65">
                      {row.region[locale]} · {row.month[locale]}
                    </p>
                  </div>
                  <SeasonStatusBadge status={row.status} />
                </div>
                <p className="mt-4 text-sm text-[#eee9de]/7">{row.limit[locale]}</p>
              </article>
            ))}
          </div>
          <Button asChild variant="outline" className="border-white/25 text-[#eee9de]">
            <Link href="/hunting-calendar">{t.nav.calendar}</Link>
          </Button>
        </div>
      </section>

      <section className="sf-section sf-topo">
        <div className="sf-container grid items-center gap-8 lg:grid-cols-2">
          <div>
            <h2 className="sf-display text-3xl sm:text-4xl">{t.home.mapTitle}</h2>
            <p className="mt-3 max-w-md text-muted-foreground">{t.home.mapLead}</p>
            <Button asChild className="mt-6">
              <Link href="/map">{t.home.mapCta}</Link>
            </Button>
          </div>
          <div className="relative aspect-[5/4] overflow-hidden rounded-2xl border border-border/70">
            <Image
              src="/storefront/map-surface.svg"
              alt=""
              fill
              sizes="(max-width:1024px) 100vw, 50vw"
              className="object-cover"
            />
          </div>
        </div>
      </section>

      <section className="sf-section bg-[var(--paper)]">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <h2 className="sf-display text-3xl">{t.home.brandsTitle}</h2>
            <p className="mt-3 text-muted-foreground">{t.home.brandsLead}</p>
          </header>
          <ul className="grid gap-3 md:grid-cols-3">
            {brandShowcase.map((brand) => (
              <li key={brand.id}>
                <Link
                  href={brand.href}
                  className="group flex items-center justify-between rounded-2xl border border-border/70 bg-card px-5 py-5 no-underline transition-colors hover:bg-muted/40"
                >
                  <div>
                    <p className="text-lg font-semibold text-foreground">
                      {brand.name}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {brand.focus[locale]}
                    </p>
                  </div>
                  <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="sf-section">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <h2 className="sf-display text-3xl">{t.home.journalTitle}</h2>
            <p className="mt-3 text-muted-foreground">{t.home.journalLead}</p>
          </header>
          <div className="grid gap-5 lg:grid-cols-3">
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
                  <h3 className="mt-2 text-xl font-semibold leading-snug">
                    {guide.title[locale]}
                  </h3>
                  <p className="mt-2 text-sm text-muted-foreground">
                    {guide.excerpt[locale]}
                  </p>
                  <Link
                    href={guide.href}
                    className="mt-4 inline-flex text-sm font-semibold text-foreground"
                  >
                    {t.fieldGuide.read}
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="sf-section border-y border-border/60 bg-[var(--paper)]">
        <div className="sf-container">
          <h2 className="sf-display text-3xl">{t.home.trustTitle}</h2>
          <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[
              {
                en: "Authentic equipment selection",
                ka: "ავთენტური აღჭურვილობის შერჩევა",
              },
              {
                en: "Field-informed curation",
                ka: "საველე ცოდნაზე დაფუძნებული კურაცია",
              },
              {
                en: "Legal context, clearly labeled",
                ka: "სამართლებრივი კონტექსტი — მკაფიოდ მონიშნული",
              },
              {
                en: "Secure account foundation",
                ka: "უსაფრთხო ანგარიშის საფუძველი",
              },
            ].map((item) => (
              <li
                key={item.en}
                className="rounded-2xl border border-border/60 bg-card/80 px-4 py-5 text-sm leading-relaxed"
              >
                {item[locale]}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="sf-section bg-[var(--night-forest)] text-[#eee9de]">
        <div className="sf-container max-w-3xl text-center">
          <h2 className="sf-display text-3xl sm:text-4xl">
            {t.home.newsletterTitle}
          </h2>
          <p className="mt-3 text-[#eee9de]/7">{t.home.newsletterLead}</p>
          <Button
            type="button"
            disabled
            className="mt-6 bg-[#eee9de] text-[var(--night-forest)]"
            title={t.home.newsletterHint}
          >
            {t.home.newsletterCta}
          </Button>
          <p className="mt-3 text-xs text-[#eee9de]/5">{t.home.newsletterHint}</p>
        </div>
      </section>
    </>
  );
}
