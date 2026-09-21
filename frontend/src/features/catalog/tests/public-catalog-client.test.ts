import { describe, expect, it } from "vitest";
import {
  buildProductFacetsPath,
  buildProductListPath,
} from "@/features/catalog/api/public-catalog-client";

describe("public catalog query builder", () => {
  it("serializes filters, minor-unit prices, and locale", () => {
    const path = buildProductListPath({
      locale: "ka",
      category: "hunting",
      brand: ["alpha", "beta"],
      attribute: { color: ["black", "forest_green"], size: ["xl"] },
      min_price: 10000,
      max_price: 50000,
      in_stock: true,
      on_sale: true,
      sort: "price_asc",
      page: 2,
      per_page: 10,
    });

    expect(path).toContain("/v1/catalog/products?");
    expect(path).toContain("min_price=10000");
    expect(path).toContain("max_price=50000");
    expect(path).toContain("brand%5B%5D=alpha");
    expect(path).toContain("attribute%5Bcolor%5D%5B%5D=black");
    expect(path).toContain("in_stock=1");
    expect(path).toContain("sort=price_asc");
    expect(path).toContain("per_page=10");
  });

  it("builds a facets path with the same query contract", () => {
    expect(buildProductFacetsPath({ category: "optics", locale: "en" })).toBe(
      "/v1/catalog/products/facets?locale=en&category=optics",
    );
  });
});
