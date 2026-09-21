import type {
  DiscountType,
  PromotionWritePayload,
  StackingMode,
} from "@/features/pricing/types/pricing-types";
import {
  parseMajorToMinor,
  parsePercentToBasisPoints,
} from "@/features/pricing/lib/money";

export function mapApiFieldErrors(
  details: Record<string, string[]> | undefined,
): Record<string, string> {
  const next: Record<string, string> = {};
  if (!details) return next;
  for (const [key, messages] of Object.entries(details)) {
    if (messages[0]) {
      next[key] = messages[0];
    }
  }
  return next;
}

export type PromotionDiscountFormInput = {
  discount_type: DiscountType;
  percentage_input: string;
  fixed_amount_input: string;
  currency_code: string;
  maximum_discount_input: string;
};

export function resolvePromotionDiscountFields(
  input: PromotionDiscountFormInput,
): {
  percentage_basis_points: number | null;
  fixed_amount_minor: number | null;
  maximum_discount_minor: number | null;
  errors: Record<string, string>;
} {
  const errors: Record<string, string> = {};
  let percentage_basis_points: number | null = null;
  let fixed_amount_minor: number | null = null;
  let maximum_discount_minor: number | null = null;

  if (input.discount_type === "percentage") {
    const parsed = parsePercentToBasisPoints(input.percentage_input);
    if (!parsed.ok) {
      errors.percentage = "Enter a valid percentage (e.g. 15 or 15.5).";
    } else {
      percentage_basis_points = parsed.amount_minor;
    }
  } else {
    const parsed = parseMajorToMinor(
      input.fixed_amount_input,
      input.currency_code || "GEL",
    );
    if (!parsed.ok) {
      errors.fixed_amount = "Enter a valid fixed discount amount.";
    } else {
      fixed_amount_minor = parsed.amount_minor;
    }
  }

  if (input.maximum_discount_input.trim() !== "") {
    const cap = parseMajorToMinor(
      input.maximum_discount_input,
      input.currency_code || "GEL",
    );
    if (!cap.ok) {
      errors.maximum_discount = "Enter a valid maximum discount cap.";
    } else {
      maximum_discount_minor = cap.amount_minor;
    }
  }

  return {
    percentage_basis_points,
    fixed_amount_minor,
    maximum_discount_minor,
    errors,
  };
}

export type PromotionGeneralFormInput = {
  code: string;
  name: string;
  description: string;
  priority: string;
  stacking_mode: StackingMode;
  starts_at: string;
  ends_at: string;
  currency_code: string;
};

export function buildPromotionWritePayload(input: {
  general: PromotionGeneralFormInput;
  discount: PromotionDiscountFormInput;
}): { payload: PromotionWritePayload | null; errors: Record<string, string> } {
  const errors: Record<string, string> = {};
  if (input.general.code.trim() === "") errors.code = "Code is required.";
  if (input.general.name.trim() === "") errors.name = "Name is required.";
  if (input.general.starts_at.trim() === "") {
    errors.starts_at = "Start date is required.";
  }

  const discountFields = resolvePromotionDiscountFields(input.discount);
  Object.assign(errors, discountFields.errors);

  if (Object.keys(errors).length > 0) {
    return { payload: null, errors };
  }

  return {
    payload: {
      code: input.general.code.trim(),
      name: input.general.name.trim(),
      description: input.general.description.trim() || null,
      discount_type: input.discount.discount_type,
      percentage_basis_points: discountFields.percentage_basis_points,
      fixed_amount_minor: discountFields.fixed_amount_minor,
      currency_code:
        input.discount.discount_type === "fixed_amount"
          ? input.general.currency_code.trim().toUpperCase() || null
          : input.general.currency_code.trim().toUpperCase() || null,
      priority: Number(input.general.priority) || 0,
      stacking_mode: input.general.stacking_mode,
      starts_at: new Date(input.general.starts_at).toISOString(),
      ends_at: input.general.ends_at
        ? new Date(input.general.ends_at).toISOString()
        : null,
      maximum_discount_minor: discountFields.maximum_discount_minor,
    },
    errors: {},
  };
}
