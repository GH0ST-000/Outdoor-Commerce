import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { JsonLd } from "@/components/seo/json-ld";
import { ProductDetailPage } from "@/features/storefront/components/ProductDetailPage";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import {
  loadBrandForProduct,
  loadProductDetail,
  loadRelatedProducts,
} from "@/features/catalog/lib/load-public-catalog";
import {
  absoluteUrl,
  metadataFromPublicSeo,
} from "@/features/catalog/seo/catalog-seo";
import { parseVariantIdParam } from "@/features/product-detail/state/variant-resolver";
import {
  openGraphImageUrl,
  productBreadcrumbJsonLd,
  productJsonLd,
} from "@/features/product-detail/seo/product-json-ld";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

type PageProps = {
  params: Promise<{ productSlug: string }>;
  searchParams: Promise<{ variant?: string | string[] }>;
};

function variantParam(value: string | string[] | undefined): number | null {
  const raw = Array.isArray(value) ? value[0] : value;
  return parseVariantIdParam(raw);
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { productSlug } = await params;
  const locale = await getRequestCatalogLocale();
  const loaded = await loadProductDetail(productSlug, locale);
  const copy = storefrontCopy[locale];

  if (loaded === "not_found" || "error" in loaded) {
    return {
      title: copy.product.notFound,
      robots: { index: false, follow: false },
    };
  }

  const image = openGraphImageUrl(loaded.detail);
  const metadata = metadataFromPublicSeo(loaded.detail.seo, {
    title: loaded.detail.name
      ? `${loaded.detail.name}${loaded.detail.brand?.name ? ` · ${loaded.detail.brand.name}` : ""}`
      : copy.product.notFound,
    description: loaded.detail.short_description ?? undefined,
    canonical: loaded.detail.canonical_path,
    locale: locale === "ka" ? "ka_GE" : "en_US",
    image,
  });

  return {
    ...metadata,
    twitter: {
      card: "summary_large_image",
      title: loaded.detail.seo.title,
      description: metadata.description,
      images: image ? [absoluteUrl(image)] : undefined,
    },
  };
}

export default async function Page({ params, searchParams }: PageProps) {
  const { productSlug } = await params;
  const query = await searchParams;
  const locale = await getRequestCatalogLocale();
  const loaded = await loadProductDetail(productSlug, locale);

  if (loaded === "not_found") {
    notFound();
  }

  if ("error" in loaded) {
    throw new Error("PUBLIC_CATALOG_UNAVAILABLE");
  }

  const [related, brandDetail] = await Promise.all([
    loadRelatedProducts(
      locale,
      loaded.detail.id,
      loaded.detail.primary_category?.slug,
    ),
    loadBrandForProduct(loaded.detail.brand?.slug, locale),
  ]);

  const crumbs = [
    { name: storefrontCopy[locale].product.home, path: "/" },
    ...loaded.detail.breadcrumbs.map((item) => ({
      name: item.name,
      path: item.path,
    })),
    { name: loaded.detail.name ?? "", path: loaded.detail.canonical_path },
  ];

  return (
    <>
      <JsonLd data={productJsonLd(loaded.detail, locale)} />
      <JsonLd data={productBreadcrumbJsonLd(crumbs)} />
      <ProductDetailPage
        slug={productSlug}
        detail={loaded.detail}
        related={related}
        brandDetail={brandDetail}
        initialVariantId={variantParam(query.variant)}
      />
    </>
  );
}
