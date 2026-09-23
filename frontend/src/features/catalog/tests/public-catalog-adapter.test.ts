import { describe, expect, it } from "vitest";
import {
  toProductCardData,
  toProductDetailView,
} from "@/features/catalog/adapters/public-catalog-adapter";
import type {
  PublicProductCard,
  PublicProductDetail,
} from "@/features/catalog/types/public-catalog";

const card: PublicProductCard = {
  id: 12,
  name: "ოპტიკა",
  slug: "optika",
  href: "/products/optika",
  brand: { id: 1, name: "Condor", slug: "condor" },
  primary_category: { id: 2, name: "Hunting", slug: "hunting" },
  primary_media: null,
  price: {
    currency: "GEL",
    min_final_amount_minor: 11049,
    max_final_amount_minor: 12999,
    min_base_amount_minor: 12999,
    is_range: true,
    on_sale: true,
  },
  availability: { status: "in_stock", purchasable: true, low_stock: false },
  is_featured: true,
  variant_count: 2,
  default_variant_id: 501,
  used_fallback: false,
};

describe("public catalog adapter", () => {
  it("maps list cards to storefront product cards with API prices", () => {
    const mapped = toProductCardData(card);
    expect(mapped.pricing?.min_amount_minor).toBe(11049);
    expect(mapped.pricing?.is_range).toBe(true);
    expect(mapped.imageSrc).toContain("placeholder");
    expect(mapped.badges).toEqual(["featured"]);
    expect(mapped.availabilityLabel?.en).toBe("In stock");
    expect(mapped.defaultVariantId).toBe(501);
    expect(mapped.variantCount).toBe(2);
    expect(mapped.purchasable).toBe(true);
  });

  it("maps product detail variant axes", () => {
    const detail: PublicProductDetail = {
      ...card,
      short_description: "Short",
      description: "<p>Full</p>",
      model_number: "M-1",
      used_fallback: false,
      brand: { id: 1, name: "Condor", slug: "condor", path: "/brands/condor" },
      primary_category: {
        id: 2,
        name: "Hunting",
        slug: "hunting",
        path: "/catalog/hunting",
      },
      categories: [],
      breadcrumbs: [],
      gallery: [],
      variants: {
        axes: [
          {
            id: 9,
            code: "color",
            name: "Color",
            type: "color",
            values: [
              { id: 1, code: "black", name: "Black", color_hex: "#000" },
            ],
          },
        ],
        combinations: [
          {
            id: 501,
            sku: "PRD-100-001",
            is_default: true,
            combination_label: "Black",
            attributes: [
              {
                code: "color",
                name: "Color",
                value: { code: "black", name: "Black", color_hex: "#000" },
              },
            ],
            media: [],
            price: {
              currency: "GEL",
              base_amount_minor: 12999,
              final_amount_minor: 11049,
              discount_amount_minor: 1950,
              on_sale: true,
              applied_promotions: [],
              calculated_at: "2026-09-21T00:00:00Z",
              signature: "sig",
            },
            availability: {
              status: "in_stock",
              purchasable: true,
              low_stock: false,
            },
          },
        ],
        default_variant_id: 501,
      },
      default_variant_id: 501,
      seo: {
        title: "Optics",
        description: null,
        canonical_path: "/products/optika",
        alternate_locale_paths: {},
        open_graph_media: null,
        robots: "index,follow",
      },
      canonical_path: "/products/optika",
      alternate_locale_paths: {},
      updated_at: null,
    };

    const view = toProductDetailView(detail);
    expect(view.variants.axes[0]?.code).toBe("color");
    expect(view.sku).toBe("PRD-100-001");
  });
});
