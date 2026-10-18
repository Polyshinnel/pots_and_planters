import type { Catalogue } from '@/types/catalog';

// Existing FE-02 catalogue titles and real cover assets. Category assignments are
// illustrative until Laravel supplies editorial relationships; no PDF URLs are invented.
export const catalogues: Catalogue[] = [
  {
    id: 'royal', slug: 'royal-planters', title: 'Royal Planters',
    description: 'Discover planter forms and finishes in the Royal Planters catalogue.',
    coverImage: '/images/home/royal-planters.jpg',
    categoryIds: ['outdoor', 'indoor'], isPublished: true,
  },
  {
    id: 'plants', slug: 'lifelike-plants', title: 'Lifelike Plants',
    description: 'Explore artificial greenery and arrangements in the Lifelike Plants catalogue.',
    coverImage: '/images/home/lifelike-plants.jpg',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'trees', slug: 'lifelike-trees', title: 'Lifelike Trees',
    description: 'Find inspiration for your next space in the Lifelike Trees catalogue.',
    coverImage: '/images/home/lifelike-trees.jpg',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'cacti', slug: 'lifelike-cacti', title: 'Lifelike Cacti',
    description: 'Explore sculptural artificial greenery in the Lifelike Cacti catalogue.',
    coverImage: '/images/home/lifelike-cacti.jpg',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'project', slug: 'project-catalogue', title: 'Project Catalogue',
    description: 'Browse artificial greenery inspiration in the Project Catalogue of Lifelike Plants.',
    coverImage: '/images/home/project-catalogue.jpg',
    categoryIds: ['artificial'], isPublished: true,
  },
  {
    id: 'trees-cacti', slug: 'trees-and-cacti', title: 'Trees & Cacti',
    description: 'Discover artificial trees and cacti for interior arrangements.',
    coverImage: '/images/home/trees-and-cacti.jpg',
    categoryIds: ['artificial'], isPublished: true,
  },
];
