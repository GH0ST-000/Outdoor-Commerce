import type {
  BrandShowcaseItem,
  CategoryGatewayItem,
  FieldGuideCardData,
  ProductCardData,
  ProductDetailFixture,
  SeasonDemoRow,
  StorefrontCopy,
} from "@/features/storefront/types/storefront-types";

export const storefrontCopy = {
  en: {
    nav: {
      hunting: "Hunting",
      fishing: "Fishing",
      camping: "Camping & Outdoor",
      clothing: "Clothing",
      optics: "Optics",
      knives: "Knives & Tools",
      brands: "Brands",
      calendar: "Hunting Calendar",
      map: "Map",
      fieldGuide: "Field Guide",
      catalog: "Catalog",
      search: "Search",
      account: "Account",
      cartSoon: "Cart coming soon",
      wishlistSoon: "Wishlist coming soon",
      openMenu: "Open menu",
      closeMenu: "Close menu",
      skipToContent: "Skip to content",
    },
    home: {
      shopCta: "Explore gear",
      guideCta: "Open field guide",
      categoriesTitle: "Choose your field",
      categoriesLead: "Enter by pursuit — then refine by terrain and season.",
      featuredTitle: "Field-selected gear",
      featuredLead:
        "A short list of equipment we would pack for Georgian terrain.",
      seasonTitle: "Season intelligence",
      seasonLead:
        "A preview of how legal windows will surface beside equipment.",
      mapTitle: "Terrain preview",
      mapLead: "Zones, access, and context — geospatial depth arrives later.",
      mapCta: "Open map shell",
      brandsTitle: "Trusted makers",
      brandsLead:
        "Names we stock for durability in wet forests and high ridges.",
      journalTitle: "Field journal",
      journalLead: "Guides for preparation, safety, and equipment care.",
      trustTitle: "Built for trust",
      newsletterTitle: "Stay ahead of the season",
      newsletterLead:
        "Season notes, gear drops, and field guides — when the list goes live.",
      newsletterCta: "Notify me",
      newsletterHint: "Email capture is not connected yet.",
    },
    catalog: {
      title: "Catalog",
      lead: "Browse outdoor equipment by pursuit and category.",
      filters: "Filters",
      sort: "Sort",
      results: "products",
      empty: "No products match these filters.",
      clearFilters: "Clear filters",
      applyFilters: "Apply filters",
    },
    product: {
      model: "Model",
      sku: "SKU",
      selectVariant: "Select options",
      specs: "Specifications",
      context: "Recommended for",
      related: "Related gear",
      notify: "Notify when available",
      notifyHint: "Purchase and inventory are not connected yet.",
      gallery: "Product images",
    },
    calendar: {
      title: "Hunting calendar",
      lead: "Visual prototype for species, regions, and seasonal status.",
      search: "Search species",
      open: "Open",
      closed: "Closed",
      conditional: "Conditional",
      unknown: "Unverified",
    },
    map: {
      title: "Map",
      lead: "Experience shell for future hunting zones and access layers.",
      legend: "Legend",
      panel: "Selected zone",
      unavailable: "Live geospatial data is not connected.",
    },
    fieldGuide: {
      title: "Field guide",
      lead: "Knowledge for preparation, legality awareness, and equipment care.",
      read: "Read guide",
      updated: "Last updated",
      sources: "Sources",
    },
    common: {
      loading: "Loading…",
      error: "Something went wrong.",
      retry: "Retry",
      back: "Back",
      priceOnRequest: "Price on request",
      comingSoon: "Availability coming soon",
      demoLegal: "Demonstration data only — not official Georgian hunting law.",
    },
  } satisfies StorefrontCopy,
  ka: {
    nav: {
      hunting: "ნადირობა",
      fishing: "თევზაობა",
      camping: "კემპინგი და აუთდორი",
      clothing: "ტანსაცმელი",
      optics: "ოპტიკა",
      knives: "დანები და ხელსაწყოები",
      brands: "ბრენდები",
      calendar: "სანადირო კალენდარი",
      map: "რუკა",
      fieldGuide: "საველე გზამკვლევი",
      catalog: "კატალოგი",
      search: "ძიება",
      account: "ანგარიში",
      cartSoon: "კალათა მალე დაემატება",
      wishlistSoon: "სურვილების სია მალე დაემატება",
      openMenu: "მენიუს გახსნა",
      closeMenu: "მენიუს დახურვა",
      skipToContent: "შინაარსზე გადასვლა",
    },
    home: {
      shopCta: "აღჭურვილობის ნახვა",
      guideCta: "გზამკვლევის გახსნა",
      categoriesTitle: "აირჩიე მიმართულება",
      categoriesLead:
        "შედი დანიშნულებით — შემდეგ დააზუსტე რელიეფით და სეზონით.",
      featuredTitle: "შერჩეული აღჭურვილობა",
      featuredLead:
        "მოკლე სია იმისა, რასაც საქართველოს რელიეფისთვის შევაფუთავდით.",
      seasonTitle: "სეზონის ინტელექტი",
      seasonLead:
        "როგორ გამოჩნდება სამართლებრივი ფანჯრები აღჭურვილობასთან ერთად.",
      mapTitle: "რელიეფის გადახედვა",
      mapLead: "ზონები და კონტექსტი — გეოსივრცითი სიღრმე მოგვიანებით.",
      mapCta: "რუკის გახსნა",
      brandsTitle: "სანდო მწარმოებლები",
      brandsLead:
        "სახელები, რომლებსაც ვარჩევთ სველი ტყისა და მაღალი ქედებისთვის.",
      journalTitle: "საველე ჟურნალი",
      journalLead: "მზადება, უსაფრთხოება და აღჭურვილობის მოვლა.",
      trustTitle: "ნდობისთვის შექმნილი",
      newsletterTitle: "იყავი სეზონზე წინ",
      newsletterLead:
        "სეზონის შენიშვნები და გზამკვლევები — როცა სია ამუშავდება.",
      newsletterCta: "შემატყობინე",
      newsletterHint: "ელფოსტის შეგროვება ჯერ არ არის დაკავშირებული.",
    },
    catalog: {
      title: "კატალოგი",
      lead: "აუთდორ აღჭურვილობა დანიშნულებისა და კატეგორიის მიხედვით.",
      filters: "ფილტრები",
      sort: "დალაგება",
      results: "პროდუქტი",
      empty: "ფილტრებს პროდუქტი არ ემთხვევა.",
      clearFilters: "ფილტრების გასუფთავება",
      applyFilters: "ფილტრების გამოყენება",
    },
    product: {
      model: "მოდელი",
      sku: "SKU",
      selectVariant: "ვარიანტის არჩევა",
      specs: "მახასიათებლები",
      context: "რეკომენდებულია",
      related: "მსგავსი აღჭურვილობა",
      notify: "შემატყობინე ხელმისაწვდომობისას",
      notifyHint: "ყიდვა და მარაგი ჯერ არ არის დაკავშირებული.",
      gallery: "პროდუქტის სურათები",
    },
    calendar: {
      title: "სანადირო კალენდარი",
      lead: "ვიზუალური პროტოტიპი სახეობების, რეგიონებისა და სეზონის სტატუსისთვის.",
      search: "სახეობის ძიება",
      open: "ღია",
      closed: "დახურული",
      conditional: "პირობითი",
      unknown: "გაურკვეველი",
    },
    map: {
      title: "რუკა",
      lead: "გამოცდილების გარსი მომავალი სანადირო ზონებისთვის.",
      legend: "ლეგენდა",
      panel: "არჩეული ზონა",
      unavailable: "ცოცხალი გეომონაცემები არ არის დაკავშირებული.",
    },
    fieldGuide: {
      title: "საველე გზამკვლევი",
      lead: "ცოდნა მზადების, სამართლებრივი კონტექსტისა და მოვლისთვის.",
      read: "წაკითხვა",
      updated: "ბოლო განახლება",
      sources: "წყაროები",
    },
    common: {
      loading: "იტვირთება…",
      error: "რაღაც შეცდომა მოხდა.",
      retry: "თავიდან ცდა",
      back: "უკან",
      priceOnRequest: "ფასი მოთხოვნით",
      comingSoon: "ხელმისაწვდომობა მალე",
      demoLegal:
        "სადემონსტრაციო მონაცემები — არ არის ოფიციალური სანადირო კანონი.",
    },
  } satisfies StorefrontCopy,
} as const;

