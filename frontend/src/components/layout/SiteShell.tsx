'use client';

import { useState, type CSSProperties } from 'react';
import { useMantineTheme } from '@mantine/core';
import { usePathname } from 'next/navigation';
import { HomeLayout } from '@/components/home/HomeLayout';
import { SiteHeader } from './SiteHeader';
import { FloatingActions } from './FloatingActions';
import styles from './Layout.module.css';
import { ENQUIRY_PATH } from '@/lib/enquiry/domain';

export function SiteShell({ children, footer }: { children: React.ReactNode; footer: React.ReactNode }) {
  const theme = useMantineTheme();
  const pathname = usePathname();
  const [menuOpened, setMenuOpened] = useState(false);
  if (pathname === '/') {
    return <HomeLayout
      footer={footer}
      menuOpened={menuOpened}
      setMenuOpened={setMenuOpened}
      floatingLayer={theme.other.layers.floating}
    >{children}</HomeLayout>;
  }
  return <div className={styles.shell} style={{ '--floating-layer': theme.other.layers.floating } as CSSProperties}>
    <a href="#main-content" className="skip-link">Skip to content</a>
    <SiteHeader opened={menuOpened} setOpened={setMenuOpened} />
    <main id="main-content" tabIndex={-1} className={styles.main}>{children}</main>
    {footer}
    <FloatingActions hidden={menuOpened} hideOnMobile={pathname === ENQUIRY_PATH} />
  </div>;
}
