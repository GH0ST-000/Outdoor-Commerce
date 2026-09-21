"use client";

import { useState, type ReactNode } from "react";
import { Heart, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { IconButton } from "@/components/ui/icon-button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { SearchInput } from "@/components/ui/search-input";
import { Checkbox } from "@/components/ui/checkbox";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Drawer,
  DrawerContent,
  DrawerTitle,
  DrawerTrigger,
} from "@/components/ui/drawer";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState } from "@/components/ui/empty-state";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { DataTable } from "@/components/ui/table";
import { Combobox } from "@/components/ui/combobox";
import {
  Container,
  Grid,
  Stack,
  Surface,
} from "@/components/layout/primitives";
import { ProductCard } from "@/features/storefront/components/commerce/ProductCard";
import { CategoryCard } from "@/components/commerce/category-card";
import { AvailabilityStatus } from "@/components/commerce/availability-status";
import { FilterCheckbox } from "@/components/commerce/filter-controls";
import {
  ColorSwatch,
  SizeOption,
  VariantGroup,
} from "@/components/commerce/variant-option";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { StatusBadge } from "@/components/commerce/status-badge";
import {
  Callout,
  EditorialHero,
  Eyebrow,
  LegalDisclaimer,
  Quote,
  SectionHeading,
} from "@/components/editorial/primitives";
import {
  LegalLimit,
  MapLegend,
  SeasonStatus,
  SpeciesSummary,
} from "@/components/outdoor-context/primitives";
import { featuredProducts } from "@/features/storefront/fixtures/demo-catalog";

function Block({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-4 border-b border-border py-10">
      <h2 className="type-h3">{title}</h2>
      {children}
    </section>
  );
}

