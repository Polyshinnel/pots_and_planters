import 'server-only';
import { cache } from 'react';
import { serverApiFetch } from '@/lib/api.server';
import type { ContactsApiResponse, ContactsResult } from '@/types/contacts';

/** Request-scoped memoization avoids duplicate shell/page requests without a persistent cache. */
export const getContacts = cache(async (): Promise<ContactsApiResponse> =>
  serverApiFetch<ContactsApiResponse>('/v1/contacts'),
);

export const getContactsResult = cache(async (): Promise<ContactsResult> => {
  try {
    return { status: 'success', data: (await getContacts()).data };
  } catch (error) {
    if (error instanceof Error && error.message.includes('Dynamic server usage')) throw error;
    console.error('[contacts] Laravel API unavailable', error);
    return { status: 'error' };
  }
});
