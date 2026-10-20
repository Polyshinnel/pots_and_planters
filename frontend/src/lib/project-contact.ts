import { siteConfig } from '@/config/site';

/** Uses the same confirmed destination as FE-01 floating actions. */
export function getProjectContact(message: string) {
  if (!siteConfig.whatsappUrl) return { href: '/contacts#project-enquiry', isWhatsApp: false };
  const url = new URL(siteConfig.whatsappUrl);
  url.searchParams.set('text', message);
  return { href: url.toString(), isWhatsApp: true };
}
