import type { CSSProperties, ReactNode } from 'react';
import { SiteHeader } from '@/components/layout/SiteHeader';
import { FloatingActions } from '@/components/layout/FloatingActions';
import styles from '@/components/layout/Layout.module.css';

export function HomeLayout({ children, footer, menuOpened, setMenuOpened, floatingLayer }: {
  children: ReactNode;
  footer: ReactNode;
  menuOpened: boolean;
  setMenuOpened: (value: boolean) => void;
  floatingLayer: number;
}) {
  return <div className={`${styles.shell} ${styles.homeShell}`} style={{ '--floating-layer': floatingLayer } as CSSProperties}>
    <a href="#main-content" className="skip-link">Skip to content</a>
    <SiteHeader opened={menuOpened} setOpened={setMenuOpened} overlay />
    <main id="main-content" tabIndex={-1} className={styles.main}>{children}</main>
    {footer}
    <FloatingActions hidden={menuOpened} home />
  </div>;
}
