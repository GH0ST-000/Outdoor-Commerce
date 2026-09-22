export type CheckoutSessionStatus =
  "draft" | "ready" | "quoted" | "expired" | "cancelled" | "converted";

export type CheckoutQuoteStatus =
  "active" | "superseded" | "expired" | "consumed" | "cancelled";

export type CheckoutContact = {
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  customer_note: string | null;
  complete: boolean;
};

export type CheckoutAddress = {
  recipient_first_name: string;
  recipient_last_name: string;
  phone: string;
  country_code: string;
  region: string | null;
  municipality_or_city: string | null;
  district: string | null;
  street: string | null;
  house_number: string | null;
  apartment: string | null;
  entrance: string | null;
  floor: string | null;
  postal_code: string | null;
  landmark: string | null;
  delivery_instructions: string | null;
};

export type PickupLocationOption = {
  id: string;
  name: string;
  address: string;
  phone: string | null;
  working_hours: string | null;
  instructions: string | null;
  selected: boolean;
};

export type FulfillmentMethodOption = {
  id: string;
  code: string;
  type: "store_pickup" | "local_delivery" | "courier_delivery" | string;
  name: string;
  description: string | null;
  eligible: boolean;
  amount_minor: number | null;
  currency: string;
  estimated_min_days: number | null;
  estimated_max_days: number | null;
  unavailable_reason: string | null;
  requires_address: boolean;
  pickup_locations?: PickupLocationOption[];
};

export type QuoteLinePricing = {
  unit_base_price_minor: number;
  unit_effective_price_minor: number;
  line_subtotal_minor: number;
  line_discount_minor: number;
  line_total_minor: number;
  currency: string;
};

export type QuoteLine = {
  id: string;
  product_id: number;
  variant_id: number;
  slug: string | null;
  name: string;
  variant_label: string;
  sku: string;
  quantity: number;
  media: { url: string; alt: string | null } | null;
  pricing: QuoteLinePricing;
  attributes: Array<{
    code: string;
    name: string;
    value: { code: string; name: string };
  }>;
  promotions: Array<{ code: string; name: string; discount_type: string }>;
  restriction: {
    code: string;
    outcome: string;
    message: string;
    required_action: string | null;
  } | null;
  issues: Array<{ code: string; message: string }>;
};

export type CheckoutQuote = {
  id: string;
  revision: number;
  status: CheckoutQuoteStatus;
  currency: string;
  expires_at: string;
  remaining_seconds: number;
  cart_version: number;
  items: QuoteLine[];
  fulfillment: {
    method_code: string | null;
    name: string | null;
    amount_minor: number;
    estimated_min_days: number | null;
    estimated_max_days: number | null;
  };
  totals: {
    items_subtotal_minor: number;
    discount_total_minor: number;
    delivery_total_minor: number;
    tax_total_minor: number;
    grand_total_minor: number;
    currency: string;
    price_includes_tax: boolean;
  };
  adjustments: Array<{
    type: string;
    code: string;
    label: string;
    amount_minor: number;
  }>;
  restrictions: Array<{
    code: string;
    outcome: string;
    message: string;
    required_action: string | null;
  }>;
  fingerprint: string;
};

export type CheckoutSession = {
  id: string;
  status: CheckoutSessionStatus;
  version: number;
  currency: string;
  cart_version: number;
  expires_at: string | null;
  contact: CheckoutContact;
  address: CheckoutAddress | null;
  billing_same_as_shipping: boolean;
  fulfillment: {
    method_code: string | null;
    pickup_location_id: string | null;
  };
  available_fulfillment_methods: FulfillmentMethodOption[];
  quote: CheckoutQuote | null;
};

export type CheckoutEnvelope = {
  data: {
    checkout_session: CheckoutSession;
    quote: CheckoutQuote | null;
  };
};

export type CheckoutStep = "contact" | "delivery" | "review";
