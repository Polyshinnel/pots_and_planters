import { notFound, permanentRedirect } from 'next/navigation';
import { getProductBySlug } from '@/lib/catalog/products.server';
import { getCategories } from '@/lib/catalog/categories.server';
import { primaryCategory } from '@/lib/catalog/product-detail';
import { productPath } from '@/lib/catalog/domain';

type Props = { params: Promise<{ slug: string }> };

export default async function LegacyProductRoute({ params }: Props) {
  const product = await getProductBySlug((await params).slug);
  if (!product) notFound();
  const category = primaryCategory(product, await getCategories());
  if (!category) notFound();
  permanentRedirect(product.canonicalPath ?? productPath(product.slug, category.id));
}
