'use client';

import { useSyncExternalStore } from 'react';
import { addEnquiryItem, ENQUIRY_STORAGE_KEY, ENQUIRY_STORAGE_VERSION, MAX_ENQUIRY_QUANTITY, readEnquiry, setEnquiryQuantity, type EnquiryItem } from './domain';

import { deriveEnquiry } from './lines';

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

function commit(items: readonly EnquiryItem[]) {
  let persistent = true;
  try { window.localStorage.setItem(ENQUIRY_STORAGE_KEY, JSON.stringify({ version: ENQUIRY_STORAGE_VERSION, items })); }
  catch { persistent = false; }
  snapshot = { items, hydrated: true, persistent };
  emit();
  return { persistent };
}
function hydrate() { if (!snapshot.hydrated) readStorage(); }

/** One SKU identifies one concrete variant. No names, prices or availability are persisted. */
export const enquiryStore = {
  getItems: () => snapshot.items,
  getTotalQuantity: () => snapshot.items.reduce((total, item) => total + item.quantity, 0),
  addItem(productId: string, sku: string, quantity: number) {
    hydrate();
    return commit(addEnquiryItem(snapshot.items, { productId, sku, quantity }));
  },
  /** Validate all lines before a single commit; a rejected line leaves the cart intact. */
  addItems(items: readonly EnquiryItem[]) {
    hydrate();
    return commit(items.reduce((next, item) => addEnquiryItem(next, item), [...snapshot.items]));
  },
  setQuantity(sku: string, quantity: number) {
    hydrate();
    return commit(setEnquiryQuantity(snapshot.items, sku, quantity));
  },
  increment(sku: string) {
    hydrate();
    const item = snapshot.items.find((line) => line.sku === sku);
    if (item) return commit(setEnquiryQuantity(snapshot.items, sku, Math.min(MAX_ENQUIRY_QUANTITY, item.quantity + 1)));
  },
  decrement(sku: string) {
    hydrate();
    const item = snapshot.items.find((line) => line.sku === sku);
    if (item) return commit(setEnquiryQuantity(snapshot.items, sku, Math.max(1, item.quantity - 1)));
  },
  removeItem(sku: string) { hydrate(); return commit(snapshot.items.filter((line) => line.sku !== sku)); },
  clearCart() { hydrate(); return commit([]); },
};

export function useEnquiry() {
  const state = useSyncExternalStore(subscribe, () => snapshot, () => empty);
  return { ...state, ...deriveEnquiry(state.items) };
}
