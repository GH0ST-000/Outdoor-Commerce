import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/utils";

export function Eyebrow({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <p className={cn("sf-label text-muted-foreground", className)}>
      {children}
    </p>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  description,
  className,
}: {
  eyebrow?: string;
  title: string;
  description?: string;
  className?: string;
}) {
  return (
    <header className={cn("max-w-[var(--measure)]", className)}>
      {eyebrow ? <Eyebrow>{eyebrow}</Eyebrow> : null}
      <h2 className="type-h2 mt-2">{title}</h2>
      {description ? (
        <p className="type-body mt-3 text-muted-foreground">{description}</p>
      ) : null}
    </header>
  );
}

export function Quote({
  children,
  attribution,
}: {
  children: string;
  attribution?: string;
}) {
  return (
    <blockquote className="max-w-[var(--measure)] border-s-2 border-border ps-4">
      <p className="type-body-lg">{children}</p>
      {attribution ? (
        <footer className="mt-2 text-sm text-muted-foreground">
          {attribution}
        </footer>
      ) : null}
    </blockquote>
  );
}

export function Callout({
  title,
  children,
}: {
  title?: string;
  children: ReactNode;
}) {
  return (
    <aside className="rounded-[var(--radius-lg)] border border-border bg-muted/50 px-4 py-3">
      {title ? <p className="font-semibold">{title}</p> : null}
      <div className={cn("text-sm", title && "mt-1")}>{children}</div>
    </aside>
  );
}

export function ArticleCard({
  href,
  category,
  title,
  excerpt,
}: {
  href: string;
  category: string;
  title: string;
  excerpt: string;
}) {
  return (
    <article className="max-w-[var(--measure)]">
      <Eyebrow>{category}</Eyebrow>
      <h3 className="type-h3 mt-2">
        <a href={href} className="no-underline hover:underline">
          {title}
        </a>
      </h3>
      <p className="type-body mt-2 text-muted-foreground">{excerpt}</p>
    </article>
  );
}

export function GuideCard(props: ComponentProps<typeof ArticleCard>) {
  return <ArticleCard {...props} />;
}

export function MetadataRow({
  items,
}: {
  items: Array<{ label: string; value: string }>;
}) {
  return (
    <dl className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
      {items.map((item) => (
        <div key={item.label}>
          <dt className="text-muted-foreground">{item.label}</dt>
          <dd className="font-medium">{item.value}</dd>
        </div>
      ))}
    </dl>
  );
}

export function TableOfContents({
  items,
  label,
}: {
  label: string;
  items: Array<{ href: string; title: string }>;
}) {
  return (
    <nav aria-label={label}>
      <ol className="space-y-1 text-sm">
        {items.map((item) => (
          <li key={item.href}>
            <a href={item.href} className="hover:underline">
              {item.title}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}

export function SourceList({
  items,
  label,
}: {
  label: string;
  items: string[];
}) {
  return (
    <section>
      <h3 className="type-h5">{label}</h3>
      <ul className="mt-2 list-disc ps-5 text-sm text-muted-foreground">
        {items.map((item) => (
          <li key={item}>{item}</li>
        ))}
      </ul>
    </section>
  );
}

export function LegalDisclaimer({ children }: { children: ReactNode }) {
  return (
    <p className="rounded-[var(--radius-md)] border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
      {children}
    </p>
  );
}

export function EditorialHero({
  eyebrow,
  title,
  lead,
  children,
}: {
  eyebrow?: string;
  title: string;
  lead?: string;
  children?: ReactNode;
}) {
  return (
    <div data-surface="dark" className="sf-section">
      <div className="sf-container">
        {eyebrow ? (
          <Eyebrow className="text-inherit opacity-70">{eyebrow}</Eyebrow>
        ) : null}
        <h1 className="sf-display type-display-l mt-3">{title}</h1>
        {lead ? (
          <p className="type-body-lg mt-4 max-w-[var(--measure)] opacity-80">
            {lead}
          </p>
        ) : null}
        {children}
      </div>
    </div>
  );
}
