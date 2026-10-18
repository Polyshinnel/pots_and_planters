import { categoryPath } from './catalog';
import type { CartPreview, SiteConfig } from '@/types/site';

// Stage 3 will replace these mock values with confirmed API data.
export const siteConfig: SiteConfig = {
  name: 'Pots & Planters',
  navigation: [
    { label: 'Main', href: '/' },
    { label: 'Outdoor Pots', href: categoryPath('outdoor') },
    { label: 'Indoor Pots', href: categoryPath('indoor') },
    { label: 'Artificial Plants & Trees', href: categoryPath('artificial') },
    { label: 'Catalogues', href: '/catalogues' },
    { label: 'Contacts', href: '#contacts' },
  ],
  company: { title: 'Company information', description: 'Placeholder — company details will be confirmed in stage 3.' },
  contacts: { title: 'Contacts', description: 'Placeholder — address, phone and email will be confirmed in stage 3.' },
  showrooms: [
    { title: 'Showrooms', description: 'Placeholder — showroom locations and opening hours will be confirmed in stage 3.' },
  ],
  // Legacy footer contains a destination; it requires confirmation before activation.
  whatsappUrl: null,
};
export const cartPreview: CartPreview = { itemCount: 0 };
