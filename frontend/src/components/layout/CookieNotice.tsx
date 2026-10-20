'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import styles from './CookieNotice.module.css';

const STORAGE_KEY = 'potsandplanters.cookieNotice';
const VERSION = 1;
type ConsentRecord = { version: number; acceptedAt: string; expiresAt: string };

function addCalendarMonths(date: Date, months: number) {
  const targetMonth = date.getMonth() + months;
  const target = new Date(date);
  target.setDate(1);
  target.setMonth(targetMonth);
  const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
  target.setDate(Math.min(date.getDate(), lastDay));
  return target;
}

function isConsentValid(raw: string | null, now: number) {
  if (!raw) return false;
  try {
    const value = JSON.parse(raw) as Partial<ConsentRecord>;
    if (value.version !== VERSION || typeof value.acceptedAt !== 'string' || typeof value.expiresAt !== 'string') return false;
    const acceptedAt = Date.parse(value.acceptedAt);
    const expiresAt = Date.parse(value.expiresAt);
    return Number.isFinite(acceptedAt) && Number.isFinite(expiresAt) && expiresAt > now && acceptedAt <= now && expiresAt > acceptedAt;
  } catch {
    return false;
  }
}

export function CookieNotice() {
  const [visible, setVisible] = useState(false);
  const [checked, setChecked] = useState(false);

  useEffect(() => {
    let accepted = false;
    try {
      accepted = isConsentValid(window.localStorage.getItem(STORAGE_KEY), Date.now());
    } catch {
      // Storage may be disabled; keep the notice available without breaking the page.
    }
    setVisible(!accepted);
    setChecked(true);
  }, []);

  function agree() {
    const now = new Date();
    const record: ConsentRecord = {
      version: VERSION,
      acceptedAt: now.toISOString(),
      expiresAt: addCalendarMonths(now, 6).toISOString(),
    };
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(record));
    } catch {
      // Dismiss for this page view when persistence is unavailable.
    }
    setVisible(false);
  }

  if (!checked || !visible) return null;

  return <aside className={styles.notice} aria-labelledby="cookie-notice-title" aria-describedby="cookie-notice-description">
    <div className={styles.copy}>
      <h2 id="cookie-notice-title">We Use Cookies</h2>
      <p id="cookie-notice-description">We use cookies and local storage to support essential website features, including your enquiry cart and preferences. Learn more in our <Link href="/private-policy">Privacy Policy</Link>.</p>
    </div>
    <button className={styles.button} type="button" onClick={agree}>I agree</button>
  </aside>;
}