export function DesignSystemGallery() {
  const [qty, setQty] = useState(1);
  const [page, setPage] = useState(2);
  const [brand, setBrand] = useState(false);
  const product = featuredProducts[0]!;

  return (
    <div className="min-h-screen bg-background text-foreground">
      <EditorialHero
        eyebrow="Caucasus Field Intelligence"
        title="Storefront design system"
        lead="Development-only gallery for tokens, primitives, and commerce presentation. Not shipped in production."
      />
      <Container className="pb-24">
        <Block title="Surfaces">
          <Grid columns="3">
            <Surface name="dark" className="rounded-[var(--radius-lg)] p-6">
              Dark cinematic
            </Surface>
            <Surface name="paper" className="rounded-[var(--radius-lg)] p-6">
              Warm editorial paper
            </Surface>
            <Surface name="commerce" className="rounded-[var(--radius-lg)] p-6">
              Neutral commerce
            </Surface>
          </Grid>
        </Block>

        <Block title="Typography">
          <Stack>
            <p className="type-display-l sf-display">Display L · Field notes</p>
            <p className="type-h1">სანადირო ჟაკეტი</p>
            <p className="type-body max-w-[var(--measure)]">
              ქართული პარაგრაფი რჩება მკითხველად, without automatic uppercase or
              Latin display fonts.
            </p>
            <p className="type-price-lg">129.00 ₾</p>
            <p className="sf-label">Technical label</p>
          </Stack>
        </Block>

        <Block title="Buttons">
          <div className="flex flex-wrap gap-3">
            <Button>Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="outline">Outline</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="danger">Danger</Button>
            <Button variant="link">Link</Button>
            <Button loading loadingLabel="Saving">
              Save
            </Button>
            <Button disabled>Disabled</Button>
            <IconButton label="Search">
              <Search />
            </IconButton>
          </div>
        </Block>

        <Block title="Forms">
          <Stack className="max-w-md">
            <Field
              label="ელფოსტა"
              htmlFor="ds-email"
              description="We never share this."
            >
              <Input id="ds-email" />
            </Field>
            <Field label="Notes" htmlFor="ds-notes" error="Required">
              <Textarea id="ds-notes" />
            </Field>
            <SearchInput aria-label="Search catalog" />
            <Combobox
              id="ds-brand"
              label="Brand"
              value="swarovski"
              onValueChange={() => undefined}
              options={[
                { value: "swarovski", label: "Swarovski" },
                { value: "harkila", label: "Härkila" },
              ]}
            />
            <label className="flex items-center gap-2 text-sm">
              <Checkbox id="ds-check" /> Notify me
            </label>
            <RadioGroup defaultValue="ka">
              <label className="flex items-center gap-2 text-sm">
                <RadioGroupItem value="ka" /> ქართული
              </label>
              <label className="flex items-center gap-2 text-sm">
                <RadioGroupItem value="en" /> English
              </label>
            </RadioGroup>
            <Switch aria-label="Demo switch" />
          </Stack>
        </Block>

        <Block title="Overlays">
          <div className="flex gap-3">
            <Dialog>
              <DialogTrigger asChild>
                <Button variant="outline">Dialog</Button>
              </DialogTrigger>
              <DialogContent>
                <DialogTitle>Confirm archive</DialogTitle>
                <DialogDescription>
                  Focus is trapped until this dialog closes.
                </DialogDescription>
              </DialogContent>
            </Dialog>
            <Drawer>
              <DrawerTrigger asChild>
                <Button variant="outline">Drawer</Button>
              </DrawerTrigger>
              <DrawerContent className="p-6">
                <DrawerTitle>Filters</DrawerTitle>
              </DrawerContent>
            </Drawer>
          </div>
        </Block>

        <Block title="Commerce">
          <div className="max-w-xs">
            <ProductCard product={product} />
          </div>
          <CategoryCard
            href="/catalog/hunting"
            name="ნადირობა"
            description="Optics, clothing, and field kits."
            countLabel="24 products"
          />
          <PriceDisplay amountMinor={12900} currency="GEL" locale="ka-GE" />
          <AvailabilityStatus status="low_stock" label="მცირე მარაგი" />
          <FilterCheckbox
            id="ds-filter"
            label="Härkila"
            checked={brand}
            onChange={setBrand}
            count={4}
          />
          <VariantGroup legend="Color" summary="Green">
            <ColorSwatch label="Green" color="#2f4f3a" selected />
            <ColorSwatch label="Stone" color="#8a8d82" />
            <SizeOption label="M" selected />
            <SizeOption label="XL" unavailable disabled />
          </VariantGroup>
          <QuantityStepper
            value={qty}
            onChange={setQty}
            label="Quantity"
            decrementLabel="Decrease"
            incrementLabel="Increase"
          />
          <div className="flex flex-wrap gap-2">
            <StatusBadge kind="sale">Sale</StatusBadge>
            <StatusBadge kind="open_season">Open season</StatusBadge>
            <Badge>Featured</Badge>
          </div>
        </Block>

        <Block title="Feedback">
          <Alert tone="warning" title="Catalog rate limited">
            Wait a moment, then retry.
          </Alert>
          <EmptyState
            title="ფილტრს შედეგი არ აქვს"
            actionLabel="Clear filters"
            onAction={() => undefined}
          />
          <Skeleton className="h-24 w-full" />
          <Pagination
            page={page}
            lastPage={6}
            onPageChange={setPage}
            previousLabel="Previous"
            nextLabel="Next"
            label="Pagination"
          />
        </Block>

        <Block title="Editorial & outdoor">
          <Eyebrow>Field notes</Eyebrow>
          <SectionHeading
            title="Seasons"
            description="Presentation only — legal status comes from the API."
          />
          <Quote attribution="Field desk">
            The ridge holds snow later than the valley.
          </Quote>
          <Callout title="Source">
            Demo calendar rows are not legal advice.
          </Callout>
          <LegalDisclaimer>
            Hunting rules must be verified against official Georgian sources.
          </LegalDisclaimer>
          <SeasonStatus status="open" label="Open season" />
          <SpeciesSummary name="Red deer" meta="Kakheti · unverified demo" />
          <LegalLimit
            label="Bag limit"
            value="1 / season"
            verificationLabel="Unverified demo"
            source="Fixture"
          />
          <MapLegend
            items={[
              { label: "Open", swatch: "var(--status-success)" },
              { label: "Closed", swatch: "var(--status-danger)" },
            ]}
          />
        </Block>

        <Block title="Tables">
          <DataTable
            caption="Specification"
            headers={["Spec", "Value"]}
            rows={[
              ["Weight", "1.2 kg"],
              ["Waterproof", "Yes"],
            ]}
          />
        </Block>

        <p className="flex items-center gap-2 text-sm text-muted-foreground">
          <Heart className="size-4" aria-hidden /> Lucide icons, imported per
          glyph.
        </p>
      </Container>
    </div>
  );
}
