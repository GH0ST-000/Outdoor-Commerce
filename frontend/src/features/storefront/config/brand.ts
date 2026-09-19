/**
 * Temporary centralized brand configuration.
 * Replace when final brand assets and naming are confirmed.
 * Do not hard-code brand strings in feature components — import from here or i18n.
 */
export const brandConfig = {
  /** Internal key — never display this as the product name alone. */
  key: "hunt",
  /** Display name used until legal brand is finalized. */
  displayName: {
    en: "Hunt",
    ka: "ჰანტი",
  },
  tagline: {
    en: "Built for the wild. Prepared for Georgia.",
    ka: "შექმნილი ველურისთვის. მზად საქართველოსთვის.",
  },
  support: {
    en: "Field-tested equipment for hunting, fishing, and every route beyond the road.",
    ka: "საველე ტესტირებული აღჭურვილობა ნადირობისთვის, თევზაობისთვის და გზის მიღმა ყველა მარშრუტისთვის.",
  },
  legalDemoDisclaimer: {
    en: "Demonstration data only — not official Georgian hunting law.",
    ka: "სადემონსტრაციო მონაცემები — არ არის ოფიციალური სანადირო კანონი.",
  },
} as const;

export type BrandLocale = keyof typeof brandConfig.displayName;
