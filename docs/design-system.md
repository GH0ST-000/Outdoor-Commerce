# Storefront design system

Day 13 productionizes the visual foundation. The storefront now uses **Alpine Slate & Olive** (technical, modern, dark-first). Admin keeps the warm atelier tokens and is out of this document's scope.

Live gallery (development only): `/dev/design-system`

ADR: [0008 — Semantic design tokens and storefront component boundaries](adr/0008-semantic-design-tokens.md)

## Design philosophy

Premium Georgian outdoor outfitter: rugged, high-end, and photographic. Alpine Slate surfaces, Technical Olive conversion actions, and Sand accents. Large outdoor imagery and tight borders — not a generic light template.

## Token architecture

| Layer | Location | Use |
| --- | --- | --- |
| Primitive | `frontend/src/styles/tokens/primitives.css` | Raw color, space, type, radius, shadow, z-index, motion, breakpoints |
| Semantic | `frontend/src/styles/tokens/semantic.css` | Purpose: text, surface, border, action, status, focus |
| Component | `frontend/src/styles/tokens/components.css` | Only when a control needs its own surface |

TypeScript mirrors for breakpoints and z-index: `frontend/src/lib/design-system/tokens.ts`. CSS remains the runtime source of truth.

Storefront theming is scoped with `[data-storefront]` on the customer layout. Do not restyle admin by changing `:root` / `.dark` for this look.

Do not add raw repeated hex, arbitrary `z-[99999]`, or one-off type sizes in feature code. Optical exceptions belong in a comment.

## Color and surfaces

Storefront Alpine Slate & Olive:

| Token | Hex | Role |
| --- | --- | --- |
| Alpine Slate | `#1A1A1A` | Page background |
| Raised slate | `#2C2C2C` | Cards and panels |
| Sand | `#C4A484` | Accent, prices, labels |
| Mist | `#E5E5E5` | Body text |
| Technical Olive | `#556B2F` | Primary actions |

Admin continues to use the warm atelier primitives (Night Forest, Copper, Paper). Do not reuse those names in new storefront work — prefer `--sand`, `--olive`, `--alpine-slate`, or semantic tokens.

```html
<div data-storefront>
  <section data-surface="dark">
```

Existing `.sf-band-ink`, `.sf-band-paper`, `.sf-band-pine` remain. Under `[data-storefront]` they all resolve to Alpine Slate. Prefer `data-surface` on new work.

Admin `next-themes` dark class still remaps admin semantic names. The storefront does not add a second customer-facing theme; `[data-storefront]` stays Alpine Slate even if the document theme class changes.

WCAG 2.2 AA for text and focus. Status always includes a label, not only color. Photography overlays use ink scrims so type stays readable.

## Typography

Storefront pairing (Latin):

- **Montserrat** — headings / display
- **Inter** — body / UI

Georgian:

- **Noto Sans Georgian** for body and display (Montserrat and Inter do not cover Mkhedruli). Georgian is never auto-uppercased and never letter-spaced like Latin small-caps.

Admin still loads Manrope + Fraunces for its own chrome.

Weights: 400–700 body, 600–800 display. Classes: `.type-display-xl`, `.type-display-l`, `.type-h1`–`.type-h5`, `.type-body-lg`, `.type-body`, `.type-body-sm`, `.sf-label` / `.type-label`, `.type-button`, `.type-price-lg`, `.type-price`, `.type-numeric`, `.type-measure`.

Prefer these classes over ad-hoc `text-[13px]`. Do not wrap every string in a React typography component.

## Spacing, grid, breakpoints

Spacing scale: 0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24, 32 (Tailwind units). Section rhythm: `--space-section`. Gutters: `--space-gutter`. Safe area: `--space-safe-bottom`.

Containers: readable (`--container-readable`, ~68ch editorial), standard (`.sf-container`), wide (`.sf-container-wide`), full bleed (`Bleed`).

Grid: 4 / 8 / 12 conceptually via `Grid` / `ResponsiveGrid`. Mobile-first.

Breakpoints (source: `tokens.ts` + CSS custom properties):

| Name | Width |
| --- | --- |
| Small mobile | 320–374px |
| Mobile | 375–767px |
| Tablet | 768–1023px |
| Desktop | 1024–1439px |
| Wide | 1440px+ |

