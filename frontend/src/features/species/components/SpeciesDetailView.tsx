import Link from "next/link";
import Image from "next/image";
import type {
  SpeciesDetail,
  SpeciesLocale,
} from "@/features/species/types/species-types";
import { SpeciesLegalOverview } from "@/features/legal/components/SpeciesLegalOverview";
import { SpeciesSeasonSection } from "@/features/seasons/components/SpeciesSeasonSection";

const copy = {
  ka: {
    identification: "სწრაფი იდენტიფიკაცია",
    taxonomy: "სამეცნიერო კლასიფიკაცია",
    traits: "ნიშნები",
    habitat: "ჰაბიტატი",
    behavior: "ქცევა",
    characteristics: "მახასიათებლები",
    similar: "მსგავსი სახეობები",
    conservation: "კონსერვაცია",
    sources: "წყაროები",
    official: "ოფიციალური წყარო",
    fallback:
      "ინგლისური თარგმანი ჯერ არ არის გამოქვეყნებული. ნაჩვენებია ქართული ტექსტი.",
    unavailable: "მიუწვდომელია",
    notLegal: "კონსერვაციის სტატუსი არ არის სანადირო ნებართვა.",
  },
  en: {
    identification: "Quick identification",
    taxonomy: "Scientific classification",
    traits: "Identification traits",
    habitat: "Habitat",
    behavior: "Behavior",
    characteristics: "Characteristics",
    similar: "Similar species",
    conservation: "Conservation",
    sources: "Sources",
    official: "Official source",
    fallback:
      "An English translation is not published yet. Georgian text is shown.",
    unavailable: "Not available",
    notLegal: "Conservation status is not hunting permission.",
  },
};

function html(value: string | null | undefined) {
  if (!value) return null;
  return (
    <div
      className="prose prose-sm max-w-none"
      dangerouslySetInnerHTML={{ __html: value }}
    />
  );
}