export const primaryNav = [
  { id: "hunting", href: "/catalog/hunting", labelKey: "hunting" as const },
  { id: "fishing", href: "/catalog/fishing", labelKey: "fishing" as const },
  { id: "camping", href: "/catalog/camping", labelKey: "camping" as const },
  { id: "clothing", href: "/catalog/clothing", labelKey: "clothing" as const },
  { id: "optics", href: "/catalog/optics", labelKey: "optics" as const },
  { id: "knives", href: "/catalog/knives", labelKey: "knives" as const },
  { id: "brands", href: "/catalog?view=brands", labelKey: "brands" as const },
  { id: "calendar", href: "/hunting-calendar", labelKey: "calendar" as const },
  { id: "map", href: "/map", labelKey: "map" as const },
  { id: "fieldGuide", href: "/field-guide", labelKey: "fieldGuide" as const },
];

export const categoryGateway: CategoryGatewayItem[] = [
  {
    id: "hunting",
    slug: "hunting",
    href: "/catalog/hunting",
    name: { en: "Hunting", ka: "ნადირობა" },
    label: { en: "Terrain-ready systems", ka: "რელიეფისთვის მზა სისტემები" },
    imageSrc: "/storefront/cat-hunting.svg",
    span: "wide",
  },
  {
    id: "fishing",
    slug: "fishing",
    href: "/catalog/fishing",
    name: { en: "Fishing", ka: "თევზაობა" },
    label: { en: "Rivers to Black Sea", ka: "მდინარეებიდან შავ ზღვამდე" },
    imageSrc: "/storefront/cat-fishing.svg",
    span: "tall",
  },
  {
    id: "camping",
    slug: "camping",
    href: "/catalog/camping",
    name: { en: "Camping", ka: "კემპინგი" },
    label: { en: "Shelter and heat", ka: "თავშესაფარი და სითბო" },
    imageSrc: "/storefront/cat-camping.svg",
  },
  {
    id: "clothing",
    slug: "clothing",
    href: "/catalog/clothing",
    name: { en: "Clothing", ka: "ტანსაცმელი" },
    label: { en: "Layering that works", ka: "შრეები, რომლებიც მუშაობს" },
    imageSrc: "/storefront/cat-clothing.svg",
  },
  {
    id: "optics",
    slug: "optics",
    href: "/catalog/optics",
    name: { en: "Optics", ka: "ოპტიკა" },
    label: { en: "Clarity at distance", ka: "სიცხადე დისტანციაზე" },
    imageSrc: "/storefront/cat-optics.svg",
  },
  {
    id: "knives",
    slug: "knives",
    href: "/catalog/knives",
    name: { en: "Knives & tools", ka: "დანები და ხელსაწყოები" },
    label: { en: "Edge and utility", ka: "პირი და პრაქტიკულობა" },
    imageSrc: "/storefront/cat-knives.svg",
  },
];

