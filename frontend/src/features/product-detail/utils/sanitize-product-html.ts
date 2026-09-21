/**
 * Defense-in-depth allowlist. The Public Catalog API already stores
 * sanitized HTML from HtmlContentSanitizer. This pass is isomorphic so
 * server HTML and client hydration match.
 */
export function sanitizeProductHtml(html: string | null | undefined): string {
  if (!html || html.trim() === "") {
    return "";
  }

  let output = html.replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, "");
  output = output.replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, "");
  output = output.replace(
    /href\s*=\s*(["'])\s*(javascript|data|vbscript):[^"']*\1/gi,
    "",
  );
  output = output.replace(
    /<a\b([^>]*)>/gi,
    '<a$1 rel="noopener noreferrer" target="_blank">',
  );
  return output.trim();
}

export function isExternalHttpUrl(url: string | null | undefined): boolean {
  if (!url) {
    return false;
  }
  try {
    const parsed = new URL(url);
    return parsed.protocol === "https:" || parsed.protocol === "http:";
  } catch {
    return false;
  }
}
