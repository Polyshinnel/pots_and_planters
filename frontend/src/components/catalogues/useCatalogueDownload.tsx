'use client';

import { useState } from 'react';
import type { Catalogue } from '@/types/catalog';
import type { LeadSource } from '@/lib/forms/contracts';
import { CatalogueDownloadDialog } from '@/components/forms/CatalogueDownloadForm';

/** Keep the form mounted through closing so Mantine can return focus. */
export function useCatalogueDownload(source: LeadSource) {
  const [selected, setSelected] = useState<Catalogue | null>(null);
  const [opened, setOpened] = useState(false);
  return {
    openCatalogue: (catalogue: Catalogue) => { setSelected(catalogue); setOpened(true); },
    dialog: selected && <CatalogueDownloadDialog opened={opened} onClose={() => setOpened(false)} source={source} catalogue={selected} />,
  };
}
