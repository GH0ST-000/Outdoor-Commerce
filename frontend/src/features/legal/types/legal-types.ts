export type LegalOutcome =
  "allowed" | "prohibited" | "conditional" | "unknown" | "conflict";

export type LegalInformation = {
  available: boolean;
  message_key: string;
  outcome?: LegalOutcome;
  summary?: string;
  rules?: {
    id: string;
    title: string;
    effect: string;
    interpretation_summary: string;
    effective_from: string;
    effective_until: string | null;
  }[];
  limits?: {
    rule_id: string;
    limit_type: string;
    amount: string | number | null;
    unit: string | null;
    period: string;
    applies_per: string;
  }[];
  citations?: {
    rule_id: string;
    reference_code: string | null;
    excerpt: string | null;
    is_primary: boolean;
    document_title?: string | null;
    source_name?: string | null;
    official_url?: string | null;
  }[];
  conflicts?: {
    id: string;
    type: string;
    severity: string;
    status: string;
  }[];
  last_verified_at?: string | null;
  disclaimer?: string;
};

export type LegalDashboard = {
  sources_awaiting_verification: number;
  versions_awaiting_review: number;
  rules_awaiting_review: number;
  open_high_conflicts: number;
  open_change_detections: number;
  recently_published_rules: { id: string; title: string }[];
  documents_without_current_version: number;
  disclaimer: string;
};

export type LegalPagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};
