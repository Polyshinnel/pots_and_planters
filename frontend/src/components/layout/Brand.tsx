import Link from 'next/link';
import Image from 'next/image';
import { siteConfig } from '@/config/site';
import styles from './Layout.module.css';

export function Brand({ variant = 'header' }: { variant?: 'header' | 'footer' | 'headerOverlay' }) {
  const footer = variant === 'footer';
  return <Link href="/" className={styles.brand} aria-label={`${siteConfig.name} — home`}>
    <Image
      src={footer ? '/images/footer_logo_black.svg' : '/images/header-logo-new.svg'}
      alt={siteConfig.name}
      width={footer ? 173 : 72}
      height={86}
      className={`${footer ? styles.footerLogo : styles.headerLogo} ${variant === 'headerOverlay' ? styles.headerLogoOverlay : ''}`}
    />
  </Link>;
}
