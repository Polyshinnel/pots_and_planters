'use client';

import { CatalogErrorState } from '@/components/catalog/CatalogErrorState';
export default function CatalogError({ reset }: { reset: () => void }) {
  return <CatalogErrorState onRetry={reset} />;
}
