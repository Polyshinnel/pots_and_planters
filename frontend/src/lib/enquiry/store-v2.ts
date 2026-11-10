'use client';

import { useSyncExternalStore } from 'react';

export const ENQUIRY_V2_STORAGE_KEY = 'potsandplanters.enquiry.v2';
export const ENQUIRY_V2_VERSION = 2;
export const ENQUIRY_V1_STORAGE_KEY = 'potsandplanters.enquiry.v1';
export const ENQUIRY_V1_VERSION = 1;
export const MAX_V2_LINES = 100;
export const MAX_V2_QUANTITY = 999999;

export type EnquiryV2Item = { productId: string; quantity: number };
export type LegacyEnquiryItem = { productId: string; sku: string; quantity: number; sourceIndex: number };
export type StorageHealth = 'ready' | 'legacy_present' | 'corrupt' | 'storage_unavailable' | 'not_hydrated';
export type V2State = {
  items: readonly EnquiryV2Item[];
  legacyItems: readonly LegacyEnquiryItem[];
  legacyRaw: string | null;
  status: StorageHealth;
  persistent: boolean;
  hydrated: boolean;
  v2Corrupt: boolean;
  v1Corrupt: boolean;
};
export type StoreErrorCode = 'invalid_input' | 'line_limit' | 'quantity_limit' | 'corrupt' | 'storage_unavailable';
export type StoreResult = { ok: true; state: V2State } | { ok: false; code: StoreErrorCode; state: V2State };

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
export const isProductUuid = (value: unknown): value is string => typeof value === 'string' && UUID.test(value);
export const isV2Quantity = (value: unknown): value is number => typeof value === 'number' && Number.isSafeInteger(value) && value >= 1 && value <= MAX_V2_QUANTITY;

export type ParseResult<T> = { ok: true; value: T } | { ok: false; reason: 'malformed' | 'unknown_version' | 'invalid_structure' };
function parseEnvelope<T>(raw: string | null, version: number, validate: (entry: unknown) => entry is T, uniqueIdentity = true): ParseResult<T[]> {
  if (raw === null) return { ok: true, value: [] };
  let parsed: unknown;
  try { parsed = JSON.parse(raw); } catch { return { ok: false, reason: 'malformed' }; }
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return { ok: false, reason: 'invalid_structure' };
  const envelope = parsed as Record<string, unknown>;
  if (envelope.version !== version) return { ok: false, reason: 'unknown_version' };
  if (Object.keys(envelope).some((key) => key !== 'version' && key !== 'items') || !Array.isArray(envelope.items)) return { ok: false, reason: 'invalid_structure' };
  const seen = new Set<string>();
  const items: T[] = [];
  for (const item of envelope.items) {
    if (!validate(item)) return { ok: false, reason: 'invalid_structure' };
    const identity = (item as { productId: string }).productId;
    if (uniqueIdentity && seen.has(identity)) return { ok: false, reason: 'invalid_structure' };
    seen.add(identity);
    items.push(item);
  }
  return { ok: true, value: items };
}
function validV2Item(item: unknown): item is EnquiryV2Item {
  return Boolean(item && typeof item === 'object' && !Array.isArray(item)
    && Object.keys(item).length === 2 && Object.keys(item).every((key) => key === 'productId' || key === 'quantity')
    && isProductUuid((item as EnquiryV2Item).productId) && isV2Quantity((item as EnquiryV2Item).quantity));
}
function validLegacyItem(item: unknown): item is LegacyEnquiryItem {
  return Boolean(item && typeof item === 'object' && !Array.isArray(item)
    && Object.keys(item).length === 3 && Object.keys(item).every((key) => ['productId', 'sku', 'quantity'].includes(key))
    && typeof (item as LegacyEnquiryItem).productId === 'string' && (item as LegacyEnquiryItem).productId.length > 0
    && typeof (item as LegacyEnquiryItem).sku === 'string' && (item as LegacyEnquiryItem).sku.length > 0
    && isV2Quantity((item as LegacyEnquiryItem).quantity));
}
export function parseV2Envelope(raw: string | null): ParseResult<EnquiryV2Item[]> {
  const result = parseEnvelope(raw, ENQUIRY_V2_VERSION, validV2Item);
  return result.ok && result.value.length > MAX_V2_LINES ? { ok: false, reason: 'invalid_structure' } : result;
}
export function parseLegacyV1Envelope(raw: string | null): ParseResult<LegacyEnquiryItem[]> {
  return parseEnvelope(raw, ENQUIRY_V1_VERSION, validLegacyItem, false);
}
export function serializeV2(items: readonly EnquiryV2Item[]): string {
  const parsed = parseV2Envelope(JSON.stringify({ version: ENQUIRY_V2_VERSION, items }));
  if (!parsed.ok) throw new TypeError('Invalid enquiry v2 state.');
  return JSON.stringify({ version: ENQUIRY_V2_VERSION, items: parsed.value });
}

