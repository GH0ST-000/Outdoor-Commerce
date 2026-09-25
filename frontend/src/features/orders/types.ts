export type OrderStatus =
  | "pending_payment"
  | "payment_processing"
  | "confirmed"
  | "manual_review"
  | "cancelled"
  | "expired"
  | string;

export type PaymentStatus =
  | "unpaid"
  | "pending"
  | "paid"
  | "failed"
  | "cancelled"
  | "partially_refunded"
  | "refunded"
  | string;

export type FulfillmentStatus =
  | "unfulfilled"
  | "processing"
  | "partially_fulfilled"
  | "fulfilled"
  | "cancelled"
  | "exception"
  | string;

export type OrderTotals = {
  items_subtotal_minor: number;
  discount_total_minor: number;
  delivery_total_minor: number;
  tax_total_minor: number;
  grand_total_minor: number;
  currency: string;
  price_includes_tax: boolean;
};

export type OrderItem = {
  id: string;
  sku: string;
  name: string;
  variant_name: string;
  attributes: unknown[];
  quantity: number;
  media: { url: string; alt: string | null } | null;
  pricing: {
    unit_base_price_minor: number;
    unit_effective_price_minor: number;
    line_subtotal_minor: number;
    line_discount_minor: number;
    line_total_minor: number;
    currency: string;
  };
};

export type OrderFulfillment = {
  method_code: string;
  method_type: string | null;
  name: string;
  delivery_total_minor: number;
  estimated_min_days: number | null;
  estimated_max_days: number | null;
  pickup_location: {
    id: string | null;
    name: string | null;
    address: string | null;
  } | null;
};

export type OrderAdjustment = {
  type: string;
  code: string;
  label: string;
  amount_minor: number;
};

export type OrderContact = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  customer_note: string | null;
};

export type OrderAddress = {
  recipient_first_name: string | null;
  recipient_last_name: string | null;
  country_code: string | null;
  region: string | null;
  municipality_or_city: string | null;
  district: string | null;
  street: string | null;
  house_number: string | null;
  apartment: string | null;
  postal_code: string | null;
};

export type OrderShipmentEvent = {
  status: string;
  message: string;
  occurred_at: string;
  location: string | null;
};

export type OrderShipment = {
  id: string;
  shipment_number: string;
  type: "delivery" | "store_pickup" | string;
  status: string;
  provider: {
    code: string;
    name: string;
    manual: boolean;
  };
  tracking: {
    number: string | null;
    url: string | null;
  };
  items: Array<{
    order_item_id: string | null;
    name: string | null;
    variant_name: string | null;
    quantity: number;
    media: { url: string; alt: string | null } | null;
  }>;
  timeline: OrderShipmentEvent[];
  pickup_location: {
    id: string | null;
    name: string | null;
    address: string | null;
    instructions?: string | null;
    working_hours?: string | null;
  } | null;
  estimated_delivery: {
    from: string | null;
    to: string | null;
    is_guaranteed: boolean;
  };
  shipped_at: string | null;
  delivered_at: string | null;
  collected_at: string | null;
  exception: { code: string | null; message: string } | null;
};

export type OrderFulfillmentProgress = {
  order_id: string;
  fulfillment_status: FulfillmentStatus;
  shipments: OrderShipment[];
  remaining_items: Array<{
    order_item_id: string;
    name: string;
    variant_name: string;
    quantity: number;
  }>;
  capabilities: {
    can_refresh: boolean;
    poll: boolean;
  };
};

import type { PaymentAttempt } from "@/features/payments/types";

export type Order = {
  id: string;
  order_number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  fulfillment_status: FulfillmentStatus;
  currency: string;
  placed_at: string;
  payment_expires_at: string | null;
  quote_revision: number;
  contact: OrderContact;
  address: OrderAddress | null;
  items: OrderItem[];
  fulfillment: OrderFulfillment;
  totals: OrderTotals;
  adjustments: OrderAdjustment[];
  can_cancel: boolean;
  cancellation_reason_code?: string | null;
  can_pay?: boolean;
  can_retry_payment?: boolean;
  current_payment_attempt?: PaymentAttempt | null;
  fulfillment_progress?: OrderFulfillmentProgress;
};

export type OrderEnvelope = {
  data: Order;
};

export type OrderIssue = {
  code: string;
  message: string;
};
