import { solutions, solutionScenes } from '@/data/solutions.mock';
import { catalogRepository } from '@/lib/catalog/repository';
import type { SolutionsRepository } from '@/types/solutions';
import { byOrder, resolveScene } from './domain';

/** Replace this adapter with Laravel reads; components consume only resolved models. */
export const solutionsRepository: SolutionsRepository = {
  async getSolutionCategories() {
    return solutions.filter((category) => category.isPublished).sort(byOrder);
  },
  async getSolutionScenes(categoryId) {
    if (!solutions.some((category) => category.id === categoryId && category.isPublished)) return [];
    const { products } = await catalogRepository.getProducts({ filters: {}, sort: 'featured', page: 1, perPage: 1000 });
    return solutionScenes.filter((scene) => scene.categoryId === categoryId && scene.status === 'published').sort(byOrder)
      .map((scene) => resolveScene(scene, products));
  },
};
