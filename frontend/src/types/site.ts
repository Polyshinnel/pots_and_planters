import type { ContactDetails, Showroom } from './contacts';

export interface NavigationItem { label: string; href: string }
export interface ContactPlaceholder { title: string; description: string }
export interface SiteConfig {
  name: string;
  navigation: readonly NavigationItem[];
  company: ContactPlaceholder;
  contacts: ContactDetails;
  showrooms: readonly Showroom[];
  whatsappUrl: `https://wa.me/${string}` | null;
}
