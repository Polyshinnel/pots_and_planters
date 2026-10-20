export interface HomeImage {
  src: string;
  alt: string;
}

/** Shared preview contract; null means the current price has not been verified. */
export interface ProductPreview {
  id: string;
  slug: string;
  name: string;
  specifications?: ProductSpecifications;
  sku?: string;
  image: HomeImage | null;
  price: { amount: number; currency: 'AED' } | null;
}

export interface ProductSpecifications {
  diameterCm?: number;
  heightCm?: number;
  finish?: string;
}

export interface HomeCategory {
  id: string;
  name: string;
  description: string;
  href: string;
  image: HomeImage | null;
}

export interface ProjectSolution {
  id: 'selection' | 'placement' | 'wholesale' | 'delivery';
  title: string;
  description: string;
}

export interface CataloguePreview {
  id: string;
  title: string;
  image: HomeImage;
  /** Published PDF URL; when present, the home card downloads the catalogue directly. */
  downloadUrl?: string;
}
