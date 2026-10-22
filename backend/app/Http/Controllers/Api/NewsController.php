<?php

namespace App\Http\Controllers\Api;

use App\Data\NewsArticleData;
use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = NewsArticle::query()
            ->publiclyVisible()
            ->with('category')
            ->publicOrder()
            ->get();

        return response()->json([
            'data' => $articles->map(fn (NewsArticle $article): array => NewsArticleData::fromModel($article)->toArray())->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(string $slug): JsonResponse
    {
        $article = NewsArticle::query()
            ->publiclyVisible()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => NewsArticleData::fromModel($article, includeBody: true)->toArray(includeBody: true),
        ])->header('Cache-Control', 'no-store');
    }
}
