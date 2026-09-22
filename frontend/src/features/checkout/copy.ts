import { useLocale } from "@/components/locale-provider";
import type { Locale } from "@/i18n/dictionaries";

export type CheckoutCopy = {
  title: string;
  steps: { contact: string; delivery: string; review: string };
  continue: string;
  back: string;
  saveContact: string;
  saveDelivery: string;
  createQuote: string;
  refreshQuote: string;
  continueToOrder: string;
  continueToOrderHint: string;
  emptyTitle: string;
  emptyHint: string;
  shopCart: string;
  loading: string;
  loadError: string;
  retry: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  note: string;
  city: string;
  street: string;
  house: string;
  apartment: string;
  postal: string;
  region: string;
  country: string;
  instructions: string;
  pickup: string;
  delivery: string;
  unavailable: string;
  subtotal: string;
  savings: string;
  shipping: string;
  taxIncluded: string;
  total: string;
  expires: string;
  expired: string;
  quoteLoading: string;
  versionConflict: string;
  stockChanged: string;
  priceChanged: string;
  editContact: string;
  editDelivery: string;
  summary: string;
  quantity: string;
  sku: string;
  noMethods: string;
};

export const checkoutCopy: Record<Locale, CheckoutCopy> = {
  en: {
    title: "Checkout",
    steps: { contact: "Contact", delivery: "Delivery", review: "Review" },
    continue: "Continue",
    back: "Back",
    saveContact: "Save contact",
    saveDelivery: "Save delivery",
    createQuote: "Review totals",
    refreshQuote: "Refresh quote",
    continueToOrder: "Continue to order",
    continueToOrderHint:
      "Order placement opens in the next step. This quote is not a payment.",
    emptyTitle: "Your cart is empty",
    emptyHint: "Add gear before starting checkout.",
    shopCart: "View cart",
    loading: "Preparing checkout",
    loadError: "Checkout could not be loaded.",
    retry: "Retry",
    firstName: "First name",
    lastName: "Last name",
    email: "Email",
    phone: "Phone",
    note: "Order note",
    city: "City or municipality",
    street: "Street",
    house: "House number",
    apartment: "Apartment",
    postal: "Postal code",
    region: "Region",
    country: "Country",
    instructions: "Delivery instructions",
    pickup: "Store pickup",
    delivery: "Delivery",
    unavailable: "Not available for this address",
    subtotal: "Items",
    savings: "Savings",
    shipping: "Delivery",
    taxIncluded: "Prices include tax where applicable.",
    total: "Quoted total",
    expires: "Quote expires",
    expired:
      "This quote has expired. Refresh to lock current prices and stock.",
    quoteLoading: "Calculating current totals",
    versionConflict:
      "Checkout was updated in another tab. The latest state is shown.",
    stockChanged: "Availability changed. Review the new quote.",
    priceChanged: "Prices changed. Review the new quote.",
    editContact: "Edit contact",
    editDelivery: "Edit delivery",
    summary: "Order summary",
    quantity: "Qty",
    sku: "SKU",
    noMethods: "No fulfillment methods are available yet.",
  },
  ka: {
    title: "შეკვეთა",
    steps: { contact: "კონტაქტი", delivery: "მიწოდება", review: "გადახედვა" },
    continue: "გაგრძელება",
    back: "უკან",
    saveContact: "კონტაქტის შენახვა",
    saveDelivery: "მიწოდების შენახვა",
    createQuote: "ჯამების ნახვა",
    refreshQuote: "კვოტის განახლება",
    continueToOrder: "შეკვეთაზე გადასვლა",
    continueToOrderHint:
      "შეკვეთის გაფორმება შემდეგ ეტაპზე გაიხსნება. ეს კვოტა გადახდა არ არის.",
    emptyTitle: "კალათა ცარიელია",
    emptyHint: "შეკვეთამდე დაამატე პროდუქტი.",
    shopCart: "კალათის ნახვა",
    loading: "შეკვეთა მზადდება",
    loadError: "შეკვეთა ვერ ჩაიტვირთა.",
    retry: "გამეორება",
    firstName: "სახელი",
    lastName: "გვარი",
    email: "ელფოსტა",
    phone: "ტელეფონი",
    note: "შენიშვნა",
    city: "ქალაქი ან მუნიციპალიტეტი",
    street: "ქუჩა",
    house: "სახლის ნომერი",
    apartment: "ბინა",
    postal: "საფოსტო კოდი",
    region: "რეგიონი",
    country: "ქვეყანა",
    instructions: "მიწოდების ინსტრუქცია",
    pickup: "მაღაზიიდან გატანა",
    delivery: "მიწოდება",
    unavailable: "ამ მისამართისთვის მიუწვდომელია",
    subtotal: "პროდუქტები",
    savings: "დაზოგვა",
    shipping: "მიწოდება",
    taxIncluded: "ფასებში გადასახადი უკვე შედის, სადაც ეს მოქმედებს.",
    total: "კვოტის ჯამი",
    expires: "კვოტის ვადა",
    expired: "კვოტის ვადა ამოიწურა. განაახლე მიმდინარე ფასისა და მარაგისთვის.",
    quoteLoading: "ითვლება მიმდინარე ჯამები",
    versionConflict:
      "შეკვეთა სხვა ჩანართში განახლდა. ნაჩვენებია ბოლო მდგომარეობა.",
    stockChanged: "ხელმისაწვდომობა შეიცვალა. ნახე ახალი კვოტა.",
    priceChanged: "ფასები შეიცვალა. ნახე ახალი კვოტა.",
    editContact: "კონტაქტის რედაქტირება",
    editDelivery: "მიწოდების რედაქტირება",
    summary: "შეკვეთის შეჯამება",
    quantity: "რაოდენობა",
    sku: "SKU",
    noMethods: "მიწოდების მეთოდი ჯერ არ არის ხელმისაწვდომი.",
  },
};

export function useCheckoutCopy(): CheckoutCopy {
  const { locale } = useLocale();
  return checkoutCopy[locale];
}
