import { describe, expect, it } from "vitest";
import {
  attributeFormSchema,
  attributeValueFormSchema,
} from "@/features/catalog/attributes/schemas/attribute-schemas";
import {
  isValidAttributeCode,
  normalizeColorHex,
} from "@/features/catalog/attributes/utils/attribute-format";

function attributeInput(overrides: Record<string, unknown> = {}) {
  return {
    code: "color",
    type: "color",
    status: "draft",
    is_filterable: true,
    sort_order: 0,
    translations: {
      ka: { locale: "ka", name: "ფერი", description: "" },
      en: { locale: "en", name: "", description: "" },
    },
    ...overrides,
  };
}

function valueInput(overrides: Record<string, unknown> = {}) {
  return {
    code: "red",
    status: "draft",
    sort_order: 0,
    color_hex: "#FF0000",
    translations: {
      ka: { locale: "ka", name: "წითელი", description: "" },
      en: { locale: "en", name: "", description: "" },
    },
    ...overrides,
  };
}

function messagesFor(
  result: {
    success: boolean;
    error?: { issues: { path: PropertyKey[]; message: string }[] };
  },
  path: string,
): string[] {
  if (result.success || !result.error) return [];
  return result.error.issues
    .filter((issue) => issue.path.join(".") === path)
    .map((issue) => issue.message);
}

describe("attributeFormSchema", () => {
  it("requires a Georgian name", () => {
    const result = attributeFormSchema.safeParse(
      attributeInput({
        translations: {
          ka: { locale: "ka", name: "  ", description: "" },
          en: { locale: "en", name: "", description: "" },
        },
      }),
    );

    expect(result.success).toBe(false);
    expect(messagesFor(result, "translations.ka.name")).toContain(
      "Name is required.",
    );
  });

  it("requires an English name once English content exists", () => {
    const result = attributeFormSchema.safeParse(
      attributeInput({
        translations: {
          ka: { locale: "ka", name: "ფერი", description: "" },
          en: { locale: "en", name: "", description: "Primary color" },
        },
      }),
    );

    expect(result.success).toBe(false);
    expect(messagesFor(result, "translations.en.name")).toHaveLength(1);
  });

  it("rejects codes that do not start with a letter", () => {
    expect(isValidAttributeCode("1color")).toBe(false);
    expect(isValidAttributeCode("color_2")).toBe(true);
    expect(
      attributeFormSchema.safeParse(attributeInput({ code: "1color" })).success,
    ).toBe(false);
  });
});

describe("attributeValueFormSchema color validation", () => {
  it("accepts 3- and 6-digit hex for color attributes", () => {
    expect(
      attributeValueFormSchema("color").safeParse(valueInput()).success,
    ).toBe(true);
    expect(
      attributeValueFormSchema("color").safeParse(
        valueInput({ color_hex: "#abc" }),
      ).success,
    ).toBe(true);
  });

  it("rejects malformed hex values", () => {
    const result = attributeValueFormSchema("color").safeParse(
      valueInput({ color_hex: "red" }),
    );

    expect(result.success).toBe(false);
    expect(messagesFor(result, "color_hex")).toContain(
      "Use a 3- or 6-digit hex color such as #1A2B3C.",
    );
  });

  it("requires a hex value for color attributes", () => {
    const result = attributeValueFormSchema("color").safeParse(
      valueInput({ color_hex: "" }),
    );

    expect(result.success).toBe(false);
    expect(messagesFor(result, "color_hex")).toContain(
      "Color values need a hex color such as #1A2B3C.",
    );
  });

  it("rejects a hex value on select attributes", () => {
    const result = attributeValueFormSchema("select").safeParse(valueInput());

    expect(result.success).toBe(false);
    expect(messagesFor(result, "color_hex")).toContain(
      "Only color attributes accept a color value.",
    );
    expect(
      attributeValueFormSchema("select").safeParse(
        valueInput({ color_hex: "" }),
      ).success,
    ).toBe(true);
  });

  it("normalizes hex input the way the backend stores it", () => {
    expect(normalizeColorHex("abc")).toBe("#AABBCC");
    expect(normalizeColorHex("#ff0000")).toBe("#FF0000");
    expect(normalizeColorHex("#ff00")).toBeNull();
  });
});
