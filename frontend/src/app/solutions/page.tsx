import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { IconArrowUpRight } from '@tabler/icons-react';
import { solutionsRepository } from '@/lib/solutions/repository';
import { SolutionSection } from '@/components/solutions/SolutionSection';
import pageStyles from '@/components/catalog/Catalog.module.css';
import aboutStyles from '@/components/about/About.module.css';
import styles from '@/components/solutions/Solutions.module.css';

export const metadata: Metadata = {
  title: 'Planter Solutions for Hotels, Offices & Homes | Pots & Planters',
  description: 'Explore planters and artificial greenery for hotels, restaurants, offices, villas and public spaces. Find suitable collections for your next project.',
  alternates: { canonical: '/solutions' },
};

export default async function SolutionsPage() {
  const solutions = await solutionsRepository.getSolutionCategories();
  const scenes = await Promise.all(solutions.map((solution) => solutionsRepository.getSolutionScenes(solution.id)));
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><span aria-current="page">Solutions</span></li>
    </ol></nav>
    <header className={styles.hero}>
      <div>
        <p className={styles.eyebrow}>SOLUTIONS FOR EVERY SPACE</p>
        <h1>Planters &amp; Greenery for Every Space</h1>
        <p className={styles.lead}>From welcoming hotel entrances to private terraces and contemporary workplaces, discover how planters and artificial greenery can shape the character and function of a space.</p>
        <p className={styles.supporting}>Explore ideas for your project, then browse suitable collections or contact our team to discuss your requirements.</p>
      </div>
      <figure>
        <div className={styles.heroImage}><Image src="/images/about/project-lounge.jpg" alt="Contemporary lounge with yellow seating and greenery in tall dark planters" fill preload sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1420px) 48vw, 650px" /></div>
        <figcaption>Imagery illustrates ideas for your space; it is not presented as completed Pots &amp; Planters projects.</figcaption>
      </figure>
    </header>
    <nav className={styles.jumpNav} aria-label="Explore solutions by space">
      {solutions.map((solution) => <a key={solution.id} href={`#${solution.id}`}>{solution.title}</a>)}
    </nav>
    <div>{solutions.map((solution, index) => <SolutionSection key={solution.id} solution={solution} scenes={scenes[index]} reverse={index % 2 === 1} />)}</div>
    <section className={`${aboutStyles.closing} ${styles.closing}`} aria-labelledby="project-title">
      <h2 id="project-title">Let&apos;s Find the Right Fit for Your Project</h2>
      <p>Tell us about your space, preferred styles and quantities. Our team can help you explore suitable collections and discuss a quotation for your requirements.</p>
      <div className={aboutStyles.actions}>
        <Link href="/contacts" className={aboutStyles.primaryButton}>Discuss Your Project <IconArrowUpRight size={20} aria-hidden="true" /></Link>
        <Link href="/shop" className={aboutStyles.secondaryButton}>Explore Collections <IconArrowUpRight size={20} aria-hidden="true" /></Link>
      </div>
    </section>
  </Container>;
}
