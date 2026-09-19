import { z } from "zod";
import {
  isValidAttributeCode,
  isValidColorHex,
} from "@/features/catalog/attributes/utils/attribute-format";
import type { AttributeType } from "@/features/catalog/attributes/types/attribute-types";

const optionalText = z
  .string()
  .trim()
  .max(2000, "Description must be 2000 characters or fewer.")
  .optional()
  .transform((value) => (value && value.length > 0 ? value : null));

const englishTranslationSchema = z.object({
  locale: z.literal("en"),
  name: z.string().trim().max(255),
  description: z.string().optional(),
});

const georgianTranslationSchema = z.object({
  locale: z.literal("ka"),
  name: z.string().trim().min(1, "Name is required.").max(255),
  description: optionalText,
});

function requireEnglishName(
  en: { name: string; description?: string } | undefined,
  ctx: z.RefinementCtx,
) {
  if (!en) {
    return;
  }
  const hasContent = en.name.trim() !== "" || Boolean(en.description?.trim());
  if (hasContent && en.name.trim() === "") {
    ctx.addIssue({
      code: "custom",
      path: ["translations", "en", "name"],
      message:
        "English name is required when providing an English translation.",
    });
  }
}

export const attributeFormSchema = z
  .object({
    code: z
      .string()
      .trim()
      .min(1, "Code is required.")
      .max(64)
      .refine(
        isValidAttributeCode,
        "Use lowercase letters, numbers, and underscores (start with a letter).",
      ),
    type: z.enum(["select", "color"]),
    status: z.enum(["draft", "active", "archived"]),
    is_filterable: z.boolean(),
    sort_order: z.number().int().min(0),
    translations: z.object({
      ka: georgianTranslationSchema,
      en: englishTranslationSchema.optional(),
    }),
  })
  .superRefine((data, ctx) => {
    requireEnglishName(data.translations.en, ctx);
  });

export type AttributeFormValues = z.infer<typeof attributeFormSchema>;

export function attributeValueFormSchema(type: AttributeType) {
  return z
    .object({
      code: z
        .string()
        .trim()
        .min(1, "Code is required.")
        .max(64)
        .refine(
          isValidAttributeCode,
          "Use lowercase letters, numbers, and underscores (start with a letter).",
        ),
      status: z.enum(["draft", "active", "archived"]),
      sort_order: z.number().int().min(0),
      color_hex: z.string().trim(),
      translations: z.object({
        ka: georgianTranslationSchema,
        en: englishTranslationSchema.optional(),
      }),
    })
    .superRefine((data, ctx) => {
      requireEnglishName(data.translations.en, ctx);

      if (data.color_hex === "") {
        if (type === "color") {
          ctx.addIssue({
            code: "custom",
            path: ["color_hex"],
            message: "Color values need a hex color such as #1A2B3C.",
          });
        }
        return;
      }

      if (type !== "color") {
        ctx.addIssue({
          code: "custom",
          path: ["color_hex"],
          message: "Only color attributes accept a color value.",
        });
        return;
      }

      if (!isValidColorHex(data.color_hex)) {
        ctx.addIssue({
          code: "custom",
          path: ["color_hex"],
          message: "Use a 3- or 6-digit hex color such as #1A2B3C.",
        });
      }
    });
}

export type AttributeValueFormValues = z.infer<
  ReturnType<typeof attributeValueFormSchema>
>;
