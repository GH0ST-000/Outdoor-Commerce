import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AttributeFormPage } from "@/features/catalog/attributes/components/AttributeFormPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const createAdminAttribute = vi.fn();
const updateAdminAttribute = vi.fn();
const fetchAdminAttribute = vi.fn();
const archiveAdminAttribute = vi.fn();
const fetchAdminAttributeValues = vi.fn();
const fetchAdminAttributeValue = vi.fn();
const createAdminAttributeValue = vi.fn();
const updateAdminAttributeValue = vi.fn();
const archiveAdminAttributeValue = vi.fn();
const restoreAdminAttributeValue = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/catalog/attributes/new",
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/catalog/attributes/api/attributes-api", () => ({
  createAdminAttribute: (...args: unknown[]) => createAdminAttribute(...args),
  updateAdminAttribute: (...args: unknown[]) => updateAdminAttribute(...args),
  fetchAdminAttribute: (...args: unknown[]) => fetchAdminAttribute(...args),
  archiveAdminAttribute: (...args: unknown[]) => archiveAdminAttribute(...args),
  fetchAdminAttributeValues: (...args: unknown[]) =>
    fetchAdminAttributeValues(...args),
  fetchAdminAttributeValue: (...args: unknown[]) =>
    fetchAdminAttributeValue(...args),
  createAdminAttributeValue: (...args: unknown[]) =>
    createAdminAttributeValue(...args),
  updateAdminAttributeValue: (...args: unknown[]) =>
    updateAdminAttributeValue(...args),
  archiveAdminAttributeValue: (...args: unknown[]) =>
    archiveAdminAttributeValue(...args),
  restoreAdminAttributeValue: (...args: unknown[]) =>
    restoreAdminAttributeValue(...args),
}));

function renderForm(
  permissions: string[],
  props: { mode: "create" | "edit"; attributeId?: string },
) {
  useAdminContextMock.mockReturnValue({
    permissions,
    roles: [],
    context: null,
    status: "ready",
    error: null,
    load: vi.fn(),
    refresh: vi.fn(),
  });

  return render(
    <TestProviders>
      <AttributeFormPage {...props} />
    </TestProviders>,
  );
}

describe("AttributeFormPage", () => {
  beforeEach(() => {
    createAdminAttribute.mockReset();
    updateAdminAttribute.mockReset();
    fetchAdminAttribute.mockReset();
    archiveAdminAttribute.mockReset();
    fetchAdminAttributeValues.mockReset();
    fetchAdminAttributeValue.mockReset();
    createAdminAttributeValue.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();

    fetchAdminAttributeValues.mockResolvedValue({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
        from: null,
        to: null,
      },
    });
  });

  it("requires a Georgian name before submit", async () => {
    const user = userEvent.setup();
    renderForm([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE], {
      mode: "create",
    });

    await user.type(await screen.findByLabelText("Code"), "color");
    await user.click(screen.getByRole("button", { name: "Create attribute" }));

    expect(await screen.findByText("Name is required.")).toBeInTheDocument();
    expect(createAdminAttribute).not.toHaveBeenCalled();
  });

  it("rejects codes that are not snake_case identifiers", async () => {
    const user = userEvent.setup();
    renderForm([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE], {
      mode: "create",
    });

    const code = await screen.findByLabelText("Code");
    await user.type(code, "1color");
    await user.type(screen.getByLabelText("Name"), "ფერი");
    await user.click(screen.getByRole("button", { name: "Create attribute" }));

    expect(
      await screen.findByText(
        "Use lowercase letters, numbers, and underscores (start with a letter).",
      ),
    ).toBeInTheDocument();
    expect(createAdminAttribute).not.toHaveBeenCalled();
  });

  it("hides activate status without catalog.publish", async () => {
    const user = userEvent.setup();
    renderForm([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE], {
      mode: "create",
    });

    await user.click(await screen.findByLabelText("Status"));
    const listbox = await screen.findByRole("listbox");
    const values = within(listbox)
      .getAllByRole("option")
      .map((option) => option.getAttribute("data-value") ?? option.textContent);

    expect(values).toEqual(["draft"]);
    expect(
      screen.getByText("Activate is hidden without catalog.publish."),
    ).toBeInTheDocument();
  });

  it("renders the values panel with a color swatch on edit", async () => {
    fetchAdminAttribute.mockResolvedValue({
      id: 3,
      code: "color",
      type: "color",
      status: "active",
      is_filterable: true,
      sort_order: 0,
      value_count: 1,
      translations: [{ locale: "ka", name: "ფერი", description: null }],
      created_by: 1,
      updated_by: 1,
      created_at: null,
      updated_at: null,
      deleted_at: null,
    });
    fetchAdminAttributeValues.mockResolvedValue({
      data: [
        {
          id: 9,
          attribute_id: 3,
          code: "red",
          name: "წითელი",
          status: "active",
          sort_order: 0,
          color_hex: "#FF0000",
          created_at: null,
          updated_at: null,
          deleted_at: null,
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    renderForm([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE], {
      mode: "edit",
      attributeId: "3",
    });

    expect(await screen.findByText("წითელი")).toBeInTheDocument();
    expect(screen.getByRole("img", { name: "Color #FF0000" })).toBeVisible();
  });

  it("hides value management without catalog.manage", async () => {
    fetchAdminAttribute.mockResolvedValue({
      id: 3,
      code: "size",
      type: "select",
      status: "draft",
      is_filterable: false,
      sort_order: 0,
      value_count: 0,
      translations: [{ locale: "ka", name: "ზომა", description: null }],
      created_by: 1,
      updated_by: 1,
      created_at: null,
      updated_at: null,
      deleted_at: null,
    });

    renderForm([PERMISSIONS.CATALOG_VIEW], {
      mode: "edit",
      attributeId: "3",
    });

    expect(await screen.findByText("No values yet.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "New value" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Save changes" })).toBeNull();
  });
});
