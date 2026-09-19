import styles from "./StorefrontStatus.module.css";

type StorefrontStatusProps = {
  label?: string;
};

export function StorefrontStatus({
  label = "Frontend health: ready",
}: StorefrontStatusProps) {
  return (
    <p className={styles.status} data-testid="storefront-status">
      {label}
    </p>
  );
}
