<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use App\Support\StaticPageSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PageSeoController extends Controller
{
    public function show(string $pageKey): JsonResponse
    {
        abort_unless(StaticPageSeo::path($pageKey) !== null, 404);

        $page = PageSeo::query()->where('page_key', $pageKey)->firstOrFail();
        $imageUrl = $page->og_image_path
            ? Storage::disk('public')->url($page->og_image_path)
            : null;

        return response()->json([
            'data' => [
                'pageKey' => $page->page_key,
                'title' => $page->title,
                'description' => $page->description,
                'ogImageUrl' => $imageUrl,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
