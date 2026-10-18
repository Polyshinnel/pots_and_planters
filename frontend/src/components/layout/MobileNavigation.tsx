'use client';

import { Drawer, Stack, useMantineTheme } from '@mantine/core';
import Link from 'next/link';
import { siteConfig } from '@/config/site';
import { SearchEntry } from './SearchEntry';
import styles from './Layout.module.css';

export function MobileNavigation({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  const theme = useMantineTheme();
  return <Drawer opened={opened} onClose={onClose} title="Menu" closeButtonProps={{ 'aria-label': 'Close menu' }} position="right" size="min(100%, 400px)" zIndex={theme.other.layers.drawer} trapFocus returnFocus closeOnEscape lockScroll>
    <nav id="mobile-navigation" aria-label="Mobile navigation">
      <Stack gap="xs">
        {siteConfig.navigation.map((item, index) => <Link key={item.label} href={item.href} className={styles.mobileLink} onClick={onClose} data-autofocus={index === 0 || undefined}>{item.label}</Link>)}
      </Stack>
    </nav>
    <SearchEntry onNavigate={onClose} />
  </Drawer>;
}
