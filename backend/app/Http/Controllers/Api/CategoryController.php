<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Category::query()->where('status', 'published')->orderBy('sort_order')->orderBy('id')->get()->map(fn (Category $category) => $this->dto($category))->values()])->header('Cache-Control', 'no-store');
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::query()->where('status', 'published')->where('slug', $slug)->firstOrFail();
        return response()->json(['data' => $this->dto($category)])->header('Cache-Control', 'no-store');
    }

    private function dto(Category $category): array
    {
        return [
            'id' => $category->public_id,
            'slug' => $category->slug,
            'path' => '/shop/'.$category->slug,
            'name' => $category->name,
            'heading' => $category->heading,
            'description' => $category->description,
            'tileDescription' => $category->tile_description,
            'introHeading' => $category->intro_heading,
            'introParagraphs' => $category->intro_paragraphs ?? [],
            'image' => $category->image_path ? '/storage/'.$category->image_path : null,
            'imageAlt' => $category->image_alt,
            'seoTitle' => $category->seo_title,
            'seoDescription' => $category->seo_description,
            'ogImage' => $category->og_image_path ? '/storage/'.$category->og_image_path : null,
        ];
    }
}
