import { z } from "zod";
import { isValidSlug } from "@/features/catalog/products/utils/slug";

const optionalText = z
  .string()
  .trim()
  .optional()
  .transform((value) => (value && value.length > 0 ? value : null));

export const productTranslationSchema = z.object({
  locale: z.enum(["ka", "en"]),
  name: z.string().trim().min(1, "Name is required.").max(255),
  slug: z
    .string()
    .trim()
    .min(1, "Slug is required.")
    .max(255)
    .refine(isValidSlug, "Enter a valid slug."),
  short_description: optionalText,
  description: optionalText,
  seo_title: z
    .string()
    .trim()
    .max(70, "SEO title must be 70 characters or fewer.")
    .optional()
    .transform((value) => (value && value.length > 0 ? value : null)),
  seo_description: z
    .string()
    .trim()
    .max(170, "SEO description must be 170 characters or fewer.")
    .optional()
    .transform((value) => (value && value.length > 0 ? value : null)),
});

export const productFormSchema = z
  .object({
    brand_id: z.number().int().positive().nullable(),
    primary_category_id: z.number().int().positive().nullable(),
    category_ids: z.array(z.number().int().positive()),
    status: z.enum(["draft", "active", "archived"]),
    model_number: optionalText,
    manufacturer_part_number: optionalText,
    is_featured: z.boolean(),
    sort_order: z.number().int().min(0),
    translations: z.object({
      ka: productTranslationSchema.extend({ locale: z.literal("ka") }),
      en: z
        .object({
          locale: z.literal("en"),
          name: z.string().trim().max(255),
          slug: z.string().trim().max(255),
          short_description: z.string().optional(),
          description: z.string().optional(),
          seo_title: z.string().optional(),
          seo_description: z.string().optional(),
        })
        .optional(),
    }),
  })
  .superRefine((data, ctx) => {
    if (
      data.primary_category_id !== null &&
      !data.category_ids.includes(data.primary_category_id)
    ) {
      ctx.addIssue({
        code: "custom",
        path: ["category_ids"],
        message: "Primary category must be included in category assignments.",
      });
    }

    const en = data.translations.en;
    if (!en) {
      return;
    }

    const hasAny =
      en.name.trim() !== "" ||
      en.slug.trim() !== "" ||
      Boolean(en.short_description?.trim()) ||
      Boolean(en.description?.trim()) ||
      Boolean(en.seo_title?.trim()) ||
      Boolean(en.seo_description?.trim());

    if (!hasAny) {
      return;
    }

    if (en.name.trim() === "") {
      ctx.addIssue({
        code: "custom",
        path: ["translations", "en", "name"],
        message:
          "English name is required when providing an English translation.",
      });
    }
    if (en.slug.trim() === "" || !isValidSlug(en.slug.trim())) {
      ctx.addIssue({
        code: "custom",
        path: ["translations", "en", "slug"],
        message: "Enter a valid English slug.",
      });
    }
  });

export type ProductFormValues = z.infer<typeof productFormSchema>;
