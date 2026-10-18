import type { ProductSpecifications } from '@/types/catalog';

export function formatSpecifications(specifications?: ProductSpecifications): string {
  if (!specifications) return '';
  const dimensions: string[] = [];
  if (specifications.diameterCm !== undefined) dimensions.push(`Ø ${specifications.diameterCm}`);
  if (specifications.heightCm !== undefined) dimensions.push(`H ${specifications.heightCm}`);
  const size = dimensions.length ? `${dimensions.join(' × ')} cm` : '';
  return [size, specifications.finish?.trim()].filter(Boolean).join(' · ');
}
