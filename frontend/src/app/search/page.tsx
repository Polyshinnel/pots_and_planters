import { Text } from '@mantine/core';
import { PreviewPage } from '@/components/layout/PreviewPage';

export default async function Search({ searchParams }: { searchParams: Promise<{ q?: string | string[] }> }) {
  const { q } = await searchParams;
  const query = typeof q === 'string' ? q : '';
  return <PreviewPage title="Search preview">
    <Text>Temporary placeholder. Search results are not connected yet.</Text>
    <Text style={{ overflowWrap: 'anywhere' }}>{query ? `Your query: ${query}` : 'Enter a query using the search in the header.'}</Text>
  </PreviewPage>;
}
