import { describe, expect, it } from "vitest";
import type { PublicVariantCombination } from "@/features/catalog/types/public-catalog";
import {
  buildVariantIndex,
  combinationKeyFromAttributes,
  combinationKeyFromSelection,
  parseVariantIdParam,
  resolveAfterValueClick,
  resolveInitialVariant,
  selectionFromCombination,
  valueAvailability,
} from "@/features/product-detail/state/variant-resolver";

function combo(
  overrides: Partial<PublicVariantCombination> & {
    id: number;
    sku: string;
    attributes: PublicVariantCombination["attributes"];
  },
): PublicVariantCombination {
  return {
    is_default: false,
    combination_label: overrides.sku,
    media: [],
    price: {
      currency: "GEL",
      base_amount_minor: 10000,
      final_amount_minor: 10000,
      discount_amount_minor: 0,
      on_sale: false,
      applied_promotions: [],
      calculated_at: "2026-09-21T00:00:00Z",
      signature: "sig",
    },
    availability: { status: "in_stock", purchasable: true, low_stock: false },
    ...overrides,
  };
}

const colorSize = [
  combo({
    id: 1,
    sku: "BLK-M",
    is_default: true,
    attributes: [
      {
        code: "color",
        name: "Color",
        value: { code: "black", name: "Black", color_hex: "#111" },
      },
      {
        code: "size",
        name: "Size",
        value: { code: "m", name: "M", color_hex: null },
      },
    ],
  }),
  combo({
    id: 2,
    sku: "BLK-XL",
    attributes: [
      {
        code: "color",
        name: "Color",
        value: { code: "black", name: "Black", color_hex: "#111" },
      },
      {
        code: "size",
        name: "Size",
        value: { code: "xl", name: "XL", color_hex: null },
      },
    ],
    availability: {
      status: "out_of_stock",
      purchasable: false,
      low_stock: false,
    },
  }),
  combo({
    id: 3,
    sku: "GRN-M",
    attributes: [
      {
        code: "color",
        name: "Color",
        value: { code: "green", name: "Green", color_hex: "#0a0" },
      },
      {
        code: "size",
        name: "Size",
        value: { code: "m", name: "M", color_hex: null },
      },
    ],
  }),
];

