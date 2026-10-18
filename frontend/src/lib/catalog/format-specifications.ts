import type { ProductSpecifications } from '@/types/catalog';

export function formatSpecifications(specifications?: ProductSpecifications): string {
  if (!specifications) return '';
  const dimensions: string[] = [];
  if (typeof specifications.diameterCm === 'number' && Number.isFinite(specifications.diameterCm) && specifications.diameterCm > 0) dimensions.push(`Ø ${specifications.diameterCm}`);
  if (typeof specifications.heightCm === 'number' && Number.isFinite(specifications.heightCm) && specifications.heightCm > 0) dimensions.push(`H ${specifications.heightCm}`);
  const size = dimensions.length ? `${dimensions.join(' × ')} cm` : '';
  return [size, specifications.finish?.trim()].filter(Boolean).join(' · ');
}
