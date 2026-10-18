import Link from 'next/link';
import { Container } from '@mantine/core';
import { notFound } from 'next/navigation';
import { catalogRepository } from '@/lib/catalog/repository';
import { SHOP_PATH } from '@/lib/catalog/domain';

export const metadata = { title: 'Product preview | Pots & Planters', robots: { index: false, follow: true } };
export default async function ProductPlaceholder({ params }: { params: Promise<{ slug: string }> }) {
  const product = await catalogRepository.getProductBySlug((await params).slug);
  if (!product) notFound();
  return <Container py="xl">
    <p>Collection preview</p><h1>{product.name}</h1><p>Model: {product.sku}</p>
    <p>Product details will be available in a later release. This catalogue preview does not offer ordering.</p>
    <Link href={SHOP_PATH}>Browse all products</Link>
  </Container>;
}
