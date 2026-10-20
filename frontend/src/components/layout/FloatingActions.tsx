'use client';

import { IconBook2, IconBrandWhatsapp } from '@tabler/icons-react';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { siteConfig } from '@/config/site';
import styles from './Layout.module.css';

export function FloatingActions({ hidden, home = false, hideOnMobile = false }: { hidden: boolean; home?: boolean; hideOnMobile?: boolean }) {
  const [pastViewport, setPastViewport] = useState(false);
  useEffect(() => {
    if (!home) return;
    let frame = 0;
    const update = () => {
      frame = 0;
      setPastViewport(window.scrollY >= window.innerHeight);
    };
    const scheduleUpdate = () => {
      if (!frame) frame = window.requestAnimationFrame(update);
    };
    update();
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    window.addEventListener('pageshow', scheduleUpdate);
    return () => {
      window.cancelAnimationFrame(frame);
      window.removeEventListener('scroll', scheduleUpdate);
      window.removeEventListener('resize', scheduleUpdate);
      window.removeEventListener('pageshow', scheduleUpdate);
    };
  }, [home]);
  const isHidden = hidden || (home && !pastViewport);
  return <aside aria-label="Quick actions" className={`${styles.floating} ${isHidden ? styles.floatingHidden : ''} ${hideOnMobile ? styles.floatingMobileHidden : ''}`} aria-hidden={isHidden} inert={isHidden}>
    <div className={styles.floatingItem}>
      {siteConfig.whatsappUrl
        ? <a className={styles.floatingButton} href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer" aria-label="WhatsApp (opens in a new tab)">
            <IconBrandWhatsapp size={26} stroke={1.5} aria-hidden="true" />
          </a>
        : <button type="button" className={styles.floatingButton} disabled aria-label="WhatsApp unavailable">
            <IconBrandWhatsapp size={26} stroke={1.5} aria-hidden="true" />
          </button>}
      <span className={styles.floatingLabel}>WhatsApp</span>
    </div>
    <div className={styles.floatingItem}>
      <Link className={styles.floatingButton} href="/catalogues" aria-label="Catalogues">
        <IconBook2 size={26} stroke={1.5} aria-hidden="true" />
      </Link>
      <span className={styles.floatingLabel}>Catalogues</span>
    </div>
  </aside>;
}