export type OperationResult = { ok: true; items: EnquiryV2Item[] } | { ok: false; code: 'invalid_input' | 'line_limit' | 'quantity_limit' };
export function addV2(items: readonly EnquiryV2Item[], productId: string, quantity: number): OperationResult {
  if (!isProductUuid(productId) || !isV2Quantity(quantity)) return { ok: false, code: 'invalid_input' };
  const existing = items.find((item) => item.productId === productId);
  if (existing) {
    const total = existing.quantity + quantity;
    if (!isV2Quantity(total)) return { ok: false, code: 'quantity_limit' };
    return { ok: true, items: items.map((item) => item.productId === productId ? { productId, quantity: total } : { ...item }) };
  }
  if (items.length >= MAX_V2_LINES) return { ok: false, code: 'line_limit' };
  return { ok: true, items: [...items.map((item) => ({ ...item })), { productId, quantity }] };
}
export function removeV2(items: readonly EnquiryV2Item[], productId: string): OperationResult {
  if (!isProductUuid(productId)) return { ok: false, code: 'invalid_input' };
  return { ok: true, items: items.filter((item) => item.productId !== productId).map((item) => ({ ...item })) };
}
export function setV2Quantity(items: readonly EnquiryV2Item[], productId: string, quantity: number): OperationResult {
  if (!isProductUuid(productId) || !isV2Quantity(quantity)) return { ok: false, code: 'invalid_input' };
  if (!items.some((item) => item.productId === productId)) return { ok: true, items: items.map((item) => ({ ...item })) };
  return { ok: true, items: items.map((item) => item.productId === productId ? { productId, quantity } : { ...item }) };
}

