"use client";

import { SiteControls } from "@/components/site-controls";
import { useLocale } from "@/components/locale-provider";
import styles from "@/features/auth/components/auth-shell.module.css";

export default function AuthLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const { t } = useLocale();

  return (
    <div className={styles.shell}>
      <aside className={styles.visual} aria-hidden="true">
        <div className={styles.visualAtmosphere} />
        <p className={styles.brandMark}>{t.brand}</p>
        <p className={styles.visualCopy}>{t.authShell.visualCopy}</p>
      </aside>
      <section className={styles.panel}>
        <div className="mb-4 flex justify-end">
          <SiteControls tone="light" />
        </div>
        <div className={styles.panelInner}>
          <p className={styles.mobileBrand}>{t.brand}</p>
          {children}
        </div>
      </section>
    </div>
  );
}
