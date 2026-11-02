<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUrlAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Resolves product paths for a future public router; this does not issue HTTP redirects. */
class ProductUrlResolver
{
    public function canonicalPath(Product $product): ?string
    {
        $product->loadMissing('primaryCategory');
        if ($product->status !== 'published' || ! $product->primaryCategory || $product->primaryCategory->status !== 'published' || blank($product->slug)) {
            return null;
        }

        return '/shop/'.$product->primaryCategory->slug.'/'.$product->slug;
    }

    public function recordAlias(Product $product, string $path): ProductUrlAlias
    {
        return DB::transaction(function () use ($product, $path): ProductUrlAlias {
            $existing = ProductUrlAlias::query()->where('path', $path)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->product_id !== (int) $product->getKey()) {
                    throw ValidationException::withMessages(['slug' => 'The previous product URL is already reserved by another product.']);
                }

                return $existing;
            }

            $segments = explode('/', trim($path, '/'));
            $categorySlug = $segments[1] ?? '';
            $productSlug = $segments[2] ?? '';
            $canonicalOwner = Product::query()->where('slug', $productSlug)->where('status', 'published')
                ->where('id', '!=', $product->getKey())
                ->whereHas('primaryCategory', fn ($query) => $query->where('status', 'published')->where('slug', $categorySlug))
                ->exists();

            if ($canonicalOwner) {
                throw ValidationException::withMessages(['slug' => 'The previous product URL is now the canonical URL of another product.']);
            }

            return ProductUrlAlias::query()->create(['product_id' => $product->getKey(), 'path' => $path]);
        });
    }

    public function assertCanonicalAvailable(Product $product, ?string $path): void
    {
        if ($path === null) {
            return;
        }

        $alias = ProductUrlAlias::query()->where('path', $path)->lockForUpdate()->first();
        if ($alias && (int) $alias->product_id !== (int) $product->getKey()) {
            throw ValidationException::withMessages(['slug' => 'This canonical URL is reserved as a historical URL for another product.']);
        }
    }

    /** @return array{type: 'canonical'|'alias'|'secondary'|'not_found', product_id?: int, canonical_path?: string} */
    public function resolve(string $path): array
    {
        $path = '/'.trim($path, '/');
        $segments = explode('/', trim($path, '/'));
        if (count($segments) !== 3 || $segments[0] !== 'shop') {
            return ['type' => 'not_found'];
        }

        [$categorySlug, $productSlug] = [$segments[1], $segments[2]];
        $product = Product::query()->with(['primaryCategory', 'categories'])
            ->where('slug', $productSlug)->where('status', 'published')->first();
        if ($product) {
            $canonical = $this->canonicalPath($product);
            if ($canonical === $path) {
                return ['type' => 'canonical', 'product_id' => (int) $product->getKey(), 'canonical_path' => $canonical];
            }
        }

        $alias = ProductUrlAlias::query()->where('path', $path)->first();
        if ($alias) {
            $owner = Product::query()->with('primaryCategory')->whereKey($alias->product_id)->where('status', 'published')->first();
            $canonical = $owner ? $this->canonicalPath($owner) : null;

            return $canonical ? ['type' => 'alias', 'product_id' => (int) $owner->getKey(), 'canonical_path' => $canonical] : ['type' => 'not_found'];
        }

        if ($product && $product->categories->contains(fn ($category): bool => $category->slug === $categorySlug && $category->status === 'published')) {
            $canonical = $this->canonicalPath($product);

            return $canonical ? ['type' => 'secondary', 'product_id' => (int) $product->getKey(), 'canonical_path' => $canonical] : ['type' => 'not_found'];
        }

        return ['type' => 'not_found'];
    }
}
