"use client";

import Link from "next/link";
import { useMemo } from "react";
import { ProductGallery } from "@/features/storefront/components/commerce/ProductGallery";
import { VariantSelector } from "@/features/storefront/components/commerce/VariantSelector";
import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import { getProductBySlug } from "@/features/storefront/adapters/fixture-catalog";
import { featuredProducts } from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Button } from "@/components/ui/button";

export function ProductDetailPage({ slug }: { slug: string }) {
  const product = getProductBySlug(slug);
  const { t, locale } = useStorefrontCopy();

  const galleryItems = useMemo(() => {
    if (product?.galleryMedia?.length) {
      return product.galleryMedia;
    }
    if (product?.gallery.length) {
      return product.gallery;
    }
    return product ? [product.imageMedia ?? product.imageSrc] : [];
  }, [product]);

  const related = useMemo(
    () => featuredProducts.filter((item) => item.slug !== slug).slice(0, 3),
    [slug],
  );

  if (!product) {
    return (
      <div className="sf-container sf-section space-y-4">
        <p className="text-destructive" role="alert">
          {t.common.error}
        </p>
        <Button asChild variant="outline">
          <Link href="/catalog">{t.common.back}</Link>
        </Button>
      </div>
    );
  }

  return (
    <div className="sf-section pb-28 md:pb-[var(--space-section)]">
      <div className="sf-container space-y-10">
        <nav aria-label="Breadcrumb" className="text-sm text-muted-foreground">
          <Link href="/catalog" className="no-underline hover:text-foreground">
            {t.nav.catalog}
          </Link>
          <span className="mx-2">/</span>
          <span className="text-foreground">{product.name[locale]}</span>
        </nav>

        <div className="grid gap-8 lg:grid-cols-2 lg:gap-10">
          <ProductGallery
            items={galleryItems}
            alt={product.imageAlt}
            locale={locale}
            ariaLabel={t.product.gallery}
          />

          <div className="space-y-6">
            <div>
              <p className="text-sm font-medium text-muted-foreground">
                {product.brand}
              </p>
              <h1 className="sf-display mt-1 text-3xl sm:text-4xl md:text-5xl">
                {product.name[locale]}
              </h1>
              <p className="mt-4 text-muted-foreground">
                {product.shortDescription[locale]}
              </p>
            </div>

            <dl className="grid grid-cols-2 gap-3 text-sm">
              {product.modelNumber ? (
                <div>
                  <dt className="text-muted-foreground">{t.product.model}</dt>
                  <dd className="font-medium">{product.modelNumber}</dd>
                </div>
              ) : null}
              {product.sku ? (
                <div>
                  <dt className="text-muted-foreground">{t.product.sku}</dt>
                  <dd className="font-medium">{product.sku}</dd>
                </div>
              ) : null}
            </dl>

            <p className="text-lg font-semibold">
              {product.priceLabel?.[locale] ?? t.common.priceOnRequest}
            </p>
            <p className="text-sm text-muted-foreground">
              {product.availabilityLabel?.[locale] ?? t.common.comingSoon}
            </p>

            <VariantSelector axes={product.variants.axes} />

            <div className="space-y-2">
              <Button
                type="button"
                size="lg"
                disabled
                className="w-full sm:w-auto"
              >
                {t.product.notify}
              </Button>
              <p className="text-xs text-muted-foreground">
                {t.product.notifyHint}
              </p>
            </div>

            <div>
              <h2 className="text-lg font-semibold">{t.product.specs}</h2>
              <dl className="mt-3 space-y-2 text-sm">
                {product.specs.map((spec) => (
                  <div
                    key={spec.label.en}
                    className="flex justify-between gap-4 border-b border-border/50 py-2"
                  >
                    <dt className="text-muted-foreground">
                      {spec.label[locale]}
                    </dt>
                    <dd className="font-medium">{spec.value[locale]}</dd>
                  </div>
                ))}
              </dl>
            </div>

            {product.contexts.length > 0 ? (
              <div>
                <h2 className="text-lg font-semibold">{t.product.context}</h2>
                <ul className="mt-3 flex flex-wrap gap-2">
                  {product.contexts.map((context) => (
                    <li
                      key={context.en}
                      className="rounded-lg border border-border/70 bg-muted/40 px-3 py-1.5 text-sm"
                    >
                      {context[locale]}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        </div>

        <section>
          <h2 className="sf-display mb-6 text-3xl">{t.product.related}</h2>
          <ProductGrid products={related} />
        </section>
      </div>

      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border/70 bg-card/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:hidden">
        <div className="flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-semibold">
              {product.priceLabel?.[locale] ?? t.common.priceOnRequest}
            </p>
            <p className="text-xs text-muted-foreground">
              {t.product.notifyHint}
            </p>
          </div>
          <Button type="button" disabled>
            {t.product.notify}
          </Button>
        </div>
      </div>
    </div>
  );
}
