import { Container } from '@mantine/core';
import Image from 'next/image';
import { IconArrowUpRight, IconBuildingStore, IconPackage, IconRulerMeasure, IconTruckDelivery } from '@tabler/icons-react';
import Link from 'next/link';
import { featuredProducts, homeCatalogues, homeCategories, homeCopy, projectSolutions } from '@/data/home.mock';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import { FeaturedProductsSlider } from './FeaturedProductsSlider';
import { HomeCataloguesSlider } from './HomeCataloguesSlider';
import { ProjectContactLink } from './ProjectContactLink';
import styles from './Home.module.css';

function SectionHeading({ id, copy }: { id: string; copy: { eyebrow: string; title: string; description: string } }) {
  return <div className={styles.sectionHeading}>
    <p className={styles.eyebrow}>{copy.eyebrow}</p>
    <h2 id={id}>{copy.title}</h2>
    <p className={styles.description}>{copy.description}</p>
  </div>;
}

export function HomeHero() {
  const copy = homeCopy.hero;
  return <section className={styles.hero} aria-labelledby="home-title">
    <picture className={styles.heroMedia} aria-hidden="true">
      <source media="(max-width: 47.999em)" srcSet="/images/home/hero-mobile.webp" />
      <Image src="/images/home/hero-desktop.webp" alt="" fill priority sizes="100vw" />
    </picture>
    <div className={styles.heroShade} aria-hidden="true" />
    <Container className={styles.heroContainer}>
    <div className={styles.heroGrid}>
      <div className={styles.heroCopy}>
        <p className={styles.eyebrow}>{copy.eyebrow}</p><h1 id="home-title">{copy.title}</h1>
        <p className={styles.description}>{copy.description}</p>
        <div className={styles.actions}>
          <a className={`${styles.button} ${styles.heroButton}`} href="/#contact-cta">Discuss Your Project <IconArrowUpRight size={20} stroke={1.5} aria-hidden="true" /></a>
          <Link href="/shop" className={styles.secondaryButton}>Explore Collection <span aria-hidden="true">↗</span></Link>
        </div>
      </div>
    </div>
  </Container></section>;
}

export function HomeCategories() {
  return <section className={styles.section} aria-labelledby="categories-title"><Container>
    <SectionHeading id="categories-title" copy={homeCopy.categories} />
    <div className={styles.categoryGrid}>{homeCategories.map((category) => <Link key={category.id} href={category.href} className={styles.categoryCard}>
      <div className={styles.categoryImage}><HomeImage image={category.image} sizes={homeImageSizes.category} objectFit="cover" /></div>
      <div className={styles.categoryContent}><h3>{category.name}</h3><IconArrowUpRight size={24} stroke={1.5} aria-hidden="true" /><p>{category.description}</p></div>
    </Link>)}</div>
  </Container></section>;
}

const solutionIcons = { selection: IconRulerMeasure, placement: IconBuildingStore, wholesale: IconPackage, delivery: IconTruckDelivery };
export function ProjectSolutions() {
  return <section className={`${styles.section} ${styles.softSection}`} aria-labelledby="solutions-title"><Container>
    <SectionHeading id="solutions-title" copy={homeCopy.solutions} />
    <div className={styles.solutionGrid}>{projectSolutions.map((solution) => {
      const Icon = solutionIcons[solution.id];
      return <article className={styles.solution} key={solution.id}><Icon size={34} stroke={1.25} aria-hidden="true" /><h3>{solution.title}</h3><p>{solution.description}</p></article>;
    })}</div>
    <ProjectContactLink />
  </Container></section>;
}

export function FeaturedProducts() {
  return <section className={styles.section} aria-labelledby="featured-title"><Container>
    <div className={styles.headingRow}><SectionHeading id="featured-title" copy={homeCopy.featured} /><Link href="/shop" className={styles.textLink}>Explore Collection <span aria-hidden="true">↗</span></Link></div>
    <FeaturedProductsSlider products={featuredProducts} />
  </Container></section>;
}

export function HomeCatalogues() {
  return <section className={`${styles.section} ${styles.softSection}`} aria-labelledby="catalogues-title"><Container>
    <div className={styles.headingRow}><SectionHeading id="catalogues-title" copy={homeCopy.catalogues} /><Link href="/catalogues" className={styles.button}>View Catalogues <IconArrowUpRight size={20} stroke={1.5} aria-hidden="true" /></Link></div>
    <HomeCataloguesSlider catalogues={homeCatalogues} />
  </Container></section>;
}

export function HomeAbout() {
  const copy = homeCopy.about;
  return <section className={`${styles.section} ${styles.about}`} aria-labelledby="about-title"><Container>
    <div className={styles.aboutGrid}><div><p className={styles.eyebrow}>{copy.eyebrow}</p><h2 id="about-title">{copy.title}</h2></div>
      <div><p className={styles.description}>{copy.description}</p><div className={styles.actions}>
        <Link href="/about" className={styles.textLink}>About us <span aria-hidden="true">↗</span></Link><a href="/#contacts" className={styles.textLink}>Contacts <span aria-hidden="true">↗</span></a>
      </div></div>
    </div>
  </Container></section>;
}

export function HomeFinalCta() {
  const copy = homeCopy.contact;
  return <section id="contact-cta" className={styles.finalCta} aria-labelledby="contact-title"><Container>
    <div className={styles.finalContent}><p className={styles.eyebrow}>{copy.eyebrow}</p><h2 id="contact-title">{copy.title}</h2><p className={styles.description}>{copy.description}</p><ProjectContactLink final /></div>
  </Container></section>;
}
