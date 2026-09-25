"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import type {
  SpeciesCard,
  SpeciesFilters,
  SpeciesLocale,
  SpeciesQuery,
} from "@/features/species/types/species-types";
import { speciesQueryToSearchParams } from "@/features/species/query-state/species-search-params";

const copy = {
  ka: {
    kicker: "საველე გზამკვლევი",
    title: "სახეობების ცოდნის ბაზა",
    lead: "ბიოლოგიური იდენტიფიკაცია და ბუნებრივი ისტორია — არა სანადირო ან სათევზაო ნებართვა.",
    search: "ძიება სახელით ან სამეცნიერო სახელით",
    empty: "ამ ფილტრებით სახეობა ვერ მოიძებნა.",
    error: "სახეობების სია ამჟამად მიუწვდომელია.",
    legal:
      "ეს გვერდი შეიცავს ბიოლოგიურ ინფორმაციას. ნადირობის ან თევზჭერის კანონიერება ცალკე, გადამოწმებული წყაროებით გამოჩნდება.",
    verified: "გადამოწმებული",
    page: "გვერდი",
    activityGroup: "აქტივობა",
    domainGroup: "გარემო",
    allActivities: "ყველა",
    allDomains: "ყველა გარემო",
    sortScientific: "სამეცნიერო სახელი",
    activities: {
      hunting: "ნადირობა",
      fishing: "თევზაობა",
      wildlife: "ველური ბუნება",
      hunting_and_wildlife: "ნადირობა და ველური ბუნება",
      fishing_and_wildlife: "თევზაობა და ველური ბუნება",
    },
    domains: {
      terrestrial: "ხმელეთი",
      freshwater: "მტკნარი წყალი",
      marine: "ზღვა",
      migratory: "მიგრაციული",
      mixed: "შერეული",
    },
  },
  en: {
    kicker: "Field guide",
    title: "Species knowledge base",
    lead: "Biological identification and natural history — not hunting or fishing permission.",
    search: "Search by common or scientific name",
    empty: "No species matched these filters.",
    error: "The species directory is unavailable.",
    legal:
      "This page contains biological information. Legal hunting or fishing permission will appear separately once verified sources exist.",
    verified: "Verified",
    page: "Page",
    activityGroup: "Activity",
    domainGroup: "Environment",
    allActivities: "All",
    allDomains: "All environments",
    sortScientific: "Scientific name",
    activities: {
      hunting: "Hunting",
      fishing: "Fishing",
      wildlife: "Wildlife",
      hunting_and_wildlife: "Hunting and wildlife",
      fishing_and_wildlife: "Fishing and wildlife",
    },
    domains: {
      terrestrial: "Terrestrial",
      freshwater: "Freshwater",
      marine: "Marine",
      migratory: "Migratory",
      mixed: "Mixed",
    },
  },
};

type Props = {
  locale: SpeciesLocale;
  query: SpeciesQuery;
  cards: SpeciesCard[];
  total: number;
  lastPage: number;
  filters: SpeciesFilters | null;
  error: string | null;
};

