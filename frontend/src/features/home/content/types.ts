export type HomeTrustItem = {
  id: "authentic" | "selection" | "support" | "legal";
  heading: string;
  body: string;
};

export type HomeGatewayCopy = {
  description: string;
};

export type HomePageContent = {
  seo: {
    title: string;
    description: string;
  };
  hero: {
    eyebrow: string;
    headline: string;
    support: string;
    primaryCta: string;
    secondaryCta: string;
    scroll: string;
    fieldNote: string;
  };
  categories: {
    eyebrow: string;
    title: string;
    lead: string;
  };
  gateway: Record<string, HomeGatewayCopy>;
  featured: {
    eyebrow: string;
    title: string;
    lead: string;
    catalogCta: string;
  };
  offers: {
    eyebrow: string;
    title: string;
    lead: string;
  };
  season: {
    eyebrow: string;
    title: string;
    question: string;
    lead: string;
    previewLabel: string;
    disclaimer: string;
    calendarCta: string;
    speciesCta: string;
  };
  map: {
    title: string;
    lead: string;
    cta: string;
    previewLabel: string;
    disclaimer: string;
  };
  brands: {
    title: string;
    lead: string;
  };
  journal: {
    eyebrow: string;
    title: string;
    lead: string;
    read: string;
  };
  trust: {
    title: string;
    items: HomeTrustItem[];
  };
  newsletter: {
    title: string;
    lead: string;
    cta: string;
    hint: string;
  };
  errors: {
    categories: string;
    retry: string;
  };
};
