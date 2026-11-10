'use client';

import { Button, Container } from '@mantine/core';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export function CatalogErrorState({ onRetry, status, clearHref }: { onRetry?: () => void; status?: number; clearHref?: string }) {
  const router = useRouter();
  const invalidFilters = status === 422;
  return <Container py="xl"><h1>Catalogue unavailable</h1><p role="alert">{invalidFilters ? 'Some selected filters are invalid or unavailable. Clear them to view products.' : 'We could not load the products. Please try again.'}</p>{invalidFilters && clearHref ? <Button component={Link} href={clearHref} variant="light">Clear filters</Button> : <Button onClick={onRetry ?? (() => router.refresh())}>Try again</Button>}</Container>;
}
