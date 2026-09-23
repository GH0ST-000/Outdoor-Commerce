import { describe, expect, it } from "vitest";
import {
  catalogHref,
  catalogQueryHasFilters,
  catalogQueryToListParams,
  emptyCatalogQuery,
  parseCatalogSearchParams,
  parseSearchPageParams,
  serializeCatalogSearchParams,
} from "@/features/catalog/query-state/catalog-search-params";
import {
  gelMajorToMinor,
  minorToGelMajor,
} from "@/features/catalog/query-state/gel";
import {
  catalogCanonicalPath,
  catalogRobots,
  escapeJsonLd,
} from "@/features/catalog/seo/catalog-seo";

describe("catalog search params", () => {
  it("parses brands, attributes, flags, and integer prices", () => {
    const query = parseCatalogSearchParams({
      brand: ["ridge", "condor"],
      "attribute[color]": "black,forest",
      min_price: "10000",
      max_price: "50000",
      in_stock: "1",
      on_sale: "true",
      sort: "price_asc",
      page: "2",
    });

    expect(query.brand).toEqual(["ridge", "condor"]);
    expect(query.attribute.color).toEqual(["black", "forest"]);
    expect(query.min_price).toBe(10000);
    expect(query.max_price).toBe(50000);
    expect(query.in_stock).toBe(true);
    expect(query.sort).toBe("price_asc");
    expect(query.page).toBe(2);
  });

  it("swaps inverted price bounds and ignores unsafe numbers", () => {
    const query = parseCatalogSearchParams({
      min_price: "900",
      max_price: "100",
      page: "0.5",
      sort: "popularity",
    });
    expect(query.min_price).toBe(100);
    expect(query.max_price).toBe(900);
    expect(query.page).toBe(1);
    expect(query.sort).toBe("featured");
  });

  it("serializes in a stable order and omits defaults", () => {
    const query = {
      ...emptyCatalogQuery(),
      brand: ["beta", "alpha"],
      attribute: { size: ["xl"], color: ["black"] },
      in_stock: true,
      page: 1,
    };
    expect(serializeCatalogSearchParams(query)).toBe(
      "brand=alpha&brand=beta&attribute%5Bcolor%5D=black&attribute%5Bsize%5D=xl&in_stock=1",
    );
    expect(catalogHref("/catalog/hunting", query)).toContain(
      "/catalog/hunting?",
    );
    expect(catalogQueryHasFilters(query)).toBe(true);
    expect(catalogQueryToListParams(query).per_page).toBe(10);
  });
});

describe("GEL major/minor conversion", () => {
  it("converts major units to tetri without float multiplication", () => {
    expect(gelMajorToMinor("12")).toBe(1200);
    expect(gelMajorToMinor("12.5")).toBe(1250);
    expect(gelMajorToMinor("12.50")).toBe(1250);
    expect(gelMajorToMinor("12,5")).toBe(1250);
    expect(gelMajorToMinor("12.505")).toBeUndefined();
    expect(gelMajorToMinor("abc")).toBeUndefined();
    expect(minorToGelMajor(1250)).toBe("12.50");
    expect(minorToGelMajor(1200)).toBe("12");
  });
});

describe("catalog SEO policy", () => {
  it("noindexes filtered and empty result pages", () => {
    const filtered = {
      ...emptyCatalogQuery(),
      brand: ["condor"],
    };
    expect(catalogRobots(filtered, 4)).toEqual({ index: false, follow: true });
    expect(catalogRobots(emptyCatalogQuery(), 0)).toEqual({
      index: false,
      follow: true,
    });
    expect(catalogRobots(emptyCatalogQuery(), 12)).toEqual({
      index: true,
      follow: true,
    });
  });

  it("self-canonicalizes unfiltered pagination", () => {
    expect(
      catalogCanonicalPath("/catalog/hunting", {
        ...emptyCatalogQuery(),
        page: 2,
      }),
    ).toBe("/catalog/hunting?page=2");
    expect(
      catalogCanonicalPath("/catalog/hunting", {
        ...emptyCatalogQuery(),
        brand: ["x"],
        page: 2,
      }),
    ).toBe("/catalog/hunting");
  });

  it("escapes JSON-LD", () => {
    expect(escapeJsonLd({ a: "</script>" })).toContain("\\u003c");
  });
});

describe("search page params", () => {
  it("defaults search sort to relevance and keeps q in the URL", () => {
    const query = parseSearchPageParams({ q: "scope", brand: "ridge" });
    expect(query.sort).toBe("default");
    expect(query.q).toBe("scope");
    expect(catalogHref("/search", query, { defaultSort: "default" })).toContain(
      "q=scope",
    );
    expect(
      catalogHref("/search", query, { defaultSort: "default" }),
    ).not.toContain("sort=");
  });
});
