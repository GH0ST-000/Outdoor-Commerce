"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Breadcrumbs } from "@/components/navigation/breadcrumbs";
import { AvailabilityStatus } from "@/components/commerce/availability-status";
import { StatusBadge } from "@/components/commerce/status-badge";
import { Button } from "@/components/ui/button";
import { ProductGallery } from "@/features/storefront/components/commerce/ProductGallery";
import { availabilityCopy } from "@/features/catalog/adapters/public-catalog-adapter";
import { absoluteUrl } from "@/features/catalog/seo/catalog-seo";
import type {
  PublicBrandDetail,
  PublicProductDetail,
} from "@/features/catalog/types/public-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import {
  getCartGateway,
  isCartEnabled,
} from "@/features/product-detail/cart/cart-gateway";
import { useCartCopy } from "@/features/cart/copy";
import { ApiClientError } from "@/lib/api-client";
import {
  buildPurchaseIntent,
  clampPurchaseQuantity,
} from "@/features/product-detail/cart/purchase-intent";
import { useProductVariantSelection } from "@/features/product-detail/hooks/use-product-variant-selection";
import { MobilePurchaseBar } from "@/features/product-detail/components/MobilePurchaseBar";
import {
  ProductBrandSection,
  ProductOutdoorContextSlot,
} from "@/features/product-detail/components/ProductBrandSection";
import { ProductDescription } from "@/features/product-detail/components/ProductDescription";
import { ProductPricePanel } from "@/features/product-detail/components/ProductPricePanel";
import { ProductPurchasePanel } from "@/features/product-detail/components/ProductPurchasePanel";
import { ProductSpecifications } from "@/features/product-detail/components/ProductSpecifications";
import { ProductVariantSelector } from "@/features/product-detail/components/ProductVariantSelector";
import { RelatedProducts } from "@/features/product-detail/components/RelatedProducts";
import { mediaForSelectedVariant } from "@/features/product-detail/utils/gallery-media";

async function shareUrl(
  url: string,
  title: string,
): Promise<"shared" | "copied" | "failed"> {
  if (
    typeof navigator !== "undefined" &&
    typeof navigator.share === "function"
  ) {
    try {
      await navigator.share({ title, url });
      return "shared";
    } catch {
      // cancelled or unsupported — try clipboard
    }
  }
  try {
    await navigator.clipboard.writeText(url);
    return "copied";
  } catch {
    return "failed";
  }
}

export function ProductDetailPage({
  slug,
  detail,
  related = [],
  brandDetail = null,
  initialVariantId = null,
  error,
}: {
  slug: string;
  detail?: PublicProductDetail;
  related?: ProductCardData[];
  brandDetail?: PublicBrandDetail | null;
  initialVariantId?: number | null;
  error?: { code: string; message: string };
}) {
  const { t } = useStorefrontCopy();

  if (error || !detail) {
    return (
      <div className="sf-container sf-section" data-product-slug={slug}>
        <p>{t.product.notFound}</p>
      </div>
    );
  }

  return (
    <ProductDetailExperience
      detail={detail}
      related={related}
      brandDetail={brandDetail}
      initialVariantId={initialVariantId}
    />
  );
}

