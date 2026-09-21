import { describe, expect, it } from "vitest";
import type { PublicProductDetail } from "@/features/catalog/types/public-catalog";
import { productJsonLd } from "@/features/product-detail/seo/product-json-ld";
import { minorToDecimalString } from "@/features/product-detail/utils/money-text";

const detail: PublicProductDetail = {
  id: 12,
  name: "Optics",
  slug: "optika",
  short_description: "Short",
  description: "<p>Full</p>",
  model_number: "M-1",
  is_featured: false,
  used_fallback: false,
  brand: { id: 1, name: "Condor", slug: "condor", path: "/brands/condor" },
  primary_category: {
    id: 2,
    name: "Hunting",
    slug: "hunting",
    path: "/catalog/hunting",
  },
  categories: [],
  breadcrumbs: [{ name: "Hunting", slug: "hunting", path: "/catalog/hunting" }],
  gallery: [
    {
      alt: "Product",
      caption: null,
      width: 800,
      height: 1000,
      aspect_ratio: 0.8,
      dominant_color: null,
      focal_point: null,
      sources: {
        jpeg: {
          srcset: "/media/a.jpg 800w",
          presets: {
            detail: {
              url: "/media/a-detail.jpg",
              width: 1600,
              height: 2000,
              byte_size: 24,
            },
          },
        },
      },
    },
  ],
  price: {
    currency: "GEL",
    min_final_amount_minor: 8000,
    max_final_amount_minor: 11000,
    min_base_amount_minor: 11000,
    is_range: true,
    on_sale: true,
  },
  availability: { status: "in_stock", purchasable: true, low_stock: false },
  variants: {
    axes: [],
    combinations: [
      {
        id: 501,
        sku: "PRD-BLACK",
        is_default: true,
        combination_label: "Black",
        attributes: [],
        media: [],
        price: {
          currency: "GEL",
          base_amount_minor: 11000,
          final_amount_minor: 8000,
          discount_amount_minor: 3000,
          on_sale: true,
          applied_promotions: [],
          calculated_at: "2026-09-21T00:00:00Z",
          signature: "a",
        },
        availability: {
          status: "in_stock",
          purchasable: true,
          low_stock: false,
        },
      },
      {
        id: 502,
        sku: "PRD-UNPRICED",
        is_default: false,
        combination_label: "Ghost",
        attributes: [],
        media: [],
        price: {
          currency: "GEL",
          base_amount_minor: null,
          final_amount_minor: null,
          discount_amount_minor: null,
          on_sale: false,
          applied_promotions: [],
          calculated_at: "2026-09-21T00:00:00Z",
          signature: null,
        },
        availability: {
          status: "unavailable",
          purchasable: false,
          low_stock: false,
        },
      },
    ],
    default_variant_id: 501,
  },
  default_variant_id: 501,
  seo: {
    title: "Optics",
    description: "SEO desc",
    canonical_path: "/products/optika",
    alternate_locale_paths: { en: "/products/optics", ka: "/products/optika" },
    open_graph_media: null,
    robots: "index,follow",
  },
  canonical_path: "/products/optika",
  alternate_locale_paths: { en: "/products/optics", ka: "/products/optika" },
  updated_at: null,
};

describe("product structured data", () => {
  it("uses real GEL prices and omits unpriced variants and fake ratings", () => {
    const data = productJsonLd(detail, "ka") as Record<string, unknown>;
    expect(data["@type"]).toBe("Product");
    expect(data.sku).toBe("PRD-BLACK");
    expect(JSON.stringify(data)).not.toContain("aggregateRating");
    expect(JSON.stringify(data)).not.toContain("review");
    const offers = data.offers as {
      "@type": string;
      price?: string;
      offers?: Array<{ sku: string; price: string }>;
    };
    expect(offers["@type"]).toBe("Offer");
    expect(offers.price).toBe("80.00");
    expect(JSON.stringify(data)).not.toContain("PRD-UNPRICED");
  });

  it("formats tetri without floating-point math", () => {
    expect(minorToDecimalString(11049)).toBe("110.49");
    expect(minorToDecimalString(0)).toBe("0.00");
  });
});
