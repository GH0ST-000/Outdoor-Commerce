import { render, screen, waitFor, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AttributesListPage } from "@/features/catalog/attributes/components/AttributesListPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchAdminAttributes = vi.fn();
const archiveAdminAttribute = vi.fn();
const restoreAdminAttribute = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/catalog/attributes",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/catalog/attributes/api/attributes-api", () => ({
  fetchAdminAttributes: (...args: unknown[]) => fetchAdminAttributes(...args),
  archiveAdminAttribute: (...args: unknown[]) => archiveAdminAttribute(...args),
  restoreAdminAttribute: (...args: unknown[]) => restoreAdminAttribute(...args),
}));

function renderList() {
  return render(
    <TestProviders>
      <AttributesListPage />
    </TestProviders>,
  );
}

function attribute(overrides: Record<string, unknown> = {}) {
  return {
    id: 3,
    code: "color",
    name: "ფერი",
    type: "color",
    status: "active",
    is_filterable: true,
    sort_order: 0,
    value_count: 4,
    created_at: null,
    updated_at: null,
    deleted_at: null,
    ...overrides,
  };
}

const meta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 1,
  from: 1,
  to: 1,
};

describe("AttributesListPage", () => {
  beforeEach(() => {
    fetchAdminAttributes.mockReset();
    archiveAdminAttribute.mockReset();
    restoreAdminAttribute.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();

    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE],
      roles: ["catalog-manager"],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });
  });

  it("shows a loading state then rows", async () => {
    let resolveAttributes: (value: unknown) => void = () => undefined;
    fetchAdminAttributes.mockReturnValue(
      new Promise((resolve) => {
        resolveAttributes = resolve;
      }),
    );

    renderList();
    expect(screen.getByRole("status")).toHaveTextContent("Loading attributes");

    resolveAttributes({ data: [attribute()], meta });

    expect(await screen.findByText("ფერი")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "ფერი" })).toHaveAttribute(
      "href",
      "/admin/catalog/attributes/3/edit",
    );
    expect(
      within(screen.getByRole("table")).getByText("Filterable"),
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Archive" })).toBeInTheDocument();
  });

  it("shows an empty state when no attributes match", async () => {
    fetchAdminAttributes.mockResolvedValue({
      data: [],
      meta: { ...meta, total: 0, from: null, to: null },
    });

    renderList();
    expect(
      await screen.findByText("No attributes match these filters."),
    ).toBeInTheDocument();
  });

  it("shows an error state when the API fails", async () => {
    const { ApiClientError } = await import("@/lib/api-client");
    fetchAdminAttributes.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "PERMISSION_DENIED",
        message: "Permission denied.",
      }),
    );

    renderList();
    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("Permission denied.");
    });
  });

  it("hides manage actions without catalog.manage", async () => {
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.CATALOG_VIEW],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });

    fetchAdminAttributes.mockResolvedValue({
      data: [attribute({ name: "Size", code: "size", type: "select" })],
      meta,
    });

    renderList();
    expect(await screen.findByText("Size")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "New attribute" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Archive" })).toBeNull();
  });
});
