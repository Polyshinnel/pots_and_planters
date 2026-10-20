import { categories } from '@/config/catalog';
import type { SolutionCategory, SolutionScene } from '@/types/solutions';

const collections = (...ids: ('outdoor' | 'indoor' | 'artificial')[]) => ids.map((id) => categories.find((category) => category.id === id)!);

// Existing site imagery illustrates applications; these are not company case studies.
// Content is independent of the Editorial layout so a Gallery can reuse it later.
const editorialCategories = [
  {
    id: 'hospitality',
    title: 'Hospitality & Hotels',
    headline: 'Welcoming Spaces, Considered Details',
    intro: 'From hotel entrances and lobbies to terraces and guest areas, planters can frame circulation, introduce greenery and create a more inviting atmosphere. Explore statement pieces, coordinated arrangements and artificial trees suited to the scale of your space.',
    image: { src: '/images/home/hero-desktop.webp', alt: 'Sculptural bronze planter with a tree beside a sunlit architectural entrance' },
    collections: collections('outdoor', 'indoor', 'artificial'),
  },
  {
    id: 'restaurants',
    title: 'Restaurants & Cafés',
    headline: 'Define the Dining Experience',
    intro: 'Planters can help define outdoor seating areas, soften transitions and introduce visual rhythm to dining spaces. Explore options for restaurant terraces, café interiors and hospitality environments where form, proportion and finish matter.',
    image: { src: '/images/about/project-lounge.jpg', alt: 'Yellow lounge seating framed by tall dark planters, greenery and metal mesh partitions' },
    collections: collections('outdoor', 'indoor', 'artificial'),
  },
  {
    id: 'offices',
    title: 'Offices & Commercial Spaces',
    headline: 'Greenery for Productive Spaces',
    intro: 'Reception areas, shared workspaces and meeting zones benefit from considered greenery and carefully chosen planters. Find forms and finishes that complement contemporary interiors, from understated accents to larger focal points.',
    image: { src: '/images/home/categories/artificial-plants.webp', alt: 'Potted trees in dark rounded planters arranged against a neutral interior wall' },
    collections: collections('indoor', 'artificial'),
  },
  {
    id: 'residential',
    title: 'Villas & Residential Projects',
    headline: 'A Natural Extension of Home',
    intro: 'Bring structure and character to entrances, courtyards, terraces and interior spaces. Explore planters and artificial greenery that complement a residential design concept, whether the brief calls for a single statement piece or a coordinated selection.',
    image: { src: '/images/home/categories/outdoor-pots.webp', alt: 'Three pale textured planters with clipped greenery on a timber terrace' },
    collections: collections('outdoor', 'indoor', 'artificial'),
  },
  {
    id: 'retail',
    title: 'Retail & Public Spaces',
    headline: 'Make Shared Spaces More Inviting',
    intro: 'In retail environments, galleries and shared public areas, planters can create focal points, soften architectural edges and help define zones. Browse collections with shapes, sizes and finishes that suit the visual language of your space.',
    image: { src: '/images/home/categories/indoor-planters.webp', alt: 'Tall decorative bronze planters with flowering plants beside a bright window' },
    collections: collections('indoor', 'outdoor', 'artificial'),
  },
];

export const solutions: SolutionCategory[] = editorialCategories.map((category, index) => ({
  ...category, slug: category.id, sortOrder: index, isPublished: true,
}));

export const solutionScenes: SolutionScene[] = [
  {
    id: 'scene-hotel-lobby', slug: 'contemporary-hotel-lobby', categoryId: 'hospitality',
    title: 'Contemporary Hotel Lobby', summary: 'Sculptural forms anchor a generous reception space. Explore suggested catalogue alternatives for a statement planter and a lower accent.',
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
    title: 'Restaurant Terrace', summary: 'Layer greenery around a shaded dining area. These catalogue suggestions explore statement and accent forms; outdoor suitability requires confirmation.',
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
