import Link from "next/link";

export function Breadcrumbs({
  items,
  label,
}: {
  label: string;
  items: Array<{ href?: string; label: string }>;
}) {
  return (
    <nav aria-label={label} className="text-sm text-muted-foreground">
      <ol className="flex flex-wrap items-center gap-2">
        {items.map((item, index) => (
          <li
            key={`${item.label}-${index}`}
            className="flex items-center gap-2"
          >
            {index > 0 ? <span aria-hidden>/</span> : null}
            {item.href && index < items.length - 1 ? (
              <Link
                href={item.href}
                className="no-underline hover:text-foreground"
              >
                {item.label}
              </Link>
            ) : (
              <span className="text-foreground">{item.label}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
