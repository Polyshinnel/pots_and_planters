'use client';

import { isProductUuid, isV2Quantity } from './store-v2';

export type V2Kind = 'product_enquiry' | 'cart_enquiry';
export type V2Source = { pagePath?: string; placement?: string };
type Contact = { name: string; phone: string; email: string; company?: string | null; message?: string | null; source?: V2Source };
export type ProductEnquiryV2Request = Contact & { type: 'product_enquiry'; apiVersion: 2; product: { productId: string; quantity: number } };
export type CartEnquiryV2Request = Contact & { type: 'cart_enquiry'; apiVersion: 2; items: Array<{ productId: string; quantity: number }> };
export type EnquiryV2Request = ProductEnquiryV2Request | CartEnquiryV2Request;
export type EnquiryV2Success = { ok: true; status: 'persisted'; enquiryId: string; notificationStatus: string; replayed: boolean };
export type EnquiryV2Failure = { ok: false; code: string; httpStatus: number; requestId?: string; fieldErrors: Record<string, string[]>; unavailableProductIds: string[]; retryable: boolean; uncertain: boolean };
export type EnquiryV2Result = EnquiryV2Success | EnquiryV2Failure;

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const KEY = /^[A-Za-z0-9._:-]{16,128}$/;
const object = (v: unknown): v is Record<string, unknown> => Boolean(v && typeof v === 'object' && !Array.isArray(v));
function cleanPayload(input: EnquiryV2Request): EnquiryV2Request {
  if (!object(input) || (input.type !== 'product_enquiry' && input.type !== 'cart_enquiry') || input.apiVersion !== 2
    || typeof input.name !== 'string' || input.name.length > 200 || typeof input.phone !== 'string' || input.phone.length > 64
    || typeof input.email !== 'string' || input.email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email)
    || input.company != null && (typeof input.company !== 'string' || input.company.length > 200)
    || input.message != null && (typeof input.message !== 'string' || input.message.length > 5000)) throw new TypeError('Invalid v2 enquiry payload.');
  let itemRows: Array<{ productId: string; quantity: number }>;
  if (input.type === 'product_enquiry') {
    if (!object(input.product) || !isProductUuid(input.product.productId) || !isV2Quantity(input.product.quantity)) throw new TypeError('Invalid product enquiry item.');
    itemRows = [{ productId: input.product.productId, quantity: input.product.quantity }];
  } else {
    if (!Array.isArray(input.items) || input.items.length < 1 || input.items.length > 100) throw new TypeError('Invalid cart enquiry items.');
    itemRows = input.items.map((item) => {
      if (!object(item) || !isProductUuid(item.productId) || !isV2Quantity(item.quantity)) throw new TypeError('Invalid cart enquiry item.');
      return { productId: item.productId, quantity: item.quantity };
    });
    if (new Set(itemRows.map((i) => i.productId.toLowerCase())).size !== itemRows.length) throw new TypeError('Duplicate cart product IDs.');
  }
  let source: V2Source | undefined;
  if (input.source !== undefined) {
    if (!object(input.source) || Object.keys(input.source).some((k) => k !== 'pagePath' && k !== 'placement')) throw new TypeError('Invalid enquiry source.');
    const { pagePath, placement } = input.source;
    if (pagePath !== undefined && (typeof pagePath !== 'string' || pagePath.length > 2048 || !/^\/(?!\/)[^?#]*$/.test(pagePath))) throw new TypeError('Invalid source page path.');
    if (placement !== undefined && (typeof placement !== 'string' || placement.length > 200)) throw new TypeError('Invalid source placement.');
    source = { ...(pagePath === undefined ? {} : { pagePath }), ...(placement === undefined ? {} : { placement }) };
  }
  const common = { type: input.type, apiVersion: 2 as const, name: input.name, phone: input.phone, email: input.email,
    ...(input.company === undefined ? {} : { company: input.company }), ...(input.message === undefined ? {} : { message: input.message }), ...(source === undefined ? {} : { source }) };
  return input.type === 'product_enquiry' ? { ...common, type: 'product_enquiry', product: itemRows[0] } : { ...common, type: 'cart_enquiry', items: itemRows };
}
export function serializeEnquiryV2(input: EnquiryV2Request): string { return JSON.stringify(cleanPayload(input)); }
export async function fingerprintEnquiryV2(input: EnquiryV2Request, cryptoProvider: Pick<Crypto, 'subtle'> = globalThis.crypto): Promise<string> {
  if (!cryptoProvider?.subtle) throw new Error('Web Crypto SHA-256 is unavailable.');
  const bytes = new TextEncoder().encode(serializeEnquiryV2(input));
  const digest = await cryptoProvider.subtle.digest('SHA-256', bytes);
  return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

export const ENQUIRY_ATTEMPT_STORAGE_KEY = 'potsandplanters.enquiry.submit-attempt.v2';
export type AttemptStatus = 'pending' | 'uncertain' | 'persisted' | 'rejected' | 'conflict';
export type Attempt = { key: string; payloadFingerprint: string; createdAt: number; status: AttemptStatus; enquiryId?: string; persistedAt?: number };
export type AttemptRecord = { version: 1; attempts: Partial<Record<V2Kind, Attempt>> };
export type AttemptError = 'attempt_storage_unavailable' | 'payload_changed_requires_confirmation' | 'attempt_expired_unknown_outcome' | 'already_persisted' | 'idempotency_conflict' | 'no_matching_attempt';
export type AttemptResult = { ok: true; attempt: Attempt } | { ok: false; code: AttemptError; attempt?: Attempt };
export type AttemptStorage = Pick<Storage, 'getItem' | 'setItem'>;
type AttemptOptions = { storage?: () => AttemptStorage; now?: () => number; random?: (bytes: Uint8Array) => void; ttlMs?: number };
const TTL = 24 * 60 * 60 * 1000;
function browserStorage(): AttemptStorage { if (typeof window === 'undefined') throw new Error('SSR'); return window.sessionStorage; }
function validateRecord(value: unknown): value is AttemptRecord {
  if (!object(value) || value.version !== 1 || !object(value.attempts) || Object.keys(value).some((k) => k !== 'version' && k !== 'attempts') || Object.keys(value.attempts).some((k) => k !== 'product_enquiry' && k !== 'cart_enquiry')) return false;
  return Object.values(value.attempts).every((a) => a === undefined || object(a) && typeof a.key === 'string' && KEY.test(a.key)
    && typeof a.payloadFingerprint === 'string' && /^[a-f0-9]{64}$/.test(a.payloadFingerprint) && Number.isFinite(a.createdAt)
    && ['pending', 'uncertain', 'persisted', 'rejected', 'conflict'].includes(String(a.status))
    && (a.enquiryId === undefined || typeof a.enquiryId === 'string') && (a.persistedAt === undefined || Number.isFinite(a.persistedAt))
    && Object.keys(a).every((k) => ['key', 'payloadFingerprint', 'createdAt', 'status', 'enquiryId', 'persistedAt'].includes(k)));
}
export function createAttemptManager(options: AttemptOptions = {}) {
  const storage = options.storage ?? browserStorage;
  const now = options.now ?? Date.now;
  const ttl = options.ttlMs ?? TTL;
  const read = (): AttemptRecord => {
    const raw = storage().getItem(ENQUIRY_ATTEMPT_STORAGE_KEY);
    if (raw === null) return { version: 1, attempts: {} };
    let parsed: unknown; try { parsed = JSON.parse(raw); } catch { throw new Error('corrupt'); }
    if (!validateRecord(parsed)) throw new Error('corrupt');
    return parsed;
  };
  const write = (record: AttemptRecord): boolean => {
    try { const s = storage(); const raw = JSON.stringify(record); s.setItem(ENQUIRY_ATTEMPT_STORAGE_KEY, raw); return s.getItem(ENQUIRY_ATTEMPT_STORAGE_KEY) === raw; } catch { return false; }
  };
  const failed: AttemptResult = { ok: false, code: 'attempt_storage_unavailable' };
  function getAttempt(kind: V2Kind, fingerprint: string): AttemptResult {
    try {
      const attempt = read().attempts[kind];
      if (!attempt) return { ok: false, code: 'no_matching_attempt' };
      if (attempt.status === 'persisted' && attempt.payloadFingerprint === fingerprint) return { ok: false, code: 'already_persisted', attempt };
      if (attempt.status === 'conflict') return { ok: false, code: 'idempotency_conflict' };
      if (attempt.payloadFingerprint !== fingerprint) return { ok: false, code: 'payload_changed_requires_confirmation' };
      if (now() - attempt.createdAt >= ttl && attempt.status !== 'persisted') return { ok: false, code: 'attempt_expired_unknown_outcome' };
      return { ok: true, attempt };
    } catch { return failed; }
  }
  function prepareAttempt(kind: V2Kind, fingerprint: string): AttemptResult {
    const previous = getAttempt(kind, fingerprint);
    if (previous.ok || previous.code !== 'no_matching_attempt') return previous;
    return make(kind, fingerprint);
  }
  function make(kind: V2Kind, fingerprint: string): AttemptResult {
    try {
      if (!/^[a-f0-9]{64}$/.test(fingerprint)) return failed;
      const bytes = new Uint8Array(24);
      if (options.random) options.random(bytes);
      else { const c = typeof window !== 'undefined' ? window.crypto : undefined; if (!c?.getRandomValues) return failed; c.getRandomValues(bytes); }
      const key = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
      const record = read(); const attempt: Attempt = { key, payloadFingerprint: fingerprint, createdAt: now(), status: 'pending' };
      record.attempts[kind] = attempt;
      return write(record) ? { ok: true, attempt } : failed;
    } catch { return failed; }
  }
  function update(kind: V2Kind, key: string, change: (attempt: Attempt) => Attempt): AttemptResult {
    try { const record = read(); const current = record.attempts[kind]; if (!current || current.key !== key) return { ok: false, code: 'no_matching_attempt' };
      const attempt = change(current); record.attempts[kind] = attempt; return write(record) ? { ok: true, attempt } : failed;
    } catch { return failed; }
  }
  return {
    getAttempt, prepareAttempt,
    markUncertain: (kind: V2Kind, key: string) => update(kind, key, (a) => ({ ...a, status: 'uncertain' })),
    markRejected: (kind: V2Kind, key: string, _reason: string) => update(kind, key, (a) => ({ ...a, status: 'rejected' })),
    markConflict: (kind: V2Kind, key: string) => update(kind, key, (a) => ({ ...a, status: 'conflict' })),
    markPersisted: (kind: V2Kind, key: string, enquiryId: string) => update(kind, key, (a) => ({ ...a, status: 'persisted', enquiryId, persistedAt: now() })),
    startNewAttempt: (kind: V2Kind, fingerprint: string, explicitConfirmation: boolean): AttemptResult => {
      if (!explicitConfirmation) return { ok: false, code: 'payload_changed_requires_confirmation' };
      try { const record = read(); const prev = record.attempts[kind];
        if (prev && prev.payloadFingerprint === fingerprint && prev.status === 'persisted') return { ok: false, code: 'already_persisted' };
        return make(kind, fingerprint);
      } catch { return failed; }
    },
  };
}

function readErrors(value: unknown): Record<string, string[]> { if (!object(value)) return {}; return Object.fromEntries(Object.entries(value).flatMap(([k, v]) => {
  const messages = Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : typeof v === 'string' ? [v] : [];
  return messages.length ? [[k, messages]] : [];
})); }
function failure(code: string, httpStatus: number, body: unknown, uncertain: boolean): EnquiryV2Failure {
  const err = object(body) && object(body.error) ? body.error : {};
  const requestId = typeof err.requestId === 'string' ? err.requestId : undefined;
  const products = Array.isArray(err.products) ? err.products.filter((p): p is string => typeof p === 'string' && UUID.test(p)) : [];
  return { ok: false, code: typeof err.code === 'string' ? err.code : code, httpStatus, ...(requestId ? { requestId } : {}),
    fieldErrors: readErrors(err.fields), unavailableProductIds: products, retryable: uncertain || httpStatus === 429 || httpStatus >= 500, uncertain };
}
export async function submitProductEnquiryV2(payload: ProductEnquiryV2Request, idempotencyKey: string): Promise<EnquiryV2Result> { return submitV2(payload, idempotencyKey); }
export async function submitCartEnquiryV2(payload: CartEnquiryV2Request, idempotencyKey: string): Promise<EnquiryV2Result> { return submitV2(payload, idempotencyKey); }
async function submitV2(payload: EnquiryV2Request, key: string): Promise<EnquiryV2Result> {
  if (!KEY.test(key)) throw new TypeError('A valid Idempotency-Key is required.');
  const body = serializeEnquiryV2(payload);
  let response: Response;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 12000);
  try { response = await fetch('/api/v1/enquiries', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': key }, body, signal: controller.signal }); }
  catch { return failure(controller.signal.aborted ? 'timeout' : 'network_error', 0, undefined, true); }
  finally { clearTimeout(timeout); }
  let parsed: unknown; try { parsed = await response.json(); } catch { return failure('protocol_error', response.status, undefined, true); }
  if (!response.ok) { const result = failure('request_failed', response.status, parsed, [0, 429, 503].includes(response.status) || response.status >= 500); return result; }
  const data = object(parsed) && object(parsed.data) ? parsed.data : undefined;
  if (![200, 201].includes(response.status) || !data || data.status !== 'persisted' || typeof data.enquiryId !== 'string' || !UUID.test(data.enquiryId) || typeof data.notificationStatus !== 'string') return failure('protocol_error', response.status, parsed, true);
  return { ok: true, status: 'persisted', enquiryId: data.enquiryId, notificationStatus: data.notificationStatus, replayed: response.status === 200 };
}

export type SubmitWithAttemptResult = { ok: true; result: EnquiryV2Success } | { ok: false; code: AttemptError | string; result?: EnquiryV2Failure };
export async function submitWithAttempt(payload: EnquiryV2Request, manager = createAttemptManager(), explicitConfirmation = false): Promise<SubmitWithAttemptResult> {
  const fingerprint = await fingerprintEnquiryV2(payload);
  let prepared = manager.prepareAttempt(payload.type, fingerprint);
  if (!prepared.ok && explicitConfirmation && ['payload_changed_requires_confirmation', 'attempt_expired_unknown_outcome', 'idempotency_conflict'].includes(prepared.code)) {
    prepared = manager.startNewAttempt(payload.type, fingerprint, true);
  }
  if (!prepared.ok && prepared.code === 'already_persisted' && prepared.attempt?.enquiryId) {
    return { ok: true, result: { ok: true, status: 'persisted', enquiryId: prepared.attempt.enquiryId, notificationStatus: 'previously_persisted', replayed: true } };
  }
  if (!prepared.ok) return prepared;
  const result = payload.type === 'product_enquiry' ? await submitProductEnquiryV2(payload, prepared.attempt.key) : await submitCartEnquiryV2(payload, prepared.attempt.key);
  let saved: AttemptResult;
  if (result.ok) saved = manager.markPersisted(payload.type, prepared.attempt.key, result.enquiryId);
  else if (result.code === 'idempotency_conflict') saved = manager.markConflict(payload.type, prepared.attempt.key);
  else if (result.uncertain || result.httpStatus === 429) saved = manager.markUncertain(payload.type, prepared.attempt.key);
  else saved = manager.markRejected(payload.type, prepared.attempt.key, result.code);
  if (!saved.ok) return { ok: false, code: saved.code, result: result.ok ? undefined : result };
  return result.ok ? { ok: true, result } : { ok: false, code: result.code, result };
}
