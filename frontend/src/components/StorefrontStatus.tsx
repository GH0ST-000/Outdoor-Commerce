import styles from "./StorefrontStatus.module.css";

export function StorefrontStatus() {
  return (
    <p className={styles.status} data-testid="storefront-status">
      Frontend health: ready
    </p>
  );
}
