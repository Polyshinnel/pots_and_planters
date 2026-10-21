import type { SolutionCategorySource, SolutionScene } from '@/types/solutions';

// Existing site imagery illustrates applications; these are not company case studies.
// Content is independent of the Editorial layout so a Gallery can reuse it later.
const editorialCategories = [
  {
    id: 'hospitality',
    title: 'Hospitality & Hotels',
    headline: 'Welcoming Spaces, Considered Details',
    seoHeading: 'Planters & Artificial Greenery for Hotels and Hospitality',
    intro: "Hotel lobbies, reception areas, guest lounges and outdoor terraces call for greenery that complements the architecture and guest experience. Explore large statement planters, decorative indoor pots and artificial trees for different hospitality environments.\n\nBrowse our interior concepts to see how selected products can work together, review individual models and request a quotation based on your project's quantities and requirements.",
    image: { src: '/images/home/hero-desktop.webp', alt: 'Sculptural bronze planter with a tree beside a sunlit architectural entrance' },
    collectionIds: ['outdoor', 'indoor', 'artificial'],
  },
  {
    id: 'restaurants',
    title: 'Restaurants & Cafés',
    headline: 'Define the Dining Experience',
    seoHeading: 'Restaurant Planters for Dining Areas and Outdoor Terraces',
    intro: 'Planters can help define seating zones, frame entrances and introduce greenery into restaurant interiors and outdoor dining areas. Explore combinations of decorative pots and artificial plants for cafés, restaurants and hospitality venues.\n\nView suggested product arrangements, compare sizes and finishes, and build an enquiry for your restaurant or terrace project.',
    image: { src: '/images/about/project-lounge.jpg', alt: 'Yellow lounge seating framed by tall dark planters, greenery and metal mesh partitions' },
    collectionIds: ['outdoor', 'indoor', 'artificial'],
  },
  {
    id: 'offices',
    title: 'Offices & Commercial Spaces',
    headline: 'Greenery for Productive Spaces',
    seoHeading: 'Office Planters & Artificial Trees for Commercial Interiors',
    intro: 'From reception desks and waiting areas to meeting rooms and shared workspaces, planters introduce natural forms into contemporary commercial interiors. Large floor pots and artificial greenery can help create focal points and visually define different areas.\n\nExplore product options for offices and corporate environments, then request a quotation for the quantities and finishes required by your project.',
    image: { src: '/images/home/categories/artificial-plants.webp', alt: 'Potted trees in dark rounded planters arranged against a neutral interior wall' },
    collectionIds: ['indoor', 'artificial'],
  },
  {
    id: 'residential',
    title: 'Villas & Residential Projects',
    headline: 'A Natural Extension of Home',
    seoHeading: 'Indoor & Outdoor Planters for Villas and Residential Projects',
    intro: 'Villas and residential developments offer opportunities to integrate planters into entrances, courtyards, patios, terraces and living spaces. The right proportions, finishes and plant combinations can complement both contemporary and traditional architecture.\n\nExplore indoor and outdoor collections, compare suitable models and discuss a coordinated selection for your residential project.',
    image: { src: '/images/home/categories/outdoor-pots.webp', alt: 'Three pale textured planters with clipped greenery on a timber terrace' },
    collectionIds: ['outdoor', 'indoor', 'artificial'],
  },
  {
    id: 'retail',
    title: 'Retail & Public Spaces',
    headline: 'Make Shared Spaces More Inviting',
    seoHeading: 'Planters & Artificial Greenery for Retail and Public Spaces',
    intro: 'Shopping centres, retail interiors, galleries and shared public areas benefit from considered greenery that complements circulation routes and architectural features. Statement planters and artificial plants can create visual interest and help define welcoming spaces.\n\nExplore decorative options for commercial environments and request a tailored quotation for your project.',
    image: { src: '/images/home/categories/indoor-planters.webp', alt: 'Tall decorative bronze planters with flowering plants beside a bright window' },
    collectionIds: ['indoor', 'outdoor', 'artificial'],
  },
];

export const solutions: SolutionCategorySource[] = editorialCategories.map((category, index) => ({
  ...category, slug: category.id, sortOrder: index, isPublished: true,
}));

export const solutionScenes: SolutionScene[] = [
  {
    id: 'scene-hotel-lobby', slug: 'contemporary-hotel-lobby', categoryId: 'hospitality',
    title: 'Contemporary Hotel Lobby', summary: 'An interior concept combining statement floor planters and decorative greenery to frame the entrance and complement a contemporary hotel lobby.',
    isConcept: true, status: 'published', sortOrder: 0, coverImageId: 'image-lobby-wide',
    images: [{ id: 'image-lobby-wide', src: '/images/solutions/contemporary-hotel-lobby.webp', alt: 'Concept hotel lobby with travertine walls, cream seating, a tall charcoal planter on the left and a low pale bowl on the right', width: 1536, height: 1024, sortOrder: 0 }],
    items: [
      { id: 'item-lobby-statement', productId: '25530', recommendedQuantity: 2, sortOrder: 0, matchType: 'suggested', displayNote: 'A tall sculptural alternative; confirm finish and planting suitability with our team.' },
      { id: 'item-lobby-accent', productId: '25526', recommendedQuantity: 1, sortOrder: 1, matchType: 'suggested', displayNote: 'A rounded accent alternative. The form and finish differ from the concept.' },
    ],
    hotspots: [
      { id: 'hotspot-lobby-statement', imageId: 'image-lobby-wide', solutionItemId: 'item-lobby-statement', x: 0.10, y: 0.73, sortOrder: 0 },
      { id: 'hotspot-lobby-accent', imageId: 'image-lobby-wide', solutionItemId: 'item-lobby-accent', x: 0.88, y: 0.79, sortOrder: 1 },
    ],
  },
  {
    id: 'scene-restaurant-terrace', slug: 'restaurant-terrace', categoryId: 'restaurants',
    title: 'Restaurant Terrace', summary: 'An outdoor dining concept using decorative planters to frame seating areas, introduce greenery and complement a contemporary restaurant terrace.',
    isConcept: true, status: 'published', sortOrder: 0, coverImageId: 'image-terrace-wide',
    images: [{ id: 'image-terrace-wide', src: '/images/solutions/restaurant-terrace.webp', alt: 'Concept restaurant terrace with woven dining chairs, a charcoal trough on the left, a large round planter on the right and a terracotta accent near the centre', width: 1536, height: 1024, sortOrder: 0 }],
    items: [
      { id: 'item-terrace-statement', productId: '25526', recommendedQuantity: 3, sortOrder: 0, matchType: 'suggested', displayNote: 'A rounded alternative to the large planter at the terrace edge; finish differs.' },
      { id: 'item-terrace-accent', productId: '24182', recommendedQuantity: 2, sortOrder: 1, matchType: 'suggested', displayNote: 'An accent alternative in antique bronze, rather than the terracotta form pictured.' },
    ],
    hotspots: [
      { id: 'hotspot-terrace-statement', imageId: 'image-terrace-wide', solutionItemId: 'item-terrace-statement', x: 0.92, y: 0.73, sortOrder: 0 },
      { id: 'hotspot-terrace-accent', imageId: 'image-terrace-wide', solutionItemId: 'item-terrace-accent', x: 0.58, y: 0.51, sortOrder: 1 },
    ],
  },
];