describe("variant resolver", () => {
  it("normalizes combination keys independently of attribute order", () => {
    const left = combinationKeyFromAttributes([
      {
        code: "size",
        name: "Size",
        value: { code: "m", name: "M", color_hex: null },
      },
      {
        code: "color",
        name: "Color",
        value: { code: "black", name: "Black", color_hex: null },
      },
    ]);
    const right = combinationKeyFromSelection({ color: "black", size: "m" });
    expect(left).toBe("color=black&size=m");
    expect(right).toBe(left);
  });

  it("parses valid variant ids and rejects junk", () => {
    expect(parseVariantIdParam("501")).toBe(501);
    expect(parseVariantIdParam(501)).toBe(501);
    expect(parseVariantIdParam("0501")).toBeNull();
    expect(parseVariantIdParam("abc")).toBeNull();
    expect(parseVariantIdParam("")).toBeNull();
  });

  it("resolves the default variant when no URL id is present", () => {
    const index = buildVariantIndex(colorSize);
    const resolved = resolveInitialVariant(index, { defaultVariantId: 1 });
    expect(resolved.variant?.sku).toBe("BLK-M");
    expect(resolved.usedUrlFallback).toBe(false);
  });

  it("resolves a URL variant when it belongs to the product", () => {
    const index = buildVariantIndex(colorSize);
    const resolved = resolveInitialVariant(index, {
      urlVariantId: 3,
      defaultVariantId: 1,
    });
    expect(resolved.variant?.sku).toBe("GRN-M");
    expect(resolved.usedUrlFallback).toBe(false);
  });

  it("falls back from an invalid URL variant", () => {
    const index = buildVariantIndex(colorSize);
    const resolved = resolveInitialVariant(index, {
      urlVariantId: 99,
      defaultVariantId: 1,
    });
    expect(resolved.variant?.id).toBe(1);
    expect(resolved.usedUrlFallback).toBe(true);
  });

  it("uses the first combination deterministically when no default exists", () => {
    const index = buildVariantIndex(
      colorSize.map((item) => ({ ...item, is_default: false })),
    );
    const resolved = resolveInitialVariant(index, { defaultVariantId: null });
    expect(resolved.variant?.id).toBe(1);
  });

  it("selects an exact two-axis combination", () => {
    const index = buildVariantIndex(colorSize);
    const next = resolveAfterValueClick(
      index,
      selectionFromCombination(colorSize[0]!),
      "size",
      "xl",
    );
    expect(next?.sku).toBe("BLK-XL");
    expect(next?.availability.status).toBe("out_of_stock");
  });

  it("auto-adjusts other axes when the attempted combination does not exist", () => {
    const index = buildVariantIndex(colorSize);
    const next = resolveAfterValueClick(
      index,
      { color: "black", size: "xl" },
      "color",
      "green",
    );
    expect(next?.sku).toBe("GRN-M");
  });

  it("marks missing combinations invalid and out-of-stock combinations selectable", () => {
    const index = buildVariantIndex(colorSize);
    const current = { color: "black", size: "m" };
    expect(valueAvailability(index, current, "size", "xl")).toBe(
      "out_of_stock",
    );
    expect(valueAvailability(index, current, "color", "green")).toBe(
      "available",
    );
    expect(valueAvailability(index, current, "size", "xxl")).toBe("invalid");
  });

  it("handles a one-axis product", () => {
    const one = [
      combo({
        id: 10,
        sku: "ONE-A",
        is_default: true,
        attributes: [
          {
            code: "length",
            name: "Length",
            value: { code: "120", name: "120", color_hex: null },
          },
        ],
      }),
      combo({
        id: 11,
        sku: "ONE-B",
        attributes: [
          {
            code: "length",
            name: "Length",
            value: { code: "150", name: "150", color_hex: null },
          },
        ],
      }),
    ];
    const index = buildVariantIndex(one);
    expect(
      resolveAfterValueClick(index, { length: "120" }, "length", "150")?.sku,
    ).toBe("ONE-B");
  });

  it("handles an empty-combination product", () => {
    const empty = [
      combo({ id: 20, sku: "SOLO", is_default: true, attributes: [] }),
    ];
    const index = buildVariantIndex(empty);
    expect(combinationKeyFromAttributes([])).toBe("");
    expect(resolveInitialVariant(index, {}).variant?.sku).toBe("SOLO");
  });

  it("does not treat archived-absent variants as selectable", () => {
    const index = buildVariantIndex(colorSize);
    expect(index.variantById.has(999)).toBe(false);
    expect(
      resolveInitialVariant(index, { urlVariantId: 999 }).usedUrlFallback,
    ).toBe(true);
  });

  it("distinguishes values that share a label but not a code", () => {
    const twins = [
      combo({
        id: 30,
        sku: "A",
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "green-forest", name: "Green", color_hex: null },
          },
        ],
      }),
      combo({
        id: 31,
        sku: "B",
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "green-olive", name: "Green", color_hex: null },
          },
        ],
      }),
    ];
    const index = buildVariantIndex(twins);
    expect(
      resolveAfterValueClick(
        index,
        { color: "green-forest" },
        "color",
        "green-olive",
      )?.sku,
    ).toBe("B");
  });

  it("resolves three-axis combinations", () => {
    const three = [
      combo({
        id: 40,
        sku: "A",
        is_default: true,
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "black", name: "Black", color_hex: null },
          },
          {
            code: "size",
            name: "Size",
            value: { code: "m", name: "M", color_hex: null },
          },
          {
            code: "length",
            name: "Length",
            value: { code: "short", name: "Short", color_hex: null },
          },
        ],
      }),
      combo({
        id: 41,
        sku: "B",
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "black", name: "Black", color_hex: null },
          },
          {
            code: "size",
            name: "Size",
            value: { code: "m", name: "M", color_hex: null },
          },
          {
            code: "length",
            name: "Length",
            value: { code: "long", name: "Long", color_hex: null },
          },
        ],
      }),
    ];
    const index = buildVariantIndex(three);
    const next = resolveAfterValueClick(
      index,
      { color: "black", size: "m", length: "short" },
      "length",
      "long",
    );
    expect(next?.sku).toBe("B");
  });
});
