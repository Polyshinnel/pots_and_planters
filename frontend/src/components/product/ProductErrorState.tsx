'use client';

import { Button, Container } from '@mantine/core';
import { useRouter } from 'next/navigation';

export function ProductErrorState({ onRetry }: { onRetry?: () => void }) {
  const router = useRouter();
  return <Container py="xl"><h1>Product unavailable</h1><p role="alert">We could not load this product. Please try again.</p><Button onClick={onRetry ?? (() => router.refresh())}>Try again</Button></Container>;
}
