import type { Metadata } from "next";
import { CartPageView } from "@/features/cart/components/CartPageView";

export const metadata: Metadata = {
  title: "Cart",
  robots: { index: false, follow: false },
};

export default function CartPage() {
  return <CartPageView />;
}
