'use client';

import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

const WhatsAppContext = createContext<string | null>(null);

export function ContactsProvider({ whatsappUrl, children }: { whatsappUrl: string | null; children: ReactNode }) {
  return <WhatsAppContext.Provider value={whatsappUrl}>{children}</WhatsAppContext.Provider>;
}

export function useWhatsAppUrl() { return useContext(WhatsAppContext); }
