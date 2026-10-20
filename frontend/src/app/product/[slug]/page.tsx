import { notFound, permanentRedirect } from 'next/navigation';
import { catalogRepository } from '@/lib/catalog/repository';
import { getCategories } from '@/lib/catalog/categories.server';
import { primaryCategory } from '@/lib/catalog/product-detail';
import { productPath } from '@/lib/catalog/domain';

type Props = { params: Promise<{ slug: string }> };

export default async function LegacyProductRoute({ params }: Props) {
  const product = await catalogRepository.getProductBySlug((await params).slug);
  if (!product) notFound();
  const category = primaryCategory(product, await getCategories());
  if (!category) notFound();
  permanentRedirect(productPath(product.slug, category.id));
}
