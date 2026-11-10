'use client';

import { ProductErrorState } from '@/components/product/ProductErrorState';

export default function ProductError({ reset }: { reset: () => void }) {
  return <ProductErrorState onRetry={reset} />;
}
