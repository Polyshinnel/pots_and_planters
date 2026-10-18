export interface NavigationItem { label: string; href: string }
export interface ContactPlaceholder { title: string; description: string }
export interface SiteConfig {
  name: string;
  navigation: readonly NavigationItem[];
  company: ContactPlaceholder;
  contacts: ContactPlaceholder;
  showrooms: readonly ContactPlaceholder[];
  whatsappUrl: `https://wa.me/${string}` | null;
}
export interface CartPreview { itemCount: number }
