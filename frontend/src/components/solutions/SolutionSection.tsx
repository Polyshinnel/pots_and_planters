import Image from 'next/image';
import Link from 'next/link';
import { IconArrowUpRight } from '@tabler/icons-react';
import type { Solution, ResolvedSolutionScene } from '@/types/solutions';
import { SolutionGallery } from './SolutionGallery';
import styles from './Solutions.module.css';

export function SolutionSection({ solution, scenes = [], reverse = false }: { solution: Solution; scenes?: ResolvedSolutionScene[]; reverse?: boolean }) {
  return <section id={solution.id} aria-labelledby={`${solution.id}-title`} className={`${styles.solution} ${scenes.length ? styles.withGallery : ''} ${reverse ? styles.reverse : ''}`}>
    <div className={styles.imageFrame} hidden={scenes.length > 0}>
      <Image src={solution.image.src} alt={solution.image.alt} fill sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1420px) 48vw, 650px" />
    </div>
    <div className={styles.copy}>
      <p className={styles.eyebrow}>{solution.title}</p>
      <h2 id={`${solution.id}-title`}>{solution.headline}</h2>
      <p className={styles.description}>{solution.intro}</p>
      <div className={styles.collections}>
        <p id={`${solution.id}-collections`}>Suitable collections</p>
        <ul aria-labelledby={`${solution.id}-collections`}>{solution.collections.map((category) => <li key={category.id}>
          <Link href={category.path}>{category.name}</Link>
        </li>)}</ul>
      </div>
      <Link href="/shop" className={styles.textLink}>Explore Collections <IconArrowUpRight size={18} stroke={1.5} aria-hidden="true" /></Link>
    </div>
    {scenes.length > 0 && <SolutionGallery scenes={scenes} categoryId={solution.id} />}
  </section>;
}
