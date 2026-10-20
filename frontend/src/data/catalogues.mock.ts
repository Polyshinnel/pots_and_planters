import type { Catalogue } from '@/types/catalog';

// Existing FE-02 catalogue titles and real cover assets. Category assignments are
// illustrative until Laravel supplies editorial relationships; no PDF URLs are invented.
export const catalogues: Catalogue[] = [
  {
    id: 'royal', slug: 'royal-planters', title: 'Royal Planters',
    description: 'Discover planter forms and finishes in the Royal Planters catalogue.',
    coverImage: '/images/home/royal-planters.jpg',
    coverAlt: 'Royal Planters catalogue cover showing tall textured planters with flowering plants',
    categoryIds: ['outdoor', 'indoor'], isPublished: true,
  },
  {
    id: 'plants', slug: 'lifelike-plants', title: 'Lifelike Plants',
    description: 'Explore artificial greenery and arrangements in the Lifelike Plants catalogue.',
    coverImage: '/images/home/lifelike-plants.jpg',
    coverAlt: 'Lifelike Plants catalogue cover with a white orchid arrangement',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'trees', slug: 'lifelike-trees', title: 'Lifelike Trees',
    description: 'Find inspiration for your next space in the Lifelike Trees catalogue.',
    coverImage: '/images/home/lifelike-trees.jpg',
    coverAlt: 'Lifelike Trees catalogue cover with detailed green foliage',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'cacti', slug: 'lifelike-cacti', title: 'Lifelike Cacti',
    description: 'Explore sculptural artificial greenery in the Lifelike Cacti catalogue.',
    coverImage: '/images/home/lifelike-cacti.jpg',
    coverAlt: 'Lifelike Cacti catalogue cover showing tall cacti against an interior wall',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'project', slug: 'project-catalogue', title: 'Project Catalogue',
    description: 'Browse artificial greenery inspiration in the Project Catalogue of Lifelike Plants.',
    coverImage: '/images/home/project-catalogue.jpg',
    coverAlt: 'Project Catalogue of Lifelike Plants cover with softly focused leaves',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'trees-cacti', slug: 'trees-and-cacti', title: 'Trees & Cacti',
    description: 'Discover artificial trees and cacti for interior arrangements.',
    coverImage: '/images/home/trees-and-cacti.jpg',
    coverAlt: 'Lifelike Trees and Cacti catalogue cover showing potted cacti beside a sofa',
    categoryIds: ['artificial'], isPublished: true,
  },
];
