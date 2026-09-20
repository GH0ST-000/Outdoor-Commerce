export type MediaStatus =
  | "pending"
  | "processing"
  | "ready"
  | "failed"
  | "quarantined";

export type MediaAttachmentRole = "gallery";

export type MediaPreset =
  | "thumbnail"
  | "card"
  | "card_large"
  | "detail"
  | "zoom";

export type MediaFormat = "webp" | "jpeg" | "png" | "avif";

export type MediaPresetEntry = {
  url: string;
  width: number;
  height: number;
  byte_size: number;
};

export type MediaFormatSources = {
  srcset: string;
  presets: Partial<Record<MediaPreset, MediaPresetEntry>>;
};

export type MediaTranslationRow = {
  locale: string;
  alt_text: string | null;
  caption: string | null;
};

export type MediaAttachment = {
  id: number;
  asset_id: number;
  role: MediaAttachmentRole;
  status: MediaStatus;
  is_primary: boolean;
  sort_order: number;
  original_filename: string | null;
  mime_type: string | null;
  byte_size: number | null;
  width: number | null;
  height: number | null;
  failure_code: string | null;
  focal_point: { x: number; y: number } | null;
  alt_text: string | null;
  caption: string | null;
  translations: MediaTranslationRow[];
  sources: Partial<Record<MediaFormat, MediaFormatSources>> | null;
  created_at: string | null;
  updated_at: string | null;
};

export type MediaAssetStatus = {
  id: number;
  status: MediaStatus;
  original_filename: string | null;
  mime_type: string | null;
  byte_size: number | null;
  width: number | null;
  height: number | null;
  attempts: number;
  failure_code: string | null;
  failure_message: string | null;
  derivative_count: number;
  processed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type MediaMetadataPayload = {
  translations?: Array<{
    locale: string;
    alt_text?: string | null;
    caption?: string | null;
  }>;
  focal_point?: { x: number; y: number } | null;
};

export type MediaOwnerScope =
  | { kind: "product"; productId: string | number }
  | {
      kind: "variant";
      productId: string | number;
      variantId: string | number;
    };
