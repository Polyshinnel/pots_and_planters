import type { Category, Product } from './catalog';

export type SolutionCategory = {
  id: string;
  slug: string;
  title: string;
  intro: string;
  sortOrder: number;
  isPublished: boolean;
  headline: string;
  image: { src: string; alt: string };
  collections: Pick<Category, 'id' | 'name' | 'path'>[];
};
export type Solution = SolutionCategory;
export type SolutionImage = {
  id: string; src: string; alt: string; width: number; height: number; sortOrder: number; caption?: string;
};
export type SolutionItem = {
  id: string; productId: string; recommendedQuantity: number; sortOrder: number;
  matchType: 'exact' | 'suggested'; displayNote?: string;
  /** Editorial evidence required before an exact association may be published. */
  matchEvidence?: string;
};
export type SolutionHotspot = {
  id: string; imageId: string; solutionItemId: string; x: number; y: number; sortOrder: number; label?: string;
};
export type SolutionScene = {
  id: string; slug: string; categoryId: string; title: string; summary: string;
  isConcept: boolean; status: 'draft' | 'published'; sortOrder: number; coverImageId: string | null;
  images: SolutionImage[]; items: SolutionItem[]; hotspots: SolutionHotspot[];
};
/** Catalogue objects are resolved by the repository, never authored again in scene fixtures. */
export type ResolvedSolutionScene = Omit<SolutionScene, 'items'> & {
  items: (SolutionItem & { product?: Product; href?: string })[];
};
export interface SolutionsRepository {
  getSolutionCategories(): Promise<SolutionCategory[]>;
  getSolutionScenes(categoryId: string): Promise<ResolvedSolutionScene[]>;
}
