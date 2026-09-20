import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { MediaEditDialog } from "@/features/catalog/media/components/MediaEditDialog";
import type { MediaAttachment } from "@/features/catalog/media/types/media-types";
import { TestProviders } from "@/test/providers";

const base: MediaAttachment = {
  id: 1,
  asset_id: 2,
  role: "gallery",
  status: "ready",
  is_primary: true,
  sort_order: 0,
  original_filename: "jacket.jpg",
  mime_type: "image/jpeg",
  byte_size: 5000,
  width: 1200,
  height: 1500,
  failure_code: null,
  focal_point: { x: 0.5, y: 0.5 },
  alt_text: "ქურთუკი",
  caption: null,
  translations: [
    { locale: "ka", alt_text: "ქურთუკი", caption: null },
    { locale: "en", alt_text: "Jacket", caption: null },
  ],
  sources: null,
  created_at: null,
  updated_at: null,
};

describe("MediaEditDialog", () => {
  it("submits updated alt text", async () => {
    const user = userEvent.setup();
    const onSave = vi.fn();

    render(
      <TestProviders>
        <MediaEditDialog
          attachment={base}
          canManage
          pending={false}
          onCancel={vi.fn()}
          onSave={onSave}
        />
      </TestProviders>,
    );

    const altInput = screen.getByLabelText("Alt text");
    await user.clear(altInput);
    await user.type(altInput, "ახალი alt");
    await user.click(screen.getByRole("button", { name: "Save metadata" }));

    expect(onSave).toHaveBeenCalledWith(
      expect.objectContaining({
        translations: expect.arrayContaining([
          expect.objectContaining({ locale: "ka", alt_text: "ახალი alt" }),
        ]),
      }),
    );
  });
});
