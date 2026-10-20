'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { Button, Drawer, Popover } from '@mantine/core';
import { useMediaQuery, useReducedMotion } from '@mantine/hooks';
import { IconShoppingCart } from '@tabler/icons-react';
import { usePathname } from 'next/navigation';
import { useEnquiry } from '@/lib/enquiry/store';
import { ENQUIRY_PATH } from '@/lib/enquiry/domain';
import { EnquiryEmpty, EnquiryItems, EnquiryStorageNotice, EnquiryTotals } from '@/components/enquiry/EnquiryItems';
import cartStyles from '@/components/enquiry/Enquiry.module.css';
import styles from './Layout.module.css';

export function CartEntry() {
  const { totalQuantity, hydrated, uniqueLineCount } = useEnquiry();
  const [opened, setOpened] = useState(false);
  const mobile = useMediaQuery('(max-width: 47.999em)');
  const reducedMotion = useReducedMotion();
  const trigger = useRef<HTMLButtonElement>(null);
  const pathname = usePathname();
  useEffect(() => { setOpened(false); }, [pathname, mobile]);
  const close = () => { setOpened(false); trigger.current?.focus(); };
  const contents = <div className={cartStyles.mini}>
    <p role="status">{hydrated ? `${totalQuantity} units in your enquiry` : 'Loading your selection…'}</p>
    <EnquiryStorageNotice />
    {hydrated && (uniqueLineCount ? <>
      <div className={cartStyles.miniScroll}><EnquiryItems onNavigate={close} compact /></div>
      <div className={cartStyles.miniFooter}><EnquiryTotals /><Button component={Link} href={ENQUIRY_PATH} fullWidth onClick={close}>Review Enquiry</Button></div>
    </> : <EnquiryEmpty onNavigate={close} />)}
  </div>;
  const toggle = <button ref={trigger} type="button" className={`${styles.headerIconButton} ${styles.cartEntry}`} title="Your Enquiry"
    aria-label={hydrated ? `Your Enquiry, ${totalQuantity} units` : 'Your Enquiry, loading'} aria-expanded={opened} aria-haspopup="dialog" onClick={() => setOpened(!opened)}>
    <IconShoppingCart size={22} stroke={1.25} aria-hidden="true" />
    {hydrated && <span className={styles.cartCount} aria-hidden="true">{totalQuantity > 99 ? '99+' : totalQuantity}</span>}
  </button>;
  if (mobile) return <>{toggle}<Drawer opened={opened} onClose={close} title="Your Enquiry" closeButtonProps={{ 'aria-label': 'Close enquiry' }} position="right" size="min(100%, 440px)" zIndex={300}
    trapFocus returnFocus closeOnEscape lockScroll transitionProps={{ duration: reducedMotion ? 0 : 180 }} classNames={{ content: cartStyles.drawerContent, body: cartStyles.drawerBody }}>{contents}</Drawer></>;
  return <Popover opened={opened} onChange={setOpened} onClose={close} position="bottom-end" width={440} offset={12} shadow="sm" withinPortal zIndex={300} trapFocus returnFocus withRoles closeOnEscape transitionProps={{ duration: reducedMotion ? 0 : 150 }}>
    <Popover.Target>{toggle}</Popover.Target>
    <Popover.Dropdown aria-label="Your Enquiry"><h2 className={cartStyles.miniHeading}>Your Enquiry</h2>{contents}</Popover.Dropdown>
  </Popover>;
}