Do not use JavaScript viewport checks for layout. Tailwind `sm`/`md`/`lg` remain the default Tailwind scale so admin screens do not shift.

## Radius, border, shadow, z-index, motion

Radius: sm, md, lg, xl, 2xl, full — technical, not pill-everything.

Shadows: subtle inset, raised, overlay. Prefer tonal borders over giant drop shadows.

Z-index: base 0, sticky 20, header 40, dropdown 50, drawer backdrop/drawer 60/61, modal backdrop/modal 70/71, toast 80, critical 90. Header never sits above a modal.

Motion tokens: instant/fast/normal/slow; ease standard/enter/exit/emphasized. Prefer transform and opacity. `prefers-reduced-motion` disables decorative reveal and hero drift. Focus and dialog behavior must not wait on animation.

## Icons

Lucide only, imported per icon. Sizes 16 / 20 / 24 / 32 via `Icon`. Decorative icons are `aria-hidden`. Icon-only controls use `IconButton` with a required `label`.

## Component organization

```text
frontend/src/components/ui/          # Button, Field, Dialog, Drawer, …
frontend/src/components/layout/      # Container, Section, Grid, Surface
frontend/src/components/navigation/  # Breadcrumbs
frontend/src/components/commerce/    # Availability, filters, variants, category
frontend/src/components/editorial/
frontend/src/components/outdoor-context/
frontend/src/components/feedback/
frontend/src/features/storefront/    # Feature composition + Public Catalog data
frontend/src/features/catalog/       # API client and adapters
```

UI primitives never fetch. Storefront feature components may receive `ProductCardData` and other presentation DTOs. No circular imports from primitives into catalog API modules.

## Server / client boundaries

Server Components by default. Client only for menus, drawers, dialogs, interactive filters, variant selection, galleries, and form controls. Pages stay server-wrapped; do not mark a route client because one child is interactive.

## Accessibility

Target WCAG 2.2 AA. Visible `:focus-visible`. Landmarks and headings on storefront chrome. Dialog/drawer: focus trap, restore, Escape, scroll lock (Radix). Tooltips never hold essential information. Color swatches expose the value name. Touch targets on filters and variant options are at least ~44px. Zoom to 200% must not clip primary actions.

Automated: `jest-axe` in Vitest. Manual keyboard checks remain required.

## Localization

Georgian is primary; English is supported. Components take labels as props or use `useStorefrontCopy` only in feature wrappers. Dates, numbers, and GEL use `Intl` via pricing helpers. Logical CSS properties (`ps`/`pe`/`ms`/`me`) where practical.

## Media, price, availability

- Cards use Day 9 responsive manifests through `ResponsiveProductImage`. Do not pick zoom derivatives in cards. Missing media uses the local placeholder.
- `PriceDisplay` consumes integer minor units. No float math, no frontend promotion calculation.
- `AvailabilityStatus` consumes public statuses only. Never show warehouse quantities or fake scarcity.

## Commands

```bash
cd frontend
npm run lint
npm run typecheck
npm run test:run
npm run build
npm run dev   # then open /dev/design-system
```

There is no Storybook and no Playwright visual suite in this repository. Do not add screenshot tests against remote images.

## Contribution rules

- New storefront UI uses tokens and existing primitives.
- Do not copy ProductCard markup into a one-off card.
- Do not introduce another icon or animation library.
- `/dev/design-system` must stay production-blocked.
- Document any justified one-off optical value next to the declaration.

## Deprecated

Inline catalog filter checkboxes, ad-hoc filter drawers, and hardcoded `#eee9de` header colors were replaced by design-system primitives. `Button` `variant="destructive"` remains as an alias of `danger` for admin screens.

## Migration notes

Existing homepage composition is intentionally unchanged for Day 14. Catalog listing, product cards, variant selector, header z-index, and filter drawer now consume the formal system.

## Browser support

Maintained current Chrome, Safari, Firefox, Edge, Mobile Safari, and Android Chrome. Use `dvh`/`svh` and `env(safe-area-inset-*)` with `vh` leftovers only as fallback. Sticky header, dialog scroll lock, and form appearance must remain usable without experimental CSS.

## Copy patterns

Keep messages short, localized, and free of backend jargon: product unavailable, price on request, out of stock, no results, filter empty, media failed, catalog error, rate limited, offline, not found, unsupported combination.
