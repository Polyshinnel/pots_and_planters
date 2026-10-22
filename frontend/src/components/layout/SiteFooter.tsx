import { Container, Stack, Text, Title } from '@mantine/core';
import Link from 'next/link';
import { siteConfig } from '@/config/site';
import type { ContactsResult } from '@/types/contacts';
import { ContactLinks } from '@/components/contacts/ContactLinks';
import { Brand } from './Brand';
import styles from './Layout.module.css';

export function SiteFooter({ contacts }: { contacts: ContactsResult }) {
  const data = contacts.status === 'success' ? contacts.data : null;
  return <footer id="contacts" className={styles.footer}>
    <Container>
      <div className={styles.footerGrid}>
        <div className={styles.footerBrand}><Brand variant="footer" /></div>
        <Stack gap="sm">
          {data?.primary && <><Title order={3} size="h5">Contacts</Title>
          <div className={styles.footerContacts}><ContactLinks details={data.primary} /></div></>}
        </Stack>
        <nav aria-label="Footer navigation">
          <Title order={2} size="h4" mb="sm">Explore</Title>
          <Stack gap="xs" align="flex-start">
            {siteConfig.navigation.map((item) => <Link key={item.label} href={item.href} className={styles.footerLink}>{item.label}</Link>)}
            <Link href="/private-policy" className={styles.footerLink}>Privacy Policy</Link>
          </Stack>
        </nav>
        <Stack gap="sm">
          {data?.showrooms.slice(0, 3).map((showroom) => <section key={showroom.id}>
            <Title order={2} size="h4" mb="sm">{showroom.name}</Title>
            <Text size="sm" mb="xs">{[showroom.city, showroom.country].filter(Boolean).join(', ')}</Text>
            <Text size="sm">{showroom.address}</Text>
          </section>)}
        </Stack>
      </div>
      <Text size="sm" py="lg"><Link href="/contacts" className={styles.footerLink}>Showroom contacts and directions</Link></Text>
    </Container>
    <div className={styles.footerBottom}><Container><Text size="sm">© 2026 Pots and Planters. All rights reserved.</Text></Container></div>
  </footer>;
}
