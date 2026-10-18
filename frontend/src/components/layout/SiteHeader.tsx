'use client';

import { useEffect, useRef, useState } from 'react';
import { Burger, Container } from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { IconSearch } from '@tabler/icons-react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { siteConfig } from '@/config/site';
import { Brand } from './Brand';
import { CartEntry } from './CartEntry';
import { MobileNavigation } from './MobileNavigation';
import { SearchEntry } from './SearchEntry';
import styles from './Layout.module.css';

export function SiteHeader({ opened, setOpened, overlay = false }: { opened: boolean; setOpened: (value: boolean) => void; overlay?: boolean }) {
  const [searchOpened, setSearchOpened] = useState(false);
  const searchToggle = useRef<HTMLButtonElement>(null);
  const searchPanel = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (searchOpened) searchPanel.current?.querySelector<HTMLInputElement>('input')?.focus();
  }, [searchOpened]);
  const desktop = useMediaQuery('(min-width: 76.25em)');
  const pathname = usePathname();
  useEffect(() => { if (desktop) setOpened(false); }, [desktop, setOpened]);
  return <header className={`${styles.header} ${overlay ? styles.headerOverlay : ''}`}>
    <Container>
      <div className={styles.headerRow}>
        <Brand variant={overlay ? 'headerOverlay' : 'header'} />
        <nav aria-label="Primary navigation" className={styles.desktopNav}>
          {siteConfig.navigation.map((item) => <Link key={item.label} href={item.href} className={styles.navLink} aria-current={item.href === pathname && (pathname !== '/catalogues' || item.label === 'Catalogues') ? 'page' : undefined}>{item.label}</Link>)}
        </nav>
        <div className={styles.headerActions}>
          <CartEntry />
          <button ref={searchToggle} type="button" className={`${styles.headerIconButton} ${styles.searchToggle}`} aria-expanded={searchOpened} aria-controls="header-search" aria-label={searchOpened ? 'Close search' : 'Open search'} onClick={() => setSearchOpened(!searchOpened)}>
            <IconSearch size={22} stroke={1.25} aria-hidden="true" />
          </button>
          <Burger className={styles.burger} opened={opened} onClick={() => setOpened(!opened)} aria-label={opened ? 'Close menu' : 'Open menu'} aria-expanded={opened} aria-controls={opened ? 'mobile-navigation' : undefined} />
        </div>
      </div>
      <div ref={searchPanel} id="header-search" hidden={!searchOpened} className={styles.searchPanel} onKeyDown={(event) => {
        if (event.key === 'Escape') {
          setSearchOpened(false);
          searchToggle.current?.focus();
        }
      }}><SearchEntry onNavigate={() => setSearchOpened(false)} /></div>
    </Container>
    <MobileNavigation opened={opened} onClose={() => setOpened(false)} />
  </header>;
}
