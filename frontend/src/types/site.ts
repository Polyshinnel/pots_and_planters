export interface NavigationItem { label: string; href: string }
export interface SiteConfig {
  name: string;
  navigation: readonly NavigationItem[];
}
