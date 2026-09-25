"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
  createAdminSimilarSpecies,
  createAdminSpecies,
  createAdminSpeciesAlias,
  createAdminSpeciesSource,
  fetchAdminSpeciesDetail,
  fetchAdminSpeciesRevisions,
  restoreAdminSpeciesRevision,
  transitionAdminSpecies,
  updateAdminSpecies,
  uploadAdminSpeciesMedia,
} from "@/features/species/api/admin-species-api";
import { ApiClientError } from "@/lib/api-client";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";

type Props = { speciesId?: string };

type TranslationDraft = {
  locale: "ka" | "en";
  common_name: string;
  short_name: string;
  summary: string;
  identification: string;
  appearance: string;
  behavior: string;
  diet: string;
  habitat_description: string;
  breeding_notes: string;
  seasonal_behavior: string;
  field_notes: string;
  safety_notes: string;
  seo_title: string;
  seo_description: string;
  content_status: string;
};

const emptyTranslation = (locale: "ka" | "en"): TranslationDraft => ({
  locale,
  common_name: "",
  short_name: "",
  summary: "",
  identification: "",
  appearance: "",
  behavior: "",
  diet: "",
  habitat_description: "",
  breeding_notes: "",
  seasonal_behavior: "",
  field_notes: "",
  safety_notes: "",
  seo_title: "",
  seo_description: "",
  content_status: "draft",
});

const TABS = [
  "overview",
  "translations",
  "taxonomy",
  "characteristics",
  "habitats",
  "identification",
  "similar",
  "conservation",
  "media",
  "sources",
  "revisions",
  "publishing",
] as const;

const HABITATS = [
  "forest",
  "alpine",
  "wetland",
  "grassland",
  "agricultural_land",
  "river",
  "stream",
  "lake",
  "reservoir",
  "coastal",
  "marine",
  "rocky",
  "mixed",
];

