import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MediaUploadDropzone } from "@/features/catalog/media/components/MediaUploadDropzone";
import { MEDIA_UPLOAD } from "@/features/catalog/media/config/media-upload-config";
import { TestProviders } from "@/test/providers";

describe("MediaUploadDropzone", () => {
  it("shows accepted formats and size limits", () => {
    render(
      <TestProviders>
        <MediaUploadDropzone disabled={false} onFilesSelected={vi.fn()} />
      </TestProviders>,
    );

    expect(
      screen.getByText(new RegExp(MEDIA_UPLOAD.acceptedExtensionsLabel)),
    ).toBeInTheDocument();
    expect(
      screen.getByText(new RegExp(MEDIA_UPLOAD.maxFileSizeLabel)),
    ).toBeInTheDocument();
  });
});