export function SpeciesDetailView({
  locale,
  species,
}: {
  locale: SpeciesLocale;
  species: SpeciesDetail;
}) {
  const t = copy[locale];
  const hero =
    species.media.find((item) => item.is_primary) ?? species.media[0];
  const image =
    hero?.derivatives.find((item) => item.preset === "detail") ??
    hero?.derivatives[0];
  const sections = [
    { id: "identification", label: t.identification },
    { id: "taxonomy", label: t.taxonomy },
    { id: "traits", label: t.traits },
    { id: "habitat", label: t.habitat },
    { id: "behavior", label: t.behavior },
    { id: "characteristics", label: t.characteristics },
    { id: "similar", label: t.similar },
    { id: "conservation", label: t.conservation },
    { id: "sources", label: t.sources },
    { id: "legal", label: locale === "ka" ? "რეგულაციები" : "Regulations" },
  ];

  return (
    <article className="sf-band-paper">
      <div className="relative min-h-[48vh] overflow-hidden">
        {image ? (
          <Image
            src={image.url}
            alt={
              hero?.alt_text || species.common_name || species.scientific_name
            }
            fill
            priority
            className="object-cover"
            sizes="100vw"
          />
        ) : (
          <div className="absolute inset-0 bg-[var(--forest-deep)]" />
        )}
        <div className="relative z-10 flex min-h-[48vh] items-end bg-gradient-to-t from-black/70 to-transparent">
          <div className="sf-container py-12 text-white">
            <nav aria-label="Breadcrumb" className="text-sm">
              <Link
                href="/species"
                className="underline-offset-4 hover:underline"
              >
                {locale === "ka" ? "სახეობები" : "Species"}
              </Link>
              <span aria-hidden> / </span>
              <span>{species.common_name}</span>
            </nav>
            <h1 className="sf-display mt-4 text-4xl sm:text-6xl">
              {species.common_name}
            </h1>
            <p className="mt-2 text-xl italic">
              <span className="sr-only">Scientific name: </span>
              {species.scientific_name}
            </p>
          </div>
        </div>
      </div>

      <div className="sf-container grid gap-10 py-12 lg:grid-cols-[220px_1fr]">
        <nav
          className="hidden lg:sticky lg:top-28 lg:block lg:self-start"
          aria-label="Desktop sections"
        >
          <ul className="space-y-2 text-sm">
            {sections.map((section) => (
              <li key={section.id}>
                <a href={`#${section.id}`} className="hover:text-[var(--sand)]">
                  {section.label}
                </a>
              </li>
            ))}
          </ul>
        </nav>

        <div className="space-y-12">
          {species.translation_fallback ? (
            <p role="status" className="rounded-xl border border-border p-4">
              {t.fallback}
            </p>
          ) : null}

          <nav
            className="flex gap-2 overflow-x-auto pb-2 lg:hidden"
            aria-label="Mobile sections"
          >
            {sections.map((section) => (
              <a
                key={section.id}
                href={`#${section.id}`}
                className="min-h-11 shrink-0 rounded-full border border-border px-3 py-2 text-sm"
              >
                {section.label}
              </a>
            ))}
          </nav>

          <section id="identification">
            <h2 className="text-2xl font-semibold">{t.identification}</h2>
            {html(species.identification.overview) ?? <p>{t.unavailable}</p>}
            {species.aliases.length > 0 ? (
              <ul className="mt-3 flex flex-wrap gap-2 text-sm">
                {species.aliases.map((alias) => (
                  <li
                    key={`${alias.type}-${alias.name}`}
                    className="rounded-full border border-border px-3 py-1"
                  >
                    {alias.name}
                  </li>
                ))}
              </ul>
            ) : null}
          </section>

          <section id="taxonomy">
            <h2 className="text-2xl font-semibold">{t.taxonomy}</h2>
            <dl className="mt-4 grid gap-2 sm:grid-cols-2">
              {["kingdom", "phylum", "class", "order", "family", "genus"].map(
                (rank) => {
                  const value = species.taxonomy[rank];
                  const label =
                    typeof value === "object" && value
                      ? value.scientific_name
                      : value;
                  return (
                    <div key={rank}>
                      <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                        {rank}
                      </dt>
                      <dd>{label || t.unavailable}</dd>
                    </div>
                  );
                },
              )}
            </dl>
          </section>

          <section id="traits">
            <h2 className="text-2xl font-semibold">{t.traits}</h2>
            <ul className="mt-4 space-y-3">
              {species.identification.traits.map((trait) => (
                <li key={`${trait.category}-${trait.label}`}>
                  <p className="font-medium">{trait.label}</p>
                  <p className="text-sm text-muted-foreground">
                    {trait.description}
                  </p>
                </li>
              ))}
            </ul>
          </section>

          <section id="habitat">
            <h2 className="text-2xl font-semibold">{t.habitat}</h2>
            <ul className="mt-3 flex flex-wrap gap-2">
              {species.habitats.map((habitat) => (
                <li
                  key={habitat.code}
                  className="rounded-full border border-border px-3 py-1 text-sm"
                >
                  {habitat.name}
                </li>
              ))}
            </ul>
            {html(species.behavior.habitat_description)}
          </section>

          <section id="behavior">
            <h2 className="text-2xl font-semibold">{t.behavior}</h2>
            {html(species.behavior.overview) ?? <p>{t.unavailable}</p>}
          </section>

          <section id="characteristics">
            <h2 className="text-2xl font-semibold">{t.characteristics}</h2>
            {species.characteristics ? (
              <dl className="mt-4 grid gap-2 sm:grid-cols-2">
                {Object.entries(species.characteristics).map(([key, value]) => {
                  if (!value) return null;
                  const formatted =
                    typeof value === "object" && value && "formatted" in value
                      ? String(
                          (value as { formatted?: string }).formatted ?? "",
                        )
                      : String(value);
                  if (!formatted) return null;
                  return (
                    <div key={key}>
                      <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                        {key}
                      </dt>
                      <dd>{formatted}</dd>
                    </div>
                  );
                })}
              </dl>
            ) : (
              <p>{t.unavailable}</p>
            )}
          </section>

          <section id="similar">
            <h2 className="text-2xl font-semibold">{t.similar}</h2>
            <ul className="mt-4 space-y-2">
              {species.identification.similar_species.map((item) => (
                <li key={item.id}>
                  <Link
                    href={`/species/${item.slug}`}
                    className="font-medium underline-offset-4 hover:underline"
                  >
                    {item.common_name}{" "}
                    <span className="italic">({item.scientific_name})</span>
                  </Link>
                </li>
              ))}
            </ul>
          </section>

          <section id="conservation">
            <h2 className="text-2xl font-semibold">{t.conservation}</h2>
            {species.conservation.length === 0 ? <p>{t.unavailable}</p> : null}
            {species.conservation.map((row) => (
              <div
                key={`${row.assessment_system}-${row.assessment_scope}`}
                className="mt-3 rounded-xl border border-border p-4"
              >
                <p className="font-medium">
                  {row.status_code} · {row.assessment_scope} ·{" "}
                  {row.assessment_system}
                </p>
                <p className="text-sm text-muted-foreground">{t.notLegal}</p>
              </div>
            ))}
          </section>

          <section id="sources">
            <h2 className="text-2xl font-semibold">{t.sources}</h2>
            <ul className="mt-4 space-y-3">
              {species.sources.map((source) => (
                <li
                  key={source.id}
                  className="rounded-xl border border-border p-4"
                >
                  <p className="font-medium">{source.title}</p>
                  <p className="text-sm text-muted-foreground">
                    {source.publisher} · {source.source_type}
                    {source.is_official ? ` · ${t.official}` : ""}
                  </p>
                  {source.url ? (
                    <a
                      href={source.url}
                      rel="noopener noreferrer"
                      target="_blank"
                      className="text-sm underline"
                    >
                      {source.url}
                    </a>
                  ) : null}
                </li>
              ))}
            </ul>
          </section>

          <SpeciesSeasonSection slug={species.slug} />
          <SpeciesLegalOverview
            locale={locale}
            legal={species.legal_information}
          />
        </div>
      </div>
    </article>
  );
}
