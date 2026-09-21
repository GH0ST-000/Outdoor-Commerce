import { afterEach, describe, expect, it, vi } from "vitest";
import { getHomePageData } from "@/features/home/api/get-home-page-data";

const getPublicCategories = vi.fn();
const getPublicProducts = vi.fn();
const getPublicBrands = vi.fn();

vi.mock("@/features/catalog/api/public-catalog-client", () => ({
  getPublicCategories: (...args: unknown[]) => getPublicCategories(...args),
  getPublicProducts: (...args: unknown[]) => getPublicProducts(...args),
  getPublicBrands: (...args: unknown[]) => getPublicBrands(...args),
}));

vi.mock("@/features/storefront/lib/log", () => ({
  logStorefrontError: vi.fn(),
  logStorefrontWarning: vi.fn(),
}));

describe("getHomePageData", () => {
  afterEach(() => {
    getPublicCategories.mockReset();
    getPublicProducts.mockReset();
    getPublicBrands.mockReset();
  });

  it("maps public categories and skips missing configured slots", async () => {
    getPublicCategories.mockResolvedValue({
      data: [
        { id: 1, name: "ნადირობა", slug: "hunting", path: "/catalog/hunting" },
      ],
    });
    getPublicProducts.mockResolvedValue({ data: [] });
    getPublicBrands.mockResolvedValue({ data: [] });

    const data = await getHomePageData("ka");
    expect(data.gateway).toHaveLength(1);
    expect(data.gateway[0]?.slug).toBe("hunting");
    expect(data.categoriesFailed).toBe(false);
  });

  it("keeps the homepage alive when featured products fail", async () => {
    getPublicCategories.mockResolvedValue({
      data: [{ id: 1, name: "Hunting", slug: "hunting" }],
    });
    getPublicProducts.mockImplementation(
      (params: { featured?: boolean; on_sale?: boolean }) => {
        if (params.featured) {
          return Promise.reject(new Error("featured down"));
        }
        return Promise.resolve({ data: [] });
      },
    );
    getPublicBrands.mockRejectedValue(new Error("brands down"));

    const data = await getHomePageData("en");
    expect(data.featuredFailed).toBe(true);
    expect(data.featured).toBeNull();
    expect(data.brandsFailed).toBe(true);
    expect(data.gateway[0]?.slug).toBe("hunting");
  });
});
