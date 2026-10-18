import type { Metadata } from 'next';
import { FeaturedProducts, HomeAbout, HomeCatalogues, HomeCategories, HomeFinalCta, HomeHero, ProjectSolutions } from '@/components/home/HomeSections';
import styles from '@/components/home/Home.module.css';

export const metadata: Metadata = {
  title: 'Pots & Planters for Projects across the UAE | Dubai',
  description: 'Pots, planters and artificial greenery for residential and commercial projects across the UAE. Discuss your project requirements with our Dubai team.',
};

export default function Home() {
  return <div className={styles.home}>
    <HomeHero />
    <HomeCategories />
    <ProjectSolutions />
    <FeaturedProducts />
    <HomeCatalogues />
    <HomeAbout />
    <HomeFinalCta />
  </div>;
}
