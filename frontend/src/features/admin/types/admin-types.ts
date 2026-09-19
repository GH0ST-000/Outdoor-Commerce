import type { ApprovedRole } from "@/features/admin/permissions/permissions";

export type AdminUserSummary = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  email_verified: boolean;
  status: "active" | "disabled";
};

export type AdminContext = {
  user: AdminUserSummary;
  roles: string[];
  permissions: string[];
};

export type AdminUser = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  email_verified: boolean;
  status: "active" | "disabled";
  last_login_at: string | null;
  roles: string[];
  created_at: string | null;
  updated_at: string | null;
};

export type AdminRole = {
  name: ApprovedRole | string;
  description: string;
  permissions: string[];
};

export type AuditLog = {
  id: number;
  actor_user_id: number | null;
  event: string;
  subject_type: string | null;
  subject_id: string | null;
  request_id: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  created_at: string | null;
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  request_id?: string;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
  links?: {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
  };
};

export type ApiSuccessEnvelope<T> = {
  data: T;
  meta?: {
    request_id?: string;
  };
};

export type UserListParams = {
  search?: string;
  status?: "active" | "disabled" | "";
  role?: string;
  sort?: "created_at" | "last_login_at" | "email" | "first_name";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type AuditLogListParams = {
  event?: string;
  actor_user_id?: number | string;
  subject_type?: string;
  subject_id?: string;
  from?: string;
  to?: string;
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};
