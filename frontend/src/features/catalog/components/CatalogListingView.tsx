import { JsonLd } from "@/components/seo/json-ld";
import { CategoryHero } from "@/features/catalog/components/CategoryHero";
import { ChildCategoryNav } from "@/features/catalog/components/ChildCategoryNav";
import type { CatalogListState } from "@/features/catalog/lib/load-public-catalog";
import {
  breadcrumbJsonLd,
  productItemListJsonLd,
} from "@/features/catalog/seo/json-ld";
import type { CatalogQuery } from "@/features/catalog/query-state/catalog-search-params";
import type {
  CatalogLocale,
  PublicCategoryDetail,
  PublicCategorySummary,
} from "@/features/catalog/types/public-catalog";
import { CatalogPage } from "@/features/storefront/components/CatalogPage";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

function topLevelCategories(
  nodes: PublicCategorySummary[],
): PublicCategorySummary[] {
  return nodes;
}

export function CatalogListingView({
  locale,
  query,
  pathname,
  list,
  category,
  categorySlug,
  brandSlug,
  title,
  lead,
}: {
  locale: CatalogLocale;
  query: CatalogQuery;
  pathname: string;
  list: CatalogListState;
  category?: PublicCategoryDetail | null;
  categorySlug?: string;
  brandSlug?: string;
  title: string;
  lead?: string;
}) {
  const copy = storefrontCopy[locale];
  const childItems = category
    ? category.children
    : topLevelCategories(list.categories);
  const breadcrumbs = category
    ? [
        { href: "/catalog", label: copy.nav.catalog },
        ...category.breadcrumbs.map((item) => ({
          href: item.path,
          label: item.name,
        })),
      ]
    : [{ href: "/catalog", label: copy.nav.catalog }, { label: title }];

  const jsonCrumbs = category
    ? [
        { name: copy.nav.catalog, path: "/catalog" },
        ...category.breadcrumbs.map((item) => ({
          name: item.name,
          path: item.path,
        })),
      ]
    : [
        { name: copy.nav.catalog, path: "/catalog" },
        { name: title, path: pathname },
      ];

  return (
    <div className="sf-band-paper min-h-[70svh] pb-[max(4rem,env(safe-area-inset-bottom))]">
      <JsonLd data={breadcrumbJsonLd(jsonCrumbs)} />
      {list.products.length > 0 && pathname !== "/search" ? (
        <JsonLd data={productItemListJsonLd(list.products, pathname)} />
      ) : null}
      <CategoryHero
        title={title}
        lead={lead}
        count={category?.product_count ?? list.total}
        countLabel={copy.catalog.productsCount}
        eyebrow={copy.nav.catalog}
        category={category}
        breadcrumbs={breadcrumbs}
      />
      <div className="sf-container space-y-5 pt-5 sm:pt-6">
        {childItems.length > 0 && pathname !== "/search" ? (
          <ChildCategoryNav
            items={childItems}
            activeSlug={categorySlug}
            allLabel={copy.catalog.allCategories}
            heading={
              category?.children.length
                ? copy.catalog.childCategories
                : undefined
            }
          />
        ) : null}
        <CatalogPage
          categorySlug={categorySlug}
          brandSlug={brandSlug}
          pathname={pathname}
          query={query}
          category={category}
          initial={list}
        />
      </div>
    </div>
  );
}