export function AdminSpeciesWorkspace({ speciesId }: Props) {
  const router = useRouter();
  const { permissions } = useAdminContext();
  const [scientificName, setScientificName] = useState("");
  const [authorship, setAuthorship] = useState("");
  const [kingdom, setKingdom] = useState("Animalia");
  const [phylum, setPhylum] = useState("");
  const [className, setClassName] = useState("");
  const [orderName, setOrderName] = useState("");
  const [family, setFamily] = useState("");
  const [genus, setGenus] = useState("");
  const [domainType, setDomainType] = useState("terrestrial");
  const [activityType, setActivityType] = useState("wildlife");
  const [verification, setVerification] = useState("unverified");
  const [noMedia, setNoMedia] = useState(false);
  const [ka, setKa] = useState(emptyTranslation("ka"));
  const [en, setEn] = useState(emptyTranslation("en"));
  const [lengthMin, setLengthMin] = useState("");
  const [lengthMax, setLengthMax] = useState("");
  const [lengthUnit, setLengthUnit] = useState("cm");
  const [habitats, setHabitats] = useState<string[]>([]);
  const [traitLabel, setTraitLabel] = useState("");
  const [traitDescription, setTraitDescription] = useState("");
  const [traitCategory, setTraitCategory] = useState("markings");
  const [traits, setTraits] = useState<
    Array<{
      locale: string;
      category: string;
      label: string;
      description: string;
    }>
  >([]);
  const [aliasName, setAliasName] = useState("");
  const [similarId, setSimilarId] = useState("");
  const [sourceTitle, setSourceTitle] = useState("");
  const [sourcePublisher, setSourcePublisher] = useState("");
  const [sourceUrl, setSourceUrl] = useState("");
  const [sourceRetrieved, setSourceRetrieved] = useState("");
  const [conservationSystem, setConservationSystem] = useState("IUCN");
  const [conservationCode, setConservationCode] = useState("");
  const [conservationScope, setConservationScope] = useState("global");
  const [conservation, setConservation] = useState<
    Array<{
      assessment_system: string;
      status_code: string;
      assessment_scope: string;
      source_public_id?: string;
    }>
  >([]);
  const [citations, setCitations] = useState<
    Array<{ source_public_id?: string }>
  >([]);
  const [revisions, setRevisions] = useState<
    Array<{
      revision_number: number;
      change_summary: string;
      created_at: string | null;
    }>
  >([]);
  const [version, setVersion] = useState(1);
  const [id, setId] = useState(speciesId ?? "");
  const [slug, setSlug] = useState("");
  const [status, setStatus] = useState("draft");
  const [reviewedAt, setReviewedAt] = useState<string | null>(null);
  const [issues, setIssues] = useState<{ code: string; message: string }[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [dirty, setDirty] = useState(false);
  const [reason, setReason] = useState("");
  const [license, setLicense] = useState("");
  const [altText, setAltText] = useState("");

  useEffect(() => {
    const onLeave = (event: BeforeUnloadEvent) => {
      if (dirty) event.preventDefault();
    };
    window.addEventListener("beforeunload", onLeave);
    return () => window.removeEventListener("beforeunload", onLeave);
  }, [dirty]);

  const applyDetail = useCallback(
    (detail: Record<string, unknown>) => {
      setScientificName(String(detail.scientific_name ?? ""));
      setAuthorship(String(detail.scientific_name_authorship ?? ""));
      setKingdom(String(detail.kingdom ?? "Animalia"));
      setPhylum(String(detail.phylum ?? ""));
      setClassName(String(detail.class_name ?? ""));
      setOrderName(String(detail.order_name ?? ""));
      setFamily(String(detail.family ?? ""));
      setGenus(String(detail.genus ?? ""));
      setDomainType(String(detail.domain_type ?? "terrestrial"));
      setActivityType(String(detail.activity_type ?? "wildlife"));
      setVerification(String(detail.verification_status ?? "unverified"));
      setNoMedia(Boolean(detail.no_media_required));
      setVersion(Number(detail.content_version ?? 1));
      setStatus(String(detail.publication_status ?? "draft"));
      setReviewedAt(
        typeof detail.reviewed_at === "string" ? detail.reviewed_at : null,
      );
      setId(String(detail.id ?? speciesId));
      setSlug(String(detail.canonical_slug ?? ""));
      const translations =
        (detail.translations as TranslationDraft[] | undefined) ?? [];
      const georgian = translations.find((row) => row.locale === "ka");
      const english = translations.find((row) => row.locale === "en");
      if (georgian) setKa({ ...emptyTranslation("ka"), ...georgian });
      if (english) setEn({ ...emptyTranslation("en"), ...english });
      const publishing = detail.publishing as {
        issues?: { code: string; message: string }[];
      };
      setIssues(publishing?.issues ?? []);
      const habitatRows =
        (detail.habitats as Array<{ code?: string }> | undefined) ?? [];
      setHabitats(
        habitatRows.map((row) => String(row.code ?? "")).filter(Boolean),
      );
      setTraits(
        ((detail.identification_traits as typeof traits | undefined) ?? []).map(
          (row) => ({
            locale: row.locale,
            category: row.category,
            label: row.label,
            description: row.description,
          }),
        ),
      );
      setConservation(
        (detail.conservation as typeof conservation | undefined) ?? [],
      );
      setCitations((detail.citations as typeof citations | undefined) ?? []);
      const chars = detail.characteristics as Record<
        string,
        string | null
      > | null;
      setLengthMin(String(chars?.average_length_min ?? ""));
      setLengthMax(String(chars?.average_length_max ?? ""));
      setLengthUnit(String(chars?.length_unit ?? "cm"));
    },
    [speciesId],
  );

  useEffect(() => {
    if (!speciesId) return;
    fetchAdminSpeciesDetail(speciesId)
      .then(applyDetail)
      .catch((caught: unknown) => {
        setError(caught instanceof ApiClientError ? caught.code : "ERROR");
      });
    if (hasPermission(permissions, PERMISSIONS.SPECIES_VIEW_REVISIONS)) {
      fetchAdminSpeciesRevisions(speciesId)
        .then((payload) => setRevisions(payload.data))
        .catch(() => undefined);
    }
  }, [speciesId, permissions, applyDetail]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setError(null);
    const translations = [ka, en].filter(
      (row) => row.common_name.trim() !== "",
    );
    const characteristics =
      lengthMin || lengthMax
        ? {
            average_length_min: lengthMin || null,
            average_length_max: lengthMax || null,
            length_unit: lengthUnit,
          }
        : undefined;
    try {
      if (!id) {
        const created = await createAdminSpecies({
          scientific_name: scientificName,
          scientific_name_authorship: authorship || null,
          taxonomic_rank: "species",
          kingdom,
          phylum: phylum || null,
          class_name: className || null,
          order_name: orderName || null,
          family: family || null,
          genus: genus || null,
          domain_type: domainType,
          activity_type: activityType,
          no_media_required: noMedia,
          translations,
        });
        setDirty(false);
        router.push(`/admin/species/${created.id as string}`);
        return;
      }
      const updated = await updateAdminSpecies(id, {
        content_version: version,
        scientific_name: scientificName,
        scientific_name_authorship: authorship || null,
        kingdom,
        phylum: phylum || null,
        class_name: className || null,
        order_name: orderName || null,
        family: family || null,
        genus: genus || null,
        taxonomic_rank: "species",
        domain_type: domainType,
        activity_type: activityType,
        verification_status: verification,
        no_media_required: noMedia,
        translations,
        characteristics,
        habitats: habitats.map((code) => ({ code, importance: "primary" })),
        identification_traits: traits,
        conservation,
        change_summary: "Editorial update",
      });
      applyDetail(updated);
      setDirty(false);
    } catch (caught) {
      setError(caught instanceof ApiClientError ? caught.code : "ERROR");
    }
  }

  async function run(
    action: "submit-review" | "publish" | "unpublish" | "archive",
  ) {
    if (!id) return;
    try {
      const updated = await transitionAdminSpecies(id, action, reason);
      applyDetail(updated);
    } catch (caught) {
      setError(caught instanceof ApiClientError ? caught.code : "ERROR");
    }
  }

  const canPublish = hasPermission(permissions, PERMISSIONS.SPECIES_PUBLISH);
  const canAlias = hasPermission(
    permissions,
    PERMISSIONS.SPECIES_MANAGE_ALIASES,
  );
  const canSources = hasPermission(
    permissions,
    PERMISSIONS.SPECIES_MANAGE_SOURCES,
  );
  const canMedia = hasPermission(permissions, PERMISSIONS.SPECIES_MANAGE_MEDIA);
  const canRestore = hasPermission(
    permissions,
    PERMISSIONS.SPECIES_RESTORE_REVISION,
  );

  return (
    <form className="space-y-6" onSubmit={save} onChange={() => setDirty(true)}>
      <AdminPageHeader
        title={id ? "Species workspace" : "New species"}
        description="Biological facts only. Do not record seasons, limits, or hunting permission."
      />
      {error ? <p role="alert">{error}</p> : null}
      <p>
        Status: {status}
        {reviewedAt ? ` · Reviewed ${reviewedAt}` : ""}
        {verification ? ` · ${verification}` : ""}
      </p>
      {issues.length > 0 ? (
        <ul>
          {issues.map((issue) => (
            <li key={issue.code}>
              {issue.code}: {issue.message}
            </li>
          ))}
        </ul>
      ) : null}

      <Tabs defaultValue="overview">
        <TabsList className="flex flex-wrap">
          {TABS.map((tab) => (
            <TabsTrigger key={tab} value={tab}>
              {tab}
            </TabsTrigger>
          ))}
        </TabsList>

        <TabsContent value="overview" className="space-y-3">
          <Label htmlFor="scientific_name">Scientific name</Label>
          <Input
            id="scientific_name"
            value={scientificName}
            onChange={(e) => setScientificName(e.target.value)}
            required
          />
          <Label htmlFor="activity_type">Activity type (navigation only)</Label>
          <select
            id="activity_type"
            className="h-11 w-full rounded-md border border-border bg-background px-3"
            value={activityType}
            onChange={(e) => setActivityType(e.target.value)}
          >
            {[
              "hunting",
              "fishing",
              "wildlife",
              "hunting_and_wildlife",
              "fishing_and_wildlife",
            ].map((value) => (
              <option key={value} value={value}>
                {value}
              </option>
            ))}
          </select>
          <Label htmlFor="domain_type">Domain type</Label>
          <select
            id="domain_type"
            className="h-11 w-full rounded-md border border-border bg-background px-3"
            value={domainType}
            onChange={(e) => setDomainType(e.target.value)}
          >
            {["terrestrial", "freshwater", "marine", "migratory", "mixed"].map(
              (value) => (
                <option key={value} value={value}>
                  {value}
                </option>
              ),
            )}
          </select>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={noMedia}
              onChange={(e) => setNoMedia(e.target.checked)}
            />
            Deliberately no identification media
          </label>
        </TabsContent>

        <TabsContent value="translations" className="grid gap-6 md:grid-cols-2">
          {([ka, en] as const).map((row, index) => (
            <fieldset key={row.locale} className="space-y-2">
              <legend>{row.locale === "ka" ? "Georgian" : "English"}</legend>
              <Input
                aria-label={`${row.locale} common name`}
                value={row.common_name}
                onChange={(e) =>
                  index === 0
                    ? setKa({ ...ka, common_name: e.target.value })
                    : setEn({ ...en, common_name: e.target.value })
                }
              />
              <Textarea
                aria-label={`${row.locale} summary`}
                value={row.summary}
                onChange={(e) =>
                  index === 0
                    ? setKa({ ...ka, summary: e.target.value })
                    : setEn({ ...en, summary: e.target.value })
                }
              />
              <Textarea
                aria-label={`${row.locale} identification`}
                value={row.identification}
                onChange={(e) =>
                  index === 0
                    ? setKa({ ...ka, identification: e.target.value })
                    : setEn({ ...en, identification: e.target.value })
                }
              />
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={row.content_status === "published"}
                  onChange={(e) => {
                    const next = e.target.checked ? "published" : "draft";
                    if (index === 0) setKa({ ...ka, content_status: next });
                    else setEn({ ...en, content_status: next });
                  }}
                />
                Publish {row.locale} translation
              </label>
            </fieldset>
          ))}
        </TabsContent>

        <TabsContent value="taxonomy" className="grid gap-3 sm:grid-cols-2">
          <div>
            <Label htmlFor="kingdom">Kingdom</Label>
            <Input
              id="kingdom"
              value={kingdom}
              onChange={(e) => setKingdom(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="phylum">Phylum</Label>
            <Input
              id="phylum"
              value={phylum}
              onChange={(e) => setPhylum(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="class_name">Class</Label>
            <Input
              id="class_name"
              value={className}
              onChange={(e) => setClassName(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="order_name">Order</Label>
            <Input
              id="order_name"
              value={orderName}
              onChange={(e) => setOrderName(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="family">Family</Label>
            <Input
              id="family"
              value={family}
              onChange={(e) => setFamily(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="genus">Genus</Label>
            <Input
              id="genus"
              value={genus}
              onChange={(e) => setGenus(e.target.value)}
            />
          </div>
          <div className="sm:col-span-2">
            <Label htmlFor="authorship">Authorship</Label>
            <Input
              id="authorship"
              value={authorship}
              onChange={(e) => setAuthorship(e.target.value)}
            />
          </div>
        </TabsContent>

        <TabsContent
          value="characteristics"
          className="grid gap-3 sm:grid-cols-3"
        >
          <div>
            <Label htmlFor="length_min">Length min</Label>
            <Input
              id="length_min"
              value={lengthMin}
              onChange={(e) => setLengthMin(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="length_max">Length max</Label>
            <Input
              id="length_max"
              value={lengthMax}
              onChange={(e) => setLengthMax(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="length_unit">Unit</Label>
            <select
              id="length_unit"
              className="h-11 w-full rounded-md border border-border bg-background px-3"
              value={lengthUnit}
              onChange={(e) => setLengthUnit(e.target.value)}
            >
              <option value="mm">mm</option>
              <option value="cm">cm</option>
              <option value="m">m</option>
            </select>
          </div>
        </TabsContent>

        <TabsContent value="habitats" className="space-y-3">
          <fieldset>
            <legend className="mb-2 text-sm font-medium">Habitats</legend>
            <div className="grid gap-2 sm:grid-cols-3">
              {HABITATS.map((code) => (
                <label
                  key={code}
                  className="flex min-h-11 items-center gap-2 text-sm"
                >
                  <input
                    type="checkbox"
                    checked={habitats.includes(code)}
                    onChange={(e) => {
                      setHabitats((current) =>
                        e.target.checked
                          ? [...current, code]
                          : current.filter((item) => item !== code),
                      );
                    }}
                  />
                  {code}
                </label>
              ))}
            </div>
          </fieldset>
        </TabsContent>

        <TabsContent value="identification" className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-3">
            <select
              aria-label="Trait category"
              className="h-11 rounded-md border border-border bg-background px-3"
              value={traitCategory}
              onChange={(e) => setTraitCategory(e.target.value)}
            >
              {[
                "size",
                "color",
                "markings",
                "shape",
                "sound",
                "tracks",
                "flight",
                "fins",
                "scales",
                "seasonal_plumage",
                "sex_difference",
                "juvenile_difference",
              ].map((value) => (
                <option key={value} value={value}>
                  {value}
                </option>
              ))}
            </select>
            <Input
              aria-label="Trait label"
              value={traitLabel}
              onChange={(e) => setTraitLabel(e.target.value)}
            />
            <Button
              type="button"
              onClick={() => {
                if (!traitLabel.trim()) return;
                setTraits([
                  ...traits,
                  {
                    locale: "ka",
                    category: traitCategory,
                    label: traitLabel,
                    description: traitDescription,
                  },
                ]);
                setTraitLabel("");
                setTraitDescription("");
              }}
            >
              Add trait
            </Button>
          </div>
          <Textarea
            aria-label="Trait description"
            value={traitDescription}
            onChange={(e) => setTraitDescription(e.target.value)}
          />
          <ul className="space-y-2 text-sm">
            {traits.map((trait, index) => (
              <li key={`${trait.label}-${index}`}>
                {trait.category}: {trait.label}
              </li>
            ))}
          </ul>
        </TabsContent>

        <TabsContent value="similar" className="space-y-3">
          <Label htmlFor="similar_id">Related species public ID</Label>
          <Input
            id="similar_id"
            value={similarId}
            onChange={(e) => setSimilarId(e.target.value)}
          />
          <Button
            type="button"
            disabled={!id}
            onClick={async () => {
              if (!id || !similarId) return;
              try {
                const updated = await createAdminSimilarSpecies(id, {
                  similar_species_id: similarId,
                  relationship_type: "visually_similar",
                });
                applyDetail(updated);
                setSimilarId("");
              } catch (caught) {
                setError(
                  caught instanceof ApiClientError ? caught.code : "ERROR",
                );
              }
            }}
          >
            Link similar species
          </Button>
        </TabsContent>

        <TabsContent value="conservation" className="space-y-3">
          <p className="text-sm text-muted-foreground">
            Conservation status is not hunting permission. Keep global and
            national assessments separate.
          </p>
          <div className="grid gap-3 sm:grid-cols-3">
            <Input
              aria-label="Assessment system"
              value={conservationSystem}
              onChange={(e) => setConservationSystem(e.target.value)}
            />
            <Input
              aria-label="Status code"
              value={conservationCode}
              onChange={(e) => setConservationCode(e.target.value)}
            />
            <select
              aria-label="Assessment scope"
              className="h-11 rounded-md border border-border bg-background px-3"
              value={conservationScope}
              onChange={(e) => setConservationScope(e.target.value)}
            >
              <option value="global">global</option>
              <option value="national">national</option>
              <option value="regional">regional</option>
            </select>
          </div>
          <Button
            type="button"
            onClick={() => {
              const source = citations[0]?.source_public_id;
              if (!conservationCode.trim() || !source) {
                setError("SPECIES_SOURCE_REQUIRED");
                return;
              }
              setConservation([
                ...conservation,
                {
                  assessment_system: conservationSystem,
                  status_code: conservationCode,
                  assessment_scope: conservationScope,
                  source_public_id: source,
                },
              ]);
              setConservationCode("");
            }}
          >
            Add assessment
          </Button>
        </TabsContent>

        <TabsContent value="media" className="space-y-3">
          <Label htmlFor="license">License</Label>
          <Input
            id="license"
            value={license}
            onChange={(e) => setLicense(e.target.value)}
          />
          <Label htmlFor="alt_text">Alt text</Label>
          <Input
            id="alt_text"
            value={altText}
            onChange={(e) => setAltText(e.target.value)}
          />
          <input
            aria-label="Identification image"
            type="file"
            accept="image/*"
            disabled={!id || !canMedia}
            onChange={async (event) => {
              const file = event.target.files?.[0];
              if (!file || !id) return;
              const form = new FormData();
              form.append("files[]", file);
              form.append("role", "identification");
              form.append("license", license);
              form.append("alt_text", altText);
              form.append("is_primary", "1");
              try {
                const updated = await uploadAdminSpeciesMedia(id, form);
                applyDetail(updated.data);
              } catch (caught) {
                setError(
                  caught instanceof ApiClientError ? caught.code : "ERROR",
                );
              }
            }}
          />
        </TabsContent>

        <TabsContent value="sources" className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              aria-label="Source title"
              value={sourceTitle}
              onChange={(e) => setSourceTitle(e.target.value)}
            />
            <Input
              aria-label="Publisher"
              value={sourcePublisher}
              onChange={(e) => setSourcePublisher(e.target.value)}
            />
            <Input
              aria-label="Source URL"
              value={sourceUrl}
              onChange={(e) => setSourceUrl(e.target.value)}
            />
            <Input
              aria-label="Retrieved date"
              type="date"
              value={sourceRetrieved}
              onChange={(e) => setSourceRetrieved(e.target.value)}
            />
          </div>
          <Button
            type="button"
            disabled={!id || !canSources}
            onClick={async () => {
              if (!id) return;
              try {
                const updated = await createAdminSpeciesSource(id, {
                  title: sourceTitle,
                  publisher: sourcePublisher,
                  source_type: "scientific_database",
                  url: sourceUrl || null,
                  retrieved_at: sourceRetrieved || null,
                });
                applyDetail(updated);
                setSourceTitle("");
                setSourcePublisher("");
                setSourceUrl("");
              } catch (caught) {
                setError(
                  caught instanceof ApiClientError ? caught.code : "ERROR",
                );
              }
            }}
          >
            Add source
          </Button>
          <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
            <Input
              aria-label="Alias"
              value={aliasName}
              onChange={(e) => setAliasName(e.target.value)}
            />
            <Button
              type="button"
              disabled={!id || !canAlias}
              onClick={async () => {
                if (!id || !aliasName.trim()) return;
                try {
                  const updated = await createAdminSpeciesAlias(id, {
                    name: aliasName,
                    type: "common_alias",
                    locale: "ka",
                    is_public: true,
                    is_searchable: true,
                  });
                  applyDetail(updated);
                  setAliasName("");
                } catch (caught) {
                  setError(
                    caught instanceof ApiClientError ? caught.code : "ERROR",
                  );
                }
              }}
            >
              Add alias
            </Button>
          </div>
        </TabsContent>

        <TabsContent value="revisions" className="space-y-3">
          <ul className="space-y-2 text-sm">
            {revisions.map((revision) => (
              <li
                key={revision.revision_number}
                className="flex items-center justify-between gap-3"
              >
                <span>
                  #{revision.revision_number} · {revision.change_summary} ·{" "}
                  {revision.created_at}
                </span>
                {canRestore && id ? (
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={async () => {
                      if (
                        !window.confirm(
                          "Restore this revision? A new revision will be created.",
                        )
                      ) {
                        return;
                      }
                      try {
                        const updated = await restoreAdminSpeciesRevision(
                          id,
                          revision.revision_number,
                        );
                        applyDetail(updated);
                      } catch (caught) {
                        setError(
                          caught instanceof ApiClientError
                            ? caught.code
                            : "ERROR",
                        );
                      }
                    }}
                  >
                    Restore
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        </TabsContent>

        <TabsContent value="publishing" className="space-y-3">
          <Label htmlFor="verification">Verification</Label>
          <select
            id="verification"
            className="h-11 w-full rounded-md border border-border bg-background px-3"
            value={verification}
            onChange={(e) => setVerification(e.target.value)}
          >
            {[
              "unverified",
              "partially_verified",
              "verified",
              "needs_review",
            ].map((value) => (
              <option key={value} value={value}>
                {value}
              </option>
            ))}
          </select>
          <Label htmlFor="reason">Reason for unpublish/archive</Label>
          <Input
            id="reason"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={() => void run("submit-review")}>
              Submit review
            </Button>
            <Button
              type="button"
              disabled={!canPublish || issues.length > 0}
              onClick={() => void run("publish")}
            >
              Publish
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => void run("unpublish")}
            >
              Unpublish
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={() => {
                if (
                  window.confirm(
                    "Archive this species? Prefer archive over delete.",
                  )
                ) {
                  void run("archive");
                }
              }}
            >
              Archive
            </Button>
          </div>
          {slug ? (
            <a className="underline" href={`/species/${slug}`}>
              Preview
            </a>
          ) : null}
        </TabsContent>
      </Tabs>
      <Button type="submit">Save</Button>
    </form>
  );
}
