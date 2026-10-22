'use client';

import { useState, type CSSProperties } from 'react';
import { useMantineTheme } from '@mantine/core';
import { usePathname } from 'next/navigation';
import { HomeLayout } from '@/components/home/HomeLayout';
import { SiteHeader } from './SiteHeader';
import { FloatingActions } from './FloatingActions';
import styles from './Layout.module.css';
import { ENQUIRY_PATH } from '@/lib/enquiry/domain';
import { CookieNotice } from './CookieNotice';
import { ContactsProvider } from './ContactsProvider';
import type { ContactsResult } from '@/types/contacts';

export function SiteShell({ children, footer, contacts }: { children: React.ReactNode; footer: React.ReactNode; contacts: ContactsResult }) {
  const theme = useMantineTheme();
  const pathname = usePathname();
  const [menuOpened, setMenuOpened] = useState(false);
  const shell = pathname === '/' ? <HomeLayout
      footer={footer}
      menuOpened={menuOpened}
      setMenuOpened={setMenuOpened}
      floatingLayer={theme.other.layers.floating}
    >{children}</HomeLayout> : <div className={styles.shell} style={{ '--floating-layer': theme.other.layers.floating } as CSSProperties}>
    <a href="#main-content" className="skip-link">Skip to content</a>
    <SiteHeader opened={menuOpened} setOpened={setMenuOpened} />
    <main id="main-content" tabIndex={-1} className={styles.main}>{children}</main>
    {footer}
    <FloatingActions hidden={menuOpened} hideOnMobile={pathname === ENQUIRY_PATH} />
  </div>;
  return <ContactsProvider whatsappUrl={contacts.status === 'success' ? contacts.data.whatsappUrl : null}>{shell}<CookieNotice /></ContactsProvider>;
}
