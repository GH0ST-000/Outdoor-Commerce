import { z } from "zod";

const noMarkup = (value: string) => !/[<>]/.test(value);

export const checkoutContactSchema = z.object({
  first_name: z.string().trim().min(1).max(80).refine(noMarkup),
  last_name: z.string().trim().min(1).max(80).refine(noMarkup),
  email: z.string().trim().email().max(255),
  phone: z.string().trim().min(6).max(32),
  customer_note: z.string().trim().max(1000).refine(noMarkup).optional(),
});

export const checkoutAddressSchema = z.object({
  recipient_first_name: z.string().trim().min(1).max(80).refine(noMarkup),
  recipient_last_name: z.string().trim().min(1).max(80).refine(noMarkup),
  phone: z.string().trim().min(6).max(32),
  country_code: z.string().length(2),
  region: z.string().trim().max(128).refine(noMarkup).optional(),
  municipality_or_city: z.string().trim().min(1).max(128).refine(noMarkup),
  district: z.string().trim().max(128).refine(noMarkup).optional(),
  street: z.string().trim().max(160).refine(noMarkup).optional(),
  house_number: z.string().trim().max(32).refine(noMarkup).optional(),
  apartment: z.string().trim().max(32).refine(noMarkup).optional(),
  entrance: z.string().trim().max(32).refine(noMarkup).optional(),
  floor: z.string().trim().max(16).refine(noMarkup).optional(),
  postal_code: z.string().trim().max(16).refine(noMarkup).optional(),
  landmark: z.string().trim().max(160).refine(noMarkup).optional(),
  delivery_instructions: z.string().trim().max(500).refine(noMarkup).optional(),
});

export type CheckoutContactInput = z.infer<typeof checkoutContactSchema>;
export type CheckoutAddressInput = z.infer<typeof checkoutAddressSchema>;
