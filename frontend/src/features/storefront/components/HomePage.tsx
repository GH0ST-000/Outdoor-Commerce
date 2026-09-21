"use client";

import Link from "next/link";
import Image from "next/image";
import { ArrowRight, ArrowUpRight, ChevronDown } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import { storefrontMedia } from "@/features/storefront/config/media";
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
      <section className="relative isolate min-h-[100svh] overflow-hidden bg-[var(--night-forest)] text-[var(--warm-bone)]">
        <Image
          src={storefrontMedia.hero}
          alt=""
          fill
          priority
          sizes="100vw"
          className="sf-hero-media object-cover object-[center_35%]"
        />
        <div
          aria-hidden
          className="absolute inset-0 bg-[linear-gradient(105deg,rgba(8,12,10,0.92)_0%,rgba(8,12,10,0.72)_38%,rgba(8,12,10,0.35)_62%,rgba(8,12,10,0.55)_100%)]"
        />
        <div
          aria-hidden
          className="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-[var(--night-forest)] via-[var(--night-forest)]/55 to-transparent"
        />

        <div className="sf-container-wide relative flex min-h-[100svh] flex-col justify-end pb-20 pt-28 sm:pb-24">
          <div className="max-w-3xl">
            <p className="sf-display sf-reveal text-[clamp(2.75rem,14vw,7.5rem)] leading-[0.92]">
              {brandName}
            </p>
            <h1 className="sf-display sf-reveal sf-reveal-delay-1 mt-4 max-w-2xl text-[clamp(1.35rem,4.2vw,2.75rem)] text-[var(--warm-bone)]/92 sm:mt-5 sm:min-h-[2.6em]">
              {tagline}
            </h1>
            <p className="sf-reveal sf-reveal-delay-2 mt-4 max-w-xl text-sm leading-relaxed text-[var(--warm-bone)]/72 sm:mt-5 sm:min-h-[4.5em] sm:text-base md:text-lg">
              {support}
            </p>
            <div className="sf-reveal sf-reveal-delay-3 mt-6 flex w-full flex-col gap-3 sm:mt-8 sm:w-auto sm:flex-row sm:flex-wrap">
              <Button
                asChild
                size="lg"
                className="w-full bg-[var(--warm-bone)] text-[var(--night-forest)] hover:bg-white sm:w-auto"
              >
                <Link href="/catalog">
                  {t.home.shopCta}
                  <ArrowRight className="size-4" />
                </Link>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="w-full border-[var(--warm-bone)]/35 bg-transparent text-[var(--warm-bone)] hover:bg-white/10 sm:w-auto"
              >
                <Link href="/field-guide">{t.home.guideCta}</Link>
              </Button>
            </div>
          </div>

          <a
            href="#storefront-categories"
            className="sf-reveal sf-reveal-delay-3 mt-14 inline-flex w-fit items-center gap-2 text-xs font-semibold tracking-[0.16em] text-[var(--warm-bone)]/55 no-underline uppercase transition-colors hover:text-[var(--warm-bone)]"
          >
            <ChevronDown className="size-4 animate-bounce" />
            {locale === "ka" ? "ქვემოთ" : "Scroll"}
          </a>
        </div>
      </section>

      <section
        id="storefront-categories"
        className="sf-section sf-band-paper sf-paper-grain"
      >
        <div className="sf-container">
          <header className="mb-10 max-w-2xl">
            <p className="sf-label text-[var(--moss)]">
              {locale === "ka" ? "კატალოგი" : "Catalog"}
            </p>
            <h2 className="sf-display mt-3 text-3xl sm:text-4xl md:text-5xl">
              {t.home.categoriesTitle}
            </h2>
            <p className="mt-3 max-w-xl text-base leading-relaxed text-[color-mix(in_oklab,var(--charcoal)_62%,transparent)]">
              {t.home.categoriesLead}
            </p>
          </header>

          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4">
            {categoryGateway.map((category, index) => (
              <Link
                key={category.id}
                href={category.href}
                className={
                  index === 0
                    ? "group relative col-span-2 aspect-[16/9] overflow-hidden rounded-[1.35rem] no-underline md:aspect-[21/9]"
                    : "group relative aspect-[4/5] overflow-hidden rounded-[1.35rem] no-underline sm:aspect-[5/6]"
                }
              >
                <Image
                  src={category.imageSrc}
                  alt=""
                  fill
                  sizes="(max-width:768px) 100vw, 40vw"
                  className="object-cover transition-transform duration-[700ms] ease-[var(--ease-out)] group-hover:scale-[1.05]"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-black/10" />
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent px-4 pt-16 pb-4 sm:px-5 sm:pb-5">
                  <p className="text-lg font-semibold text-[var(--warm-bone)] sm:text-xl">
                    {category.name[locale]}
                  </p>
                  <p className="sf-line-clamp-2 mt-1 text-sm leading-snug text-[var(--warm-bone)]/75">
                    {category.label[locale]}
                  </p>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="sf-section sf-band-ink">
        <div className="sf-container">
          <header className="mb-10 flex flex-wrap items-end justify-between gap-5">
            <div className="max-w-xl">
              <p className="sf-label text-[var(--warm-bone)]/45">
                {locale === "ka" ? "შერჩევა" : "Selection"}
              </p>
              <h2 className="sf-display mt-3 text-3xl sm:text-4xl md:text-5xl">
                {t.home.featuredTitle}
              </h2>
              <p className="mt-3 min-h-[3em] text-[var(--warm-bone)]/65">
                {t.home.featuredLead}
              </p>
            </div>
            <Button
              asChild
              variant="outline"
              className="border-[var(--warm-bone)]/25 bg-transparent text-[var(--warm-bone)] hover:bg-white/10"
            >
              <Link href="/catalog">
                {t.nav.catalog}
                <ArrowRight className="size-4" />
              </Link>
            </Button>
          </header>
          <ProductGrid products={featuredProducts} tone="on-ink" />
        </div>
      </section>

      <section className="sf-section sf-band-pine">
        <div className="sf-container space-y-6">
          <header className="max-w-2xl">
            <h2 className="sf-display text-3xl sm:text-4xl">
              {t.home.seasonTitle}
            </h2>
            <p className="mt-3 text-[var(--warm-bone)]/68">
              {t.home.seasonLead}
            </p>
          </header>
          <LegalDemoBanner />
          <div className="grid gap-3 md:grid-cols-3">
            {seasonDemo.map((row) => (
              <article
                key={row.id}
                className="rounded-[1.25rem] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-lg font-semibold">
                      {row.species[locale]}
                    </p>
                    <p className="sf-line-clamp-2 mt-1 min-h-[2.5em] text-sm text-[var(--warm-bone)]/60">
                      {row.region[locale]} · {row.month[locale]}
                    </p>
                  </div>
                  <SeasonStatusBadge status={row.status} />
                </div>
                <p className="sf-line-clamp-2 mt-4 min-h-[2.5em] text-sm text-[var(--warm-bone)]/68">
                  {row.limit[locale]}
                </p>
              </article>
            ))}
          </div>
          <Button
            asChild
            variant="outline"
            className="border-white/25 bg-transparent text-[var(--warm-bone)] hover:bg-white/10"
          >
            <Link href="/hunting-calendar">{t.nav.calendar}</Link>
          </Button>
        </div>
      </section>

      <section className="sf-section sf-band-paper sf-topo">
        <div className="sf-container grid items-center gap-10 lg:grid-cols-2">
          <div>
            <h2 className="sf-display text-3xl sm:text-4xl md:text-5xl">
              {t.home.mapTitle}
            </h2>
            <p className="mt-3 max-w-md min-h-[3em] text-[color-mix(in_oklab,var(--charcoal)_62%,transparent)]">
              {t.home.mapLead}
            </p>
            <Button asChild className="mt-6">
              <Link href="/map">{t.home.mapCta}</Link>
            </Button>
          </div>
          <div className="relative aspect-[5/4] overflow-hidden rounded-[1.5rem] border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] shadow-[0_24px_60px_-28px_rgba(11,15,12,0.55)]">
            <Image
              src={storefrontMedia.map}
              alt=""
              fill
              sizes="(max-width:1024px) 100vw, 50vw"
              className="object-cover"
            />
          </div>
        </div>
      </section>

      <section className="sf-section sf-band-paper">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <h2 className="sf-display text-3xl sm:text-4xl">
              {t.home.brandsTitle}
            </h2>
            <p className="mt-3 min-h-[3em] text-[color-mix(in_oklab,var(--charcoal)_62%,transparent)]">
              {t.home.brandsLead}
            </p>
          </header>
          <ul className="grid gap-3 md:grid-cols-3">
            {brandShowcase.map((brand) => (
              <li key={brand.id}>
                <Link
                  href={brand.href}
                  className="group flex min-h-[7.5rem] items-center justify-between rounded-[1.25rem] border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white/70 px-5 py-5 no-underline transition-[transform,background-color] duration-[var(--duration-control)] hover:-translate-y-0.5 hover:bg-white"
                >
                  <div className="min-w-0 pr-3">
                    <p className="text-lg font-semibold text-[var(--charcoal)]">
                      {brand.name}
                    </p>
                    <p className="sf-line-clamp-2 mt-1 min-h-[2.5em] text-sm text-[color-mix(in_oklab,var(--charcoal)_58%,transparent)]">
                      {brand.focus[locale]}
                    </p>
                  </div>
                  <ArrowUpRight className="size-4 shrink-0 text-[var(--moss)] transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="sf-section sf-band-ink">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <h2 className="sf-display text-3xl sm:text-4xl">
              {t.home.journalTitle}
            </h2>
            <p className="mt-3 min-h-[3em] text-[var(--warm-bone)]/65">
              {t.home.journalLead}
            </p>
          </header>
          <div className="grid gap-5 lg:grid-cols-3">
            {fieldGuides.map((guide) => (
              <article
                key={guide.id}
                className="overflow-hidden rounded-[1.35rem] border border-white/10 bg-white/[0.04]"
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
                  <p className="sf-label text-[var(--warm-bone)]/45">
                    {guide.category[locale]}
                  </p>
                  <h3 className="sf-line-clamp-2 mt-2 min-h-[2.6em] text-xl font-semibold leading-snug text-[var(--warm-bone)]">
                    {guide.title[locale]}
                  </h3>
                  <p className="sf-line-clamp-3 mt-2 min-h-[4.5em] text-sm text-[var(--warm-bone)]/65">
                    {guide.excerpt[locale]}
                  </p>
                  <Link
                    href={guide.href}
                    className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--warm-bone)] no-underline"
                  >
                    {t.fieldGuide.read}
                    <ArrowRight className="size-3.5" />
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="sf-section sf-band-paper border-y border-[color-mix(in_oklab,var(--charcoal)_10%,transparent)]">
        <div className="sf-container">
          <h2 className="sf-display text-3xl sm:text-4xl">
            {t.home.trustTitle}
          </h2>
          <ul className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                ka: "სამართლებრივი კონტექსტი — მკაფიოდ",
              },
              {
                en: "Secure account foundation",
                ka: "უსაფრთხო ანგარიშის საფუძველი",
              },
            ].map((item) => (
              <li
                key={item.en}
                className="min-h-[6.5rem] rounded-[1.15rem] border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white/65 px-4 py-5 text-sm leading-relaxed text-[var(--charcoal)]"
              >
                {item[locale]}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="sf-section sf-band-ink">
        <div className="sf-container max-w-3xl text-center">
          <h2 className="sf-display text-3xl sm:text-4xl">
            {t.home.newsletterTitle}
          </h2>
          <p className="mt-3 min-h-[3em] text-[var(--warm-bone)]/65">
            {t.home.newsletterLead}
          </p>
          <Button
            type="button"
            disabled
            className="mt-6 bg-[var(--warm-bone)] text-[var(--night-forest)]"
            title={t.home.newsletterHint}
          >
            {t.home.newsletterCta}
          </Button>
          <p className="mt-3 text-xs text-[var(--warm-bone)]/45">
            {t.home.newsletterHint}
          </p>
        </div>
      </section>
    </>
  );
}
