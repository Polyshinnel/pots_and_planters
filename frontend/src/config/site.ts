import type { SiteConfig } from '@/types/site';

export const siteNavigation = [
  { label: 'Home', href: '/' },
  { label: 'Collections', href: '/shop' },
  { label: 'Solutions', href: '/solutions' },
  { label: 'Catalogues', href: '/catalogues' },
  { label: 'About Us', href: '/about' },
  { label: 'News', href: '/news' },
  { label: 'Contacts', href: '/contacts' },
] as const;

export function isNavigationItemActive(pathname: string, href: string): boolean {
  if (href === '/') return pathname === '/';
  return pathname === href || pathname.startsWith(`${href}/`);
}

export const siteConfig: SiteConfig = {
  name: 'Pots & Planters',
  navigation: siteNavigation,
};
