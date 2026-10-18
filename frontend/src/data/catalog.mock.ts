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
    ...(product.sku === '25526' ? {
      descriptionHeading: 'Coral Planter with Textured Bronze Patina',
      descriptionParagraphs: [
        'The Coral Planter has a rounded, tapered silhouette and a textured bronze patina finish. With a diameter of 67 cm and a height of 67 cm, it brings a sculptural accent to spaces where the planter itself forms part of the design.',
        'Pots & Planters works with architects, interior designers and project teams across Dubai and the UAE. Contact our team to confirm suitability for your intended setting, discuss quantities and request the latest product specifications.',
      ],
      seoTitle: 'Coral Planter 25526 — Bronze Patina | Pots & Planters UAE',
      seoDescription: 'Explore Coral Planter 25526 with a textured bronze patina finish, Ø 67 × H 67 cm. Enquire about specifications and project quantities in Dubai and the UAE.',
    } : {}),
    primaryCategoryId: index > 5 ? 'indoor' : 'outdoor',
    images: product.image ? [product.image] : [], currency: 'AED', priceVerified: false,
    attributes: { material: [index % 2 ? 'polystone' : 'fiberglass'], color: [index === 2 || index === 7 ? 'silver' : 'bronze'], size: [index < 4 ? 'large' : 'medium'], usage: ['indoor', 'outdoor'] },
    priceAed: product.price?.amount ?? null, published: true, featured: index < 4, sample: index >= 4,
  })),
  ...Array.from({ length: 8 }, (_, index): Product => ({
    id: `sample-${index + 1}`, slug: `sample-${index + 1}`, name: `${['Cylinder planter', 'Round bowl', 'Tall vase', 'Square planter', 'Low bowl', 'Column planter', 'Artificial olive tree', 'Artificial palm'][index]} — sample`,
    sku: `DEMO-${String(index + 1).padStart(3, '0')}`, image: null,
    categoryIds: index > 5 ? ['artificial'] : index % 2 ? ['indoor'] : ['outdoor'],
    primaryCategoryId: index > 5 ? 'artificial' : index % 2 ? 'indoor' : 'outdoor',
    images: [], currency: 'AED', priceVerified: false,
    attributes: { material: [index > 5 ? 'synthetic' : index % 2 ? 'polystone' : 'fiberglass'], color: [index > 5 ? 'green' : index % 2 ? 'gray' : 'black'], size: [index % 3 ? 'medium' : 'small'], usage: index > 5 ? ['indoor'] : ['outdoor'] },
    priceAed: [450, 780, 1250, 1600, 980, 1350, 1800, 2000][index], published: true, featured: false, sample: true,
  })),
  { id: 'unpublished', slug: 'unpublished-sample', name: 'Unpublished sample', sku: 'DEMO-HIDDEN', image: null, categoryIds: ['outdoor'], attributes: {}, priceAed: null, published: false, featured: false, sample: true },
];
