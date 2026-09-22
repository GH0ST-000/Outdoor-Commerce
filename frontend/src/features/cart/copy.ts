import { useLocale } from "@/components/locale-provider";
import type { Locale } from "@/i18n/dictionaries";
import type { CartIssueCode } from "@/features/cart/types";

export type CartCopy = {
  title: string;
  open: string;
  close: string;
  badge: string;
  items: string;
  emptyTitle: string;
  emptyHint: string;
  viewCart: string;
  checkout: string;
  checkoutSoon: string;
  continueShopping: string;
  remove: string;
  clear: string;
  subtotal: string;
  savings: string;
  notice: string;
  quantity: string;
  quantityDecrease: string;
  quantityIncrease: string;
  added: string;
  adding: string;
  sku: string;
  lineTotal: string;
  unitPrice: string;
  chooseOptions: string;
  addToCart: string;
  loading: string;
  loadError: string;
  retry: string;
  versionConflict: string;
  mergeAdjusted: string;
  shopCatalog: string;
  shopHome: string;
  search: string;
  issues: Record<CartIssueCode, string>;
};

export const cartCopy: Record<Locale, CartCopy> = {
  en: {
    title: "Your cart",
    open: "Open cart",
    close: "Close cart",
    badge: "Items in cart",
    items: "items",
    emptyTitle: "Your cart is empty",
    emptyHint: "Browse the catalog or search for field gear.",
    viewCart: "View cart",
    checkout: "Checkout",
    checkoutSoon:
      "Totals here are not a final payable amount. Checkout confirms price, stock, and delivery.",
    continueShopping: "Continue shopping",
    remove: "Remove",
    clear: "Clear cart",
    subtotal: "Subtotal",
    savings: "Savings",
    notice: "Shipping and final availability are calculated during checkout.",
    quantity: "Quantity",
    quantityDecrease: "Decrease quantity",
    quantityIncrease: "Increase quantity",
    added: "Added to cart",
    adding: "Adding to cart",
    sku: "SKU",
    lineTotal: "Line total",
    unitPrice: "Unit price",
    chooseOptions: "Choose options",
    addToCart: "Add to cart",
    loading: "Loading cart",
    loadError: "The cart could not be loaded. Try again.",
    retry: "Retry",
    versionConflict:
      "Your cart was updated in another tab. The latest items are shown.",
    mergeAdjusted:
      "Some quantities were adjusted after sign-in to match current availability.",
    shopCatalog: "Browse catalog",
    shopHome: "Go to homepage",
    search: "Search",
    issues: {
      PRODUCT_UNAVAILABLE: "This product is no longer available.",
      VARIANT_UNAVAILABLE: "This option is no longer available.",
      INSUFFICIENT_STOCK:
        "Requested quantity is higher than current availability.",
      QUANTITY_LIMIT: "This quantity exceeds the allowed limit.",
      QUANTITY_ADJUSTED_ON_MERGE:
        "Some quantities were adjusted to match current availability and limits.",
      PRICE_CHANGED:
        "The current price is different from when this item was added.",
      PRICE_INCREASED: "The price of this item has increased.",
      PRICE_DECREASED: "The price of this item has decreased.",
      PROMOTION_ENDED: "A promotion on this item is no longer available.",
      PROMOTION_APPLIED: "A promotion now applies to this item.",
      CART_EXPIRED: "This cart has expired.",
    },
  },
  ka: {
    title: "კალათა",
    open: "კალათის გახსნა",
    close: "კალათის დახურვა",
    badge: "პროდუქტები კალათაში",
    items: "ცალი",
    emptyTitle: "კალათა ცარიელია",
    emptyHint: "ნახე კატალოგი ან მოძებნე საველე აღჭურვილობა.",
    viewCart: "კალათის ნახვა",
    checkout: "შეკვეთა",
    checkoutSoon:
      "აქ ნაჩვენები ჯამი საბოლოო გადასახდელი არ არის. ფასი, მარაგი და მიწოდება შეკვეთისას დასტურდება.",
    continueShopping: "შოპინგის გაგრძელება",
    remove: "წაშლა",
    clear: "კალათის გასუფთავება",
    subtotal: "ქვეჯამი",
    savings: "დაზოგვა",
    notice: "მიწოდება და საბოლოო ხელმისაწვდომობა ითვლება შეკვეთისას.",
    quantity: "რაოდენობა",
    quantityDecrease: "რაოდენობის შემცირება",
    quantityIncrease: "რაოდენობის გაზრდა",
    added: "დაემატა კალათაში",
    adding: "კალათაში ემატება",
    sku: "SKU",
    lineTotal: "ხაზის ჯამი",
    unitPrice: "ერთეულის ფასი",
    chooseOptions: "აირჩიე პარამეტრები",
    addToCart: "კალათაში დამატება",
    loading: "კალათა იტვირთება",
    loadError: "კალათა ვერ ჩაიტვირთა. სცადე თავიდან.",
    retry: "გამეორება",
    versionConflict:
      "კალათა სხვა ჩანართში განახლდა. ნაჩვენებია ბოლო მდგომარეობა.",
    mergeAdjusted: "შესვლის შემდეგ ზოგი რაოდენობა ხელმისაწვდომობას მოერგო.",
    shopCatalog: "კატალოგის ნახვა",
    shopHome: "მთავარზე გადასვლა",
    search: "ძიება",
    issues: {
      PRODUCT_UNAVAILABLE: "ეს პროდუქტი აღარ არის ხელმისაწვდომი.",
      VARIANT_UNAVAILABLE: "ეს ვარიანტი აღარ არის ხელმისაწვდომი.",
      INSUFFICIENT_STOCK: "მოთხოვნილი რაოდენობა მარაგს აღემატება.",
      QUANTITY_LIMIT: "ეს რაოდენობა დასაშვებ ზღვარს აჭარბებს.",
      QUANTITY_ADJUSTED_ON_MERGE:
        "ზოგი რაოდენობა ხელმისაწვდომობასა და ლიმიტებს მოერგო.",
      PRICE_CHANGED:
        "მიმდინარე ფასი განსხვავდება დამატებისას დაფიქსირებულისგან.",
      PRICE_INCREASED: "ამ პროდუქტის ფასი გაიზარდა.",
      PRICE_DECREASED: "ამ პროდუქტის ფასი შემცირდა.",
      PROMOTION_ENDED: "ამ პროდუქტზე აქცია აღარ მოქმედებს.",
      PROMOTION_APPLIED: "ამ პროდუქტზე ახლა აქცია მოქმედებს.",
      CART_EXPIRED: "ეს კალათა ვადაგასულია.",
    },
  },
};

export function useCartCopy(): CartCopy {
  const { locale } = useLocale();
  return cartCopy[locale];
}

export function issueMessage(
  copy: CartCopy,
  code: string,
  fallback: string,
): string {
  return copy.issues[code as CartIssueCode] ?? fallback;
}
