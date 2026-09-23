import { useLocale } from "@/components/locale-provider";
import type { Locale } from "@/i18n/dictionaries";

export type OrderCopy = {
  confirmationTitle: string;
  pendingTitle: string;
  pendingBody: string;
  createdBody: string;
  orderNumber: string;
  status: string;
  paymentStatus: string;
  fulfillmentStatus: string;
  paymentDeadline: string;
  holdNotice: string;
  cancel: string;
  cancelling: string;
  continueShopping: string;
  paymentLater: string;
  loading: string;
  loadError: string;
  notFound: string;
  retry: string;
  expiredTitle: string;
  expiredBody: string;
  cancelledTitle: string;
  cancelledBody: string;
  confirming: string;
  confirmOrder: string;
  confirmHint: string;
  quoteExpired: string;
  reservationExpired: string;
  versionConflict: string;
  subtotal: string;
  savings: string;
  shipping: string;
  taxIncluded: string;
  total: string;
  quantity: string;
  contact: string;
  address: string;
  pickup: string;
  statuses: Record<string, string>;
  paymentStatuses: Record<string, string>;
};

export const orderCopy: Record<Locale, OrderCopy> = {
  en: {
    confirmationTitle: "Order created",
    pendingTitle: "Payment is still pending",
    pendingBody:
      "Your order was created. Payment has not been collected yet. Inventory is held until the deadline below.",
    createdBody: "The order was created successfully.",
    orderNumber: "Order number",
    status: "Order status",
    paymentStatus: "Payment",
    fulfillmentStatus: "Fulfillment",
    paymentDeadline: "Payment deadline",
    holdNotice: "Reserved stock is held until this time.",
    cancel: "Cancel unpaid order",
    cancelling: "Cancelling",
    continueShopping: "Continue shopping",
    paymentLater: "Online payment will be available in a later step.",
    loading: "Loading order",
    loadError: "The order could not be loaded.",
    notFound: "Order was not found.",
    retry: "Retry",
    expiredTitle: "Payment window ended",
    expiredBody:
      "This unpaid order expired. Reserved stock has been released. Start checkout again to place a new order.",
    cancelledTitle: "Order cancelled",
    cancelledBody:
      "This unpaid order was cancelled. Reserved stock was released.",
    confirming: "Creating your order",
    confirmOrder: "Confirm order",
    confirmHint:
      "Confirms the quoted totals and holds inventory until payment. This is not a payment.",
    quoteExpired: "The quote expired. Refresh totals, then confirm again.",
    reservationExpired: "A stock hold expired. Refresh checkout and try again.",
    versionConflict:
      "Checkout changed in another tab. The latest quote is shown.",
    subtotal: "Items",
    savings: "Savings",
    shipping: "Delivery",
    taxIncluded: "Prices include tax where applicable.",
    total: "Order total",
    quantity: "Qty",
    contact: "Contact",
    address: "Delivery address",
    pickup: "Pickup",
    statuses: {
      pending_payment: "Pending payment",
      payment_processing: "Payment processing",
      confirmed: "Confirmed",
      manual_review: "Manual review",
      cancelled: "Cancelled",
      expired: "Expired",
    },
    paymentStatuses: {
      unpaid: "Unpaid",
      pending: "Pending",
      paid: "Paid",
      failed: "Failed",
      cancelled: "Cancelled",
    },
  },
  ka: {
    confirmationTitle: "შეკვეთა შეიქმნა",
    pendingTitle: "გადახდა ჯერ არ არის",
    pendingBody:
      "შეკვეთა შეიქმნა. გადახდა ჯერ არ ჩატარებულა. მარაგი დროებით დაცულია ქვემოთ მითითებულ ვადამდე.",
    createdBody: "შეკვეთა წარმატებით შეიქმნა.",
    orderNumber: "შეკვეთის ნომერი",
    status: "შეკვეთის სტატუსი",
    paymentStatus: "გადახდა",
    fulfillmentStatus: "მიწოდება",
    paymentDeadline: "გადახდის ვადა",
    holdNotice: "დარეზერვებული მარაგი ამ დრომდე ინახება.",
    cancel: "გადაუხდელი შეკვეთის გაუქმება",
    cancelling: "უქმდება",
    continueShopping: "შოპინგის გაგრძელება",
    paymentLater: "ონლაინ გადახდა შემდეგ ეტაპზე გახდება ხელმისაწვდომი.",
    loading: "შეკვეთა იტვირთება",
    loadError: "შეკვეთა ვერ ჩაიტვირთა.",
    notFound: "შეკვეთა ვერ მოიძებნა.",
    retry: "გამეორება",
    expiredTitle: "გადახდის ვადა ამოიწურა",
    expiredBody:
      "გადაუხდელი შეკვეთის ვადა ამოიწურა. დარეზერვებული მარაგი გათავისუფლდა. ახალი შეკვეთისთვის თავიდან დაიწყე შეკვეთა.",
    cancelledTitle: "შეკვეთა გაუქმდა",
    cancelledBody:
      "გადაუხდელი შეკვეთა გაუქმდა. დარეზერვებული მარაგი გათავისუფლდა.",
    confirming: "შეკვეთა იქმნება",
    confirmOrder: "შეკვეთის დადასტურება",
    confirmHint:
      "ადასტურებს კვოტის ჯამებს და მარაგს გადახდამდე ინახავს. ეს გადახდა არ არის.",
    quoteExpired:
      "კვოტის ვადა ამოიწურა. განაახლე ჯამები და დაადასტურე თავიდან.",
    reservationExpired:
      "მარაგის რეზერვაცია ამოიწურა. განაახლე შეკვეთა და სცადე თავიდან.",
    versionConflict: "შეკვეთა სხვა ჩანართში შეიცვალა. ნაჩვენებია ბოლო კვოტა.",
    subtotal: "პროდუქტები",
    savings: "დაზოგვა",
    shipping: "მიწოდება",
    taxIncluded: "ფასებში გადასახადი უკვე შედის, სადაც ეს მოქმედებს.",
    total: "შეკვეთის ჯამი",
    quantity: "რაოდენობა",
    contact: "კონტაქტი",
    address: "მიწოდების მისამართი",
    pickup: "გატანა",
    statuses: {
      pending_payment: "ელოდება გადახდას",
      payment_processing: "გადახდა მუშავდება",
      confirmed: "დადასტურებული",
      manual_review: "ხელით შემოწმება",
      cancelled: "გაუქმებული",
      expired: "ვადაგასული",
    },
    paymentStatuses: {
      unpaid: "გადაუხდელი",
      pending: "მოლოდინში",
      paid: "გადახდილი",
      failed: "წარუმატებელი",
      cancelled: "გაუქმებული",
    },
  },
};

export function useOrderCopy(): OrderCopy {
  const { locale } = useLocale();
  return orderCopy[locale];
}

export function orderStatusLabel(copy: OrderCopy, status: string): string {
  return copy.statuses[status] ?? status;
}

export function paymentStatusLabel(copy: OrderCopy, status: string): string {
  return copy.paymentStatuses[status] ?? status;
}
