import { describe, expect, it } from "vitest";
import { buildPromotionWritePayload } from "@/features/pricing/schemas/pricing-schemas";

describe("Promotion form discount parsing", () => {
  it("converts percentage input to basis points", () => {
    const result = buildPromotionWritePayload({
      general: {
        code: "SUMMER15",
        name: "Summer sale",
        description: "",
        priority: "10",
        stacking_mode: "exclusive",
        starts_at: "2026-06-01T10:00",
        ends_at: "",
        currency_code: "GEL",
      },
      discount: {
        discount_type: "percentage",
        percentage_input: "15.5",
        fixed_amount_input: "",
        currency_code: "GEL",
        maximum_discount_input: "",
      },
    });

    expect(result.errors).toEqual({});
    expect(result.payload?.percentage_basis_points).toBe(1550);
    expect(result.payload?.discount_type).toBe("percentage");
  });

  it("rejects invalid percentage values", () => {
    const result = buildPromotionWritePayload({
      general: {
        code: "BAD",
        name: "Bad promo",
        description: "",
        priority: "0",
        stacking_mode: "exclusive",
        starts_at: "2026-06-01T10:00",
        ends_at: "",
        currency_code: "GEL",
      },
      discount: {
        discount_type: "percentage",
        percentage_input: "",
        fixed_amount_input: "",
        currency_code: "GEL",
        maximum_discount_input: "",
      },
    });

    expect(result.payload).toBeNull();
    expect(result.errors.percentage).toBeTruthy();
  });
});
