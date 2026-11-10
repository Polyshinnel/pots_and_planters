<?php

namespace App\Http\Controllers\Api;

use App\Data\ProductDetailData;
use App\Data\ProductListData;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductReadController extends Controller
{
    private const SORTS = ['newest', 'name-asc', 'name-desc', 'price-asc', 'price-desc'];

    private const LEGACY_KEYS = ['material', 'color', 'size', 'usage'];

    public function resolve(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        try {
            $input = $request->json()->all();
            $errors = [];
            foreach (array_diff(array_keys($input), ['ids']) as $key) {
                $errors[$key][] = 'This field is not supported.';
            }
            if (! array_key_exists('ids', $input) || ! is_array($input['ids']) || ! array_is_list($input['ids'])) {
                $errors['ids'][] = 'The ids field must be an array.';
            } else {
                if (count($input['ids']) < 1 || count($input['ids']) > 100) {
                    $errors['ids'][] = 'The ids field must contain between 1 and 100 items.';
                }
                $seen = [];
                foreach ($input['ids'] as $index => $id) {
                    if (! is_string($id) || ! Str::isUuid($id)) {
                        $errors["ids.{$index}"][] = 'Each id must be a valid UUID.';

                        continue;
                    }
                    $normalized = strtolower($id);
                    if (isset($seen[$normalized])) {
                        $errors["ids.{$index}"][] = 'The ids must be unique.';
                    }
                    $seen[$normalized] = true;
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $ids = $input['ids'];
            $products = $this->visibleProducts()->whereIn('products.public_id', $ids)
                ->with($this->listRelations())->get()->keyBy(fn (Product $product) => strtolower((string) $product->public_id));
            $data = collect($ids)->map(function (string $id) use ($products): array {
                $product = $products->get(strtolower($id));

                return $product
                    ? ['id' => $id, 'status' => 'available', 'product' => (new ProductListData($product))->toArray()]
                    : ['id' => $id, 'status' => 'unavailable'];
            });

            return response()->json(['data' => $data])->header('Cache-Control', 'no-store');
        } catch (ValidationException $exception) {
            return $this->error(422, 'validation_failed', 'The request parameters are invalid.', $requestId, $exception->errors());
        } catch (QueryException $exception) {
            report($exception);
            Log::error('Product API database request failed.', ['request_id' => $requestId]);

            return $this->error(503, 'service_unavailable', 'The product service is temporarily unavailable.', $requestId);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        try {
            $input = $this->validateList($request);
            $category = null;
            if ($input['category'] !== null) {
                $category = Category::query()->where('slug', $input['category'])->where('status', 'published')->with(['filterAttributes.options', 'filterAttributes.productTypes'])->first();
                if (! $category) {
                    return $this->error(404, 'category_not_found', 'The requested category was not found.', $requestId);
                }
            }
            $filters = $this->parseFilters($request, $category);
            $query = $this->visibleProducts($category);
            foreach ($filters as $filter) {
                $this->applyFilter($query, $filter);
            }
            $total = (clone $query)->count('products.id');
            $this->sort($query, $input['sort']);
            $products = $query->with($this->listRelations())->forPage($input['page'], $input['perPage'])->get();

            return response()->json([
                'data' => $products->map(fn (Product $product) => (new ProductListData($product))->toArray())->values(),
                'meta' => ['page' => $input['page'], 'perPage' => $input['perPage'], 'total' => $total, 'lastPage' => max(1, (int) ceil($total / $input['perPage'])), 'sort' => $input['sort']],
                'facets' => $this->facets($category, $filters),
            ])->header('Cache-Control', 'no-store');
        } catch (ValidationException $exception) {
            return $this->error(422, 'validation_failed', 'The request parameters are invalid.', $requestId, $exception->errors());
        } catch (QueryException $exception) {
            report($exception);
            Log::error('Product API database request failed.', ['request_id' => $requestId]);

            return $this->error(503, 'service_unavailable', 'The product service is temporarily unavailable.', $requestId);
        }
    }

    public function show(string $slug): JsonResponse
    {
        $requestId = (string) Str::uuid();
        try {
            $product = $this->visibleProducts()->where('products.slug', $slug)->with($this->detailRelations())->first();
            if (! $product) {
                return $this->error(404, 'product_not_found', 'The requested product was not found.', $requestId);
            }

            return response()->json(['data' => (new ProductDetailData($product))->toArray()])->header('Cache-Control', 'no-store');
        } catch (QueryException $exception) {
            report($exception);
            Log::error('Product API database request failed.', ['request_id' => $requestId]);

            return $this->error(503, 'service_unavailable', 'The product service is temporarily unavailable.', $requestId);
        }
    }

    private function validateList(Request $request): array
    {
        $input = $request->query();
        $errors = [];
        foreach (['page' => [1, PHP_INT_MAX], 'perPage' => [1, 48]] as $key => [$min, $max]) {
            if (isset($input[$key]) && (! is_string($input[$key]) || ! ctype_digit($input[$key]) || (int) $input[$key] < $min || (int) $input[$key] > $max)) {
                $errors[$key][] = "The {$key} parameter is invalid.";
            }
        }
        if (isset($input['sort']) && (! is_string($input['sort']) || ! in_array($input['sort'], self::SORTS, true))) {
            $errors['sort'][] = 'The sort parameter is invalid.';
        }
        if (isset($input['category']) && (! is_string($input['category']) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $input['category']))) {
            $errors['category'][] = 'The category parameter is invalid.';
        }
        $allowed = ['page', 'perPage', 'sort', 'category', 'filter', ...self::LEGACY_KEYS];
        foreach (array_diff(array_keys($input), $allowed) as $key) {
            $errors[$key][] = 'This query parameter is not supported.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['page' => (int) ($input['page'] ?? 1), 'perPage' => (int) ($input['perPage'] ?? 12), 'sort' => $input['sort'] ?? 'newest', 'category' => $input['category'] ?? null];
    }

    private function parseFilters(Request $request, ?Category $category): array
    {
        $legacyPresent = array_values(array_intersect(self::LEGACY_KEYS, array_keys($request->query())));
        $mapping = config('product_filters.legacy', []);
        $legacyMappings = [];
        if ($legacyPresent) {
            foreach ($legacyPresent as $legacy) {
                $code = $category ? ($mapping[$category->slug][$legacy] ?? null) : null;
                if (! $code) {
                    throw ValidationException::withMessages([$legacy => 'This legacy filter has no confirmed mapping for the requested category.']);
                }
                if (isset($request->query('filter', [])[$code])) {
                    throw ValidationException::withMessages([$legacy => 'The legacy filter conflicts with the filter parameter.']);
                }
                $legacyMappings[$code] = $request->query($legacy);
            }
        }
        $raw = $request->query('filter', []);
        if (is_array($raw)) {
            $raw = array_merge($raw, $legacyMappings);
        }
        if ($raw === []) {
            return [];
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['filter' => 'Filters must be an object.']);
        }
        $configured = $category
            ? $category->filterAttributes->keyBy('code')
            : Attribute::query()->with(['options', 'productTypes'])->where('is_active', true)->where('is_filterable', true)->whereHas('productTypes')->get()->keyBy('code');
        $filters = [];
        foreach ($raw as $code => $value) {
            $attribute = $configured->get($code);
            if (! $attribute || ! $attribute->is_active || ! $attribute->is_filterable || $attribute->productTypes->isEmpty()) {
                throw ValidationException::withMessages(["filter.{$code}" => 'This filter is not available for the category.']);
            }
            $filter = ['attribute' => $attribute, 'value' => $value];
            $this->validateFilter($filter);
            $filters[] = $filter;
        }

        return $filters;
    }

    private function validateFilter(array $filter): void
    {
        /** @var Attribute $attribute */
        $attribute = $filter['attribute'];
        $value = $filter['value'];
        if ($attribute->type === 'number' && is_array($value)) {
            if (array_diff(array_keys($value), ['min', 'max']) || $value === []) {
                throw ValidationException::withMessages(["filter.{$attribute->code}" => 'Provide a valid numeric range.']);
            }
            foreach ($value as $bound) {
                if (! $this->decimal($bound)) {
                    throw ValidationException::withMessages(["filter.{$attribute->code}" => 'Range boundaries must be decimal values.']);
                }
            }
            if (isset($value['min'], $value['max']) && $this->compareDecimal((string) $value['min'], (string) $value['max']) > 0) {
                throw ValidationException::withMessages(["filter.{$attribute->code}" => 'The minimum must not exceed the maximum.']);
            }

            return;
        }
        $values = is_array($value) ? $value : [$value];
        if ($values === [] || count($values) > 32) {
            throw ValidationException::withMessages(["filter.{$attribute->code}" => 'Provide one or more valid values.']);
        }
        foreach ($values as $item) {
            $valid = match ($attribute->type) {
                'number' => $this->decimal($item),
                'boolean' => in_array($item, ['true', 'false'], true),
                'text' => is_string($item) && $item !== '' && mb_strlen($item) <= 255,
                'select', 'multiselect' => is_string($item) && $attribute->options->contains(fn ($option) => $option->value === $item && $option->is_active),
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages(["filter.{$attribute->code}" => 'One or more filter values are invalid or unavailable.']);
            }
        }
    }

    private function applyFilter($query, array $filter): void
    {
        $attribute = $filter['attribute'];
        $value = $filter['value'];
        $type = $attribute->type;
        $query->where(function ($products) use ($attribute, $value, $type): void {
            if ($type === 'number' && is_array($value)) {
                $products->whereHas('attributeValues', function ($values) use ($attribute, $value): void {
                    $values->where('attribute_id', $attribute->id)->when(isset($value['min']), fn ($q) => $q->where('number_value', '>=', $value['min']))->when(isset($value['max']), fn ($q) => $q->where('number_value', '<=', $value['max']));
                });

                return;
            }
            $items = is_array($value) ? $value : [$value];
            $products->whereHas('attributeValues', function ($values) use ($attribute, $items, $type): void {
                $values->where('attribute_id', $attribute->id)->where(function ($match) use ($items, $type): void {
                    if (in_array($type, ['select', 'multiselect'], true)) {
                        $match->whereHas('selections.option', fn ($options) => $options->whereIn('value', $items));
                    } else {
                        $column = ['number' => 'number_value', 'boolean' => 'boolean_value', 'text' => 'text_value'][$type];
                        if ($type === 'text') {
                            foreach ($items as $index => $item) {
                                $index === 0
                                    ? $match->whereRaw('BINARY text_value = BINARY ?', [$item])
                                    : $match->orWhereRaw('BINARY text_value = BINARY ?', [$item]);
                            }
                        } elseif ($type === 'boolean') {
                            $match->whereIn($column, array_map(fn ($item) => $item === 'true' ? 1 : 0, $items));
                        } else {
                            $match->whereIn($column, $items);
                        }
                    }
                });
            });
        });
    }

    private function facets(?Category $category, array $filters): array
    {
        $facets = [];
        $attributes = $category
            ? $category->filterAttributes
            : Attribute::query()->with(['options', 'productTypes'])->where('is_active', true)->where('is_filterable', true)->whereHas('productTypes')->orderBy('name')->orderBy('id')->get();
        foreach ($attributes as $attribute) {
            if (! $attribute->is_active || ! $attribute->is_filterable || $attribute->productTypes->isEmpty()) {
                continue;
            }
            $base = $this->visibleProducts($category);
            foreach ($filters as $filter) {
                $this->applyFilter($base, $filter);
            }
            $counts = [];
            $rows = DB::table('product_attribute_values as pav')->join('products as p', 'p.id', '=', 'pav.product_id')->whereIn('p.id', (clone $base)->select('products.id'))->where('pav.attribute_id', $attribute->id);
            $facet = [
                'code' => $attribute->code,
                'label' => $attribute->name,
                'type' => $attribute->type,
                'displayType' => $category ? $attribute->pivot->display_type : $this->globalDisplayType($attribute),
            ];
            if ($attribute->type === 'text') {
                $groups = $rows->select('pav.text_value as val')->selectRaw('COUNT(DISTINCT p.id) as aggregate')->whereNotNull('pav.text_value')->groupBy('pav.text_value')->get();
            } elseif ($attribute->type === 'number') {
                $groups = (clone $rows)->selectRaw('MIN(pav.number_value) as min_value, MAX(pav.number_value) as max_value')->first();
            } elseif ($attribute->type === 'boolean') {
                $groups = $rows->select('pav.boolean_value as val')->selectRaw('COUNT(DISTINCT p.id) as aggregate')->whereNotNull('pav.boolean_value')->groupBy('pav.boolean_value')->get();
            } else {
                $groups = DB::table('product_attribute_selections as s')->join('product_attribute_values as pav', 'pav.id', '=', 's.product_attribute_value_id')->join('products as p', 'p.id', '=', 'pav.product_id')->join('attribute_options as o', 'o.id', '=', 's.attribute_option_id')->whereIn('p.id', (clone $base)->select('products.id'))->where('pav.attribute_id', $attribute->id)->where('o.is_active', true)->select('o.value as val', 'o.label')->selectRaw('COUNT(DISTINCT p.id) as aggregate')->groupBy('o.id', 'o.value', 'o.label')->get();
            }
            if ($attribute->type === 'number') {
                $facets[] = [...$facet, 'min' => $groups?->min_value === null ? null : (string) $groups->min_value, 'max' => $groups?->max_value === null ? null : (string) $groups->max_value];
            } else {
                foreach ($groups as $group) {
                    if ((int) $group->aggregate > 0) {
                        $value = $attribute->type === 'boolean' ? ((string) $group->val === '1' ? 'true' : 'false') : (string) $group->val;
                        $counts[] = ['value' => $value, 'label' => $attribute->type === 'boolean' ? ($value === 'true' ? 'Yes' : 'No') : ($group->label ?? $value), 'count' => (int) $group->aggregate];
                    }
                }
                $facets[] = [...$facet, 'options' => $counts];
            }
        }

        return $facets;
    }

    private function globalDisplayType(Attribute $attribute): string
    {
        return match ($attribute->type) {
            'text' => 'values',
            'number' => 'range',
            'boolean' => 'boolean',
            'select' => 'options',
            'multiselect' => 'multi_options',
        };
    }

    private function visibleProducts(?Category $category = null)
    {
        return Product::query()->select('products.*')->where('products.status', 'published')
            ->whereHas('primaryCategory', fn ($q) => $q->where('status', 'published'))
            ->whereHas('supplier', fn ($q) => $q->where('is_active', true))
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($categories) => $categories->where('categories.id', $category->id)->where('categories.status', 'published')));
    }

    private function sort($query, string $sort): void
    {
        match ($sort) {
            'name-asc' => $query->orderBy('products.name')->orderBy('products.public_id'),
            'name-desc' => $query->orderByDesc('products.name')->orderBy('products.public_id'),
            'price-asc' => $query->orderByRaw('products.price_aed IS NULL')->orderBy('products.price_aed')->orderBy('products.public_id'),
            'price-desc' => $query->orderByRaw('products.price_aed IS NULL')->orderByDesc('products.price_aed')->orderBy('products.public_id'),
            default => $query->orderByDesc('products.created_at')->orderBy('products.name')->orderBy('products.public_id'),
        };
    }

    private function listRelations(): array
    {
        return ['brand', 'primaryCategory', 'categories' => fn ($query) => $query->where('status', 'published'), 'productType.attributes.options', 'attributeValues.selections.option', 'images.mediaAsset'];
    }

    private function detailRelations(): array
    {
        return [...$this->listRelations(), 'series', 'attributeValues.attribute.options', 'seoOgMediaAsset'];
    }

    private function decimal(mixed $value): bool
    {
        return is_string($value) && preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d{1,6})?$/', $value) === 1;
    }

    private function compareDecimal(string $left, string $right): int
    {
        $normalize = static function (string $number): array {
            $negative = str_starts_with($number, '-');
            $number = ltrim($number, '-');
            [$whole, $fraction] = array_pad(explode('.', $number, 2), 2, '');
            $whole = ltrim($whole, '0') ?: '0';
            $fraction = rtrim($fraction, '0');

            return [$negative && ($whole !== '0' || $fraction !== '') ? -1 : 1, $whole, $fraction];
        };
        [$leftSign, $leftWhole, $leftFraction] = $normalize($left);
        [$rightSign, $rightWhole, $rightFraction] = $normalize($right);
        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }
        $comparison = strlen($leftWhole) <=> strlen($rightWhole);
        if ($comparison === 0) {
            $comparison = strcmp($leftWhole, $rightWhole) <=> 0;
        }
        if ($comparison === 0) {
            $comparison = strcmp(str_pad($leftFraction, max(strlen($leftFraction), strlen($rightFraction)), '0'), str_pad($rightFraction, max(strlen($leftFraction), strlen($rightFraction)), '0')) <=> 0;
        }

        return $comparison * $leftSign;
    }

    private function error(int $status, string $code, string $message, string $requestId, array $errors = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message, 'requestId' => $requestId];
        if ($errors) {
            $error['errors'] = $errors;
        }

        return response()->json(['error' => $error], $status)->header('Cache-Control', 'no-store');
    }
}
