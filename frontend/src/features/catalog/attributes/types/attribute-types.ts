export type AttributeStatus = "draft" | "active" | "archived";
export type AttributeType = "select" | "color";
export type AttributeValueStatus = AttributeStatus;

export type AttributeTranslation = {
  locale: string;
  name: string;
  description: string | null;
};

export type AttributeListItem = {
  id: number;
  code: string;
  name: string | null;
  type: AttributeType;
  status: AttributeStatus;
  is_filterable: boolean;
  sort_order: number;
  value_count: number;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type AttributeDetail = {
  id: number;
  code: string;
  type: AttributeType;
  status: AttributeStatus;
  is_filterable: boolean;
  sort_order: number;
  value_count: number;
  translations: AttributeTranslation[];
  created_by: number | null;
  updated_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type AttributeValueListItem = {
  id: number;
  attribute_id: number;
  code: string;
  name: string | null;
  status: AttributeValueStatus;
  sort_order: number;
  color_hex: string | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type AttributeValueDetail = {
  id: number;
  attribute_id: number;
  attribute: {
    id: number;
    code: string;
    type: AttributeType;
    status: AttributeStatus;
  } | null;
  code: string;
  status: AttributeValueStatus;
  sort_order: number;
  color_hex: string | null;
  metadata: Record<string, unknown> | null;
  translations: AttributeTranslation[];
  created_by: number | null;
  updated_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type AttributeListParams = {
  search?: string;
  status?: AttributeStatus | "";
  type?: AttributeType | "";
  is_filterable?: "1" | "0" | "";
  locale?: string;
  include_deleted?: "1" | "0" | "";
  sort?: "created_at" | "updated_at" | "sort_order" | "code" | "status";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type AttributeValueListParams = {
  search?: string;
  status?: AttributeValueStatus | "";
  locale?: string;
  include_deleted?: "1" | "0" | "";
  sort?: "created_at" | "updated_at" | "sort_order" | "code" | "status";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type AttributeTranslationInput = {
  locale: string;
  name: string;
  description?: string | null;
};

export type AttributeWritePayload = {
  code?: string;
  type?: AttributeType;
  status?: AttributeStatus;
  is_filterable?: boolean;
  sort_order?: number;
  translations?: AttributeTranslationInput[];
};

export type AttributeValueWritePayload = {
  code?: string;
  status?: AttributeValueStatus;
  sort_order?: number;
  color_hex?: string | null;
  translations?: AttributeTranslationInput[];
};
