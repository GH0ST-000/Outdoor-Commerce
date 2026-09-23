import { useLocale } from "@/components/locale-provider";
import type { Locale } from "@/i18n/dictionaries";

export type PaymentCopy = {
  title: string;
  explanation: string;
  selectMethod: string;
  pay: string;
  paying: string;
  retry: string;
  cancelAttempt: string;
  noMethods: string;
  testOnly: string;
  processingTitle: string;
  processingBody: string;
  successTitle: string;
  successBody: string;
  failedTitle: string;
  failedBody: string;
  cancelledTitle: string;
  expiredTitle: string;
  manualReviewTitle: string;
  manualReviewBody: string;
  unknownTitle: string;
  unknownBody: string;
  refreshStatus: string;
  returnHome: string;
  deadline: string;
  secureNote: string;
  loadError: string;
  invalidRedirect: string;
};

export const paymentCopy: Record<Locale, PaymentCopy> = {
  en: {
    title: "Pay for this order",
    explanation:
      "Payment is completed on a hosted page. This site never collects card numbers.",
    selectMethod: "Payment method",
    pay: "Continue to payment",
    paying: "Starting payment",
    retry: "Try payment again",
    cancelAttempt: "Cancel this payment attempt",
    noMethods: "Online payment is not available for this order yet.",
    testOnly: "Test provider — not a real bank",
    processingTitle: "Confirming payment",
    processingBody:
      "We are waiting for a verified payment confirmation. This page does not treat return links as proof of payment.",
    successTitle: "Payment confirmed",
    successBody: "The order is paid. Inventory has been committed.",
    failedTitle: "Payment did not complete",
    failedBody:
      "You can retry while the payment window is open. Stock remains held.",
    cancelledTitle: "Payment attempt cancelled",
    expiredTitle: "Payment expired",
    manualReviewTitle: "Payment needs review",
    manualReviewBody:
      "A payment was received that cannot be applied automatically. Our team will resolve it. This is not an automatic confirmation.",
    unknownTitle: "Payment status is still unknown",
    unknownBody:
      "The provider did not finish reporting. Refresh status or wait — we will reconcile shortly.",
    refreshStatus: "Refresh payment status",
    returnHome: "Back to order",
    deadline: "Pay by",
    secureNote:
      "Card details stay on the payment provider. Amounts come from the order.",
    loadError: "Payment details could not be loaded.",
    invalidRedirect: "The payment redirect was rejected as unsafe.",
  },
  ka: {
    title: "გადაიხადე ეს შეკვეთა",
    explanation:
      "გადახდა სრულდება პროვაიდერის გვერდზე. ეს საიტი ბარათის ნომრებს არ აგროვებს.",
    selectMethod: "გადახდის მეთოდი",
    pay: "გადახდაზე გადასვლა",
    paying: "გადახდა იწყება",
    retry: "გადახდის გამეორება",
    cancelAttempt: "გადახდის მცდელობის გაუქმება",
    noMethods: "ონლაინ გადახდა ამ შეკვეთისთვის ჯერ არ არის ხელმისაწვდომი.",
    testOnly: "ტესტური პროვაიდერი — ეს ბანკი არ არის",
    processingTitle: "გადახდა დასტურდება",
    processingBody:
      "ველოდებით დადასტურებულ გადახდას. დაბრუნების ბმული გადახდის მტკიცებულება არ არის.",
    successTitle: "გადახდა დადასტურდა",
    successBody: "შეკვეთა გადახდილია. მარაგი ჩამოიჭრა.",
    failedTitle: "გადახდა ვერ დასრულდა",
    failedBody: "შეგიძლია გაიმეორო, სანამ ვადა ღიაა. მარაგი კვლავ დაცულია.",
    cancelledTitle: "გადახდის მცდელობა გაუქმდა",
    expiredTitle: "გადახდის ვადა ამოიწურა",
    manualReviewTitle: "გადახდა საჭიროებს შემოწმებას",
    manualReviewBody:
      "მიღებულია გადახდა, რომელიც ავტომატურად ვერ გამოიყენება. გუნდი გადაწყვეტს. ეს ავტომატური დადასტურება არ არის.",
    unknownTitle: "გადახდის სტატუსი უცნობია",
    unknownBody:
      "პროვაიდერმა პასუხი ვერ დაასრულა. განაახლე სტატუსი ან დაელოდე შეჯერებას.",
    refreshStatus: "სტატუსის განახლება",
    returnHome: "შეკვეთაზე დაბრუნება",
    deadline: "გადახდის ვადა",
    secureNote:
      "ბარათის მონაცემები რჩება პროვაიდერთან. თანხა შეკვეთიდან მოდის.",
    loadError: "გადახდის დეტალები ვერ ჩაიტვირთა.",
    invalidRedirect: "გადახდის გადამისამართება უსაფრთხო არ არის.",
  },
};

export function usePaymentCopy(): PaymentCopy {
  const { locale } = useLocale();
  return paymentCopy[locale];
}
