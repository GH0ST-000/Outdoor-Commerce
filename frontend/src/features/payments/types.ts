export type PaymentAttemptStatus =
  | "created"
  | "pending"
  | "requires_action"
  | "processing"
  | "succeeded"
  | "failed"
  | "cancelled"
  | "expired"
  | "unknown"
  | "manual_review"
  | string;

export type PaymentMethod = {
  code: string;
  type: string;
  name: string;
  description: string;
  icon: string;
  development_only: boolean;
  supported_currencies: string[];
};

export type PaymentAction = {
  type: string;
  url?: string;
  method?: string;
  expires_at?: string | null;
} | null;

export type PaymentAttempt = {
  id: string;
  order_id: string;
  status: PaymentAttemptStatus;
  payment_method: {
    code: string;
    name: string;
  };
  amount: {
    amount_minor: number;
    currency: string;
  };
  action: PaymentAction;
  failure: {
    category: string;
    code: string | null;
    message_key: string;
  } | null;
  order: {
    status: string;
    payment_status: string;
    payment_expires_at: string | null;
  };
};

export type PaymentAttemptEnvelope = {
  data: PaymentAttempt | null;
};

export type PaymentMethodsEnvelope = {
  data: PaymentMethod[];
};
