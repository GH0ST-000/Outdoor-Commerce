import type { LegalInformation } from "@/features/legal/types/legal-types";

export type SpeciesLocale = "ka" | "en";

export type { LegalInformation };

export type SpeciesMediaDerivative = {
  preset: string;
  format: string;
  url: string;
  width: number;
  height: number;
};

export type SpeciesMedia = {
  role: string;
  is_primary: boolean;
  alt_text: string | null;
  caption: string | null;
  photographer_or_creator: string | null;
  license: string | null;
  source_url: string | null;
  derivatives: SpeciesMediaDerivative[];
};

export type SpeciesCard = {
  id: string;
  slug: string;
  locale: string;
  common_name: string | null;
  scientific_name: string;
  summary: string | null;
  activity_type: string;
  domain_type: string;
  habitats: { code: string; name: string }[];
  verified: boolean;
  media: SpeciesMedia | null;
  legal_information: LegalInformation;
};

export type SpeciesDetail = {
  id: string;
  slug: string;
  locale: string;
  requested_locale: string;
  translation_fallback: boolean;
  common_name: string | null;
  short_name: string | null;
  scientific_name: string;
  scientific_name_authorship: string | null;
  summary: string | null;
  activity_type: string;
  domain_type: string;
  native_status: string | null;
  taxonomy: Record<
    string,
    { code: string; scientific_name: string } | string | null
  >;
  aliases: { name: string; type: string; locale: string | null }[];
  identification: {
    overview: string | null;
    appearance: string | null;
    traits: { category: string; label: string; description: string }[];
    similar_species: {
      id: string;
      slug: string;
      common_name: string | null;
      scientific_name: string;
      relationship_type: string;
      confidence: string;
      notes: string | null;
    }[];
  };
  habitats: { code: string; name: string; importance: string }[];
  behavior: Record<string, string | null>;
  characteristics: Record<string, unknown> | null;
  conservation: {
    assessment_system: string;
    status_code: string;
    assessment_scope: string;
    assessed_at: string | null;
    notes: string | null;
    source: SpeciesSource | null;
    not_legal_status: boolean;
  }[];
  media: SpeciesMedia[];
  sources: SpeciesSource[];
  legal_information: LegalInformation;
  seo: { title: string | null; description: string | null };
  meta: {
    verified: boolean;
    verification_status: string;
    content_version: number;
    published_at: string | null;
    updated_at: string | null;
  };
};

export type SpeciesSource = {
  id: string;
  title: string;
  publisher: string;
  source_type: string;
  url: string | null;
  language: string | null;
  published_at: string | null;
  retrieved_at: string | null;
  is_official: boolean;
  verification_status: string;
  page_reference?: string | null;
  section_reference?: string | null;
  quotation_excerpt?: string | null;
};

export type SpeciesFilters = {
  activity_types: string[];
  domain_types: string[];
  habitats: { code: string; name: string }[];
  taxonomy: { classes: string[]; orders: string[]; families: string[] };
  conservation: {
    status_code: string;
    assessment_scope: string;
    assessment_system: string;
  }[];
  sorts: string[];
};

export type SpeciesListResponse = {
  data: SpeciesCard[];
  meta: {
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
    locale: string;
    search_mode?: string;
    fallback_used?: boolean;
  };
};

export type SpeciesQuery = {
  q?: string;
  activity_type?: string;
  domain_type?: string;
  taxonomy?: string;
  habitat?: string;
  conservation_status?: string;
  sort: string;
  page: number;
  per_page?: number;
};