function ProductDetailExperience({
  detail,
  related,
  brandDetail,
  initialVariantId,
}: {
  detail: PublicProductDetail;
  related: ProductCardData[];
  brandDetail: PublicBrandDetail | null;
  initialVariantId: number | null;
}) {
  const { locale, t } = useStorefrontCopy();
  const cartCopy = useCartCopy();
  const router = useRouter();
  const pathname = usePathname();
  const localeRef = useRef(locale);
  const { selectedVariant, selection, index, selectValue } =
    useProductVariantSelection(detail, initialVariantId);
  const [quantity, setQuantity] = useState(1);
  const [shareFeedback, setShareFeedback] = useState<string | null>(null);
  const [purchasePending, setPurchasePending] = useState(false);
  const [purchaseError, setPurchaseError] = useState<string | null>(null);

  const gallery = mediaForSelectedVariant(detail.gallery, selectedVariant);
  const availability = selectedVariant?.availability ?? detail.availability;
  const availabilityLabel = availabilityCopy(availability.status)[locale];
  const hasPrice =
    selectedVariant?.price.final_amount_minor != null &&
    Number.isInteger(selectedVariant.price.final_amount_minor);
  const purchasable = Boolean(availability.purchasable && hasPrice);
  const numberLocale = locale === "ka" ? "ka-GE" : "en-GE";
  const displayedQuantity = purchasable ? clampPurchaseQuantity(quantity) : 1;

  function handlePurchase(): void {
    if (!selectedVariant || !isCartEnabled() || purchasePending) {
      return;
    }
    setPurchaseError(null);
    setPurchasePending(true);
    void getCartGateway()
      .addToCart(
        buildPurchaseIntent(
          detail,
          selectedVariant.id,
          displayedQuantity,
          selectedVariant.price.signature,
        ),
      )
      .catch((error: unknown) => {
        setPurchaseError(
          error instanceof ApiClientError ? error.message : t.common.error,
        );
      })
      .finally(() => {
        setPurchasePending(false);
      });
  }

  useEffect(() => {
    if (localeRef.current === locale) {
      return;
    }
    localeRef.current = locale;
    const alternate = detail.alternate_locale_paths[locale];
    if (!alternate || alternate === pathname) {
      return;
    }
    const next =
      selectedVariant != null
        ? `${alternate}?variant=${selectedVariant.id}`
        : alternate;
    router.replace(next, { scroll: false });
  }, [
    detail.alternate_locale_paths,
    locale,
    pathname,
    router,
    selectedVariant,
  ]);

  const crumbs = [
    { href: "/", label: t.product.home },
    ...detail.breadcrumbs.map((item) => ({
      href: item.path,
      label: item.name,
    })),
    { label: detail.name ?? "" },
  ];

  const relatedItems = related
    .filter((item) => item.id !== String(detail.id))
    .slice(0, 4);
  const shareHref = selectedVariant
    ? `${detail.canonical_path}?variant=${selectedVariant.id}`
    : detail.canonical_path;

  return (
    <div className="sf-band-paper pb-28 lg:pb-16">
      <div className="sf-container pt-6 pb-8 md:pt-8">
        <Breadcrumbs label={t.product.breadcrumbs} items={crumbs} />
      </div>

      <div className="sf-container grid items-start gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(20rem,0.95fr)] lg:gap-12">
        <ProductGallery
          items={gallery}
          alt={{
            en: detail.name ?? "",
            ka: detail.name ?? "",
          }}
          locale={locale}
          ariaLabel={t.product.gallery}
          previousLabel={t.product.galleryPrevious}
          nextLabel={t.product.galleryNext}
          openLabel={t.product.galleryOpen}
          closeLabel={t.product.galleryClose}
          missingLabel={t.product.galleryMissing}
        />

        <div className="space-y-5 lg:sticky lg:top-24">
          <div className="space-y-2">
            {detail.brand?.name ? (
              <Link
                href={detail.brand.path}
                className="text-xs font-semibold tracking-[0.08em] text-muted-foreground no-underline"
              >
                {detail.brand.name}
              </Link>
            ) : null}
            <h1 className="sf-display text-3xl leading-tight md:text-4xl">
              {detail.name}
            </h1>
            <div className="flex flex-wrap gap-2">
              {detail.is_featured ? (
                <StatusBadge kind="featured">{t.product.featured}</StatusBadge>
              ) : null}
              {selectedVariant?.price.on_sale ? (
                <StatusBadge kind="sale">{t.product.onSale}</StatusBadge>
              ) : null}
            </div>
            {detail.short_description ? (
              <p className="max-w-prose text-sm leading-relaxed text-muted-foreground">
                {detail.short_description}
              </p>
            ) : null}
            {detail.model_number ? (
              <p className="text-sm text-muted-foreground">
                {t.product.model}: {detail.model_number}
              </p>
            ) : null}
          </div>

          <ProductPricePanel
            variant={selectedVariant}
            productPrice={detail.price}
            locale={locale}
            missingLabel={t.common.priceOnRequest}
            wasLabel={t.product.priceWas}
            nowLabel={t.product.priceNow}
          />

          <div aria-live="polite" className="sr-only">
            {hasPrice && selectedVariant
              ? `${selectedVariant.sku}`
              : t.common.priceOnRequest}
          </div>

          {selectedVariant ? (
            <p className="text-sm text-muted-foreground">
              <span>{t.product.sku}: </span>
              <span data-testid="product-sku">{selectedVariant.sku}</span>
            </p>
          ) : null}

          <ProductVariantSelector
            axes={detail.variants.axes}
            index={index}
            selection={selection}
            onSelect={selectValue}
            invalidLabel={t.product.invalidCombination}
            outOfStockLabel={t.product.outOfStockOption}
          />

          <div aria-live="polite">
            <AvailabilityStatus
              status={availability.status}
              label={availabilityLabel}
              className="text-sm"
            />
          </div>

          <ProductPurchasePanel
            purchasable={purchasable}
            hasPrice={hasPrice}
            quantity={displayedQuantity}
            onQuantityChange={setQuantity}
            pending={purchasePending}
            error={purchaseError}
            onPurchase={handlePurchase}
            labels={{
              quantity: t.product.quantity,
              decrease: t.product.quantityDecrease,
              increase: t.product.quantityIncrease,
              addToCart: t.product.addToCart,
              cartSoon: t.product.cartSoon,
              unavailable: t.product.unavailableAction,
              hint: t.product.notifyHint,
              disabledHint: t.product.purchaseDisabledHint,
              adding: cartCopy.adding,
            }}
          />

          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={async () => {
              const result = await shareUrl(
                absoluteUrl(shareHref),
                detail.name ?? "",
              );
              setShareFeedback(
                result === "failed"
                  ? t.product.shareFailed
                  : t.product.shareCopied,
              );
            }}
          >
            {t.product.share}
          </Button>
          {shareFeedback ? (
            <p className="text-sm text-muted-foreground" role="status">
              {shareFeedback}
            </p>
          ) : null}
        </div>
      </div>

      <div className="sf-container mt-12 space-y-12 md:mt-16">
        <ProductDescription
          html={detail.description}
          heading={t.product.description}
        />
        <ProductSpecifications
          detail={detail}
          variant={selectedVariant}
          heading={t.product.specs}
          labels={{
            brand: t.product.brandHeading,
            model: t.product.model,
            sku: t.product.sku,
            category: t.product.categoryLabel,
            variant: t.product.selectVariant,
          }}
        />
        <ProductBrandSection
          brand={brandDetail}
          heading={t.product.brandHeading}
          moreLabel={t.product.moreFromBrand}
          websiteLabel={t.product.visitBrand}
        />
        <ProductOutdoorContextSlot />
        <RelatedProducts products={relatedItems} heading={t.product.related} />
      </div>

      <MobilePurchaseBar
        summary={selectedVariant?.combination_label || detail.name || ""}
        price={selectedVariant?.price.final_amount_minor ?? null}
        currency={selectedVariant?.price.currency ?? detail.price.currency}
        locale={numberLocale}
        availabilityStatus={availability.status}
        availabilityLabel={availabilityLabel}
        purchasable={purchasable}
        hasPrice={hasPrice}
        missingLabel={t.common.priceOnRequest}
        actionLabel={purchasePending ? cartCopy.adding : t.product.addToCart}
        cartSoonLabel={t.product.cartSoon}
        unavailableLabel={t.product.unavailableAction}
        onPurchase={handlePurchase}
      />
    </div>
  );
}