export const featuredProducts: ProductCardData[] = [
  {
    id: "1",
    slug: "alpine-hunting-jacket",
    brand: "Caucasus Field",
    name: {
      en: "Alpine Hunting Jacket",
      ka: "ალპური სანადირო ქურთუკი",
    },
    href: "/products/alpine-hunting-jacket",
    imageSrc: "/storefront/product-jacket.svg",
    imageAlt: {
      en: "Hunting jacket silhouette",
      ka: "სანადირო ქურთუკის სილუეტი",
    },
    badges: ["featured"],
    attributePreview: {
      en: "Forest green · M–XL",
      ka: "ტყის მწვანე · M–XL",
    },
    priceLabel: {
      en: "Price on request",
      ka: "ფასი მოთხოვნით",
    },
    availabilityLabel: {
      en: "Availability coming soon",
      ka: "ხელმისაწვდომობა მალე",
    },
  },
  {
    id: "2",
    slug: "ridge-optic-scope",
    brand: "Ridge Optics",
    name: { en: "Ridge 3–9× Scope", ka: "Ridge 3–9× სამიზნე" },
    href: "/products/ridge-optic-scope",
    imageSrc: "/storefront/product-optic.svg",
    imageAlt: { en: "Rifle scope silhouette", ka: "სამიზნის სილუეტი" },
    badges: ["new"],
    attributePreview: { en: "Duplex · Mil-Dot", ka: "Duplex · Mil-Dot" },
    priceLabel: { en: "Price on request", ka: "ფასი მოთხოვნით" },
    availabilityLabel: {
      en: "Availability coming soon",
      ka: "ხელმისაწვდომობა მალე",
    },
  },
  {
    id: "3",
    slug: "river-braided-line",
    brand: "Blackwater",
    name: { en: "River Braided Line", ka: "მდინარის წნული ძაფი" },
    href: "/products/river-braided-line",
    imageSrc: "/storefront/product-line.svg",
    imageAlt: { en: "Fishing line spool", ka: "სათევზაო ძაფის კოჭა" },
    attributePreview: {
      en: "100–300 m · 10–20 lb",
      ka: "100–300 მ · 10–20 lb",
    },
    priceLabel: { en: "Price on request", ka: "ფასი მოთხოვნით" },
  },
  {
    id: "4",
    slug: "fixed-blade-knife",
    brand: "Tetri",
    name: { en: "Fixed Blade Field Knife", ka: "ფიქსირებული პირის დანა" },
    href: "/products/fixed-blade-knife",
    imageSrc: "/storefront/product-knife.svg",
    imageAlt: { en: "Fixed blade knife", ka: "ფიქსირებული პირის დანა" },
    attributePreview: { en: "Full tang · Sheath", ka: "Full tang · ქარქაში" },
    priceLabel: { en: "Price on request", ka: "ფასი მოთხოვნით" },
  },
];

