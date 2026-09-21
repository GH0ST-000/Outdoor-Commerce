import Link from "next/link";
import Image from "next/image";
import {
  ArrowRight,
  ArrowUpRight,
  ChevronDown,
  Landmark,
  ShieldCheck,
  Trees,
  UserRound,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { JsonLd } from "@/components/seo/json-ld";
import { ErrorState } from "@/components/ui/empty-state";
import { LegalDisclaimer } from "@/components/editorial/primitives";
import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import { STOREFRONT_EVENTS } from "@/features/storefront/analytics/events";
import { TrackedLink } from "@/features/storefront/analytics/tracked-link";
import { storefrontMedia } from "@/features/storefront/config/media";
import { brandConfig } from "@/features/storefront/config/brand";
import { fieldGuides } from "@/features/storefront/fixtures/demo-catalog";
import type { HomePageContent } from "@/features/home/content";
import type { HomePageData } from "@/features/home/api/get-home-page-data";
import { HomeSectionRetry } from "@/features/home/components/home-section-retry";
import {
  organizationJsonLd,
  websiteJsonLd,
} from "@/features/catalog/seo/json-ld";

const trustIcons = {
  authentic: ShieldCheck,
  selection: Trees,
  support: UserRound,
  legal: Landmark,
} as const;

export function HomeView({
  data,
  content,
}: {
  data: HomePageData;
  content: HomePageContent;
}) {
  const { locale } = data;
  const brandName = brandConfig.displayName[locale];

  return (
    <>
      <JsonLd data={organizationJsonLd(locale)} />
      <JsonLd data={websiteJsonLd(locale)} />

      <section className="relative isolate -mt-14 min-h-[100svh] overflow-hidden bg-[#1a1613] text-[var(--warm-bone)] sm:-mt-16 lg:-mt-[4.25rem]">
        <Image
          src={storefrontMedia.hero}
          alt=""
          fill
          priority
          sizes="100vw"
          className="sf-hero-media z-0 object-cover object-[center_28%] brightness-[1.12] saturate-[1.12] contrast-[1.04]"
        />
        <div
          aria-hidden
          className="absolute inset-x-0 top-0 z-[1] h-48 bg-gradient-to-b from-black/70 to-transparent"
        />
        <div
          aria-hidden
          className="absolute inset-x-0 bottom-0 z-[1] h-[58%] bg-gradient-to-t from-[#1a1613] via-[#1a1613]/50 to-transparent"
        />

        <div className="sf-container-wide relative z-[2] flex min-h-[100svh] flex-col justify-end pb-16 pt-28 sm:pb-24">
          <div className="max-w-2xl [text-shadow:0_1px_2px_rgba(0,0,0,0.55),0_10px_28px_rgba(0,0,0,0.45)]">
            <p className="sf-label sf-reveal text-[var(--warm-bone)]/70">
              {content.hero.eyebrow}
            </p>
            <p className="sf-display sf-reveal sf-reveal-delay-1 mt-3 text-[clamp(2.35rem,8vw,5.25rem)] leading-[1.02]">
              {brandName}
            </p>
            <h1 className="sf-reveal sf-reveal-delay-2 mt-4 max-w-xl text-[clamp(1.15rem,3.2vw,1.85rem)] font-medium leading-snug text-[var(--warm-bone)]/92 sm:mt-5">
              {content.hero.headline}
            </h1>
            <p className="sf-reveal sf-reveal-delay-2 mt-3 max-w-lg text-sm leading-relaxed text-[var(--warm-bone)]/74 sm:mt-4 sm:text-base">
              {content.hero.support}
            </p>
            <p className="sf-reveal sf-reveal-delay-2 mt-3 font-mono text-[0.7rem] tracking-wide text-[var(--warm-bone)]/55">
              {content.hero.fieldNote}
            </p>
            <div className="sf-reveal sf-reveal-delay-3 mt-7 flex w-full min-w-0 flex-col gap-3 sm:mt-8 sm:w-auto sm:flex-row sm:flex-wrap">
              <Button
                asChild
                size="lg"
                variant="accent"
                className="w-full min-[320px]:w-full sm:w-auto"
              >
                <TrackedLink
                  href="/catalog"
                  event={STOREFRONT_EVENTS.homepage_cta_clicked}
                  payload={{ href: "/catalog" }}
                >
                  {content.hero.primaryCta}
                  <ArrowRight className="size-4" />
                </TrackedLink>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="w-full border-[var(--warm-bone)]/35 bg-white/5 text-[var(--warm-bone)] backdrop-blur-md hover:bg-white/12 sm:w-auto"
              >
                <TrackedLink
                  href="/field-guide"
                  event={STOREFRONT_EVENTS.homepage_cta_clicked}
                  payload={{ href: "/field-guide" }}
                >
                  {content.hero.secondaryCta}
                </TrackedLink>
              </Button>
            </div>
          </div>

          <a
            href="#storefront-categories"
            className="sf-reveal sf-reveal-delay-3 mt-14 inline-flex w-fit items-center gap-2 text-xs font-semibold text-[var(--warm-bone)]/70 no-underline transition-colors hover:text-[var(--warm-bone)]"
          >
            <ChevronDown className="size-4 motion-reduce:animate-none animate-bounce" />
            {content.hero.scroll}
          </a>
        </div>
      </section>

      <section
        id="storefront-categories"
        className="sf-section sf-band-paper sf-paper-grain"
      >
        <div className="sf-container">
          <header className="mb-10 max-w-2xl">
            <p className="sf-label text-[var(--copper)]">
              {content.categories.eyebrow}
            </p>
            <h2 className="sf-display mt-3 text-3xl sm:text-4xl md:text-5xl">
              {content.categories.title}
            </h2>
            <p className="mt-3 max-w-xl text-base leading-relaxed text-muted-foreground">
              {content.categories.lead}
            </p>
          </header>

          {data.categoriesFailed ? (
            <div className="space-y-3">
              <ErrorState title={content.errors.categories} />
              <HomeSectionRetry label={content.errors.retry} />
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4">
              {data.gateway.map((category, index) => (
                <TrackedLink
                  key={category.slug}
                  href={category.href}
                  event={STOREFRONT_EVENTS.category_opened}
                  payload={{ category: category.slug }}
                  className={
                    index === 0 || category.span === "wide"
                      ? "sf-lift group relative col-span-2 aspect-[16/10] overflow-hidden rounded-[1.5rem] no-underline focus-visible:outline-2 focus-visible:outline-offset-4 md:aspect-[21/9]"
                      : "sf-lift group relative aspect-[4/5] overflow-hidden rounded-[1.5rem] no-underline focus-visible:outline-2 focus-visible:outline-offset-4 sm:aspect-[5/6]"
                  }
                >
                  <Image
                    src={category.imageSrc}
                    alt=""
                    fill
                    sizes="(max-width:768px) 100vw, 40vw"
                    className="object-cover transition-transform duration-[700ms] ease-[var(--ease-out)] group-hover:scale-[1.05] motion-reduce:transform-none"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent transition-opacity duration-500 group-hover:from-black/60" />
                  <div className="absolute inset-x-0 bottom-0 px-4 pt-16 pb-4 sm:px-5 sm:pb-5">
                    <p className="text-lg font-semibold text-[var(--warm-bone)] sm:text-xl">
                      {category.name}
                    </p>
                    {category.description ? (
                      <p className="sf-line-clamp-2 mt-1 text-sm leading-snug text-[var(--warm-bone)]/75">
                        {category.description}
                      </p>
                    ) : null}
                  </div>
                </TrackedLink>
              ))}
            </div>
          )}
        </div>
      </section>

      {data.featuredFailed ? (
        <section className="sf-section sf-band-paper">
          <div className="sf-container flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
              {content.featured.title}
            </p>
            <HomeSectionRetry label={content.errors.retry} />
          </div>
        </section>
      ) : data.featured && data.featured.length > 0 ? (
        <section className="sf-section sf-band-paper">
          <div className="sf-container">
            <header className="mb-10 flex flex-wrap items-end justify-between gap-5">
              <div className="max-w-xl">
                <p className="sf-label text-[var(--copper)]">
                  {content.featured.eyebrow}
                </p>
                <h2 className="sf-display mt-3 text-3xl sm:text-4xl md:text-5xl">
                  {content.featured.title}
                </h2>
                <p className="mt-3 min-h-[3em] text-muted-foreground">
                  {content.featured.lead}
                </p>
              </div>
              <Button asChild variant="outline">
                <Link href="/catalog">
                  {content.featured.catalogCta}
                  <ArrowRight className="size-4" />
                </Link>
              </Button>
            </header>
            <ProductGrid products={data.featured} />
          </div>
        </section>
      ) : null}

      <section className="sf-section relative overflow-hidden bg-[color-mix(in_oklab,var(--charcoal)_94%,black)] text-[var(--warm-bone)]">
        <div className="sf-container grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-center">
          <div>
            <p className="sf-label text-[var(--copper)]">
              {content.season.eyebrow}
            </p>
            <h2 className="sf-display mt-3 text-3xl sm:text-4xl">
              {content.season.title}
            </h2>
            <p className="mt-4 max-w-xl text-xl font-medium leading-snug sm:text-2xl">
              {content.season.question}
            </p>
            <p className="mt-3 max-w-xl text-sm leading-relaxed text-[var(--warm-bone)]/72 sm:text-base">
              {content.season.lead}
            </p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button asChild variant="accent">
                <Link href="/hunting-calendar">
                  {content.season.calendarCta}
                </Link>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled
                className="border-[var(--warm-bone)]/30 text-[var(--warm-bone)]"
                title={content.season.speciesCta}
              >
                {content.season.speciesCta}
              </Button>
            </div>
          </div>
          <div className="rounded-[1.35rem] border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
            <p className="text-[0.7rem] font-semibold tracking-wide text-[var(--copper)]">
              {content.season.previewLabel}
            </p>
            <div className="mt-4 grid gap-2 sm:grid-cols-3">
              {["I", "II", "III"].map((slot) => (
                <div
                  key={slot}
                  className="rounded-xl border border-dashed border-white/20 px-3 py-6 text-center text-xs text-[var(--warm-bone)]/55"
                >
                  {slot}
                </div>
              ))}
            </div>
            <LegalDisclaimer>{content.season.disclaimer}</LegalDisclaimer>
          </div>
        </div>
      </section>

      {data.onSaleFailed ? null : data.onSale && data.onSale.length > 0 ? (
        <section className="sf-section sf-band-paper">
          <div className="sf-container">
            <header className="mb-10 max-w-xl">
              <p className="sf-label text-[var(--copper)]">
                {content.offers.eyebrow}
              </p>
              <h2 className="sf-display mt-3 text-3xl sm:text-4xl">
                {content.offers.title}
              </h2>
              <p className="mt-3 text-muted-foreground">
                {content.offers.lead}
              </p>
            </header>
            <ProductGrid products={data.onSale} />
          </div>
        </section>
      ) : null}

      <section className="sf-section sf-band-paper sf-topo">
        <div className="sf-container grid items-center gap-10 lg:grid-cols-2">
          <div>
            <h2 className="sf-display text-3xl sm:text-4xl md:text-5xl">
              {content.map.title}
            </h2>
            <p className="mt-3 max-w-md min-h-[3em] text-muted-foreground">
              {content.map.lead}
            </p>
            <Button asChild className="mt-6">
              <Link href="/map">{content.map.cta}</Link>
            </Button>
            <div className="mt-4 max-w-md">
              <LegalDisclaimer>{content.map.disclaimer}</LegalDisclaimer>
            </div>
          </div>
          <figure className="relative aspect-[5/4] overflow-hidden rounded-[1.5rem] border border-border shadow-[0_24px_60px_-28px_rgba(11,15,12,0.55)]">
            <Image
              src={storefrontMedia.map}
              alt=""
              fill
              sizes="(max-width:1024px) 100vw, 50vw"
              className="object-cover"
            />
            <figcaption className="absolute top-3 left-3 rounded-full bg-black/55 px-3 py-1 text-[0.7rem] font-semibold tracking-wide text-[var(--warm-bone)]">
              {content.map.previewLabel}
            </figcaption>
          </figure>
        </div>
      </section>

      {data.brandsFailed || !data.brands || data.brands.length === 0 ? null : (
        <section className="sf-section sf-band-paper">
          <div className="sf-container">
            <header className="mb-8 max-w-xl">
              <h2 className="sf-display text-3xl sm:text-4xl">
                {content.brands.title}
              </h2>
              <p className="mt-3 min-h-[3em] text-muted-foreground">
                {content.brands.lead}
              </p>
            </header>
            <ul className="grid gap-3 md:grid-cols-3">
              {data.brands.slice(0, 6).map((brand) => (
                <li key={brand.id}>
                  <Link
                    href={brand.path || `/brands/${brand.slug}`}
                    className="sf-lift group flex min-h-[7.5rem] items-center justify-between rounded-[1.25rem] border border-border bg-card px-5 py-5 no-underline"
                  >
                    <div className="min-w-0 pr-3">
                      <p className="text-lg font-semibold text-foreground">
                        {brand.name}
                      </p>
                      <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                        {brand.product_count}
                      </p>
                    </div>
                    <ArrowUpRight className="size-4 shrink-0 text-[var(--copper)] transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none" />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}

      <section className="sf-section sf-band-paper">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <p className="sf-label text-[var(--copper)]">
              {content.journal.eyebrow}
            </p>
            <h2 className="sf-display mt-3 text-3xl sm:text-4xl">
              {content.journal.title}
            </h2>
            <p className="mt-3 min-h-[3em] text-muted-foreground">
              {content.journal.lead}
            </p>
          </header>
          <div className="grid gap-5 lg:grid-cols-3">
            {fieldGuides.map((guide) => (
              <article
                key={guide.id}
                className="sf-lift group overflow-hidden rounded-[1.35rem] border border-border bg-card"
              >
                <div className="relative aspect-[16/10] overflow-hidden">
                  <Image
                    src={guide.imageSrc}
                    alt=""
                    fill
                    sizes="(max-width:1024px) 100vw, 33vw"
                    className="object-cover transition-transform duration-700 ease-[var(--ease-out)] group-hover:scale-[1.06] motion-reduce:transform-none"
                  />
                </div>
                <div className="p-5">
                  <p className="sf-label text-[var(--copper)]">
                    {guide.category[locale]}
                  </p>
                  <h3 className="sf-line-clamp-2 mt-2 min-h-[2.6em] text-xl font-semibold leading-snug text-foreground">
                    {guide.title[locale]}
                  </h3>
                  <p className="sf-line-clamp-3 mt-2 min-h-[4.5em] text-sm text-muted-foreground">
                    {guide.excerpt[locale]}
                  </p>
                  <Link
                    href={guide.href}
                    className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--copper)] no-underline"
                  >
                    {content.journal.read}
                    <ArrowRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-0.5 motion-reduce:transform-none" />
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="sf-section sf-band-paper border-y border-border">
        <div className="sf-container">
          <h2 className="sf-display text-3xl sm:text-4xl">
            {content.trust.title}
          </h2>
          <ul className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {content.trust.items.map((item) => {
              const Glyph = trustIcons[item.id];
              return (
                <li
                  key={item.id}
                  className="sf-lift min-h-[8rem] rounded-[1.15rem] border border-border bg-card px-4 py-5"
                >
                  <Glyph className="size-5 text-[var(--copper)]" aria-hidden />
                  <p className="mt-3 text-sm font-semibold text-foreground">
                    {item.heading}
                  </p>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                    {item.body}
                  </p>
                </li>
              );
            })}
          </ul>
        </div>
      </section>

      <section className="sf-section relative overflow-hidden bg-[color-mix(in_oklab,var(--copper)_12%,var(--background))]">
        <div className="sf-container relative max-w-3xl text-center">
          <h2 className="sf-display text-3xl text-foreground sm:text-4xl">
            {content.newsletter.title}
          </h2>
          <p className="mt-3 min-h-[3em] text-muted-foreground">
            {content.newsletter.lead}
          </p>
          <Button
            type="button"
            disabled
            variant="accent"
            className="mt-6"
            title={content.newsletter.hint}
          >
            {content.newsletter.cta}
          </Button>
          <p className="mt-3 text-xs text-muted-foreground">
            {content.newsletter.hint}
          </p>
        </div>
      </section>
    </>
  );
}
