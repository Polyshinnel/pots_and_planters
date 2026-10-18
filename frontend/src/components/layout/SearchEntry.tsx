'use client';

import { useState } from 'react';
import { Button, Group, TextInput } from '@mantine/core';
import { useRouter } from 'next/navigation';
import styles from './Layout.module.css';

export function SearchEntry({ onNavigate }: { onNavigate?: () => void }) {
  const [query, setQuery] = useState('');
  const router = useRouter();
  return <form role="search" aria-label="Site search" className={styles.search} onSubmit={(event) => {
    event.preventDefault();
    const value = query.trim();
    if (!value) return;
    router.push(`/search?q=${encodeURIComponent(value)}`);
    onNavigate?.();
  }}>
    <Group gap="xs" align="end" wrap="nowrap">
      <TextInput className={styles.searchInput} label="Search" name="q" type="search" placeholder="What are you looking for?" value={query} onChange={(event) => setQuery(event.currentTarget.value)} required maxLength={200} />
      <Button type="submit">Search</Button>
    </Group>
  </form>;
}
