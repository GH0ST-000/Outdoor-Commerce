import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/utils";

export function ResponsiveTableWrapper({
  children,
  caption,
}: {
  children: ReactNode;
  caption?: string;
}) {
  return (
    <div className="overflow-x-auto">
      {caption ? <p className="sr-only">{caption}</p> : null}
      {children}
    </div>
  );
}

export function DataTable({
  headers,
  rows,
  caption,
}: {
  caption: string;
  headers: string[];
  rows: Array<Array<ReactNode>>;
}) {
  return (
    <ResponsiveTableWrapper>
      <table className="w-full min-w-[32rem] border-collapse text-sm">
        <caption className="mb-2 text-start text-sm text-muted-foreground">
          {caption}
        </caption>
        <thead>
          <tr>
            {headers.map((header) => (
              <th
                key={header}
                scope="col"
                className="border-b border-border px-3 py-2 text-start font-semibold"
              >
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index} className="border-b border-border/70">
              {row.map((cell, cellIndex) => (
                <td
                  key={cellIndex}
                  className={cn(
                    "px-3 py-2 align-top",
                    cellIndex > 0 && "tabular-nums",
                  )}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </ResponsiveTableWrapper>
  );
}

export function SpecificationTable(props: ComponentProps<typeof DataTable>) {
  return <DataTable {...props} />;
}

export function ComparisonTable(props: ComponentProps<typeof DataTable>) {
  return <DataTable {...props} />;
}
