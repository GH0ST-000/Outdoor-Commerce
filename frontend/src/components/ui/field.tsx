import * as React from "react";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type FieldProps = {
  label: string;
  htmlFor?: string;
  hint?: string;
  description?: string;
  error?: string | null;
  required?: boolean;
  className?: string;
  children: React.ReactNode;
};

function Field({
  label,
  htmlFor,
  hint,
  description,
  error,
  required,
  className,
  children,
}: FieldProps) {
  const descriptionText = description ?? hint;
  const descriptionId =
    htmlFor && descriptionText ? `${htmlFor}-description` : undefined;
  const errorId = htmlFor && error ? `${htmlFor}-error` : undefined;
  const describedBy =
    [descriptionId, errorId].filter(Boolean).join(" ") || undefined;

  const control = React.isValidElement(children)
    ? React.cloneElement(
        children as React.ReactElement<{
          id?: string;
          "aria-invalid"?: boolean;
          "aria-describedby"?: string;
          "aria-required"?: boolean;
        }>,
        {
          id: htmlFor,
          "aria-invalid": error ? true : undefined,
          "aria-describedby": describedBy,
          "aria-required": required || undefined,
        },
      )
    : children;

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <Label
        htmlFor={htmlFor}
        className="text-sm font-medium normal-case tracking-normal text-foreground"
      >
        {label}
        {required ? (
          <span aria-hidden className="ms-1 text-destructive">
            *
          </span>
        ) : null}
      </Label>
      {control}
      {descriptionText ? (
        <p id={descriptionId} className="text-xs text-muted-foreground">
          {descriptionText}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} className="text-xs text-destructive" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

export { Field };
export { Field as FormField };
