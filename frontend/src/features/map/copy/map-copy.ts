import type { Locale } from "@/i18n/dictionaries";

export type MapCopy = {
  metaTitle: string;
  metaDescription: string;
  title: string;
  summary: string;
  disclaimer: string;
  loading: string;
  mapFailed: string;
  basemapMissing: string;
  overlayError: string;
  overlayHidden: string;
  overlayIncomplete: string;
  overlayEmpty: string;
  zoomCloser: string;
  retry: string;
  stale: string;
  offline: string;
  activity: string;
  hunting: string;
  fishing: string;
  date: string;
  from: string;
  to: string;
  presets: {
    today: string;
    week: string;
    next7: string;
    month: string;
    custom: string;
  };
  modes: { date: string; range: string; any: string; timeline: string };
  modeLabel: string;
  huntingObject: string;
  speciesChoose: string;
  seasonScopeMissing: string;
  species: string;
  speciesSearch: string;
  speciesEmpty: string;
  speciesClear: string;
  layers: string;
  showAll: string;
  hideOptional: string;
  layerNames: Record<string, string>;
  layerHelp: Record<string, string>;
  legend: string;
  legendCategories: string;
  legendOutcomes: string;
  protectedCategories: Record<string, string>;
  explain: Record<string, string>;
  viewSpecies: string;
  viewSeason: string;
  outcomes: Record<string, string>;
  boundary: string;
  selectedPoint: string;
  userLocation: string;
  freshness: string;
  checkLocation: string;
  useLocation: string;
  locationWhy: string;
  locationDenied: string;
  locationUnavailable: string;
  locationTimeout: string;
  locationLowAccuracy: string;
  accuracy: string;
  longitude: string;
  latitude: string;
  copyCoordinates: string;
  copied: string;
  zonesAtPoint: string;
  noZoneMatch: string;
  notAllowed: string;
  why: string;
  conditions: string;
  limits: string;
  permits: string;
  licenses: string;
  methods: string;
  equipment: string;
  citations: string;
  source: string;
  verified: string;
  openSource: string;
  season: string;
  seasonNeedsSpecies: string;
  seasonForDate: string;
  seasonNotThisPoint: string;
  onlyIn: string;
  excluding: string;
  dailyLimit: string;
  seasonSkipped: string;
  zoneDetail: string;
  zoomTo: string;
  share: string;
  sharePoint: string;
  sharePointNotice: string;
  shareConfirm: string;
  shareCancel: string;
  shareFailed: string;
  list: string;
  map: string;
  split: string;
  results: string;
  emptyList: string;
  search: string;
  searchHint: string;
  noSearch: string;
  addressUnavailable: string;
  close: string;
  filters: string;
  resetFilters: string;
  invalidLink: string;
  removedZone: string;
  viewZone: string;
  overlaysInView: string;
  checkCoordinates: string;
  legalTime: string;
};

const layerNamesEn = {
  protected: "Protected areas",
  hunting: "Hunting restrictions",
  fishing: "Fishing restrictions",
  special: "Special regulation zones",
  wildlife: "Wildlife-management zones",
  admin: "Administrative boundaries",
};

const layerNamesKa = {
  protected: "დაცული ტერიტორიები",
  hunting: "ნადირობის შეზღუდვები",
  fishing: "თევზაობის შეზღუდვები",
  special: "სპეციალური რეგულირების ზონები",
  wildlife: "ველური ბუნების მართვის ზონები",
  admin: "ადმინისტრაციული საზღვრები",
};

const outcomesEn = {
  allowed: "Allowed",
  prohibited: "Prohibited",
  conditional: "Conditional",
  unknown: "Unknown",
  conflict: "Conflict",
};

const outcomesKa = {
  allowed: "დაშვებული",
  prohibited: "აკრძალული",
  conditional: "პირობითი",
  unknown: "უცნობი",
  conflict: "კონფლიქტი",
};

