import 'server-only';

/** Runtime-only Docker URL. Import exclusively from server components/actions. */
export async function serverApiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  if (!path.startsWith('/') || path.startsWith('//')) {
    throw new Error('API paths must start with a single slash');
  }
  const baseUrl = process.env.API_INTERNAL_URL;
  if (!baseUrl) throw new Error('API_INTERNAL_URL is required for server API calls');
  const response = await fetch(`${baseUrl.replace(/\/$/, '')}${path}`, {
    ...init,
    cache: 'no-store',
    headers: { Accept: 'application/json', ...init?.headers },
  });
  if (!response.ok) throw new Error(`API request failed: ${response.status}`);
  return response.json() as Promise<T>;
}
