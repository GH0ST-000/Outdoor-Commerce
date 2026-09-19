import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { CombinationBuilder } from "@/features/catalog/variants/components/CombinationBuilder";
import type { AttributeValueListItem } from "@/features/catalog/attributes/types/attribute-types";
import type { VariantAxis } from "@/features/catalog/variants/types/variant-types";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const previewProductVariantGeneration = vi.fn();
const generateProductVariants = vi.fn();

vi.mock("@/features/catalog/variants/api/variants-api", () => ({
  previewProductVariantGeneration: (...args: unknown[]) =>
    previewProductVariantGeneration(...args),
  generateProductVariants: (...args: unknown[]) =>
    generateProductVariants(...args),
}));

const axes: VariantAxis[] = [
  {
    attribute_id: 1,
    code: "color",
    name: "Color",
    type: "color",
    status: "active",
    sort_order: 0,
  },
];

const values: Record<number, AttributeValueListItem[]> = {
  1: [
    {
      id: 11,
      attribute_id: 1,
      code: "red",
      name: "Red",
      status: "active",
      sort_order: 0,
      color_hex: "#FF0000",
      created_at: null,
      updated_at: null,
      deleted_at: null,
    },
    {
      id: 12,
      attribute_id: 1,
      code: "blue",
      name: "Blue",
      status: "active",
      sort_order: 1,
      color_hex: "#0000FF",
      created_at: null,
      updated_at: null,
      deleted_at: null,
    },
  ],
};

function preview(overrides: Record<string, unknown> = {}) {
  return {
    axes: [
      { attribute_id: 1, code: "color", name: "Color", value_ids: [11, 12] },
    ],
    limit: 100,
    total_combinations: 6,
    existing_count: 2,
    new_count: 4,
    exceeds_limit: false,
    truncated: false,
    combinations: [],
    ...overrides,
  };
}

function renderBuilder(canManage = true, onGenerated = vi.fn()) {
  return render(
    <TestProviders>
      <CombinationBuilder
        productId="7"
        axes={axes}
        valuesByAxis={values}
        canManage={canManage}
        onGenerated={onGenerated}
      />
    </TestProviders>,
  );
}

describe("CombinationBuilder", () => {
  beforeEach(() => {
    previewProductVariantGeneration.mockReset();
    generateProductVariants.mockReset();
    resetApiClientStateForTests();
  });

  it("shows total, existing, new, and limit counts from the preview", async () => {
    const user = userEvent.setup();
    previewProductVariantGeneration.mockResolvedValue(preview());

    renderBuilder();

    expect(
      screen.getByRole("button", { name: "Preview combinations" }),
    ).toBeDisabled();

    await user.click(screen.getByRole("checkbox", { name: /Red/ }));
    await user.click(screen.getByRole("checkbox", { name: /Blue/ }));
    await user.click(
      screen.getByRole("button", { name: "Preview combinations" }),
    );

    const summary = await screen.findByTestId("preview-summary");
    expect(summary).toHaveTextContent("Total combinations: 6");
    expect(summary).toHaveTextContent("Existing: 2");
    expect(summary).toHaveTextContent("New: 4");
    expect(summary).toHaveTextContent("Limit: 100");
    expect(previewProductVariantGeneration).toHaveBeenCalledWith("7", [
      { attribute_id: 1, attribute_value_ids: [11, 12] },
    ]);
    expect(
      screen.getByRole("button", { name: "Generate variants" }),
    ).toBeEnabled();
  });

  it("warns and blocks generation when the selection exceeds the limit", async () => {
    const user = userEvent.setup();
    previewProductVariantGeneration.mockResolvedValue(
      preview({
        total_combinations: 240,
        new_count: 240,
        existing_count: 0,
        exceeds_limit: true,
        truncated: true,
      }),
    );

    renderBuilder();
    await user.click(screen.getByRole("checkbox", { name: /Red/ }));
    await user.click(
      screen.getByRole("button", { name: "Preview combinations" }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "exceeds the 100 combination limit",
    );
    expect(
      screen.queryByRole("button", { name: "Generate variants" }),
    ).toBeNull();
  });

  it("generates after confirmation", async () => {
    const user = userEvent.setup();
    const onGenerated = vi.fn();
    previewProductVariantGeneration.mockResolvedValue(preview());
    generateProductVariants.mockResolvedValue({
      created: [],
      summary: { requested: 6, created: 4, skipped: 2, limit: 100 },
    });

    renderBuilder(true, onGenerated);
    await user.click(screen.getByRole("checkbox", { name: /Red/ }));
    await user.click(
      screen.getByRole("button", { name: "Preview combinations" }),
    );
    await user.click(
      await screen.findByRole("button", { name: "Generate variants" }),
    );

    expect(
      await screen.findByText("Generate 4 new draft variants?"),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Confirm generate" }));

    expect(generateProductVariants).toHaveBeenCalledWith(
      "7",
      [{ attribute_id: 1, attribute_value_ids: [11] }],
      "draft",
    );
    expect(onGenerated).toHaveBeenCalledWith(
      "Generated 4 variants (2 skipped).",
    );
  });

  it("hides generation controls without catalog.manage", async () => {
    const user = userEvent.setup();
    previewProductVariantGeneration.mockResolvedValue(preview());

    renderBuilder(false);
    expect(screen.getByRole("checkbox", { name: /Red/ })).toBeDisabled();

    await user.click(
      screen.getByRole("button", { name: "Preview combinations" }),
    );
    expect(
      screen.queryByRole("button", { name: "Generate variants" }),
    ).toBeNull();
  });
});
