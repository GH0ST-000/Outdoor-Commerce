import { apiRequest } from "@/lib/api-client";
import type {
  AdminContext,
  AdminRole,
  AdminUser,
  ApiSuccessEnvelope,
  AuditLog,
  AuditLogListParams,
  PaginatedResponse,
  UserListParams,
} from "@/features/admin/types/admin-types";

function toQuery(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === "") {
      continue;
    }
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

export async function fetchAdminContext(): Promise<AdminContext> {
  const response =
    await apiRequest<ApiSuccessEnvelope<AdminContext>>("/v1/admin/context");
  return response.data;
}

export async function fetchAdminUsers(
  params: UserListParams = {},
): Promise<PaginatedResponse<AdminUser>> {
  return apiRequest<PaginatedResponse<AdminUser>>(
    `/v1/admin/users${toQuery(params)}`,
  );
}

export async function fetchAdminUser(id: number | string): Promise<AdminUser> {
  const response = await apiRequest<ApiSuccessEnvelope<AdminUser>>(
    `/v1/admin/users/${id}`,
  );
  return response.data;
}

export async function updateUserStatus(
  id: number | string,
  status: "active" | "disabled",
): Promise<AdminUser> {
  const response = await apiRequest<ApiSuccessEnvelope<AdminUser>>(
    `/v1/admin/users/${id}/status`,
    {
      method: "PATCH",
      body: { status },
    },
  );
  return response.data;
}

export async function updateUserRoles(
  id: number | string,
  roles: string[],
): Promise<AdminUser> {
  const response = await apiRequest<ApiSuccessEnvelope<AdminUser>>(
    `/v1/admin/users/${id}/roles`,
    {
      method: "PUT",
      body: { roles },
    },
  );
  return response.data;
}

export async function fetchAdminRoles(): Promise<AdminRole[]> {
  const response = await apiRequest<
    ApiSuccessEnvelope<AdminRole[]> | PaginatedResponse<AdminRole>
  >("/v1/admin/roles");
  return Array.isArray(response.data) ? response.data : [];
}

export async function fetchAuditLogs(
  params: AuditLogListParams = {},
): Promise<PaginatedResponse<AuditLog>> {
  const normalized: Record<string, string | number | undefined> = {
    event: params.event,
    actor_user_id: params.actor_user_id,
    subject_type: params.subject_type,
    subject_id: params.subject_id,
    from: params.from,
    to: params.to,
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };
  return apiRequest<PaginatedResponse<AuditLog>>(
    `/v1/admin/audit-logs${toQuery(normalized)}`,
  );
}

export async function fetchAuditLog(id: number | string): Promise<AuditLog> {
  const response = await apiRequest<ApiSuccessEnvelope<AuditLog>>(
    `/v1/admin/audit-logs/${id}`,
  );
  return response.data;
}
