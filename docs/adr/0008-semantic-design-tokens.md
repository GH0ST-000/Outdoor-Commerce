# ADR 0008: Semantic design tokens and storefront component boundaries

## Status

Accepted — Day 13

## Context

Days 8–12 established a premium Caucasus Field Intelligence look and a public catalog API. Tokens, buttons, cards, and overlays were still spread across `globals.css` and feature files, with Latin display fonts applied to Georgian headings, uppercase technical labels, and ad-hoc z-index values. A future homepage (Day 14) and product detail (Day 15) would otherwise copy those inconsistencies.

## Decision

1. **Three token layers.** Primitive palette and scale live in `frontend/src/styles/tokens/primitives.css`. Semantic aliases (`text-primary`, `surface-*`, `status-*`) live in `semantic.css`. Component tokens are used only where a control needs a dedicated surface (`button-primary-background`, `input-border`). Feature components reference semantic names, not hex values.

2. **Surface contexts, not a second theme switch.** `[data-surface="dark"|"paper"|"pine"|"commerce"]` remaps semantic colors so the same button or price can sit on cinematic ink or editorial paper. Admin already has `next-themes`; the storefront does not add another customer-facing toggle.

3. **Georgian typography is a layout constraint.** Fraunces is Latin-only and never applied under `:lang(ka)`. Noto Serif Georgian covers display; Noto Sans Georgian covers body. Technical labels drop `uppercase` and wide tracking in Georgian. Line-height for Georgian body is 1.7.

4. **Primitives do not fetch.** UI, layout, commerce presentation, editorial, and outdoor-context components receive already-resolved DTOs. Public Catalog API calls stay in `features/catalog`. Pricing and availability numbers are never recalculated in the browser.

5. **Accessibility is part of the API.** Buttons expose loading/disabled correctly; fields wire `aria-describedby`; overlays use Radix focus trap; status is never color-only; `prefers-reduced-motion` disables decorative motion.

6. **Documentation stays development-only.** Storybook would add a second styling pipeline on Next 16 + Tailwind 4. `/dev/design-system` is `notFound()` in production.

## Consequences

- Day 14/15 pages compose these primitives instead of inventing new cards.
- Token changes propagate through surfaces without rewriting feature JSX.
- Font files are subsetted by script and weight.

## Alternatives considered

- **Storybook:** rejected for this day — Next 16 / Tailwind 4 / React 19 setup cost outweighs the gallery need.
- **Global light/dark customer switch:** rejected — surfaces already encode editorial contrast; admin theme remains separate.
- **Polymorphic `as` on every primitive:** rejected — link/button polymorphism stays on Button via `asChild`.
