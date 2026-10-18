import type { Category } from '@/types/catalog';

// Category sections nested under the shop route.
export const categories: Category[] = [
  { id: 'outdoor', slug: 'outdoor-pots', path: '/shop/outdoor-pots', name: 'Outdoor Pots', heading: 'Outdoor Planters for Projects Across the UAE', seoTitle: 'Outdoor Planters in Dubai & UAE | Pots & Planters', description: 'Explore pots and planters for terraces, entrances and open-air spaces.' },
  { id: 'indoor', slug: 'indoor-pots', path: '/shop/indoor-pots', name: 'Indoor Pots', heading: 'Indoor Planters for Projects Across the UAE', seoTitle: 'Indoor Planters Supplier in UAE | Pots & Planters', description: 'Explore decorative pots and sculptural forms for interiors.' },
  { id: 'artificial', slug: 'artificial-plants', path: '/shop/artificial-plants', name: 'Artificial Plants & Trees', heading: 'Artificial Plants & Trees for UAE Projects', seoTitle: 'Artificial Plants & Trees in Dubai, UAE | Pots & Planters', description: 'Explore sample greenery for interior projects.' },
];

export const categoryPath = (id: string) => categories.find((category) => category.id === id)!.path;
