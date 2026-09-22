export type HighlightSegment = {
  text: string;
  match: boolean;
};

export function highlightSegments(
  text: string,
  query: string,
): HighlightSegment[] {
  const source = text ?? "";
  const needle = query.trim();
  if (source === "" || needle === "") {
    return [{ text: source, match: false }];
  }

  const lowerSource = source.toLocaleLowerCase();
  const lowerNeedle = needle.toLocaleLowerCase();
  const segments: HighlightSegment[] = [];
  let cursor = 0;

  while (cursor < source.length) {
    const index = lowerSource.indexOf(lowerNeedle, cursor);
    if (index === -1) {
      segments.push({ text: source.slice(cursor), match: false });
      break;
    }
    if (index > cursor) {
      segments.push({ text: source.slice(cursor, index), match: false });
    }
    segments.push({
      text: source.slice(index, index + needle.length),
      match: true,
    });
    cursor = index + needle.length;
  }

  return segments.filter((segment) => segment.text !== "");
}

export function HighlightedText({
  text,
  query,
  className,
}: {
  text: string;
  query: string;
  className?: string;
}) {
  return (
    <span className={className}>
      {highlightSegments(text, query).map((segment, index) =>
        segment.match ? (
          <mark
            key={`${segment.text}-${index}`}
            className="rounded-sm bg-transparent font-semibold text-foreground underline decoration-foreground/40 underline-offset-2"
          >
            {segment.text}
          </mark>
        ) : (
          <span key={`${segment.text}-${index}`}>{segment.text}</span>
        ),
      )}
    </span>
  );
}
