/**
 * Typed design-system constants.
 * CSS variables in `src/styles/tokens` remain the runtime source of truth.
 * Keep these values aligned with primitives.css.
 */

export const breakpoints = {
  mobile: 375,
  tablet: 768,
  desktop: 1024,
  wide: 1440,
} as const;

export const zIndex = {
  base: "var(--z-base)",
  sticky: "var(--z-sticky)",
  header: "var(--z-header)",
  dropdown: "var(--z-dropdown)",
  drawerBackdrop: "var(--z-drawer-backdrop)",
  drawer: "var(--z-drawer)",
  modalBackdrop: "var(--z-modal-backdrop)",
  modal: "var(--z-modal)",
  toast: "var(--z-toast)",
  critical: "var(--z-critical)",
} as const;

export const iconSizes = {
  sm: 16,
  md: 20,
  lg: 24,
  xl: 32,
} as const;

export const controlHeights = {
  sm: "var(--control-height-sm)",
  md: "var(--control-height-md)",
  lg: "var(--control-height-lg)",
} as const;

export type SurfaceName =
  "dark" | "ink" | "pine" | "light" | "paper" | "commerce";
