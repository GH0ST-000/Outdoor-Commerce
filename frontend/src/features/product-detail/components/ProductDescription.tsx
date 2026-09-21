import { sanitizeProductHtml } from "@/features/product-detail/utils/sanitize-product-html";

export function ProductDescription({
  html,
  heading,
}: {
  html: string | null;
  heading: string;
}) {
  const safe = sanitizeProductHtml(html);
  if (!safe) {
    return null;
  }

  return (
    <section
      className="sf-container-readable space-y-3"
      aria-labelledby="product-description-heading"
    >
      <h2 id="product-description-heading" className="font-display text-2xl">
        {heading}
      </h2>
      <div
        className="product-description max-w-prose space-y-3 text-base leading-relaxed text-foreground [&_a]:underline [&_h2]:mt-4 [&_h2]:text-xl [&_h3]:text-lg [&_li]:ms-5 [&_ol]:list-decimal [&_ul]:list-disc"
        dangerouslySetInnerHTML={{ __html: safe }}
      />
    </section>
  );
}
