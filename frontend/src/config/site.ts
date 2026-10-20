import { categoryPath } from './catalog';
import type { SiteConfig } from '@/types/site';
import { contactsData } from '@/data/contacts.mock';

// Stage 3 will replace these mock values with confirmed API data.
export const siteConfig: SiteConfig = {
  name: 'Pots & Planters',
  navigation: [
    { label: 'Main', href: '/' },
    { label: 'Outdoor Pots', href: categoryPath('outdoor') },
    { label: 'Indoor Pots', href: categoryPath('indoor') },
    { label: 'Artificial Plants & Trees', href: categoryPath('artificial') },
    { label: 'Catalogues', href: '/catalogues' },
    { label: 'About Us', href: '/about' },
    { label: 'Contacts', href: '/contacts' },
  ],
  company: { title: 'Company information', description: 'Placeholder — company details will be confirmed in stage 3.' },
  contacts: contactsData.primary,
  showrooms: contactsData.showrooms,
  // Legacy footer contains a destination; it requires confirmation before activation.
  whatsappUrl: contactsData.whatsappUrl,
};