export const seasonDemo: SeasonDemoRow[] = [
  {
    id: "s1",
    species: { en: "Roe deer", ka: "შველი" },
    region: { en: "Kakheti foothills", ka: "კახეთის მთისწინეთი" },
    month: { en: "September", ka: "სექტემბერი" },
    status: "conditional",
    limit: { en: "Limit pending verification", ka: "ლიმიტი დასადასტურებელია" },
  },
  {
    id: "s2",
    species: { en: "Wild boar", ka: "გარეული ღორი" },
    region: { en: "Samegrelo forests", ka: "სამეგრელოს ტყეები" },
    month: { en: "October", ka: "ოქტომბერი" },
    status: "open",
    limit: {
      en: "Sample limit — verify officially",
      ka: "სანიმუშო ლიმიტი — გადაამოწმეთ",
    },
  },
  {
    id: "s3",
    species: { en: "Chamois", ka: "ჯიხვი" },
    region: { en: "Svaneti ridges", ka: "სვანეთის ქედები" },
    month: { en: "November", ka: "ნოემბერი" },
    status: "closed",
    limit: { en: "Closed in demo dataset", ka: "დახურულია დემო მონაცემებში" },
  },
];

export const fieldGuides: FieldGuideCardData[] = [
  {
    id: "g1",
    slug: "layering-for-wet-forests",
    href: "/field-guide/layering-for-wet-forests",
    category: { en: "Clothing", ka: "ტანსაცმელი" },
    title: {
      en: "Layering for wet Caucasus forests",
      ka: "შრეები სველი კავკასიური ტყისთვის",
    },
    excerpt: {
      en: "Breathability, shell timing, and pack weight when the rain never fully stops.",
      ka: "სუნთქვა, გარე ფენის დრო და ზურგჩანთის წონა, როცა წვიმა არ წყდება.",
    },
    imageSrc: "/storefront/guide-layering.svg",
  },
  {
    id: "g2",
    slug: "optic-care-in-dust",
    href: "/field-guide/optic-care-in-dust",
    category: { en: "Optics", ka: "ოპტიკა" },
    title: {
      en: "Optic care in dry dust and fog",
      ka: "ოპტიკის მოვლა მტვერსა და ნისლში",
    },
    excerpt: {
      en: "Lens discipline that keeps glass usable after ridge winds.",
      ka: "ლინზების დისციპლინა ქედის ქარის შემდეგ.",
    },
    imageSrc: "/storefront/guide-optics.svg",
  },
  {
    id: "g3",
    slug: "river-access-ethics",
    href: "/field-guide/river-access-ethics",
    category: { en: "Fishing", ka: "თევზაობა" },
    title: {
      en: "River access and bank ethics",
      ka: "მდინარეზე წვდომა და ეთიკა",
    },
    excerpt: {
      en: "Leave banks intact, read flow, and pack out every filament.",
      ka: "ნაპირი უვნებელი დატოვე და ყველა ძაფი წაიღე.",
    },
    imageSrc: "/storefront/guide-river.svg",
  },
];

