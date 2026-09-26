import type { RecommendationLocale } from "@/features/recommendations/types/recommendation-types";

export type RecommendationCopy = {
  title: string;
  speciesTitle: string;
  loading: string;
  empty: string;
  error: string;
  stale: string;
  why: string;
  hideWhy: string;
  quickView: string;
  close: string;
  add: string;
  adding: string;
  added: string;
  cartError: string;
  chooseVariant: string;
  variants: string;
  confidence: string;
  confidenceHigh: string;
  confidenceMedium: string;
  confidenceLow: string;
  confidenceInsufficient: string;
  requiredBadge: string;
  promoted: string;
  conditional: string;
  blocked: string;
  conflict: string;
  unknown: string;
  speciesRelated: string;
  generalCatalog: string;
  planner: string;
  previous: string;
  next: string;
  page: string;
  stock: Record<string, string>;
};

const copy: Record<RecommendationLocale, RecommendationCopy> = {
  ka: {
    title: "ამ კონტექსტისთვის შერჩეული აღჭურვილობა",
    speciesTitle: "სახეობასთან დაკავშირებული აღჭურვილობა",
    loading: "შერჩეული აღჭურვილობა იტვირთება",
    empty: "ამ კონტექსტისთვის გადამოწმებული პროდუქტი ვერ მოიძებნა.",
    error: "რეკომენდაციები ვერ ჩაიტვირთა. სცადეთ თავიდან.",
    stale: "კონტექსტი შეიცვალა. ეს შერჩევა აღარ არის მიმდინარე.",
    why: "რატომ ეს პროდუქტი?",
    hideWhy: "ახსნა-განმარტების დახურვა",
    quickView: "სწრაფი ნახვა",
    close: "დახურვა",
    add: "კალათაში დამატება",
    adding: "ემატება",
    added: "დაემატა კალათას",
    cartError:
      "კალათამ ვერ დაადასტურა ფასი ან მარაგი. სცადეთ პროდუქტის გვერდიდან.",
    chooseVariant: "ვარიანტი აირჩიეთ პროდუქტის გვერდზე.",
    variants: "შესაფერისი ვარიანტები",
    confidence: "შესაბამისობის დარწმუნებულობა",
    confidenceHigh: "მაღალი",
    confidenceMedium: "საშუალო",
    confidenceLow: "დაბალი",
    confidenceInsufficient: "არასაკმარისი",
    requiredBadge: "მოთხოვნილი აღჭურვილობის კატეგორია",
    promoted: "რეკლამირებული",
    conditional:
      "ეს შეთავაზებები საინფორმაციოა. გაგრძელებამდე გადაამოწმეთ ჩამოთვლილი მოთხოვნები.",
    blocked: "ამ კონტექსტში აქტივობის აღჭურვილობა არ ჩანს.",
    conflict:
      "სამართლებრივი კონტექსტი დაზუსტებას საჭიროებს. კონტექსტური რეკომენდაციები დამალულია.",
    unknown:
      "მდებარეობა არ არის დადასტურებული. ნაჩვენებია მხოლოდ ზოგადი კატალოგი, არა ამ ადგილისთვის დამტკიცებული პროდუქტები.",
    speciesRelated:
      "ეს არის სახეობასთან დაკავშირებული აღჭურვილობა. მდებარეობა არ არის გადამოწმებული.",
    generalCatalog: "ზოგადი კატალოგი",
    planner: "უფრო ზუსტი შერჩევისთვის გახსენით რუკა",
    previous: "წინა",
    next: "შემდეგი",
    page: "გვერდი",
    stock: {
      in_stock: "მარაგშია",
      low_stock: "მარაგი იწურება",
      out_of_stock: "მარაგი ამოწურულია",
    },
  },
  en: {
    title: "Equipment matched to this context",
    speciesTitle: "Species-related gear",
    loading: "Loading gear suggestions",
    empty: "No verified products match this context.",
    error: "Recommendations could not be loaded. Try again.",
    stale: "The context changed. These suggestions are no longer current.",
    why: "Why this product?",
    hideWhy: "Hide explanation",
    quickView: "Quick view",
    close: "Close",
    add: "Add to cart",
    adding: "Adding",
    added: "Added to cart",
    cartError:
      "The cart could not confirm price or stock. Try the product page.",
    chooseVariant: "Choose a variant on the product page.",
    variants: "Eligible variants",
    confidence: "Match confidence",
    confidenceHigh: "High",
    confidenceMedium: "Medium",
    confidenceLow: "Low",
    confidenceInsufficient: "Insufficient",
    requiredBadge: "Required equipment category",
    promoted: "Promoted",
    conditional:
      "These suggestions are informational. Confirm the listed requirements before proceeding.",
    blocked: "Activity equipment is not shown for this context.",
    conflict:
      "The legal context needs clarification. Contextual recommendations are hidden.",
    unknown:
      "Location is not verified. Only general catalog discovery is available, not products approved for this place.",
    speciesRelated:
      "These are species-related gear suggestions. Location is not verified.",
    generalCatalog: "General catalog",
    planner: "Open the map for more accurate suggestions",
    previous: "Previous",
    next: "Next",
    page: "Page",
    stock: {
      in_stock: "In stock",
      low_stock: "Low stock",
      out_of_stock: "Out of stock",
    },
  },
};

export function recommendationCopy(
  locale: RecommendationLocale,
): RecommendationCopy {
  return copy[locale];
}
