import type { ProductPreview } from '@/types/home';

// Product fixtures remain available only to the legacy Solutions/cart fixture adapter.
// The public home Product section reads the Laravel Product API.
export const featuredProducts: ProductPreview[] = [
  { id: '25526', slug: 'coral-planter-25526', name: 'Coral Planter', specifications: { diameterCm: 67, heightCm: 67, finish: 'Bronze patina' }, sku: '25526', image: { src: '/images/home/products/product-1.webp', alt: 'Round textured bronze planter' }, price: { amount: 1462, currency: 'AED' } },
  { id: '24182', slug: 'magnifico-floor-vase-24182', name: 'Magnifico Floor Vase', specifications: { diameterCm: 35, heightCm: 72, finish: 'Antique bronze' }, sku: '24182', image: { src: '/images/home/products/product-2.webp', alt: 'Tall antique bronze floor planter' }, price: { amount: 1780, currency: 'AED' } },
  { id: '17310', slug: 'pandora-floor-vase-17310', name: 'Pandora Floor Vase', specifications: { diameterCm: 50, heightCm: 125, finish: 'Silver leaf' }, sku: '17310', image: { src: '/images/home/products/product-3.webp', alt: 'Tall silver leaf floor planter' }, price: { amount: 1240, currency: 'AED' } },
  { id: '25530', slug: 'coral-floor-vase-25530', name: 'Coral Floor Vase', specifications: { diameterCm: 52, heightCm: 120, finish: 'Bronze patina' }, sku: '25530', image: { src: '/images/home/products/product-4.webp', alt: 'Set of three tall textured bronze planters' }, price: { amount: 1990, currency: 'AED' } },
  { id: 'featured-05', slug: 'textured-bronze-planter-05', name: 'Textured bronze planter', sku: 'PP-520', image: { src: '/images/home/products/product-5.webp', alt: 'Wide textured bronze planter' }, price: { amount: 850, currency: 'AED' } },
  { id: 'featured-06', slug: 'textured-bronze-planter-06', name: 'Textured bronze planter', sku: 'PP-521', image: { src: '/images/home/products/product-6.webp', alt: 'Rounded textured bronze planter' }, price: { amount: 1390, currency: 'AED' } },
  { id: 'featured-07', slug: 'bronze-bowl-planter-07', name: 'Bronze bowl planter', sku: 'PP-522', image: { src: '/images/home/products/product-7.webp', alt: 'Low rounded bronze planter with a softly aged finish' }, price: { amount: 620, currency: 'AED' } },
  { id: 'featured-08', slug: 'silver-bowl-planter-08', name: 'Silver bowl planter', sku: 'PP-523', image: { src: '/images/home/products/product-8.webp', alt: 'Low rounded silver planter' }, price: { amount: 1100, currency: 'AED' } },
];