export function SpeciesDirectoryView({
  locale,
  query,
  cards,
  total,
  lastPage,
  filters,
  error,
}: Props) {
  const router = useRouter();
  const t = copy[locale];

  function update(next: Partial<SpeciesQuery>) {
    const merged = { ...query, ...next, page: next.page ?? 1 };
    router.push(`/species${speciesQueryToSearchParams(merged)}`);
  }

  return (
    <div className="sf-band-paper sf-section">
      <div className="sf-container space-y-10">
        <header className="max-w-3xl">
          <p className="sf-label text-[var(--sand)]">{t.kicker}</p>
          <h1 className="sf-display mt-2 text-4xl sm:text-5xl">{t.title}</h1>
          <p className="mt-3 text-muted-foreground">{t.lead}</p>
        </header>

        <aside
          className="rounded-2xl border border-border/70 bg-card p-4 text-sm"
          role="note"
        >
          {t.legal}
        </aside>

        <form
          className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end"
          onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            update({ q: String(data.get("q") ?? ""), page: 1 });
          }}
        >
          <label className="block">
            <span className="sr-only">{t.search}</span>
            <input
              name="q"
              defaultValue={query.q ?? ""}
              className="h-12 w-full rounded-xl border border-border bg-background px-4"
              placeholder={t.search}
            />
          </label>
          <button
            type="submit"
            className="h-12 rounded-xl bg-[var(--button-primary-background)] px-5 text-sm font-semibold text-primary-foreground"
          >
            {locale === "ka" ? "ძიება" : "Search"}
          </button>
        </form>

        <div
          className="flex flex-wrap gap-2"
          role="group"
          aria-label={t.activityGroup}
        >
          {[
            "",
            "hunting",
            "fishing",
            "wildlife",
            "hunting_and_wildlife",
            "fishing_and_wildlife",
          ].map((value) => (
            <button
              key={value || "all"}
              type="button"
              className="min-h-11 rounded-full border border-border px-4 text-sm focus-visible:outline focus-visible:outline-2"
              aria-pressed={
                query.activity_type === value ||
                (!query.activity_type && value === "")
              }
              onClick={() => update({ activity_type: value || undefined })}
            >
              {value
                ? t.activities[value as keyof typeof t.activities]
                : t.allActivities}
            </button>
          ))}
        </div>

        <div
          className="flex flex-wrap gap-2"
          role="group"
          aria-label={t.domainGroup}
        >
          {[
            "",
            "terrestrial",
            "freshwater",
            "marine",
            "migratory",
            "mixed",
          ].map((value) => (
            <button
              key={value || "all-domain"}
              type="button"
              className="min-h-11 rounded-full border border-border px-4 text-sm focus-visible:outline focus-visible:outline-2"
              aria-pressed={
                query.domain_type === value ||
                (!query.domain_type && value === "")
              }
              onClick={() => update({ domain_type: value || undefined })}
            >
              {value
                ? t.domains[value as keyof typeof t.domains]
                : t.allDomains}
            </button>
          ))}
        </div>
        {filters ? (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <select
              aria-label="Habitat"
              className="h-11 rounded-xl border border-border bg-background px-3"
              value={query.habitat ?? ""}
              onChange={(event) =>
                update({ habitat: event.target.value || undefined })
              }
            >
              <option value="">
                {locale === "ka" ? "ჰაბიტატი" : "Habitat"}
              </option>
              {filters.habitats.map((habitat) => (
                <option key={habitat.code} value={habitat.code}>
                  {habitat.name}
                </option>
              ))}
            </select>
            <select
              aria-label="Taxonomy"
              className="h-11 rounded-xl border border-border bg-background px-3"
              value={query.taxonomy ?? ""}
              onChange={(event) =>
                update({ taxonomy: event.target.value || undefined })
              }
            >
              <option value="">
                {locale === "ka" ? "ტაქსონომია" : "Taxonomy"}
              </option>
              {filters.taxonomy.families.map((family) => (
                <option key={family} value={family}>
                  {family}
                </option>
              ))}
            </select>
            <select
              aria-label="Conservation"
              className="h-11 rounded-xl border border-border bg-background px-3"
              value={query.conservation_status ?? ""}
              onChange={(event) =>
                update({ conservation_status: event.target.value || undefined })
              }
            >
              <option value="">
                {locale === "ka" ? "კონსერვაცია" : "Conservation"}
              </option>
              {filters.conservation.map((row) => (
                <option
                  key={`${row.assessment_scope}-${row.status_code}`}
                  value={row.status_code}
                >
                  {row.status_code} ({row.assessment_scope})
                </option>
              ))}
            </select>
            <select
              aria-label="Sort"
              className="h-11 rounded-xl border border-border bg-background px-3"
              value={query.sort}
              onChange={(event) => update({ sort: event.target.value })}
            >
              <option value="name">
                {locale === "ka" ? "სახელი" : "Name"}
              </option>
              <option value="scientific">{t.sortScientific}</option>
              <option value="newest">
                {locale === "ka" ? "ახალი" : "Newest"}
              </option>
            </select>
          </div>
        ) : null}

        {error ? (
          <p role="alert" className="text-destructive">
            {t.error}
          </p>
        ) : null}

        {!error && cards.length === 0 ? <p role="status">{t.empty}</p> : null}

        <ul className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          {cards.map((card) => {
            const image =
              card.media?.derivatives.find((item) => item.preset === "card") ??
              card.media?.derivatives[0];
            return (
              <li key={card.id}>
                <article className="sf-lift overflow-hidden rounded-2xl border border-border/70 bg-card">
                  <Link href={`/species/${card.slug}`} className="block">
                    <div className="relative aspect-[16/10] bg-muted">
                      {image ? (
                        <Image
                          src={image.url}
                          alt={
                            card.media?.alt_text ||
                            card.common_name ||
                            card.scientific_name
                          }
                          fill
                          sizes="(max-width:1024px) 100vw, 33vw"
                          className="object-cover"
                        />
                      ) : null}
                    </div>
                    <div className="space-y-2 p-5">
                      <h2 className="text-xl font-semibold">
                        {card.common_name}
                      </h2>
                      <p className="text-sm italic text-muted-foreground">
                        <span className="sr-only">Scientific name: </span>
                        {card.scientific_name}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {card.summary}
                      </p>
                      <p className="text-xs uppercase tracking-wide text-[var(--sand)]">
                        {t.activities[
                          card.activity_type as keyof typeof t.activities
                        ] ?? card.activity_type}{" "}
                        ·{" "}
                        {t.domains[
                          card.domain_type as keyof typeof t.domains
                        ] ?? card.domain_type}
                      </p>
                      {card.habitats.length > 0 ? (
                        <ul className="flex flex-wrap gap-1">
                          {card.habitats.map((habitat) => (
                            <li
                              key={habitat.code}
                              className="rounded-full border border-border px-2 py-0.5 text-xs"
                            >
                              {habitat.name}
                            </li>
                          ))}
                        </ul>
                      ) : null}
                      {card.verified ? (
                        <p className="text-xs">{t.verified}</p>
                      ) : null}
                    </div>
                  </Link>
                </article>
              </li>
            );
          })}
        </ul>

        {lastPage > 1 ? (
          <nav aria-label={t.page} className="flex gap-2">
            {Array.from({ length: lastPage }, (_, index) => index + 1).map(
              (page) => (
                <button
                  key={page}
                  type="button"
                  className="min-h-11 min-w-11 rounded-lg border border-border"
                  aria-current={page === query.page ? "page" : undefined}
                  onClick={() => update({ page })}
                >
                  {page}
                </button>
              ),
            )}
          </nav>
        ) : null}

        <p className="text-sm text-muted-foreground">{total}</p>
      </div>
    </div>
  );
}
