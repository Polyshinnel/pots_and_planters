<?php

namespace App\Http\Controllers\Api;

use App\Data\CatalogueData;
use App\Http\Controllers\Controller;
use App\Models\Catalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CatalogueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'category' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ])->validate();

        $query = Catalogue::query()
            ->where('status', 'published')
            ->with(['categories' => fn ($categories) => $categories
                ->where('categories.status', 'published')
                ->orderBy('categories.sort_order')
                ->orderBy('categories.id')]);

        if (isset($filters['category'])) {
            $query->whereHas('categories', fn ($categories) => $categories
                ->where('categories.status', 'published')
                ->where('categories.slug', $filters['category']));
        }

        $catalogues = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'data' => $catalogues->map(fn (Catalogue $catalogue): array => CatalogueData::fromModel($catalogue)->toArray())->values(),
        ])->header('Cache-Control', 'no-store');
    }
}
