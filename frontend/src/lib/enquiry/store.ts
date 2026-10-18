'use client';

import { useSyncExternalStore } from 'react';
import { addEnquiryItem, ENQUIRY_STORAGE_KEY, readEnquiry, type EnquiryItem } from './domain';

type Snapshot = { items: readonly EnquiryItem[]; hydrated: boolean; persistent: boolean };
const empty: Snapshot = { items: [], hydrated: false, persistent: true };
let snapshot = empty;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((listener) => listener());

function readStorage() {
  try {
    snapshot = { items: readEnquiry(window.localStorage.getItem(ENQUIRY_STORAGE_KEY)), hydrated: true, persistent: true };
  } catch {
    snapshot = { ...snapshot, hydrated: true, persistent: false };
  }
  emit();
}
function onStorage(event: StorageEvent) {
  if (event.key === ENQUIRY_STORAGE_KEY || event.key === null) readStorage();
}
function subscribe(listener: () => void) {
  listeners.add(listener);
  if (listeners.size === 1) {
    window.addEventListener('storage', onStorage);
    if (!snapshot.hydrated) readStorage();
  }
  return () => {
    listeners.delete(listener);
    if (!listeners.size) window.removeEventListener('storage', onStorage);
  };
}

/** One SKU identifies one concrete variant. No names, prices or availability are persisted. */
export const enquiryStore = {
  getItems: () => snapshot.items,
  getTotalQuantity: () => snapshot.items.reduce((total, item) => total + item.quantity, 0),
  addItem(productId: string, sku: string, quantity: number) {
    if (!snapshot.hydrated) readStorage();
    const items = addEnquiryItem(snapshot.items, { productId, sku, quantity });
    let persistent = true;
    try { window.localStorage.setItem(ENQUIRY_STORAGE_KEY, JSON.stringify({ version: 1, items })); }
    catch { persistent = false; }
    snapshot = { items, hydrated: true, persistent };
    emit();
    return { persistent };
  },
};

export function useEnquiry() {
  const state = useSyncExternalStore(subscribe, () => snapshot, () => empty);
  return { ...state, totalQuantity: state.items.reduce((total, item) => total + item.quantity, 0) };
}
