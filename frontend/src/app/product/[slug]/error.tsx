'use client';

import { Button, Container } from '@mantine/core';

export default function LegacyProductError({ reset }: { reset: () => void }) {
  return <Container py="xl"><h1>Product unavailable</h1><p role="alert">We could not load this product. Please try again.</p><Button onClick={reset}>Try again</Button></Container>;
}
