'use client';

import type { DownloadGrant, LeadPayload, LeadSubmissionResult } from './contracts';

type EnquiryResponse = {
  data?: {
    status?: unknown;
    enquiryId?: unknown;
    downloadGrant?: unknown;
  };
  error?: { code?: unknown; fields?: unknown };
  errors?: Record<string, unknown>;
};

export class EnquiryApiError extends Error {
  constructor(readonly status: number, readonly code: string, readonly fieldErrors: Record<string, string>) {
    super(messageFor(status, code));
    this.name = 'EnquiryApiError';
  }
}

export const enquirySubmissionsEnabled = () => process.env.NEXT_PUBLIC_ENQUIRIES_ENABLED === 'true';

function messageFor(status: number, code: string): string {
  if (status === 409 || code === 'idempotency_conflict') return 'This submission changed while it was being retried. Review your details and submit again.';
  if (status === 404) return 'This catalogue is no longer available. Your details have been retained; choose an available catalogue to continue.';
  if (status === 429) return 'Too many requests were made. Please wait a moment and try again.';
  if (status === 422) return 'Please review the highlighted fields and try again.';
  if (status >= 500) return 'The service could not confirm your request. Your details are retained; retry safely.';
  return 'Your request could not be confirmed. Your details are retained; please try again.';
}

function readFieldErrors(value: unknown): Record<string, string> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  return Object.fromEntries(Object.entries(value).flatMap(([field, messages]) => {
    const first = Array.isArray(messages) ? messages[0] : messages;
    return typeof first === 'string' ? [[field, first]] : [];
  }));
}

function isGrant(value: unknown): value is DownloadGrant {
  if (!value || typeof value !== 'object') return false;
  const grant = value as Record<string, unknown>;
  return typeof grant.token === 'string' && /^[a-f0-9]{64}$/.test(grant.token)
    && typeof grant.expiresAt === 'string' && Number.isFinite(Date.parse(grant.expiresAt))
    && grant.downloadPath === '/api/v1/catalogues/download'
    && grant.enquiryHeader === 'X-Catalogue-Enquiry';
}

function newIdempotencyKey(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const bytes = new Uint8Array(24);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/** Reuses one key for the same payload while the component stays mounted after an uncertain result. */
export async function submitEnquiry(payload: LeadPayload, key: string): Promise<LeadSubmissionResult> {
  if (!enquirySubmissionsEnabled()) return { status: 'unavailable' };
  const bodyPayload = JSON.stringify(payload);
  let response: Response;
  try {
    response = await fetch('/api/v1/enquiries', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': key },
      body: bodyPayload,
    });
  } catch {
    throw new EnquiryApiError(0, 'network_error', {});
  }
  let body: EnquiryResponse = {};
  try { body = await response.json() as EnquiryResponse; } catch { /* Handle malformed/non-JSON responses as API failures below. */ }
  if (!response.ok) throw new EnquiryApiError(response.status, typeof body.error?.code === 'string' ? body.error.code : '', readFieldErrors(body.error?.fields ?? body.errors));
  const data = body.data;
  if (data?.status !== 'persisted' || typeof data.enquiryId !== 'string' || !data.enquiryId) {
    throw new EnquiryApiError(502, 'invalid_response', {});
  }
  const grant = payload.type === 'catalogue_download' && isGrant(data.downloadGrant) ? data.downloadGrant : undefined;
  if (payload.type === 'catalogue_download' && !grant) throw new EnquiryApiError(502, 'missing_download_grant', {});
  return { status: 'persisted', leadId: data.enquiryId, ...(grant ? { downloadGrant: grant } : {}) };
}

export function newEnquiryIdempotencyKey(): string { return newIdempotencyKey(); }

export async function downloadCatalogue(grant: DownloadGrant, enquiryId: string): Promise<void> {
  let response: Response;
  try {
    response = await fetch(grant.downloadPath, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Authorization: `Bearer ${grant.token}`, [grant.enquiryHeader]: enquiryId },
    });
  } catch {
    throw new Error('Your request was saved, but the catalogue download was interrupted. Retry the download below.');
  }
  if (!response.ok) {
    if (response.status === 410) throw new Error('Your request was saved, but download access has expired. Submit the form again to receive fresh access.');
    if (response.status === 404) throw new Error('Your request was saved, but this catalogue is no longer available.');
    if (response.status === 429) throw new Error('Your request was saved, but downloads are temporarily limited. Please retry shortly.');
    throw new Error('Your request was saved, but the catalogue could not be downloaded. Retry below.');
  }
  if (!response.headers.get('content-type')?.toLowerCase().includes('application/pdf')) {
    throw new Error('Your request was saved, but the server did not return a PDF. Retry below.');
  }
  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = 'catalogue.pdf';
  anchor.hidden = true;
  document.body.append(anchor);
  anchor.click();
  anchor.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
}
