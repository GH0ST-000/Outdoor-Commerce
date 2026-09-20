import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { MediaGallery } from "@/features/catalog/media/components/MediaGallery";
import type { MediaAttachment } from "@/features/catalog/media/types/media-types";
import { TestProviders } from "@/test/providers";

function attachment(
  overrides: Partial<MediaAttachment> = {},
): MediaAttachment {
  return {
    id: 1,
    asset_id: 10,
    role: "gallery",
    status: "ready",
    is_primary: false,
    sort_order: 0,
    original_filename: "scope.jpg",
    mime_type: "image/jpeg",
    byte_size: 1000,
    width: 800,
    height: 600,
    failure_code: null,
    focal_point: null,
    alt_text: null,
    caption: null,
    translations: [],
    sources: null,
    created_at: null,
    updated_at: null,
    ...overrides,
  };
}

describe("MediaGallery", () => {
  it("renders an empty state", () => {
    render(
      <TestProviders>
        <MediaGallery
          attachments={[]}
          canManage
          pending={false}
          onSetPrimary={vi.fn()}
          onEdit={vi.fn()}
          onRemove={vi.fn()}
          onRetry={vi.fn()}
          onReorder={vi.fn()}
        />
      </TestProviders>,
    );

    expect(screen.getByTestId("media-gallery-empty")).toHaveTextContent(
      "No images yet",
    );
  });

  it("calls onSetPrimary from the card action", async () => {
    const user = userEvent.setup();
    const onSetPrimary = vi.fn();

    render(
      <TestProviders>
        <MediaGallery
          attachments={[attachment({ id: 5, status: "ready" })]}
          canManage
          pending={false}
          onSetPrimary={onSetPrimary}
          onEdit={vi.fn()}
          onRemove={vi.fn()}
          onRetry={vi.fn()}
          onReorder={vi.fn()}
        />
      </TestProviders>,
    );

    await user.click(screen.getByRole("button", { name: "Set primary" }));
    expect(onSetPrimary).toHaveBeenCalledWith(
      expect.objectContaining({ id: 5 }),
    );
  });

  it("hides manage actions without catalog.manage", () => {
    render(
      <TestProviders>
        <MediaGallery
          attachments={[attachment()]}
          canManage={false}
          pending={false}
          onSetPrimary={vi.fn()}
          onEdit={vi.fn()}
          onRemove={vi.fn()}
          onRetry={vi.fn()}
          onReorder={vi.fn()}
        />
      </TestProviders>,
    );

    expect(
      screen.getByText(/catalog.manage required/i),
    ).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Set primary" })).toBeNull();
  });
});
