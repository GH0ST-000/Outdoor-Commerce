import { StorefrontStatus } from "@/components/StorefrontStatus";
import styles from "./page.module.css";

export default function Home() {
  return (
    <div className={styles.page}>
      <main className={styles.main}>
        <p className={styles.brand}>Outdoor Commerce</p>
        <h1 className={styles.headline}>Storefront is running</h1>
        <p className={styles.copy}>
          Day 1 local development environment for the hunting, fishing, and
          outdoor equipment platform.
        </p>
        <StorefrontStatus />
      </main>
    </div>
  );
}
