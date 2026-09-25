import Link from "next/link";
import { cookies } from "next/headers";

export default async function SpeciesNotFound() {
  const locale =
    (await cookies()).get("outdoor-locale")?.value === "en" ? "en" : "ka";
  const copy =
    locale === "en"
      ? {
          title: "Species not found",
          body: "This species is not published, or the link is incorrect. Species pages contain biological information only — not hunting or fishing permission.",
          back: "Back to the species directory",
        }
      : {
          title: "სახეობა ვერ მოიძებნა",
          body: "ეს სახეობა გამოქვეყნებული არ არის, ან ბმული არასწორია. სახეობების გვერდები შეიცავს მხოლოდ ბიოლოგიურ ინფორმაციას — არა სანადირო ან სათევზაო ნებართვას.",
          back: "სახეობების დირექტორიაში დაბრუნება",
        };

  return (
    <div className="sf-container sf-section space-y-4">
      <div className="mx-auto max-w-xl space-y-3 text-center">
        <h1 className="sf-display text-3xl">{copy.title}</h1>
        <p className="text-muted-foreground">{copy.body}</p>
        <Link
          href="/species"
          className="inline-flex min-h-11 items-center justify-center rounded-xl border border-border px-4"
        >
          {copy.back}
        </Link>
      </div>
    </div>
  );
}
