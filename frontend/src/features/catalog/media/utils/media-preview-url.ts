import type {
  MediaAttachment,
  MediaPreset,
} from "@/features/catalog/media/types/media-types";

const FORMAT_PRIORITY = ["webp", "jpeg", "png", "avif"] as const;

export function previewUrlForAttachment(
  attachment: MediaAttachment,
  preset: MediaPreset = "thumbnail",
): string | null {
  const sources = attachment.sources;
  if (!sources) {
    return null;
  }

  for (const format of FORMAT_PRIORITY) {
    const bucket = sources[format];
    const entry = bucket?.presets?.[preset];
    if (entry?.url) {
      return entry.url;
    }
  }

  return null;
}
