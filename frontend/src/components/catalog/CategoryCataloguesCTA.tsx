'use client';

import Image from 'next/image';
import Link from 'next/link';
import { Button, Paper, Text, Title } from '@mantine/core';
import { IconArrowUpRight } from '@tabler/icons-react';
import type { Catalogue, Category } from '@/types/catalog';
import { cataloguesPath } from '@/lib/catalog/catalogues';
import styles from './CategoryCataloguesCTA.module.css';

/** Receives published, category-scoped data from the page's repository adapter. */
export function CategoryCataloguesCTA({ catalogues, category }: {
  catalogues: Catalogue[]; category?: Pick<Category, 'name' | 'slug'>;
}) {
  const catalogue = catalogues[0];
  if (!catalogue) return null;

  return <Paper component="section" aria-label="Product catalogues" className={styles.banner}>
    <div className={styles.cover}>
      <Image src={catalogue.coverImage} alt={`${catalogue.title} catalogue cover`} fill
        sizes="(max-width: 575px) calc(100vw - 64px), 280px" className={styles.image} />
    </div>
    <div className={styles.content}>
      <Text className={styles.eyebrow}>PRODUCT CATALOGUES</Text>
      <Title order={2} className={styles.title}>{category ? `Explore Our ${category.name} Collection` : 'Explore Our Product Catalogues'}</Title>
      <Text className={styles.description}>{category ? catalogue.description : 'Browse our catalogues for planter forms, finishes and artificial greenery for your next project.'}</Text>
      <Button component={Link} href={cataloguesPath(category?.slug)} className={styles.button}
        rightSection={<IconArrowUpRight size={18} stroke={1.5} aria-hidden="true" />}>View Catalogues</Button>
    </div>
  </Paper>;
}