export const mapCopy: Record<Locale, MapCopy> = {
  en: {
    metaTitle: "Legal map",
    metaDescription:
      "Explore published protected areas and hunting or fishing restrictions in Georgia. Missing data is unknown. This is not legal advice.",
    title: "Legal map",
    summary:
      "Published protected areas and restrictions. An empty map is not permission to hunt or fish.",
    disclaimer:
      "Informational only — not legal advice. Absence of a polygon does not mean an activity is allowed. Official sources prevail.",
    loading: "Loading the map…",
    mapFailed: "The map could not be started. Use the list to read published zones.",
    basemapMissing:
      "A production basemap style URL is not configured. Legal overlays stay available in the list.",
    overlayError:
      "Legal overlays could not be loaded. This does not mean the area is unrestricted.",
    overlayHidden: "Legal overlays hidden.",
    overlayIncomplete:
      "This view has more zones than the map loaded. Zoom in before relying on the picture.",
    overlayEmpty:
      "No published zones were returned for this view. That is not a finding of no restrictions.",
    zoomCloser: "Zoom in to load legal overlays for this area.",
    retry: "Retry overlays",
    stale: "Showing previously loaded geometry. It is not a current legal verification.",
    offline: "Live legal verification is unavailable while offline.",
    activity: "Activity",
    hunting: "Hunting",
    fishing: "Fishing",
    date: "Date",
    from: "From",
    to: "To",
    presets: {
      today: "Today",
      week: "This week",
      next7: "Next 7 days",
      month: "This month",
      custom: "Custom period",
    },
    modes: {
      date: "Single date",
      range: "Date range",
      any: "Any date",
      timeline: "Timeline",
    },
    modeLabel: "Mode",
    huntingObject: "Hunting species",
    speciesChoose: "Choose a species",
    seasonScopeMissing:
      "Green is the municipality named in the published season for this date. Red nature reserves and national parks stay prohibited. The surrounding buffer and a separate city limit are not drawn.",
    species: "Species",
    speciesSearch: "Search species",
    speciesEmpty: "No published species matched.",
    speciesClear: "Clear species",
    layers: "Layers",
    showAll: "Show all",
    hideOptional: "Hide optional layers",
    layerNames: layerNamesEn,
    layerHelp: {
      protected: "Protected and reserve categories",
      hunting: "Published hunting restriction polygons",
      fishing: "Published fishing restriction polygons",
      special: "Special legal-management zones",
      wildlife: "Wildlife-management areas",
      admin: "Regions and municipalities. Optional context, not a permission.",
    },
    legend: "Legend",
    legendCategories: "Zone categories",
    legendOutcomes: "Legal outcomes",
    protectedCategories: {
      national_park: "National park",
      strict_nature_reserve: "Strict nature reserve",
      managed_reserve: "Managed reserve",
      natural_monument: "Natural monument",
      protected_area: "Protected landscape",
    },
    explain: {
      allowed: "Published rules support this activity for the selected evidence. Read the citations before acting.",
      prohibited: "A published prohibition applies. The source is listed below.",
      conditional: "Published rules allow this only when the conditions below are met.",
      unknown: "Verified information is not sufficient. Unknown is not closed and it is not open.",
      conflict: "Published sources or rules conflict. No definitive conclusion is shown.",
    },
    viewSpecies: "View species",
    viewSeason: "View season",
    outcomes: outcomesEn,
    boundary: "Boundary uncertainty",
    selectedPoint: "Selected point",
    userLocation: "Your location",
    freshness: "Data as of",
    checkLocation: "Check this location",
    useLocation: "Use my location",
    locationWhy:
      "Location is used once to evaluate this point. It is not saved, tracked, or sent to analytics.",
    locationDenied: "Location permission was denied.",
    locationUnavailable: "Location is unavailable on this device.",
    locationTimeout: "Location request timed out.",
    locationLowAccuracy:
      "The reported accuracy is too wide for an exact legal status. Read the official sources.",
    accuracy: "Reported accuracy",
    longitude: "Longitude",
    latitude: "Latitude",
    copyCoordinates: "Copy coordinates",
    copied: "Copied",
    zonesAtPoint: "Zones containing this point",
    noZoneMatch:
      "No published zone contains this point. The result stays unknown unless a source explicitly allows the activity.",
    notAllowed: "This is not a statement that hunting or fishing is allowed.",
    why: "Why this result?",
    conditions: "Conditions",
    limits: "Limits",
    permits: "Permit requirements",
    licenses: "License requirements",
    methods: "Method restrictions",
    equipment: "Equipment restrictions",
    citations: "Citations",
    source: "Official source",
    verified: "Last verified",
    openSource: "Open official source",
    season: "Season",
    seasonNeedsSpecies: "Select a species to evaluate the season.",
    seasonForDate: "Published seasons for this date",
    seasonNotThisPoint:
      "These seasons are not a permission for the selected point. A protected-area boundary is not a hunting permission, and municipality boundaries are not loaded.",
    onlyIn: "Only in",
    excluding: "Excluding",
    dailyLimit: "Daily limit",
    seasonSkipped: "Any-date mode does not apply a season conclusion.",
    zoneDetail: "Zone details",
    zoomTo: "Zoom to zone",
    share: "Copy link",
    sharePoint: "Share this point",
    sharePointNotice: "The link will include the selected coordinates.",
    shareConfirm: "Copy point link",
    shareCancel: "Cancel",
    shareFailed: "The link could not be copied. Select it and copy it manually.",
    list: "List",
    map: "Map",
    split: "Split",
    results: "Zones in view",
    emptyList: "No published zones are loaded for this view.",
    search: "Search zones or species",
    searchHint: "Search uses published platform records, not street addresses.",
    noSearch: "No matching published records.",
    addressUnavailable: "Address search is not available.",
    close: "Close",
    filters: "Filters",
    resetFilters: "Reset",
    invalidLink: "Some link values were ignored because they are not supported.",
    removedZone: "That zone is not in the current published data.",
    viewZone: "View zone",
    overlaysInView: "loaded in this view",
    checkCoordinates: "Check coordinates",
    legalTime: "Legal dates use Asia/Tbilisi.",
  },
  ka: {
    metaTitle: "სამართლებრივი რუკა",
    metaDescription:
      "გამოქვეყნებული დაცული ტერიტორიები და ნადირობის ან თევზაობის შეზღუდვები. დაუდასტურებელი მონაცემი უცნობია. ეს არ არის იურიდიული კონსულტაცია.",
    title: "სამართლებრივი რუკა",
    summary:
      "გამოქვეყნებული დაცული ტერიტორიები და შეზღუდვები. ცარიელი რუკა ნადირობის ან თევზაობის ნებართვა არ არის.",
    disclaimer:
      "მხოლოდ საინფორმაციოა და არ არის იურიდიული კონსულტაცია. პოლიგონის არარსებობა არ ნიშნავს, რომ ქმედება დაშვებულია. ოფიციალური წყარო უპირატესია.",
    loading: "რუკა იტვირთება…",
    mapFailed: "რუკა ვერ გაეშვა. გამოქვეყნებული ზონები იხილეთ სიით.",
    basemapMissing:
      "საწარმოო რუკის სტილი არ არის მითითებული. სამართლებრივი ჩანაწერები სიაში რჩება.",
    overlayError:
      "სამართლებრივი შრეები ვერ ჩაიტვირთა. ეს არ ნიშნავს, რომ ტერიტორია შეუზღუდავია.",
    overlayHidden: "სამართლებრივი შრეები დამალულია.",
    overlayIncomplete:
      "ამ ხედში უფრო მეტი ზონაა, ვიდრე რუკამ აჩვენა. სანდო სურათისთვის მიუახლოვდით.",
    overlayEmpty:
      "ამ ხედში გამოქვეყნებული ზონა არ დაბრუნდა. ეს შეზღუდვის არარსებობა არ არის.",
    zoomCloser: "სამართლებრივი შრეების სანახავად მიუახლოვდით.",
    retry: "შრეების თავიდან ცდა",
    stale: "ნაჩვენებია ადრე ჩატვირთული გეომეტრია. ეს მიმდინარე სამართლებრივი შემოწმება არ არის.",
    offline: "ქსელის გარეშე ცოცხალი სამართლებრივი შემოწმება მიუწვდომელია.",
    activity: "ქმედება",
    hunting: "ნადირობა",
    fishing: "თევზაობა",
    date: "თარიღი",
    from: "დან",
    to: "მდე",
    presets: {
      today: "დღეს",
      week: "ეს კვირა",
      next7: "შემდეგი 7 დღე",
      month: "ეს თვე",
      custom: "საკუთარი პერიოდი",
    },
    modes: {
      date: "ერთი თარიღი",
      range: "პერიოდი",
      any: "ნებისმიერი თარიღი",
      timeline: "ქრონოლოგია",
    },
    modeLabel: "რეჟიმი",
    huntingObject: "სანადირო ობიექტი",
    speciesChoose: "აირჩიეთ სახეობა",
    seasonScopeMissing:
      "მწვანე ამ თარიღის გამოქვეყნებული სეზონის მუნიციპალიტეტია. წითელი ნაკრძალი და ეროვნული პარკი აკრძალული რჩება. გარშემო ზოლი და ქალაქის ცალკე საზღვარი არ იხატება.",
    species: "სახეობა",
    speciesSearch: "სახეობის ძიება",
    speciesEmpty: "გამოქვეყნებული სახეობა არ მოიძებნა.",
    speciesClear: "სახეობის გასუფთავება",
    layers: "შრეები",
    showAll: "ყველას ჩვენება",
    hideOptional: "არასავალდებულო შრეების დამალვა",
    layerNames: layerNamesKa,
    layerHelp: {
      protected: "დაცული და ნაკრძალის კატეგორიები",
      hunting: "გამოქვეყნებული ნადირობის შეზღუდვის პოლიგონები",
      fishing: "გამოქვეყნებული თევზაობის შეზღუდვის პოლიგონები",
      special: "სპეციალური სამართლებრივი ზონები",
      wildlife: "ველური ბუნების მართვის ტერიტორიები",
      admin: "რეგიონები და მუნიციპალიტეტები. კონტექსტია, არა ნებართვა.",
    },
    legend: "ლეგენდა",
    legendCategories: "ზონის კატეგორიები",
    legendOutcomes: "სამართლებრივი შედეგები",
    protectedCategories: {
      national_park: "ეროვნული პარკი",
      strict_nature_reserve: "ნაკრძალი",
      managed_reserve: "მართვადი ნაკრძალი",
      natural_monument: "ბუნების ძეგლი",
      protected_area: "დაცული ლანდშაფტი",
    },
    explain: {
      allowed: "გამოქვეყნებული წესები ამ მტკიცებულებით ქმედებას უჭერს მხარს. მოქმედებამდე წაიკითხეთ ციტატები.",
      prohibited: "გამოქვეყნებული აკრძალვა მოქმედებს. წყარო ქვემოთაა.",
      conditional: "გამოქვეყნებული წესები ამას მხოლოდ ქვემოთ ჩამოთვლილი პირობებით უშვებს.",
      unknown: "დადასტურებული ინფორმაცია საკმარისი არ არის. უცნობი არც დახურვაა და არც გახსნა.",
      conflict: "გამოქვეყნებული წყაროები ან წესები ეწინააღმდეგება ერთმანეთს. საბოლოო დასკვნა არ არის წარმოდგენილი.",
    },
    viewSpecies: "სახეობის ნახვა",
    viewSeason: "სეზონის ნახვა",
    outcomes: outcomesKa,
    boundary: "საზღვრის გაურკვევლობა",
    selectedPoint: "არჩეული წერტილი",
    userLocation: "თქვენი მდებარეობა",
    freshness: "მონაცემი",
    checkLocation: "ამ წერტილის შემოწმება",
    useLocation: "ჩემი მდებარეობა",
    locationWhy:
      "მდებარეობა ერთხელ გამოიყენება ამ წერტილის შესაფასებლად. ის არ ინახება, არ იკვეთება და ანალიტიკაში არ იგზავნება.",
    locationDenied: "მდებარეობის ნებართვა უარყოფილია.",
    locationUnavailable: "მდებარეობა ამ მოწყობილობაზე მიუწვდომელია.",
    locationTimeout: "მდებარეობის მოთხოვნას ვადა გაუვიდა.",
    locationLowAccuracy:
      "სიზუსტის რადიუსი ზედმეტად ფართოა ზუსტი სამართლებრივი სტატუსისთვის. იხილეთ ოფიციალური წყაროები.",
    accuracy: "მითითებული სიზუსტე",
    longitude: "გრძედი",
    latitude: "განედი",
    copyCoordinates: "კოორდინატების კოპირება",
    copied: "დაკოპირდა",
    zonesAtPoint: "ზონები, რომლებიც ამ წერტილს მოიცავს",
    noZoneMatch:
      "გამოქვეყნებული ზონა ამ წერტილს არ მოიცავს. შედეგი უცნობი რჩება, სანამ წყარო პირდაპირ არ დაუშვებს ქმედებას.",
    notAllowed: "ეს არ ნიშნავს, რომ ნადირობა ან თევზაობა დაშვებულია.",
    why: "რატომ ეს შედეგი?",
    conditions: "პირობები",
    limits: "ლიმიტები",
    permits: "ნებართვის მოთხოვნები",
    licenses: "ლიცენზიის მოთხოვნები",
    methods: "მეთოდის შეზღუდვები",
    equipment: "აღჭურვილობის შეზღუდვები",
    citations: "ციტატები",
    source: "ოფიციალური წყარო",
    verified: "ბოლო გადამოწმება",
    openSource: "ოფიციალური წყაროს გახსნა",
    season: "სეზონი",
    seasonNeedsSpecies: "სეზონის შესაფასებლად აირჩიეთ სახეობა.",
    seasonForDate: "ამ თარიღის გამოქვეყნებული სეზონები",
    seasonNotThisPoint:
      "ეს სეზონები არჩეულ წერტილზე ნებართვა არ არის. დაცული ტერიტორიის საზღვარი ნადირობის ნებართვა არ არის. მუნიციპალიტეტის საზღვრები ჩატვირთული არ არის.",
    onlyIn: "მხოლოდ",
    excluding: "გარდა",
    dailyLimit: "დღიური ლიმიტი",
    seasonSkipped: "ნებისმიერი თარიღის რეჟიმი სეზონის დასკვნას არ იყენებს.",
    zoneDetail: "ზონის დეტალები",
    zoomTo: "ზონაზე მიახლოება",
    share: "ბმულის კოპირება",
    sharePoint: "ამ წერტილის გაზიარება",
    sharePointNotice: "ბმული შეიცავს არჩეულ კოორდინატებს.",
    shareConfirm: "წერტილის ბმულის კოპირება",
    shareCancel: "გაუქმება",
    shareFailed: "ბმული ვერ დაკოპირდა. მონიშნეთ და დააკოპირეთ ხელით.",
    list: "სია",
    map: "რუკა",
    split: "გაყოფილი",
    results: "ზონები ხედში",
    emptyList: "ამ ხედში გამოქვეყნებული ზონა არ არის ჩატვირთული.",
    search: "ზონის ან სახეობის ძიება",
    searchHint: "ძიება იყენებს გამოქვეყნებულ ჩანაწერებს, არა ქუჩის მისამართებს.",
    noSearch: "შესაბამისი გამოქვეყნებული ჩანაწერი არ არის.",
    addressUnavailable: "მისამართის ძიება არ არის ხელმისაწვდომი.",
    close: "დახურვა",
    filters: "ფილტრები",
    resetFilters: "განულება",
    invalidLink: "ბმულის ზოგიერთი მნიშვნელობა გამოტოვდა, რადგან მხარდაჭერილი არ არის.",
    removedZone: "ეს ზონა მიმდინარე გამოქვეყნებულ მონაცემებში არ არის.",
    viewZone: "ზონის ნახვა",
    overlaysInView: "ჩატვირთულია ამ ხედში",
    checkCoordinates: "კოორდინატების შემოწმება",
    legalTime: "სამართლებრივი თარიღები იყენებს Asia/Tbilisi-ს.",
  },
};

export function getMapCopy(locale: Locale): MapCopy {
  return mapCopy[locale];
}
