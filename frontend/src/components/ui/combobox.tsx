"use client";

import * as React from "react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";

type ComboboxOption = {
  value: string;
  label: string;
  disabled?: boolean;
};

export function Combobox({
  id,
  label,
  options,
  value,
  onValueChange,
  placeholder,
  emptyLabel = "No matches",
  loading = false,
  loadingLabel = "Loading",
  disabled,
}: {
  id: string;
  label: string;
  options: ComboboxOption[];
  value: string;
  onValueChange: (value: string) => void;
  placeholder?: string;
  emptyLabel?: string;
  loading?: boolean;
  loadingLabel?: string;
  disabled?: boolean;
}) {
  const listId = `${id}-listbox`;
  const query =
    options.find((option) => option.value === value)?.label ?? value;
  const [open, setOpen] = React.useState(false);
  const [filter, setFilter] = React.useState(query);
  const matches = options.filter((option) =>
    option.label.toLowerCase().includes(filter.toLowerCase()),
  );

  return (
    <div className="relative">
      <Input
        id={id}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={open}
        aria-controls={listId}
        aria-label={label}
        value={open ? filter : query}
        disabled={disabled}
        placeholder={placeholder}
        onFocus={() => setOpen(true)}
        onChange={(event) => {
          setFilter(event.target.value);
          setOpen(true);
        }}
        onKeyDown={(event) => {
          if (event.key === "Escape") {
            setOpen(false);
          }
        }}
        onBlur={() => {
          window.setTimeout(() => setOpen(false), 120);
        }}
      />
      {open ? (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-[var(--z-dropdown)] mt-1 max-h-56 w-full overflow-auto rounded-[var(--radius-lg)] border border-border bg-popover p-1 shadow-[var(--shadow-raised)]"
        >
          {loading ? (
            <li className="px-3 py-2 text-sm text-muted-foreground">
              {loadingLabel}
            </li>
          ) : matches.length === 0 ? (
            <li className="px-3 py-2 text-sm text-muted-foreground">
              {emptyLabel}
            </li>
          ) : (
            matches.map((option) => (
              <li key={option.value} role="none">
                <button
                  type="button"
                  role="option"
                  aria-selected={option.value === value}
                  disabled={option.disabled}
                  className={cn(
                    "flex w-full rounded-md px-3 py-2 text-start text-sm",
                    option.value === value ? "bg-muted" : "hover:bg-muted/70",
                  )}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => {
                    onValueChange(option.value);
                    setFilter(option.label);
                    setOpen(false);
                  }}
                >
                  {option.label}
                </button>
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}
