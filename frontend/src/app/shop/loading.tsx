import { Container } from '@mantine/core';

export default function CatalogLoading() {
  return <Container py="xl"><p role="status" aria-live="polite">Loading catalogue…</p></Container>;
}
