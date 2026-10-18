import type { CataloguePreview, HomeCategory, ProductPreview, ProjectSolution } from '@/types/home';

// FE-02 local editorial data. Replace this module with an API adapter in a later stage.
// Sources and asset gaps: docs/frontend/FE-02-home-inventory.md.
export const homeCopy = {
  hero: {
    eyebrow: 'Project support across the UAE · Based in Dubai',
    title: 'Considered greenery. Distinctive spaces.',
    description: 'Pots, planters and artificial greenery for residential and commercial projects across the UAE. Speak with us about your project requirements.',
    image: { src: '/images/home/royal-planters.jpg', alt: 'Royal Planters catalogue cover showing tall textured planters with flowering plants' },
    caption: 'A look inside the collection · Royal Planters catalogue',
  },
  categories: { eyebrow: 'The collection', title: 'Find your starting point', description: 'Explore the three directions of our collection, from outdoor spaces to interior greenery.' },
  solutions: { eyebrow: 'For designers, architects & project teams', title: 'Project support across the UAE', description: 'Tell us what you are sourcing for a hotel, restaurant, office or home, and discuss the options with our Dubai team.' },
  featured: { eyebrow: 'Selected from the collection', title: 'Featured products', description: 'A selection from our previous collection. Please discuss current availability and pricing with us.' },
  catalogues: { eyebrow: 'Materials for your next idea', title: 'Explore our catalogues', description: 'Discover planter forms, finishes and artificial greenery in our catalogue collection.' },
  about: { eyebrow: 'About Pots & Planters', title: 'Based in Dubai. Working across the UAE.', description: 'Our Dubai office supports residential and commercial projects across all seven emirates. Explore indoor and outdoor pots, planters and artificial plants, or tell us what you are sourcing for your space.' },
  contact: { eyebrow: 'Let’s talk about your project', title: 'Tell us what you are planning', description: 'Share your project type, emirate, preferred planters and approximate quantity. Ask us about available project support and delivery options.', message: 'Hello Pots & Planters, I would like to discuss a project in the UAE.' },
};

// Confirmed top-level directions and URLs from legacy home.tpl; routes are future pages.
export const homeCategories: HomeCategory[] = [
  { id: 'outdoor', name: 'Outdoor Pots', description: 'For terraces, entrances and open-air spaces.', href: '/shop/outdoor-pots', image: { src: '/images/home/categories/outdoor-pots.webp', alt: 'Three textured outdoor planters with greenery on a timber terrace' } },
  { id: 'indoor', name: 'Indoor Pots', description: 'Forms and finishes for considered interiors.', href: '/shop/indoor-pots', image: { src: '/images/home/categories/indoor-planters.webp', alt: 'Decorative indoor planters with flowering plants in a bright interior' } },
  { id: 'artificial', name: 'Artificial Plants', description: 'Explore lifelike plants and trees.', href: '/shop/artificial-plants', image: { src: '/images/home/categories/artificial-plants.webp', alt: 'Potted artificial trees arranged in a contemporary interior' } },
];

export const projectSolutions: ProjectSolution[] = [
  { id: 'selection', title: 'Project selection', description: 'Get help selecting pots, planters and artificial greenery for your project.' },
  { id: 'placement', title: 'On-site planter placement', description: 'Ask whether temporary on-site placement is available for your project.' },
  { id: 'wholesale', title: 'Wholesale terms', description: 'Enquire about terms for project and volume orders.' },
  { id: 'delivery', title: 'Delivery across the UAE', description: 'Discuss delivery availability for your project location.' },
];

// First four names/SKUs transcribed from main_desktop.png; PP-520–PP-523 are mock entries.
// Slugs are proposals, not legacy URLs.
// These illustrative prices are mock values, not current commercial prices.
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

export const homeCatalogues: CataloguePreview[] = [
  { id: 'royal', title: 'Royal Planters', image: homeCopy.hero.image },
  { id: 'plants', title: 'Lifelike Plants', image: { src: '/images/home/lifelike-plants.jpg', alt: 'Lifelike Plants catalogue cover with a white orchid arrangement' } },
  { id: 'trees', title: 'Lifelike Trees', image: { src: '/images/home/lifelike-trees.jpg', alt: 'Lifelike Trees catalogue cover with detailed green foliage' } },
  { id: 'cacti', title: 'Lifelike Cacti', image: { src: '/images/home/lifelike-cacti.jpg', alt: 'Lifelike Cacti catalogue cover showing tall cacti against an interior wall' } },
  { id: 'project', title: 'Project Catalogue', image: { src: '/images/home/project-catalogue.jpg', alt: 'Project Catalogue of Lifelike Plants cover with softly focused leaves' } },
  { id: 'trees-cacti', title: 'Trees & Cacti', image: { src: '/images/home/trees-and-cacti.jpg', alt: 'Lifelike Trees and Cacti catalogue cover showing potted cacti beside a sofa' } },
];

