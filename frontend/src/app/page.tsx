import { getStaticPageMetadata } from '@/lib/page-seo.server';
import { FeaturedProducts, HomeAbout, HomeCatalogues, HomeCategories, HomeFinalCta, HomeHero, ProjectSolutions } from '@/components/home/HomeSections';
import styles from '@/components/home/Home.module.css';
import { getCategories } from '@/lib/catalog/categories.server';

export const generateMetadata = () => getStaticPageMetadata('home');
export const dynamic = 'force-dynamic';

export default async function Home() {
  const categories = await getCategories();
  return <div className={styles.home}>
    <HomeHero />
    <HomeCategories categories={categories} />
    <ProjectSolutions />
    <FeaturedProducts />
    <HomeCatalogues />
    <HomeAbout />
    <HomeFinalCta />
  </div>;
}