const initialState: V2State = { items: [], legacyItems: [], legacyRaw: null, status: 'not_hydrated', persistent: false, hydrated: false, v2Corrupt: false, v1Corrupt: false };
let state = initialState;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((listener) => listener());
function setState(next: V2State) { state = next; emit(); }
function getStorage(): Storage { return window.localStorage; }
function readStorage() {
  try {
    const storage = getStorage();
    const rawV2 = storage.getItem(ENQUIRY_V2_STORAGE_KEY);
    const rawV1 = storage.getItem(ENQUIRY_V1_STORAGE_KEY);
    const v2 = parseV2Envelope(rawV2);
    const v1 = parseLegacyV1Envelope(rawV1);
    const v2Corrupt = !v2.ok;
    const v1Corrupt = !v1.ok;
    const legacyItems = v1.ok ? v1.value.map((item, sourceIndex) => ({ ...item, sourceIndex })) : [];
    setState({ items: v2.ok ? v2.value : state.items, legacyItems, legacyRaw: rawV1, hydrated: true, persistent: true,
      v2Corrupt, v1Corrupt, status: v2Corrupt || v1Corrupt ? 'corrupt' : legacyItems.length ? 'legacy_present' : 'ready' });
  } catch {
    setState({ ...state, hydrated: true, persistent: false, status: 'storage_unavailable' });
  }
}
function onStorage(event: StorageEvent) {
  if (event.key === ENQUIRY_V2_STORAGE_KEY || event.key === ENQUIRY_V1_STORAGE_KEY || event.key === null) readStorage();
}
function subscribe(listener: () => void) {
  listeners.add(listener);
  if (listeners.size === 1 && typeof window !== 'undefined') {
    window.addEventListener('storage', onStorage);
    if (!state.hydrated) readStorage();
  }
  return () => { listeners.delete(listener); if (!listeners.size && typeof window !== 'undefined') window.removeEventListener('storage', onStorage); };
}
function hydrate() { if (!state.hydrated && typeof window !== 'undefined') readStorage(); }
function persist(items: readonly EnquiryV2Item[]): StoreResult {
  if (state.v2Corrupt || state.v1Corrupt) return { ok: false, code: 'corrupt', state };
  let serialized: string;
  try { serialized = serializeV2(items); } catch { return { ok: false, code: 'invalid_input', state }; }
  try {
    const storage = getStorage();
    storage.setItem(ENQUIRY_V2_STORAGE_KEY, serialized);
    const checked = parseV2Envelope(storage.getItem(ENQUIRY_V2_STORAGE_KEY));
    if (!checked.ok || JSON.stringify(checked.value) !== JSON.stringify(items)) {
      setState({ ...state, items: items.map((item) => ({ ...item })), persistent: false, status: 'storage_unavailable', hydrated: true });
      return { ok: false, code: 'storage_unavailable', state };
    }
    const next = { ...state, items: checked.value, persistent: true, hydrated: true,
      status: state.legacyItems.length ? 'legacy_present' as const : 'ready' as const };
    setState(next);
    return { ok: true, state };
  } catch {
    setState({ ...state, items: items.map((item) => ({ ...item })), persistent: false, status: 'storage_unavailable', hydrated: true });
    return { ok: false, code: 'storage_unavailable', state };
  }
}
function execute(operation: (items: readonly EnquiryV2Item[]) => OperationResult): StoreResult {
  hydrate();
  if (state.status === 'storage_unavailable') return { ok: false, code: 'storage_unavailable', state };
  if (state.v2Corrupt || state.v1Corrupt) return { ok: false, code: 'corrupt', state };
  const result = operation(state.items);
  if (!result.ok) return { ok: false, code: result.code, state };
  return persist(result.items);
}
export const enquiryStoreV2 = {
  getState: () => state,
  hydrate,
  subscribe,
  add: (productId: string, quantity = 1) => execute((items) => addV2(items, productId, quantity)),
  remove: (productId: string) => execute((items) => removeV2(items, productId)),
  setQuantity: (productId: string, quantity: number) => execute((items) => setV2Quantity(items, productId, quantity)),
  removeLegacyAt(sourceIndex: number): StoreResult {
    hydrate();
    if (state.v1Corrupt) return { ok: false, code: 'corrupt', state };
    if (state.status === 'storage_unavailable' || !Number.isSafeInteger(sourceIndex) || sourceIndex < 0) return { ok: false, code: 'storage_unavailable', state };
    try {
      const storage = getStorage();
      const raw = storage.getItem(ENQUIRY_V1_STORAGE_KEY);
      if (raw !== state.legacyRaw) return { ok: false, code: 'corrupt', state };
      const parsed = parseLegacyV1Envelope(raw);
      if (!parsed.ok || sourceIndex >= parsed.value.length) return { ok: false, code: 'corrupt', state };
      const items = parsed.value.filter((_, index) => index !== sourceIndex);
      const serialized = JSON.stringify({ version: ENQUIRY_V1_VERSION, items });
      storage.setItem(ENQUIRY_V1_STORAGE_KEY, serialized);
      if (storage.getItem(ENQUIRY_V1_STORAGE_KEY) !== serialized) throw new Error('Legacy storage read-back mismatch.');
      const next = { ...state, legacyRaw: serialized, legacyItems: items.map((item, index) => ({ ...item, sourceIndex: index })),
        status: state.v2Corrupt ? 'corrupt' as const : items.length ? 'legacy_present' as const : 'ready' as const };
      setState(next);
      return { ok: true, state };
    } catch {
      return { ok: false, code: 'storage_unavailable', state };
    }
  },
};

export function useEnquiryV2() {
  return useSyncExternalStore(subscribe, () => state, () => initialState);
}
