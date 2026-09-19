import { z } from "zod";

export const SKU_PATTERN = /^[A-Za-z0-9_-]+$/;

export function variantFormSchema(axisIds: readonly number[]) {
  return z
    .object({
      sku: z
        .string()
        .trim()
        .max(64, "SKU must be at most 64 characters.")
        .refine(
          (value) => value === "" || SKU_PATTERN.test(value),
          "SKU may only contain A-Z, 0-9, hyphen, and underscore.",
        ),
      barcode: z
        .string()
        .trim()
        .max(32, "Barcode must be at most 32 characters."),
      status: z.enum(["draft", "active", "archived"]),
      is_default: z.boolean(),
      attribute_values: z.record(z.string(), z.number().int().nullable()),
    })
    .superRefine((data, ctx) => {
      for (const axisId of axisIds) {
        const value = data.attribute_values[String(axisId)];
        if (value == null) {
          ctx.addIssue({
            code: "custom",
            path: ["attribute_values", String(axisId)],
            message: "Select a value for every axis.",
          });
        }
      }
    });
}

export type VariantFormValues = z.infer<ReturnType<typeof variantFormSchema>>;
