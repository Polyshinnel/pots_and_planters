'use client';

import { Button, Container } from '@mantine/core';
export default function CatalogError({ reset }: { reset: () => void }) {
  return <Container py="xl"><h1>Catalogue unavailable</h1><p role="alert">We could not load the products. Please try again.</p><Button onClick={reset}>Try again</Button></Container>;
}
