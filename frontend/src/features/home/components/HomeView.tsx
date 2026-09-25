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
  return (
    <>
      <JsonLd data={organizationJsonLd(data.locale)} />
      <JsonLd data={websiteJsonLd(data.locale)} />

      <section className="relative isolate -mt-14 min-h-[100svh] overflow-hidden bg-[var(--alpine-slate)] text-[var(--mist)] sm:-mt-16 lg:-mt-[4.25rem]">
        <Image
          src={storefrontMedia.hero}
          alt=""
          fill
          priority
          sizes="100vw"
          className="sf-hero-media z-0 object-cover object-[center_28%] brightness-[1.05] saturate-[1.05] contrast-[1.04]"
        />
        <div
          aria-hidden
          className="absolute inset-x-0 top-0 z-[1] h-40 bg-gradient-to-b from-black/55 to-transparent"
        />
        <div
          aria-hidden
          className="absolute inset-0 z-[1] bg-gradient-to-t from-[var(--alpine-slate)] via-[var(--alpine-slate)]/45 to-black/20"
        />

        <div className="sf-container-wide relative z-[2] flex min-h-[100svh] flex-col justify-end pb-16 pt-28 sm:pb-24">
          <div className="max-w-3xl [text-shadow:0_1px_2px_rgba(0,0,0,0.55),0_10px_28px_rgba(0,0,0,0.45)]">
            <p className="sf-label sf-reveal text-[var(--sand)]">
              {content.hero.eyebrow}
            </p>
            <h1 className="sf-display sf-reveal sf-reveal-delay-1 mt-4 text-[clamp(2.75rem,9vw,6.25rem)] leading-[0.92]">
              {content.hero.headline}
            </h1>
            <p className="sf-reveal sf-reveal-delay-2 mt-5 max-w-lg text-sm leading-relaxed text-[var(--mist)]/80 sm:mt-6 sm:text-base">
              {content.hero.support}
            </p>
            <div className="sf-reveal sf-reveal-delay-3 mt-8 flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
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
                className="w-full border-[var(--mist)]/35 bg-white/5 text-[var(--mist)] backdrop-blur-md hover:bg-white/12 sm:w-auto"
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
            className="sf-reveal sf-reveal-delay-3 mt-16 inline-flex w-fit items-center gap-2 text-xs font-semibold text-[var(--mist)]/70 no-underline transition-colors hover:text-[var(--mist)]"
          >
            <ChevronDown className="size-4 motion-reduce:animate-none animate-bounce" />
            {content.hero.scroll}
          </a>
        </div>
      </section>

      <section id="storefront-categories" className="sf-section">
        <div className="sf-container">
          <header className="mb-10 max-w-2xl">
            <p className="sf-label text-[var(--sand)]">
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
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
              {data.gateway.map((category) => (
                <TrackedLink
                  key={category.slug}
                  href={category.href}
                  event={STOREFRONT_EVENTS.category_opened}
                  payload={{ category: category.slug }}
                  className="sf-lift group relative aspect-[4/5] overflow-hidden rounded-[var(--radius-xl)] no-underline focus-visible:outline-2 focus-visible:outline-offset-4 sm:aspect-[5/6]"
                >
                  <Image
                    src={category.imageSrc}
                    alt=""
                    fill
                    sizes="(max-width:768px) 50vw, 25vw"
                    className="object-cover transition-transform duration-[700ms] ease-[var(--ease-out)] group-hover:scale-[1.05] motion-reduce:transform-none"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent transition-opacity duration-500 group-hover:from-black/65" />
                  <div className="absolute inset-x-0 bottom-0 px-4 pt-16 pb-4 sm:px-5 sm:pb-5">
                    <p className="text-lg font-semibold text-[var(--mist)] sm:text-xl">
                      {category.name}
                    </p>
                    {category.description ? (
                      <p className="sf-line-clamp-2 mt-1 text-sm leading-snug text-[var(--mist)]/75">
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

      <section className="relative isolate overflow-hidden">
        <div className="relative min-h-[28rem] sm:min-h-[34rem]">
          <Image
            src={storefrontMedia.season}
            alt=""
            fill
            sizes="100vw"
            className="object-cover object-center"
          />
          <div
            aria-hidden
            className="absolute inset-0 bg-[linear-gradient(90deg,rgba(26,26,26,0.82)_0%,rgba(26,26,26,0.45)_55%,rgba(26,26,26,0.2)_100%)]"
          />
          <div className="sf-container relative flex min-h-[28rem] flex-col justify-end py-12 sm:min-h-[34rem] sm:py-16">
            <p className="sf-label text-[var(--sand)]">
              {content.season.eyebrow}
            </p>
            <h2 className="sf-display mt-3 max-w-xl text-3xl sm:text-4xl md:text-5xl">
              {content.season.title}
            </h2>
            <p className="mt-4 max-w-lg text-sm leading-relaxed text-[var(--mist)]/80 sm:text-base">
              {content.season.question}
            </p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button asChild variant="primary">
                <Link href="/seasons">{content.season.calendarCta}</Link>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled
                className="border-[var(--mist)]/30 text-[var(--mist)]"
                title={content.season.speciesCta}
              >
                {content.season.speciesCta}
              </Button>
            </div>
            <div className="mt-5 max-w-lg">
              <LegalDisclaimer>{content.season.disclaimer}</LegalDisclaimer>
            </div>
          </div>
        </div>
      </section>

      {data.featuredFailed ? (
        <section className="sf-section">
          <div className="sf-container flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
              {content.featured.title}
            </p>
            <HomeSectionRetry label={content.errors.retry} />
          </div>
        </section>
      ) : data.featured && data.featured.length > 0 ? (
        <section className="sf-section">
          <div className="sf-container">
            <header className="mb-10 flex flex-wrap items-end justify-between gap-5">
              <div className="max-w-xl">
                <p className="sf-label text-[var(--sand)]">
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

      {data.onSaleFailed ? null : data.onSale && data.onSale.length > 0 ? (
        <section className="sf-section">
          <div className="sf-container">
            <header className="mb-10 max-w-xl">
              <p className="sf-label text-[var(--sand)]">
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

      <section className="sf-section sf-topo">
        <div className="sf-container grid items-center gap-10 lg:grid-cols-2">
          <div>
            <p className="sf-label text-[var(--sand)]">
              {content.map.previewLabel}
            </p>
            <h2 className="sf-display mt-3 text-3xl sm:text-4xl md:text-5xl">
              {content.map.title}
            </h2>
            <p className="mt-3 max-w-md min-h-[3em] text-muted-foreground">
              {content.map.lead}
            </p>
            <Button asChild variant="primary" className="mt-6">
              <Link href="/map">{content.map.cta}</Link>
            </Button>
            <div className="mt-4 max-w-md">
              <LegalDisclaimer>{content.map.disclaimer}</LegalDisclaimer>
            </div>
          </div>
          <figure className="relative aspect-[5/4] overflow-hidden rounded-[var(--radius-xl)] border border-border">
            <Image
              src={storefrontMedia.map}
              alt=""
              fill
              sizes="(max-width:1024px) 100vw, 50vw"
              className="object-cover"
            />
            <figcaption className="absolute top-3 left-3 rounded-md bg-black/55 px-3 py-1 text-[0.7rem] font-semibold tracking-wide text-[var(--mist)]">
              {content.map.previewLabel}
            </figcaption>
          </figure>
        </div>
      </section>

      {data.brandsFailed || !data.brands || data.brands.length === 0 ? null : (
        <section className="sf-section">
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
                    className="sf-lift group flex min-h-[7.5rem] items-center justify-between rounded-[var(--radius-xl)] border border-border bg-card px-5 py-5 no-underline"
                  >
                    <div className="min-w-0 pr-3">
                      <p className="text-lg font-semibold text-foreground">
                        {brand.name}
                      </p>
                      <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                        {brand.product_count}
                      </p>
                    </div>
                    <ArrowUpRight className="size-4 shrink-0 text-[var(--sand)] transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none" />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}

      <section className="sf-section">
        <div className="sf-container">
          <header className="mb-8 max-w-xl">
            <p className="sf-label text-[var(--sand)]">
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
                className="sf-lift group overflow-hidden rounded-[var(--radius-xl)] border border-border bg-card"
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
                  <p className="sf-label text-[var(--sand)]">
                    {guide.category[data.locale]}
                  </p>
                  <h3 className="sf-line-clamp-2 mt-2 min-h-[2.6em] text-xl font-semibold leading-snug text-foreground">
                    {guide.title[data.locale]}
                  </h3>
                  <p className="sf-line-clamp-3 mt-2 min-h-[4.5em] text-sm text-muted-foreground">
                    {guide.excerpt[data.locale]}
                  </p>
                  <Link
                    href={guide.href}
                    className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--sand)] no-underline"
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

      <section className="sf-section border-y border-border">
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
                  className="sf-lift min-h-[8rem] rounded-[var(--radius-xl)] border border-border bg-card px-4 py-5"
                >
                  <Glyph className="size-5 text-[var(--olive)]" aria-hidden />
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

      <section className="sf-section relative overflow-hidden bg-[var(--alpine-raised)]">
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
            variant="primary"
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
