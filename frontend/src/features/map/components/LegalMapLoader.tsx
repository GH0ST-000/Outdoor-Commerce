"use client";

import dynamic from "next/dynamic";

const LegalMapExperience = dynamic(
  () =>
    import("@/features/map/components/LegalMapExperience").then(
      (mod) => mod.LegalMapExperience,
    ),
  {
    ssr: false,
    loading: () => (
      <div
        className="grid h-[calc(100dvh-3.5rem)] place-items-center text-sm"
        role="status"
      >
        Loading the map…
      </div>
    ),
  },
);

export function LegalMapLoader() {
  return <LegalMapExperience />;
}
