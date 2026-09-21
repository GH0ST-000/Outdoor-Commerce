/** Mirrors backend `config/media.php` upload limits for client-side checks. */
export const MEDIA_UPLOAD = {
  maxFilesPerRequest: 10,
  maxFileSizeBytes: 15 * 1024 * 1024,
  acceptedMimeTypes: ["image/jpeg", "image/png", "image/webp"] as const,
  acceptedExtensionsLabel: "JPEG, PNG, WebP",
  maxFileSizeLabel: "15 MB",
} as const;

export function isAcceptedMediaMime(type: string): boolean {
  return (MEDIA_UPLOAD.acceptedMimeTypes as readonly string[]).includes(type);
}
