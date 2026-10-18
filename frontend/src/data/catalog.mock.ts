import { featuredProducts } from './home.mock';
import type { Product } from '@/types/catalog';

export { categories } from '@/config/catalog';

// Non-mutating adapter preserves FE-02's accepted fixtures and all their names/SKUs/images.
// Only the first four identities are transcribed historical products. Attributes and category
// assignments below are illustrative, not confirmed material, finish or suitability claims.
export const products: Product[] = [
  ...featuredProducts.map((product, index): Product => ({
    id: product.id, slug: product.slug, name: product.name, sku: product.sku ?? product.id, specifications: product.specifications,
    image: product.image?.src ?? null, categoryIds: index > 5 ? ['indoor'] : ['outdoor', 'indoor'],
    attributes: { material: [index % 2 ? 'polystone' : 'fiberglass'], color: [index === 2 || index === 7 ? 'silver' : 'bronze'], size: [index < 4 ? 'large' : 'medium'], usage: ['indoor', 'outdoor'] },
    priceAed: product.price?.amount ?? null, published: true, featured: index < 4, sample: index >= 4,
  })),
  ...Array.from({ length: 8 }, (_, index): Product => ({
    id: `sample-${index + 1}`, slug: `sample-${index + 1}`, name: `${['Cylinder planter', 'Round bowl', 'Tall vase', 'Square planter', 'Low bowl', 'Column planter', 'Artificial olive tree', 'Artificial palm'][index]} — sample`,
    sku: `DEMO-${String(index + 1).padStart(3, '0')}`, image: null,
    categoryIds: index > 5 ? ['artificial'] : index % 2 ? ['indoor'] : ['outdoor'],
    attributes: { material: [index > 5 ? 'synthetic' : index % 2 ? 'polystone' : 'fiberglass'], color: [index > 5 ? 'green' : index % 2 ? 'gray' : 'black'], size: [index % 3 ? 'medium' : 'small'], usage: index > 5 ? ['indoor'] : ['outdoor'] },
    priceAed: [450, 780, 1250, 1600, 980, 1350, 1800, 2000][index], published: true, featured: false, sample: true,
  })),
  { id: 'unpublished', slug: 'unpublished-sample', name: 'Unpublished sample', sku: 'DEMO-HIDDEN', image: null, categoryIds: ['outdoor'], attributes: {}, priceAed: null, published: false, featured: false, sample: true },
];
