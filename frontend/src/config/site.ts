import type { SiteConfig } from '@/types/site';
import { contactsData } from '@/data/contacts.mock';

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

// Stage 3 will replace these mock values with confirmed API data.
export const siteConfig: SiteConfig = {
  name: 'Pots & Planters',
  navigation: siteNavigation,
  company: { title: 'Company information', description: 'Placeholder — company details will be confirmed in stage 3.' },
  contacts: contactsData.primary,
  showrooms: contactsData.showrooms,
  // Legacy footer contains a destination; it requires confirmation before activation.
  whatsappUrl: contactsData.whatsappUrl,
};
