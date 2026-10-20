'use client';

import type { ReactNode } from 'react';
import { Modal } from '@mantine/core';
import { useMediaQuery, useReducedMotion } from '@mantine/hooks';
import styles from './Forms.module.css';

export function LeadDialog({ opened, onClose, title, children, largeTitle = false, narrow = false, compactMobile = false }: { opened: boolean; onClose: () => void; title: string; children: ReactNode; largeTitle?: boolean; narrow?: boolean; compactMobile?: boolean }) {
  const mobile = useMediaQuery('(max-width: 35.999em)');
  const reducedMotion = useReducedMotion();
  return <Modal opened={opened} onClose={onClose} title={title} fullScreen={mobile} size={narrow ? 560 : "lg"} zIndex={300} keepMounted
    trapFocus returnFocus closeOnEscape closeOnClickOutside lockScroll closeButtonProps={{ 'aria-label': `Close ${title.toLowerCase()}` }}
    transitionProps={{ duration: reducedMotion ? 0 : 180 }} classNames={compactMobile && mobile ? { header: styles.compactModalHeader, title: styles.compactModalTitle, body: styles.compactModalBody } : undefined} styles={largeTitle && !mobile ? { title: { fontSize: 30, lineHeight: 1.2, fontWeight: 600 } } : undefined}>{children}</Modal>;
}
