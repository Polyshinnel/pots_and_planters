import type { Product } from '@/types/catalog';
import type { SolutionScene, ResolvedSolutionScene } from '@/types/solutions';
import { productPath } from '@/lib/catalog/domain';
import { validQuantity } from '@/lib/enquiry/domain';

export const byOrder = <T extends { sortOrder: number; id: string }>(a: T, b: T) => a.sortOrder - b.sortOrder || a.id.localeCompare(b.id);

/** Defensive read projection. Invalid associations must never create actionable markers. */
export function resolveScene(scene: SolutionScene, products: Product[]): ResolvedSolutionScene {
  const images = scene.images.filter((image) => image.src.trim() && Number.isFinite(image.width) && image.width > 0 && Number.isFinite(image.height) && image.height > 0).sort(byOrder);
  const items = scene.items.filter((item) => validQuantity(item.recommendedQuantity)).sort(byOrder).map((item) => {
    const product = products.find((candidate) => candidate.id === item.productId && candidate.published);
    return { ...item, matchType: item.matchType === 'exact' && item.matchEvidence?.trim() ? 'exact' as const : 'suggested' as const,
      product, href: product ? productPath(product.slug, product.primaryCategoryId ?? product.categoryIds[0]) : undefined };
  });
  const hotspots = scene.hotspots.filter((hotspot) => images.some((image) => image.id === hotspot.imageId)
    && items.some((item) => item.id === hotspot.solutionItemId && item.product && item.href)
    && Number.isFinite(hotspot.x) && hotspot.x >= 0 && hotspot.x <= 1
    && Number.isFinite(hotspot.y) && hotspot.y >= 0 && hotspot.y <= 1).sort(byOrder);
  return { ...scene, images, items, hotspots, coverImageId: images.find((image) => image.id === scene.coverImageId)?.id ?? images[0]?.id ?? null };
}