export const brandShowcase: BrandShowcaseItem[] = [
  {
    id: "b1",
    name: "Ridge Optics",
    focus: { en: "Glass for distance", ka: "მინა დისტანციისთვის" },
    href: "/catalog?brand=ridge-optics",
  },
  {
    id: "b2",
    name: "Blackwater",
    focus: { en: "Lines and leaders", ka: "ძაფები და ლიდერები" },
    href: "/catalog?brand=blackwater",
  },
  {
    id: "b3",
    name: "Tetri",
    focus: { en: "Field knives", ka: "საველე დანები" },
    href: "/catalog?brand=tetri",
  },
];

export const productDetails: Record<string, ProductDetailFixture> = {
  "alpine-hunting-jacket": {
    ...featuredProducts[0]!,
    modelNumber: "CF-AJ-01",
    sku: "PRD-DEMO-001",
    shortDescription: {
      en: "Quiet shell for wet forests and windy ridges.",
      ka: "ჩუმი გარსი სველი ტყისა და ქარიანი ქედებისთვის.",
    },
    description: {
      en: "Built as a presentation fixture for variant selection and media layout. Pricing and stock connect in later platform days.",
      ka: "ვიზუალური ფიქსტურა ვარიანტებისა და მედიის განლაგებისთვის. ფასი და მარაგი მოგვიანებით დაემატება.",
    },
    gallery: [
      "/storefront/product-jacket.svg",
      "/storefront/product-jacket-b.svg",
      "/storefront/product-jacket-c.svg",
    ],
    variants: {
      axes: [
        {
          id: "color",
          code: "color",
          name: { en: "Color", ka: "ფერი" },
          type: "color",
          values: [
            {
              id: "forest",
              code: "forest_green",
              name: { en: "Forest green", ka: "ტყის მწვანე" },
              colorHex: "#315B3A",
            },
            {
              id: "black",
              code: "black",
              name: { en: "Black", ka: "შავი" },
              colorHex: "#1A1C1A",
            },
          ],
        },
        {
          id: "size",
          code: "size",
          name: { en: "Size", ka: "ზომა" },
          type: "select",
          values: [
            { id: "m", code: "m", name: { en: "M", ka: "M" } },
            { id: "l", code: "l", name: { en: "L", ka: "L" } },
            {
              id: "xl",
              code: "xl",
              name: { en: "XL", ka: "XL" },
              disabled: true,
            },
          ],
        },
      ],
    },
    specs: [
      {
        label: { en: "Weight class", ka: "წონის კლასი" },
        value: { en: "Mid-layer shell", ka: "შუა ფენის გარსი" },
      },
      {
        label: { en: "Primary use", ka: "ძირითადი გამოყენება" },
        value: {
          en: "Still hunting / glassing",
          ka: "ნელა ნადირობა / დაკვირვება",
        },
      },
    ],
    contexts: [
      { en: "Mountain hunting", ka: "მთის ნადირობა" },
      { en: "Wet conditions", ka: "სველი პირობები" },
      { en: "Autumn season", ka: "შემოდგომის სეზონი" },
    ],
  },
};

export const searchSuggestions = {
  en: {
    products: ["Alpine Hunting Jacket", "Ridge 3–9× Scope"],
    categories: ["Optics", "Hunting"],
    guides: ["Layering for wet Caucasus forests"],
  },
  ka: {
    products: ["ალპური სანადირო ქურთუკი", "Ridge 3–9× სამიზნე"],
    categories: ["ოპტიკა", "ნადირობა"],
    guides: ["შრეები სველი კავკასიური ტყისთვის"],
  },
};
