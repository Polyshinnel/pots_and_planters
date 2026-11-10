import type { ProductSpecifications } from '@/types/catalog';

export function formatSpecifications(specifications?: ProductSpecifications | null): string {
  if (!specifications) return '';
  if (!specifications) return '';
  const dimensions: string[] = [];
  const diameterUnit = specifications.diameterUnit?.trim() || 'cm';
  const heightUnit = specifications.heightUnit?.trim() || 'cm';
  if (typeof specifications.diameterCm === 'number' && Number.isFinite(specifications.diameterCm) && specifications.diameterCm > 0) dimensions.push(`Ø ${specifications.diameterCm}${diameterUnit === heightUnit ? '' : ` ${diameterUnit}`}`);
  if (typeof specifications.heightCm === 'number' && Number.isFinite(specifications.heightCm) && specifications.heightCm > 0) dimensions.push(`H ${specifications.heightCm}${diameterUnit === heightUnit ? '' : ` ${heightUnit}`}`);
  const size = dimensions.length ? `${dimensions.join(' × ')}${diameterUnit === heightUnit ? ` ${dimensions.length === 1 && !specifications.diameterCm ? heightUnit : diameterUnit}` : ''}` : '';
  return [size, specifications.finish?.trim()].filter(Boolean).join(' · ');
}
