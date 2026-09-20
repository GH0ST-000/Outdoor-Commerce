"use client";

import { useId, useRef, useState } from "react";
import {
  isAcceptedMediaMime,
  MEDIA_UPLOAD,
} from "@/features/catalog/media/config/media-upload-config";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type UploadProgressItem = {
  id: string;
  name: string;
  progress: number;
  state: "uploading" | "done" | "error";
  message?: string;
};

export function MediaUploadDropzone({
  disabled,
  onFilesSelected,
  uploadItems,
}: {
  disabled?: boolean;
  onFilesSelected: (files: File[]) => void;
  uploadItems?: UploadProgressItem[];
}) {
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  const [clientError, setClientError] = useState<string | null>(null);

  function validateAndEmit(fileList: FileList | null) {
    if (!fileList?.length || disabled) {
      return;
    }

    const accepted: File[] = [];
    const errors: string[] = [];

    for (const file of Array.from(fileList).slice(
      0,
      MEDIA_UPLOAD.maxFilesPerRequest,
    )) {
      if (file.size > MEDIA_UPLOAD.maxFileSizeBytes) {
        errors.push(`${file.name} exceeds ${MEDIA_UPLOAD.maxFileSizeLabel}.`);
        continue;
      }
      if (!isAcceptedMediaMime(file.type)) {
        errors.push(`${file.name} is not an accepted image type.`);
        continue;
      }
      accepted.push(file);
    }

    if (errors.length) {
      setClientError(errors.join(" "));
    } else {
      setClientError(null);
    }

    if (accepted.length) {
      onFilesSelected(accepted);
    }

    if (inputRef.current) {
      inputRef.current.value = "";
    }
  }

  return (
    <div className="space-y-3">
      <div
        className={cn(
          "rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-8 text-center transition-colors",
          dragOver && !disabled && "border-primary/50 bg-primary/5",
          disabled && "opacity-60",
        )}
        onDragOver={(event) => {
          event.preventDefault();
          if (!disabled) setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(event) => {
          event.preventDefault();
          setDragOver(false);
          validateAndEmit(event.dataTransfer.files);
        }}
      >
        <p className="text-sm font-medium">Drop images here</p>
        <p className="mt-1 text-xs text-muted-foreground">
          Accepted: {MEDIA_UPLOAD.acceptedExtensionsLabel} · up to{" "}
          {MEDIA_UPLOAD.maxFileSizeLabel} each · max{" "}
          {MEDIA_UPLOAD.maxFilesPerRequest} files per upload
        </p>
        <div className="mt-4">
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={disabled}
            onClick={() => inputRef.current?.click()}
          >
            Choose files
          </Button>
        </div>
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          className="sr-only"
          accept={MEDIA_UPLOAD.acceptedMimeTypes.join(",")}
          multiple
          disabled={disabled}
          onChange={(event) => validateAndEmit(event.target.files)}
        />
      </div>

      {clientError ? (
        <p className="text-xs text-destructive" role="alert">
          {clientError}
        </p>
      ) : null}

      {uploadItems?.length ? (
        <ul className="space-y-2" aria-label="Upload progress">
          {uploadItems.map((item) => (
            <li
              key={item.id}
              className="rounded-lg border border-border/60 bg-background px-3 py-2 text-xs"
            >
              <div className="flex items-center justify-between gap-2">
                <span className="truncate font-medium">{item.name}</span>
                <span className="text-muted-foreground">{item.progress}%</span>
              </div>
              <div
                className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={item.progress}
                aria-valuemin={0}
                aria-valuemax={100}
              >
                <div
                  className={cn(
                    "h-full transition-all",
                    item.state === "error" ? "bg-destructive" : "bg-primary",
                  )}
                  style={{ width: `${item.progress}%` }}
                />
              </div>
              {item.message ? (
                <p className="mt-1 text-destructive">{item.message}</p>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
