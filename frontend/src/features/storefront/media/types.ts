import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";

export type MediaStatus =
  | "pending"
  | "processing"
  | "ready"
  | "failed"
  | "quarantined";

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

export type ResponsiveMedia = {
  alt: Record<StorefrontLocale, string>;
  width?: number | null;
  height?: number | null;
  focalPoint?: { x: number; y: number } | null;
  sources?: Partial<Record<MediaFormat, MediaFormatSources>> | null;
  /** Fixture/simple URL fallback when derivatives are not available yet. */
  fallbackSrc?: string;
};
