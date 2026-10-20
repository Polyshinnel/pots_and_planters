'use client';

import Image from 'next/image';
import { useState } from 'react';
import type { HomeImage as HomeImageData } from '@/types/home';
import styles from './Home.module.css';

/** Small client island so broken/missing assets retain their allocated space. */
export function HomeImage({ image, sizes, priority = false, objectFit = 'contain' }: { image: HomeImageData | null; sizes: string; priority?: boolean; objectFit?: 'contain' | 'cover' }) {
  const [failedSrc, setFailedSrc] = useState<string | null>(null);
  const missing = !image || failedSrc === image.src;
  return <><Image
    src={missing ? '/images/home/image-pending.svg' : image.src}
    alt={missing ? '' : image.alt}
    fill
    sizes={sizes}
    priority={priority}
    unoptimized={!missing && image.src.startsWith('/storage/')}
    style={{ objectFit }}
    onError={missing ? undefined : () => setFailedSrc(image.src)}
  />{missing && <span className={styles.imagePending}>Image pending</span>}</>;
}
